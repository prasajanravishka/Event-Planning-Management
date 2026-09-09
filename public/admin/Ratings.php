<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'ratings';

// 1. Handle Action: Toggle Status (Approved <-> Hidden)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid. Please try again.');
        header('Location: Ratings.php');
        exit();
    }

    $rating_id = (int)($_POST['rating_id'] ?? 0);
    $new_status = ($_POST['new_status'] ?? '') === 'hidden' ? 'hidden' : 'approved';

    $stmt = $conn->prepare("UPDATE ratings SET admin_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $rating_id);
    if ($stmt->execute()) {
        $label = ($new_status === 'hidden') ? 'hidden from public display' : 'approved and published';
        set_flash_message('success', "Review #{$rating_id} is now {$label}.");
    } else {
        set_flash_message('error', "Failed to update review status: " . $stmt->error);
    }
    $stmt->close();

    header('Location: Ratings.php');
    exit();
}

// 2. Handle Action: Delete Review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_rating') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid. Please try again.');
        header('Location: Ratings.php');
        exit();
    }

    $rating_id = (int)($_POST['rating_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM ratings WHERE id = ?");
    $stmt->bind_param("i", $rating_id);
    if ($stmt->execute()) {
        set_flash_message('success', "Review #{$rating_id} has been permanently deleted.");
    } else {
        set_flash_message('error', "Failed to delete review: " . $stmt->error);
    }
    $stmt->close();

    header('Location: Ratings.php');
    exit();
}

// 3. Global Tab Badge Counters (Persistent across all tabs)
$tab_counts = [
    'all' => 0,
    'star_5' => 0,
    'star_4' => 0,
    'critical' => 0, // 1 to 3 stars
    'hidden' => 0
];
$g_res = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as s5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as s4,
        SUM(CASE WHEN rating <= 3 THEN 1 ELSE 0 END) as crit,
        SUM(CASE WHEN admin_status = 'hidden' THEN 1 ELSE 0 END) as hid
    FROM ratings
");
if ($g_res && $gr = $g_res->fetch_assoc()) {
    $tab_counts['all'] = (int)$gr['total'];
    $tab_counts['star_5'] = (int)$gr['s5'];
    $tab_counts['star_4'] = (int)$gr['s4'];
    $tab_counts['critical'] = (int)$gr['crit'];
    $tab_counts['hidden'] = (int)$gr['hid'];
}

// 4. Supplier List for Dropdown
$suppliers_list = [];
$sup_res = $conn->query("SELECT id, business_name FROM suppliers ORDER BY business_name ASC");
if ($sup_res) {
    while ($s = $sup_res->fetch_assoc()) {
        $suppliers_list[] = $s;
    }
}

// 5. Filtering, Search & Sorting Parameters
$filter_supplier = isset($_GET['supplier_id']) && $_GET['supplier_id'] !== '' ? (int)$_GET['supplier_id'] : 0;
$filter_rating = isset($_GET['rating']) && $_GET['rating'] !== '' ? (int)$_GET['rating'] : 0;
$filter_scope = isset($_GET['rating_scope']) ? trim($_GET['rating_scope']) : '';
$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$where = ["1=1"];
$params = [];
$types = "";

if ($filter_supplier > 0) {
    $where[] = "r.supplier_id = ?";
    $params[] = $filter_supplier;
    $types .= "i";
}

if ($filter_rating > 0) {
    $where[] = "r.rating = ?";
    $params[] = $filter_rating;
    $types .= "i";
}

if ($filter_scope === 'critical') {
    $where[] = "r.rating <= 3";
}

if (!empty($filter_status)) {
    $where[] = "r.admin_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($search_query)) {
    $where[] = "(u.fullname LIKE ? OR u.username LIKE ? OR s.business_name LIKE ? OR r.review_title LIKE ? OR r.review_text LIKE ? OR r.booking_id LIKE ?)";
    $like = "%{$search_query}%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

$where_clause = implode(" AND ", $where);

// Sorting logic
$order_clause = "ORDER BY r.created_at DESC";
if ($sort_by === 'highest') {
    $order_clause = "ORDER BY r.rating DESC, r.created_at DESC";
} elseif ($sort_by === 'lowest') {
    $order_clause = "ORDER BY r.rating ASC, r.created_at DESC";
} elseif ($sort_by === 'oldest') {
    $order_clause = "ORDER BY r.created_at ASC";
}

// 6. Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=eventflare_ratings_export_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Review ID', 'Booking ID', 'Customer Name', 'Customer Username', 'Customer Email', 'Supplier Name', 'Supplier Category', 'Rating (1-5)', 'Headline', 'Feedback Text', 'Moderation Status', 'Date Created']);

    $export_sql = "
        SELECT r.id, r.booking_id, u.fullname as customer_name, u.username as customer_username, u.email as customer_email,
               s.business_name as supplier_name, s.category as supplier_category,
               r.rating, r.review_title, r.review_text, r.admin_status, r.created_at
        FROM ratings r
        JOIN users u ON r.user_id = u.id
        JOIN suppliers s ON r.supplier_id = s.id
        LEFT JOIN bookings b ON r.booking_id = b.BookingID
        WHERE {$where_clause}
        {$order_clause}
    ";

    if (!empty($params)) {
        $ex_stmt = $conn->prepare($export_sql);
        $ex_stmt->bind_param($types, ...$params);
        $ex_stmt->execute();
        $ex_res = $ex_stmt->get_result();
        while ($row = $ex_res->fetch_assoc()) {
            fputcsv($output, [
                $row['id'], $row['booking_id'], $row['customer_name'], $row['customer_username'], $row['customer_email'],
                $row['supplier_name'], $row['supplier_category'], $row['rating'], $row['review_title'], $row['review_text'],
                $row['admin_status'], $row['created_at']
            ]);
        }
        $ex_stmt->close();
    } else {
        $ex_res = $conn->query($export_sql);
        if ($ex_res) {
            while ($row = $ex_res->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'], $row['booking_id'], $row['customer_name'], $row['customer_username'], $row['customer_email'],
                    $row['supplier_name'], $row['supplier_category'], $row['rating'], $row['review_title'], $row['review_text'],
                    $row['admin_status'], $row['created_at']
                ]);
            }
        }
    }
    fclose($output);
    exit();
}

