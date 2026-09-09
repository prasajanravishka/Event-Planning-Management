<?php
session_start();
include __DIR__ . '/../../config/database.php';

// If logged in as admin, redirect to Admin Console (Admins manage suppliers via Admin Panel)
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: ../admin/Suppliers.php");
    exit();
}

// Ensure user is logged in as a supplier
if (!isset($_SESSION['login_user']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'supplier') {
    header("Location: ../Login.php");
    exit();
}

$listing_count = 0;
$business_name = 'Supplier Dashboard';
$rating_avg = 0.0;
$rating_count = 0;
$reviews = [];

$supp_stmt = $conn->prepare("SELECT s.id, s.business_name FROM users u JOIN suppliers s ON u.id = s.user_id WHERE u.username = ?");
$supp_stmt->bind_param("s", $_SESSION['login_user']);
$supp_stmt->execute();
$supp_res = $supp_stmt->get_result();
if ($supp_res->num_rows > 0) {
    $row = $supp_res->fetch_assoc();
    $supp_id = (int)$row['id'];
    $business_name = $row['business_name'];
    $cnt_res = $conn->query("SELECT COUNT(*) as c FROM supplier_listings WHERE supplier_id = $supp_id");
    if ($cnt_res) {
        $listing_count = (int)$cnt_res->fetch_assoc()['c'];
    }

    // Fetch verified customer ratings & reviews
    $rev_stmt = $conn->prepare("
        SELECT r.id, r.rating, r.review_title, r.review_text, r.created_at, r.booking_id,
               u.fullname as customer_name, u.username as customer_username,
               b.EventType, b.EventDate, b.Place
        FROM ratings r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN bookings b ON r.booking_id = b.BookingID
        WHERE r.supplier_id = ? AND r.admin_status = 'approved'
        ORDER BY r.created_at DESC
    ");
    if ($rev_stmt) {
        $rev_stmt->bind_param("i", $supp_id);
        $rev_stmt->execute();
        $rev_res = $rev_stmt->get_result();
        while ($rev = $rev_res->fetch_assoc()) {
            $reviews[] = $rev;
        }
        $rev_stmt->close();
    }

    $rating_count = count($reviews);
    if ($rating_count > 0) {
        $rating_avg = round(array_sum(array_column($reviews, 'rating')) / $rating_count, 1);
    }
}
$supp_stmt->close();
$conn->close();

include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($business_name); ?> - Supplier Dashboard</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .supplier-wrapper {
            max-width: 1200px;
            margin: 50px auto 100px;
            padding: 0 20px;
        }

        .supplier-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .supplier-header h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--text-heading) 30%, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .supplier-header p {
            color: var(--text-muted);
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .dash-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .dash-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 20px var(--card-glow);
        }

        .dash-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .dash-card h3 {
            font-size: 22px;
            color: var(--text-heading);
            margin-bottom: 10px;
        }

        .dash-card p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .dash-btn {
            margin-top: auto;
            width: 100%;
        }

        .star-gold { color: #f59e0b; }
        .star-gray { color: #cbd5e1; }
        .reviews-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(6px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reviews-modal.show { display: flex; }
        .reviews-dialog {
            background: #ffffff;
            border-radius: 20px;
            max-width: 680px;
            width: 100%;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="supplier-wrapper">
        <div class="supplier-header">
            <h1><?= htmlspecialchars($business_name); ?></h1>
            <p>Welcome back, <strong><?= htmlspecialchars($_SESSION['login_user']); ?></strong>! Manage your venues, services, and packages.</p>
        </div>

        <div class="dashboard-grid">
            
            <div class="dash-card">
                <i class="fas fa-id-badge dash-icon"></i>
                <h3>Business Profile</h3>
                <p>Complete or update your business details, category, phone, location, and services offered.</p>
                <a href="Profile.php" class="btn btn-primary dash-btn">Edit Profile</a>
            </div>

            <div class="dash-card">
                <i class="fas fa-store dash-icon"></i>
                <h3>My Listings</h3>
                <p>View, edit, or add new packages and services. Currently <strong><?= $listing_count; ?> package<?= $listing_count === 1 ? '' : 's'; ?></strong> listed.</p>
                <a href="Listings.php" class="btn btn-primary dash-btn">Manage Listings</a>
            </div>

            <div class="dash-card">
                <i class="fas fa-star dash-icon" style="color: #f59e0b;"></i>
                <h3>Customer Ratings</h3>
                <?php if ($rating_count > 0): ?>
                    <div style="font-size: 30px; font-weight: 900; color: var(--text-heading); margin-bottom: 4px;">
                        <?= number_format($rating_avg, 1); ?> <span style="font-size: 16px; color: var(--text-muted); font-weight: 600;">/ 5.0</span>
                    </div>
                    <div style="margin-bottom: 12px; font-size: 16px;">
                        <?php
                        $f = floor($rating_avg);
                        $h = ($rating_avg - $f >= 0.5) ? 1 : 0;
                        for ($i = 0; $i < $f; $i++) echo '<i class="fas fa-star star-gold"></i> ';
                        if ($h) echo '<i class="fas fa-star-half-alt star-gold"></i> ';
                        for ($i = 0; $i < (5 - $f - $h); $i++) echo '<i class="far fa-star star-gray"></i> ';
                        ?>
                    </div>
                    <p>Based on <strong><?= $rating_count; ?> verified review<?= $rating_count === 1 ? '' : 's' ?></strong> from clients who booked your services.</p>
                    <button type="button" class="btn btn-primary dash-btn" onclick="openReviewsModal()">
                        <i class="fas fa-comments"></i> View Customer Reviews
                    </button>
                <?php else: ?>
                    <div style="font-size: 18px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px;">
                        No Reviews Yet
                    </div>
                    <p>Deliver exceptional services on your upcoming bookings to earn verified client ratings and reviews.</p>
                    <button type="button" class="btn btn-primary dash-btn" disabled style="opacity: 0.6; cursor: not-allowed;">
                        Awaiting Bookings
                    </button>
                <?php endif; ?>
            </div>

            <div class="dash-card">
                <i class="fas fa-calendar-check dash-icon"></i>
                <h3>Active Bookings</h3>
                <p>Review event bookings that clients have requested for your services.</p>
                <button type="button" class="btn btn-primary dash-btn" disabled title="Coming Soon" style="opacity: 0.6; cursor: not-allowed;">View Bookings</button>
            </div>

            <div class="dash-card">
                <i class="fas fa-chart-line dash-icon"></i>
                <h3>Earnings & Stats</h3>
                <p>Track your revenue, view popular services, and analyze your performance on the EVENTFLARE platform.</p>
                <button type="button" class="btn btn-secondary dash-btn" disabled title="Coming Soon" style="opacity: 0.6; cursor: not-allowed;">View Analytics</button>
            </div>

        </div>
    </div>

    <!-- Modal: Verified Customer Reviews -->
    <div class="reviews-modal" id="supplierReviewsModal">
        <div class="reviews-dialog">
            <div style="padding: 20px 24px; border-bottom: 1px solid rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: #fffbeb; color: #b45309; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 1px solid #fde68a;">
                        <i class="fas fa-star star-gold"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-heading);">Client Ratings & Reviews</h3>
                        <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                            Overall Rating: <strong><?= number_format($rating_avg, 1); ?>/5.0</strong> (<?= $rating_count; ?> review<?= $rating_count === 1 ? '' : 's' ?>)
                        </p>
                    </div>
                </div>
                <button type="button" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted);" onclick="closeReviewsModal()">&times;</button>
            </div>
            <div style="padding: 24px; overflow-y: auto; max-height: calc(85vh - 140px);">
                <?php if (!empty($reviews)): ?>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($reviews as $rv): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), #a855f7); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                            <?= strtoupper(substr($rv['customer_name'] ?: $rv['customer_username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--text-heading); font-size: 14px;"><?= htmlspecialchars($rv['customer_name']); ?></div>
                                            <small style="color: var(--text-muted); font-size: 11px;">
                                                Reservation #<?= htmlspecialchars($rv['booking_id']); ?> &bull; <?= htmlspecialchars($rv['EventType'] ?? 'Event'); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 13px; margin-bottom: 2px;">
                                            <?php
                                            for ($s = 1; $s <= 5; $s++) {
                                                echo ($s <= (int)$rv['rating']) ? '<i class="fas fa-star star-gold"></i> ' : '<i class="far fa-star star-gray"></i> ';
                                            }
                                            ?>
                                        </div>
                                        <small style="font-size: 11px; color: var(--text-muted);"><?= date('M j, Y', strtotime($rv['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php if (!empty($rv['review_title'])): ?>
                                    <div style="font-weight: 700; font-size: 14px; color: var(--text-heading); margin-bottom: 4px;">
                                        <?= htmlspecialchars($rv['review_title']); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="font-size: 13px; line-height: 1.5; color: #475569;">
                                    <?= nl2br(htmlspecialchars($rv['review_text'] ?? '')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-comment-slash" style="font-size: 32px; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                        No customer reviews received yet.
                    </div>
                <?php endif; ?>
            </div>
            <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid rgba(0,0,0,0.06); text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeReviewsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
    function openReviewsModal() {
        document.getElementById('supplierReviewsModal').classList.add('show');
    }
    function closeReviewsModal() {
        document.getElementById('supplierReviewsModal').classList.remove('show');
    }
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('supplierReviewsModal');
        if (e.target === modal) closeReviewsModal();
    });
    </script>
</body>
</html>