// 7. Dynamic Analytics & Metrics Calculation (respects active search & filters)
$stats = [
    'total_reviews' => 0,
    'avg_rating' => 0.0,
    'star_5' => 0,
    'star_4' => 0,
    'star_3' => 0,
    'star_2' => 0,
    'star_1' => 0,
    'hidden_count' => 0,
    'five_star_pct' => 0
];

$stat_sql = "
    SELECT 
        COUNT(*) as total,
        AVG(r.rating) as avg_score,
        SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as star_5,
        SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as star_4,
        SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as star_3,
        SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as star_2,
        SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as star_1,
        SUM(CASE WHEN r.admin_status = 'hidden' THEN 1 ELSE 0 END) as hidden_cnt
    FROM ratings r
    JOIN users u ON r.user_id = u.id
    JOIN suppliers s ON r.supplier_id = s.id
    WHERE {$where_clause}
";

if (!empty($params)) {
    $stat_stmt = $conn->prepare($stat_sql);
    $stat_stmt->bind_param($types, ...$params);
    $stat_stmt->execute();
    $stat_res = $stat_stmt->get_result();
    $s_row = $stat_res ? $stat_res->fetch_assoc() : null;
    $stat_stmt->close();
} else {
    $stat_res = $conn->query($stat_sql);
    $s_row = $stat_res ? $stat_res->fetch_assoc() : null;
}

if ($s_row) {
    $stats['total_reviews'] = (int)($s_row['total'] ?? 0);
    $stats['avg_rating'] = $stats['total_reviews'] > 0 ? round((float)$s_row['avg_score'], 1) : 0.0;
    $stats['star_5'] = (int)($s_row['star_5'] ?? 0);
    $stats['star_4'] = (int)($s_row['star_4'] ?? 0);
    $stats['star_3'] = (int)($s_row['star_3'] ?? 0);
    $stats['star_2'] = (int)($s_row['star_2'] ?? 0);
    $stats['star_1'] = (int)($s_row['star_1'] ?? 0);
    $stats['hidden_count'] = (int)($s_row['hidden_cnt'] ?? 0);
    if ($stats['total_reviews'] > 0) {
        $stats['five_star_pct'] = round(($stats['star_5'] / $stats['total_reviews']) * 100);
    }
}

// 8. Fetch Detailed Reviews List
$sql = "
    SELECT 
        r.*, 
        u.username as customer_username, 
        u.fullname as customer_name, 
        u.email as customer_email,
        s.business_name as supplier_name, 
        s.category as supplier_category,
        s.contact_phone as supplier_phone,
        b.EventType, 
        b.Place as event_place, 
        b.EventDate, 
        b.NumberOfGuests
    FROM ratings r
    JOIN users u ON r.user_id = u.id
    JOIN suppliers s ON r.supplier_id = s.id
    LEFT JOIN bookings b ON r.booking_id = b.BookingID
    WHERE {$where_clause}
    {$order_clause}
";

$reviews = [];
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $reviews[] = $row;
    }
    $stmt->close();
} else {
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ratings & Reviews Console - EVENTFLARE Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
    <style>
        .star-gold { color: #f59e0b; }
        .star-gray { color: #cbd5e1; }

        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 800;
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fef3c7;
        }

        /* Segmented Filter Navigation Bar */
        .segmented-filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 6px;
            border-radius: 14px;
            margin-bottom: 24px;
            overflow-x: auto;
            box-shadow: var(--shadow-sm);
        }

        .seg-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .seg-tab-btn:hover {
            color: var(--text-heading);
            background: rgba(0, 0, 0, 0.03);
        }

        .seg-tab-btn.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
        }

        .seg-tab-count {
            padding: 2px 7px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            background: rgba(0, 0, 0, 0.07);
        }

        .seg-tab-btn.active .seg-tab-count {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* Interactive Rating Distribution Card */
        .breakdown-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 30px;
            align-items: center;
        }

        @media (max-width: 800px) {
            .breakdown-card {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        .overall-score-box {
            text-align: center;
            border-right: 1px solid rgba(0,0,0,0.06);
            padding-right: 25px;
        }

        @media (max-width: 800px) {
            .overall-score-box {
                border-right: none;
                border-bottom: 1px solid rgba(0,0,0,0.06);
                padding-right: 0;
                padding-bottom: 20px;
            }
        }

        .big-score {
            font-size: 48px;
            font-weight: 900;
            color: var(--text-heading);
            line-height: 1;
            margin-bottom: 6px;
        }

        .score-stars {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .distribution-bars {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dist-row-link {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            text-decoration: none;
            color: inherit;
            padding: 4px 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dist-row-link:hover {
            background: #f8fafc;
            transform: translateX(4px);
        }

        .dist-row-link.active-dist {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .dist-label {
            width: 50px;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .dist-bar-track {
            flex: 1;
            height: 10px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
        }

        .dist-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
            border-radius: 10px;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dist-count {
            width: 70px;
            text-align: right;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Sentiment Badges */
        .sentiment-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 12px;
            margin-top: 4px;
        }
        .sentiment-exceptional { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .sentiment-great { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .sentiment-average { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .sentiment-critical { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .status-pill-approved { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-pill-hidden { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body class="dashboard-body">

    <!-- Admin Sidebar -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Customer Ratings & Reviews Console</h1>
                <p>Real-time customer satisfaction analytics, verified booking feedback, and moderation management.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- Segmented 1-Click Navigation Filters -->
        <div class="segmented-filter-bar">
            <a href="Ratings.php" class="seg-tab-btn <?= (empty($filter_rating) && empty($filter_scope) && empty($filter_status)) ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> All Reviews
                <span class="seg-tab-count"><?= $tab_counts['all']; ?></span>
            </a>
            <a href="Ratings.php?rating=5" class="seg-tab-btn <?= ($filter_rating === 5) ? 'active' : ''; ?>">
                <i class="fas fa-gem" style="color: #f59e0b;"></i> 5 Stars (Exceptional)
                <span class="seg-tab-count"><?= $tab_counts['star_5']; ?></span>
            </a>
            <a href="Ratings.php?rating=4" class="seg-tab-btn <?= ($filter_rating === 4) ? 'active' : ''; ?>">
                <i class="fas fa-thumbs-up" style="color: #3b82f6;"></i> 4 Stars (Great)
                <span class="seg-tab-count"><?= $tab_counts['star_4']; ?></span>
            </a>
            <a href="Ratings.php?rating_scope=critical" class="seg-tab-btn <?= ($filter_scope === 'critical') ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Critical / 1-3★
                <span class="seg-tab-count"><?= $tab_counts['critical']; ?></span>
            </a>
            <a href="Ratings.php?status=hidden" class="seg-tab-btn <?= ($filter_status === 'hidden') ? 'active' : ''; ?>">
                <i class="fas fa-eye-slash" style="color: #64748b;"></i> Moderated / Hidden
                <span class="seg-tab-count"><?= $tab_counts['hidden']; ?></span>
            </a>
        </div>

        <!-- KPI Metric Cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon icon-orange"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($stats['avg_rating'], 1); ?> <span style="font-size: 15px; color: var(--text-muted); font-weight: 500;">/ 5.0</span></h3>
                    <p><?= ($filter_supplier > 0 || !empty($search_query) || $filter_rating > 0) ? 'Filtered Average Rating' : 'Platform Average Rating'; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-comments"></i></div>
                <div class="stat-info">
                    <h3><?= $stats['total_reviews']; ?></h3>
                    <p><?= ($filter_supplier > 0 || !empty($search_query) || $filter_rating > 0) ? 'Matching Reviews' : 'Total Verified Reviews'; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-heart"></i></div>
                <div class="stat-info">
                    <h3><?= $stats['five_star_pct']; ?>%</h3>
                    <p>5-Star Satisfaction Ratio</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-shield-alt"></i></div>
                <div class="stat-info">
                    <h3><?= $stats['hidden_count']; ?></h3>
                    <p>Hidden / Flagged Reviews</p>
                </div>
            </div>
        </div>

        <!-- Rating Distribution Summary (Clickable for Instant Drill-Down) -->
        <div class="breakdown-card">
            <div class="overall-score-box">
                <div class="big-score"><?= number_format($stats['avg_rating'], 1); ?></div>
                <div class="score-stars">
                    <?php
                    $full_stars = floor($stats['avg_rating']);
                    $half_star = ($stats['avg_rating'] - $full_stars >= 0.5) ? 1 : 0;
                    $empty_stars = 5 - $full_stars - $half_star;
                    for ($i = 0; $i < $full_stars; $i++) echo '<i class="fas fa-star star-gold"></i> ';
                    if ($half_star) echo '<i class="fas fa-star-half-alt star-gold"></i> ';
                    for ($i = 0; $i < $empty_stars; $i++) echo '<i class="far fa-star star-gray"></i> ';
                    ?>
                </div>
                <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
                    Based on <?= $stats['total_reviews']; ?> review<?= $stats['total_reviews'] === 1 ? '' : 's' ?>
                </div>
                <?php if ($filter_supplier > 0 || !empty($search_query) || $filter_rating > 0 || !empty($filter_status)): ?>
                    <div style="margin-top: 8px;">
                        <a href="Ratings.php" style="font-size: 11px; font-weight: 700; color: var(--primary); text-decoration: underline;">View All Platform Reviews</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="distribution-bars">
                <?php
                for ($star = 5; $star >= 1; $star--):
                    $cnt = (int)($stats["star_{$star}"] ?? 0);
                    $pct = ($stats['total_reviews'] > 0) ? round(($cnt / $stats['total_reviews']) * 100) : 0;
                    $is_active_star = ($filter_rating === $star);
                ?>
                    <a href="Ratings.php?rating=<?= $star; ?>" 
                       class="dist-row-link <?= $is_active_star ? 'active-dist' : ''; ?>"
                       title="Click to view only <?= $star; ?>-star reviews">
                        <span class="dist-label"><?= $star; ?> <i class="fas fa-star star-gold" style="font-size: 11px;"></i></span>
                        <div class="dist-bar-track">
                            <div class="dist-bar-fill" style="width: <?= $pct; ?>%;"></div>
                        </div>
                        <span class="dist-count">
                            <strong><?= $cnt; ?></strong> <small style="color:#94a3b8;">(<?= $pct; ?>%)</small>
                        </span>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Filter, Search & Export Controls -->
        <div class="table-card" style="margin-bottom: 25px; padding: 20px;">
            <form method="GET" action="Ratings.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                    <!-- Search Input -->
                    <div style="min-width: 220px; flex: 1;">
                        <input type="text" name="q" value="<?= htmlspecialchars($search_query); ?>" 
                               class="form-input" placeholder="Search client, vendor, booking ID, keywords..."
                               style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);">
                    </div>

                    <!-- Supplier Filter -->
                    <div style="min-width: 180px;">
                        <select name="supplier_id" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);">
                            <option value="">All Suppliers (<?= count($suppliers_list); ?>)</option>
                            <?php foreach ($suppliers_list as $sl): ?>
                                <option value="<?= $sl['id']; ?>" <?= ($filter_supplier === (int)$sl['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sl['business_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Star Score Filter -->
                    <div style="min-width: 140px;">
                        <select name="rating" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);">
                            <option value="">All Ratings</option>
                            <option value="5" <?= ($filter_rating === 5) ? 'selected' : ''; ?>>5 Stars ★★★★★</option>
                            <option value="4" <?= ($filter_rating === 4) ? 'selected' : ''; ?>>4 Stars ★★★★</option>
                            <option value="3" <?= ($filter_rating === 3) ? 'selected' : ''; ?>>3 Stars ★★★</option>
                            <option value="2" <?= ($filter_rating === 2) ? 'selected' : ''; ?>>2 Stars ★★</option>
                            <option value="1" <?= ($filter_rating === 1) ? 'selected' : ''; ?>>1 Star ★</option>
                        </select>
                    </div>

                    <!-- Sorting Dropdown -->
                    <div style="min-width: 150px;">
                        <select name="sort" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);">
                            <option value="newest" <?= ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="highest" <?= ($sort_by === 'highest') ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="lowest" <?= ($sort_by === 'lowest') ? 'selected' : ''; ?>>Lowest Rating</option>
                            <option value="oldest" <?= ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 16px; border-radius: 8px;">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <?php if (!empty($search_query) || $filter_supplier > 0 || $filter_rating > 0 || !empty($filter_status) || !empty($filter_scope) || $sort_by !== 'newest'): ?>
                        <a href="Ratings.php" class="btn btn-outline" style="padding: 10px 14px; border-radius: 8px;" title="Reset filters">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                    <a href="Ratings.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-outline" style="padding: 10px 14px; border-radius: 8px;" title="Export CSV spreadsheet">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <button type="button" id="downloadPdfBtn" class="btn btn-outline" style="padding: 10px 14px; border-radius: 8px;" title="Download PDF Report">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- Customer & Supplier Reviews Table -->
        <div class="table-card" id="makepdf">
            <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Verified Customer Reviews</h2>
                    <p style="color: var(--text-muted); font-size: 13px; margin-top: 3px;">
                        Displaying <?= count($reviews); ?> review record<?= count($reviews) === 1 ? '' : 's'; ?>
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Customer Account</th>
                            <th>Contracted Supplier</th>
                            <th>Rating & Sentiment</th>
                            <th>Headline & Review Comments</th>
                            <th>Reservation</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td><strong>#<?= $rev['id']; ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), #a855f7); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                                <?= strtoupper(substr($rev['customer_name'] ?: $rev['customer_username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="Userlist.php?search=<?= urlencode($rev['customer_username']); ?>" 
                                                   style="font-weight: 700; color: var(--text-heading); text-decoration: none;"
                                                   title="Inspect Customer in Directory">
                                                    <?= htmlspecialchars($rev['customer_name']); ?>
                                                </a>
                                                <div style="font-size: 11px; color: #15803d; font-weight: 700; display: flex; align-items: center; gap: 3px;">
                                                    <i class="fas fa-check-circle"></i> Verified Client
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="Suppliers.php?search=<?= urlencode($rev['supplier_name']); ?>" 
                                               style="font-weight: 700; color: var(--text-heading); text-decoration: none;"
                                               title="Inspect Supplier Dossier">
                                                <?= htmlspecialchars($rev['supplier_name']); ?>
                                            </a>
                                        </div>
                                        <small class="badge badge-neutral" style="font-size: 11px; margin-top: 3px; display: inline-block;">
                                            <?= htmlspecialchars($rev['supplier_category'] ?: 'Supplier'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="score-pill">
                                            <span><?= $rev['rating']; ?>.0</span>
                                            <i class="fas fa-star star-gold"></i>
                                        </div>
                                        <div>
                                            <?php
                                            $rval = (int)$rev['rating'];
                                            if ($rval === 5): ?>
                                                <span class="sentiment-badge sentiment-exceptional"><i class="fas fa-gem fa-xs"></i> Exceptional</span>
                                            <?php elseif ($rval === 4): ?>
                                                <span class="sentiment-badge sentiment-great"><i class="fas fa-thumbs-up fa-xs"></i> Great</span>
                                            <?php elseif ($rval === 3): ?>
                                                <span class="sentiment-badge sentiment-average"><i class="fas fa-balance-scale fa-xs"></i> Average</span>
                                            <?php else: ?>
                                                <span class="sentiment-badge sentiment-critical"><i class="fas fa-exclamation-triangle fa-xs"></i> Critical</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="max-width: 340px;">
                                        <?php if (!empty($rev['review_title'])): ?>
                                            <div style="font-weight: 700; color: var(--text-heading); font-size: 13px; margin-bottom: 3px;">
                                                <?= htmlspecialchars($rev['review_title']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 12px; color: #475569; line-height: 1.45;">
                                            <?= htmlspecialchars(mb_strimwidth($rev['review_text'] ?? '', 0, 110, '...')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="Bookinglist.php?search=<?= urlencode($rev['booking_id']); ?>" 
                                           style="font-family: monospace; font-size: 11px; font-weight: 800; color: var(--primary); background: #f3e8ff; padding: 4px 8px; border-radius: 6px; text-decoration: none;"
                                           title="Inspect Booking Dossier">
                                            #<?= htmlspecialchars($rev['booking_id']); ?>
                                        </a>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                                            <?= htmlspecialchars($rev['EventType'] ?? 'Event'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 12px; color: var(--text-muted);">
                                            <?= date('M j, Y', strtotime($rev['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($rev['admin_status'] === 'approved'): ?>
                                            <span class="badge status-pill-approved" style="display:inline-flex; align-items:center; gap:4px; font-size: 11px;">
                                                <i class="fas fa-check-circle fa-xs"></i> Approved
                                            </span>
                                        <?php else: ?>
                                            <span class="badge status-pill-hidden" style="display:inline-flex; align-items:center; gap:4px; font-size: 11px;">
                                                <i class="fas fa-eye-slash fa-xs"></i> Hidden
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="table-actions" style="justify-content: flex-end;">
                                            <!-- Inspect Review Dossier -->
                                            <button type="button" class="btn btn-outline btn-sm inspect-review-btn" 
                                                    data-review='<?= htmlspecialchars(json_encode($rev), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="Inspect Full Review Dossier & Booking Context"
                                                    style="border-color: var(--primary); color: var(--primary); font-weight: 700;">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Toggle Visibility (Hide / Approve) -->
                                            <form method="POST" action="Ratings.php" style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="rating_id" value="<?= $rev['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?= ($rev['admin_status'] === 'approved') ? 'hidden' : 'approved'; ?>">
                                                <?php if ($rev['admin_status'] === 'approved'): ?>
                                                    <button type="submit" class="btn btn-outline btn-sm" title="Hide review from supplier profile" style="color: #ea580c; border-color: #fdba74;">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-outline btn-sm" title="Approve and publish review" style="color: #16a34a; border-color: #86efac;">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete Review -->
                                            <form method="POST" action="Ratings.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete review #<?= $rev['id']; ?>?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_rating">
                                                <input type="hidden" name="rating_id" value="<?= $rev['id']; ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Review Permanently">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 45px 20px; color: var(--text-muted);">
                                    <i class="fas fa-star-half-alt" style="font-size: 36px; display: block; margin-bottom: 12px; color: #cbd5e1;"></i>
                                    <div style="font-size: 16px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;">No reviews found</div>
                                    <p style="font-size: 13px; margin: 0 0 16px 0;">No client reviews match your search or filter parameters.</p>
                                    <a href="Ratings.php" class="btn btn-primary btn-sm"><i class="fas fa-undo"></i> Reset All Filters</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal: Inspect Customer Review Dossier with In-Modal Actions -->
    <div class="admin-modal" id="inspectReviewModal">
        <div class="modal-dialog" style="max-width: 680px;">
            <div class="modal-header">
                <h3><i class="fas fa-star star-gold"></i> Review Dossier #<span id="mRevId"></span></h3>
                <button type="button" class="modal-close" onclick="closeModal('inspectReviewModal')">&times;</button>
            </div>
            <div class="modal-body" id="mRevBody">
                <!-- Dynamically populated via JavaScript -->
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div id="mRevActionContainer" style="display: flex; gap: 8px;"></div>
                <button type="button" class="btn btn-secondary" onclick="closeModal('inspectReviewModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
    let activeModalReview = null;

    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.classList.remove('show');
    }

    document.querySelectorAll('.inspect-review-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const r = JSON.parse(btn.getAttribute('data-review'));
            activeModalReview = r;
            document.getElementById('mRevId').innerText = r.id;

            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= parseInt(r.rating)) {
                    starsHtml += '<i class="fas fa-star star-gold"></i> ';
                } else {
                    starsHtml += '<i class="far fa-star star-gray"></i> ';
                }
            }

            const bodyHtml = `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div>
                            <div style="font-size: 22px; font-weight: 900; color: var(--text-heading); margin-bottom: 4px;">
                                ${starsHtml} <span style="font-size: 16px; margin-left: 6px; font-weight: 800;">${r.rating}.0 / 5.0</span>
                            </div>
                            <h4 style="margin: 6px 0 0 0; font-size: 16px; color: var(--text-heading); font-weight: 700;">
                                ${r.review_title ? escapeHtml(r.review_title) : 'Customer Verified Review'}
                            </h4>
                        </div>
                        <span class="badge ${r.admin_status === 'approved' ? 'status-pill-approved' : 'status-pill-hidden'}">
                            ${r.admin_status === 'approved' ? 'Approved & Public' : 'Hidden from Public'}
                        </span>
                    </div>
                    <div style="font-size: 14px; line-height: 1.6; color: #334155; margin-top: 12px; background: #fff; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; font-style: italic;">
                        "${r.review_text ? escapeHtml(r.review_text) : 'No written commentary submitted.'}"
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 10px; text-align: right;">
                        <i class="fas fa-clock"></i> Submitted on ${new Date(r.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
                        <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">REVIEWING CLIENT</small>
                        <div style="font-weight: 700; font-size: 14px; color: var(--text-heading);">${escapeHtml(r.customer_name)}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">@${escapeHtml(r.customer_username)}</div>
                        <div style="font-size: 12px; color: var(--primary); margin-top: 4px;"><i class="fas fa-envelope fa-xs"></i> ${escapeHtml(r.customer_email || 'N/A')}</div>
                    </div>

                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
                        <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">EVALUATED SUPPLIER</small>
                        <div style="font-weight: 700; font-size: 14px; color: var(--text-heading);">${escapeHtml(r.supplier_name)}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(r.supplier_category || 'Supplier')}</div>
                        <div style="font-size: 12px; color: #16a34a; margin-top: 4px;"><i class="fas fa-phone fa-xs"></i> ${escapeHtml(r.supplier_phone || 'Direct line')}</div>
                    </div>
                </div>

                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
                    <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">ASSOCIATED RESERVATION CONTEXT</small>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-family: monospace; font-size: 13px; font-weight: 800; color: var(--primary);">#${escapeHtml(r.booking_id)}</span>
                            <span style="margin-left: 8px; font-weight: 600; color: var(--text-heading);">${escapeHtml(r.EventType || 'Event')}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            <i class="fas fa-calendar"></i> ${r.EventDate ? r.EventDate : 'N/A'}
                        </div>
                    </div>
                    ${r.event_place ? `<div style="font-size: 12px; color: #64748b; margin-top: 4px;"><i class="fas fa-map-marker-alt fa-xs" style="color: #ef4444;"></i> ${escapeHtml(r.event_place)} (${r.NumberOfGuests || 0} Guests)</div>` : ''}
                </div>
            `;

            document.getElementById('mRevBody').innerHTML = bodyHtml;

            // In-modal quick action buttons
            const toggleLabel = (r.admin_status === 'approved') ? 'Hide Review' : 'Approve Review';
            const toggleNewStatus = (r.admin_status === 'approved') ? 'hidden' : 'approved';
            const toggleIcon = (r.admin_status === 'approved') ? 'fa-eye-slash' : 'fa-check';
            const toggleColor = (r.admin_status === 'approved') ? '#ea580c' : '#16a34a';

            document.getElementById('mRevActionContainer').innerHTML = `
                <form method="POST" action="Ratings.php" style="display:inline;">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="rating_id" value="${r.id}">
                    <input type="hidden" name="new_status" value="${toggleNewStatus}">
                    <button type="submit" class="btn btn-outline btn-sm" style="color: ${toggleColor}; border-color: currentColor; font-weight: 700;">
                        <i class="fas ${toggleIcon}"></i> ${toggleLabel}
                    </button>
                </form>
                <button type="button" class="btn btn-outline btn-sm" onclick="printReviewSlip()" style="border-color: var(--primary); color: var(--primary); font-weight: 700;">
                    <i class="fas fa-print"></i> Print Slip
                </button>
            `;

            const modal = document.getElementById('inspectReviewModal');
            modal.classList.add('show');
        });
    });

    function printReviewSlip() {
        if (!activeModalReview) return;
        const r = activeModalReview;
        const win = window.open('', '', 'width=750,height=600');
        win.document.write(`
            <html>
            <head>
                <title>Review #${r.id} - ${r.supplier_name}</title>
                <style>
                    body { font-family: sans-serif; padding: 30px; color: #1e293b; }
                    h1 { color: #8b5cf6; margin: 0; font-size: 22px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
                    th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
                    th { background: #f8fafc; font-weight: 700; width: 30%; }
                </style>
            </head>
            <body>
                <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #8b5cf6; padding-bottom: 10px;">
                    <div>
                        <h1>EVENTFLARE</h1>
                        <p style="margin:4px 0 0; color:#64748b; font-size:12px;">Verified Customer Review Certificate</p>
                    </div>
                    <div style="text-align:right;">
                        <strong>Review #${r.id}</strong><br>
                        <small>Score: ${r.rating}.0 / 5.0</small>
                    </div>
                </div>
                <table>
                    <tr><th>Reviewer</th><td>${r.customer_name} (@${r.customer_username})</td></tr>
                    <tr><th>Supplier</th><td>${r.supplier_name} (${r.supplier_category || 'Vendor'})</td></tr>
                    <tr><th>Booking Reference</th><td>#${r.booking_id} &bull; ${r.EventType || 'Event'}</td></tr>
                    <tr><th>Score</th><td>${r.rating} / 5.0 Stars</td></tr>
                    <tr><th>Headline</th><td><strong>${r.review_title || 'N/A'}</strong></td></tr>
                    <tr><th>Feedback</th><td>${r.review_text || 'None'}</td></tr>
                    <tr><th>Status</th><td>${(r.admin_status || 'approved').toUpperCase()}</td></tr>
                    <tr><th>Date Submitted</th><td>${r.created_at}</td></tr>
                </table>
            </body>
            </html>
        `);
        win.document.close();
        win.focus();
        win.print();
        win.close();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function(m) {
            switch (m) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#039;';
                default: return m;
            }
        });
    }

    // PDF Download
    document.getElementById("downloadPdfBtn").addEventListener("click", function () {
        const element = document.getElementById("makepdf");
        const opt = {
            margin:       10,
            filename:     'eventflare_customer_reviews_audit.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
        };
        html2pdf().set(opt).from(element).save();
    });

    window.addEventListener('click', (e) => {
        const modal = document.getElementById('inspectReviewModal');
        if (e.target === modal) closeModal('inspectReviewModal');
    });
    </script>
</body>
</html>
