<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'event_types';

// Category theme resolver (icon, vibrant gradient, and soft background)
function get_category_theme($name) {
    $name_lower = strtolower($name);
    if (strpos($name_lower, 'wed') !== false) {
        return [
            'icon' => 'fa-ring',
            'gradient' => 'linear-gradient(135deg, #ec4899, #be185d)',
            'bg_soft' => '#fdf2f8',
            'text_color' => '#be185d',
            'banner_color' => '#ec4899'
        ];
    } elseif (strpos($name_lower, 'birth') !== false) {
        return [
            'icon' => 'fa-cake-candles',
            'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
            'bg_soft' => '#fffbeb',
            'text_color' => '#d97706',
            'banner_color' => '#f59e0b'
        ];
    } elseif (strpos($name_lower, 'dj') !== false || strpos($name_lower, 'party') !== false) {
        return [
            'icon' => 'fa-compact-disc',
            'gradient' => 'linear-gradient(135deg, #8b5cf6, #6d28d9)',
            'bg_soft' => '#faf5ff',
            'text_color' => '#6d28d9',
            'banner_color' => '#8b5cf6'
        ];
    } elseif (strpos($name_lower, 'corp') !== false || strpos($name_lower, 'gala') !== false || strpos($name_lower, 'conf') !== false) {
        return [
            'icon' => 'fa-briefcase',
            'gradient' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            'bg_soft' => '#eff6ff',
            'text_color' => '#1d4ed8',
            'banner_color' => '#3b82f6'
        ];
    } elseif (strpos($name_lower, 'together') !== false || strpos($name_lower, 'reunion') !== false) {
        return [
            'icon' => 'fa-champagne-glasses',
            'gradient' => 'linear-gradient(135deg, #10b981, #047857)',
            'bg_soft' => '#f0fdf4',
            'text_color' => '#047857',
            'banner_color' => '#10b981'
        ];
    } elseif (strpos($name_lower, 'hotel') !== false || strpos($name_lower, 'venue') !== false) {
        return [
            'icon' => 'fa-hotel',
            'gradient' => 'linear-gradient(135deg, #06b6d4, #0e7490)',
            'bg_soft' => '#ecfeff',
            'text_color' => '#0e7490',
            'banner_color' => '#06b6d4'
        ];
    }
    return [
        'icon' => 'fa-layer-group',
        'gradient' => 'linear-gradient(135deg, #8b5cf6, #4f46e5)',
        'bg_soft' => '#f5f3ff',
        'text_color' => '#4f46e5',
        'banner_color' => '#8b5cf6'
    ];
}

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: EventTypes.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    // Add Event Type
    if ($action === 'add_event_type') {
        $type_name = trim(htmlspecialchars($_POST['type_name'] ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($type_name)) {
            set_flash_message('error', 'Event category name is required.');
        } else {
            $check = $conn->prepare("SELECT event_type_id FROM event_types WHERE type_name = ?");
            $check->bind_param("s", $type_name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                set_flash_message('error', "An event type named '{$type_name}' already exists.");
            } else {
                $ins = $conn->prepare("INSERT INTO event_types (type_name, description, is_active) VALUES (?, ?, ?)");
                $ins->bind_param("ssi", $type_name, $description, $is_active);
                if ($ins->execute()) {
                    set_flash_message('success', "Event category '{$type_name}' created successfully.");
                } else {
                    set_flash_message('error', "Failed to create category: " . $ins->error);
                }
                $ins->close();
            }
            $check->close();
        }
    }

    // Edit Event Type
    elseif ($action === 'edit_event_type') {
        $type_id = (int)($_POST['event_type_id'] ?? 0);
        $type_name = trim(htmlspecialchars($_POST['type_name'] ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($type_id > 0 && !empty($type_name)) {
            $check = $conn->prepare("SELECT event_type_id FROM event_types WHERE type_name = ? AND event_type_id != ?");
            $check->bind_param("si", $type_name, $type_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                set_flash_message('error', "Another category with the name '{$type_name}' already exists.");
            } else {
                $upd = $conn->prepare("UPDATE event_types SET type_name = ?, description = ?, is_active = ? WHERE event_type_id = ?");
                $upd->bind_param("ssii", $type_name, $description, $is_active, $type_id);
                if ($upd->execute()) {
                    set_flash_message('success', "Event category updated successfully.");
                } else {
                    set_flash_message('error', "Failed to update category: " . $upd->error);
                }
                $upd->close();
            }
            $check->close();
        }
    }

    // Toggle Active Status
    elseif ($action === 'toggle_status') {
        $type_id = (int)($_POST['event_type_id'] ?? 0);
        if ($type_id > 0) {
            $tgl = $conn->prepare("UPDATE event_types SET is_active = IF(is_active = 1, 0, 1) WHERE event_type_id = ?");
            $tgl->bind_param("i", $type_id);
            $tgl->execute();
            $tgl->close();
            set_flash_message('success', "Category availability status updated.");
        }
    }

    // Delete Event Type
    elseif ($action === 'delete_event_type') {
        $type_id = (int)($_POST['event_type_id'] ?? 0);
        $type_name = trim($_POST['type_name'] ?? '');

        // Check if bookings reference this type
        $b_check = $conn->prepare("SELECT COUNT(*) as c FROM bookings WHERE EventType = ?");
        $b_check->bind_param("s", $type_name);
        $b_check->execute();
        $b_cnt = (int)$b_check->get_result()->fetch_assoc()['c'];
        $b_check->close();

        if ($b_cnt > 0) {
            set_flash_message('warning', "Cannot delete '{$type_name}' because {$b_cnt} existing booking(s) reference it. You can deactivate it instead.");
        } else {
            $del = $conn->prepare("DELETE FROM event_types WHERE event_type_id = ?");
            $del->bind_param("i", $type_id);
            if ($del->execute()) {
                set_flash_message('success', "Event category '{$type_name}' and its services removed.");
            } else {
                set_flash_message('error', "Failed to delete: " . $del->error);
            }
            $del->close();
        }
    }

    // Add Service to Event Type
    elseif ($action === 'add_service') {
        $type_id = (int)($_POST['event_type_id'] ?? 0);
        $service_name = trim(htmlspecialchars($_POST['service_name'] ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $typical_capacity = !empty($_POST['typical_capacity']) ? (int)$_POST['typical_capacity'] : null;

        if ($type_id > 0 && !empty($service_name)) {
            $ins_svc = $conn->prepare("INSERT INTO services (event_type_id, service_name, description, is_required, typical_capacity) VALUES (?, ?, ?, ?, ?)");
            $ins_svc->bind_param("issii", $type_id, $service_name, $description, $is_required, $typical_capacity);
            if ($ins_svc->execute()) {
                set_flash_message('success', "New service offering '{$service_name}' added.");
            } else {
                set_flash_message('error', "Failed to add service: " . $ins_svc->error);
            }
            $ins_svc->close();
        }
    }

    // Delete Service
    elseif ($action === 'delete_service') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        if ($service_id > 0) {
            $del_svc = $conn->prepare("DELETE FROM services WHERE service_id = ?");
            $del_svc->bind_param("i", $service_id);
            if ($del_svc->execute()) {
                set_flash_message('success', "Service offering removed.");
            } else {
                set_flash_message('error', "Failed to delete service.");
            }
            $del_svc->close();
        }
    }

    header('Location: EventTypes.php');
    exit();
}

// 2. Search & Filter Parameters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(et.type_name LIKE ? OR et.description LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like;
    $params[] = &$like;
    $types .= "ss";
}

if ($status_filter === 'active') {
    $where_clauses[] = "et.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_clauses[] = "et.is_active = 0";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch event types with services and bookings counts
$sql = "SELECT et.*, 
               COUNT(DISTINCT s.service_id) AS services_count,
               (SELECT COUNT(*) FROM bookings b WHERE b.EventType = et.type_name) AS bookings_count
        FROM event_types et
        LEFT JOIN services s ON et.event_type_id = s.event_type_id
        WHERE {$where_sql}
        GROUP BY et.event_type_id
        ORDER BY et.event_type_id ASC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$event_types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all services grouped by event_type_id
$services_by_type = [];
$all_svc_res = $conn->query("SELECT * FROM services ORDER BY priority_rank ASC, service_name ASC");
if ($all_svc_res) {
    while ($sr = $all_svc_res->fetch_assoc()) {
        $services_by_type[$sr['event_type_id']][] = $sr;
    }
}

// Fetch verified suppliers per event type
$type_suppliers_map = [];
$sup_et_res = $conn->query("
    SELECT DISTINCT s.event_type_id, sup.id as supplier_id, sup.business_name, sup.category, sup.contact_phone
    FROM services s
    JOIN supplier_services ss ON s.service_id = ss.service_id
    JOIN suppliers sup ON ss.supplier_id = sup.id
    ORDER BY sup.business_name ASC
");
if ($sup_et_res) {
    while ($r = $sup_et_res->fetch_assoc()) {
        $type_suppliers_map[$r['event_type_id']][] = $r;
    }
}

// KPI Metrics Calculation
$total_types = count($event_types);
$active_types = 0;
$total_services = 0;
$total_bookings = 0;
$top_category = ['name' => 'None', 'count' => 0];

foreach ($event_types as $t) {
    if ((int)$t['is_active'] === 1) {
        $active_types++;
    }
    $total_services += (int)$t['services_count'];
    $total_bookings += (int)$t['bookings_count'];
    if ((int)$t['bookings_count'] > $top_category['count']) {
        $top_category = ['name' => $t['type_name'], 'count' => (int)$t['bookings_count']];
    }
}
$top_share = ($total_bookings > 0 && $top_category['count'] > 0) ? round(($top_category['count'] / $total_bookings) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Types & Services - EVENTFLARE Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Event Types & Services Catalog</h1>
                <p>Manage celebration categories (Weddings, Birthdays, Corporate) and their service offerings.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- KPI Metrics Strip -->
        <div class="kpi-stats-strip">
            <div class="kpi-stat-card">
                <div class="kpi-stat-icon" style="background: #e0e7ff; color: #4338ca;">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="kpi-stat-content">
                    <div class="kpi-stat-label">Active Categories</div>
                    <div class="kpi-stat-value"><?= $active_types ?> <span style="font-size: 14px; font-weight: normal; color: var(--text-muted);">/ <?= $total_types ?></span></div>
                    <div class="kpi-stat-sub">Available for customer bookings</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon" style="background: #fdf2f8; color: #db2777;">
                    <i class="fas fa-concierge-bell"></i>
                </div>
                <div class="kpi-stat-content">
                    <div class="kpi-stat-label">Sub-Services Configured</div>
                    <div class="kpi-stat-value"><?= $total_services ?></div>
                    <div class="kpi-stat-sub">Component offerings across catalog</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon" style="background: #f0fdf4; color: #15803d;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="kpi-stat-content">
                    <div class="kpi-stat-label">Linked Bookings</div>
                    <div class="kpi-stat-value"><?= $total_bookings ?></div>
                    <div class="kpi-stat-sub">Active reservations tied to categories</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon" style="background: #fffbeb; color: #b45309;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="kpi-stat-content">
                    <div class="kpi-stat-label">Top Demand Category</div>
                    <div class="kpi-stat-value" style="font-size: 18px;"><?= htmlspecialchars($top_category['name']) ?></div>
                    <div class="kpi-stat-sub"><?= $top_category['count'] ?> bookings (<?= $top_share ?>% share)</div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="dashboard-toolbar">
            <form method="GET" action="EventTypes.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1; align-items: center;">
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search category title or details..." value="<?= htmlspecialchars($search); ?>">
                </div>

                <select name="status" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($status_filter === 'active') ? 'selected' : ''; ?>>Active Categories</option>
                    <option value="inactive" <?= ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive Categories</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="EventTypes.php" class="btn btn-outline btn-sm">Reset</a>
                <?php endif; ?>
            </form>

            <div class="toolbar-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <!-- View Mode Segmented Control -->
                <div class="view-toggle-group">
                    <button type="button" class="view-toggle-btn active" id="btnViewGrid" onclick="setViewMode('grid')">
                        <i class="fas fa-th-large"></i> Cards
                    </button>
                    <button type="button" class="view-toggle-btn" id="btnViewTable" onclick="setViewMode('table')">
                        <i class="fas fa-table"></i> Table
                    </button>
                </div>

                <button type="button" class="btn btn-primary btn-sm" id="openAddTypeBtn">
                    <i class="fas fa-plus-circle"></i> + Add Category
                </button>

                <a href="../Booking.php" target="_blank" class="btn btn-outline btn-sm" title="Preview how clients experience categories on the reservation form">
                    <i class="fas fa-external-link-alt"></i> Client Booking View
                </a>

                <button id="downloadPdf" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button id="downloadCsv" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>

        <div id="makepdf">
            <!-- 1. VISUAL CARD GRID VIEW -->
            <div class="event-card-grid" id="gridViewContainer">
                <?php if (!empty($event_types)): ?>
                    <?php foreach ($event_types as $t): ?>
                        <?php 
                        $is_act = ((int)$t['is_active'] === 1);
                        $theme = get_category_theme($t['type_name']);
                        $services = $services_by_type[$t['event_type_id']] ?? [];
                        $suppliers = $type_suppliers_map[$t['event_type_id']] ?? [];
                        ?>
                        <div class="event-type-card <?= $is_act ? '' : 'inactive' ?>">
                            <div class="event-card-banner" style="background: <?= $theme['gradient'] ?>;"></div>
                            
                            <div class="event-card-header">
                                <div class="event-card-identity">
                                    <div class="event-card-badge" style="background: <?= $theme['gradient'] ?>;">
                                        <i class="fas <?= $theme['icon'] ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="event-card-title"><?= htmlspecialchars($t['type_name']) ?></h3>
                                        <div class="event-card-meta">Updated <?= date('M j, Y', strtotime($t['updated_at'])) ?></div>
                                    </div>
                                </div>
                                <span class="badge <?= $is_act ? 'badge-success' : 'badge-danger' ?>" style="font-size: 11px;">
                                    <?= $is_act ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>

                            <div class="event-card-body">
                                <div class="event-card-desc">
                                    <?= htmlspecialchars($t['description'] ?: 'Curated event category offering comprehensive planning packages and specialist services.') ?>
                                </div>

                                <!-- Services Roster Preview -->
                                <div class="event-services-roster">
                                    <div class="event-services-header">
                                        <span><i class="fas fa-concierge-bell fa-xs"></i> Service Offerings (<?= count($services) ?>)</span>
                                        <small style="color: #64748b; font-size: 10px;">🔴 Mandatory</small>
                                    </div>
                                    <div class="event-services-tags">
                                        <?php if (!empty($services)): ?>
                                            <?php 
                                            $preview_count = 4;
                                            $slice = array_slice($services, 0, $preview_count);
                                            foreach ($slice as $s): 
                                                $is_req = ((int)$s['is_required'] === 1);
                                            ?>
                                                <span class="service-pill <?= $is_req ? 'mandatory' : '' ?>" title="<?= $is_req ? 'Mandatory component' : 'Optional component' ?>">
                                                    <?php if ($is_req): ?><i class="fas fa-asterisk fa-xs" style="font-size:8px;"></i><?php endif; ?>
                                                    <?= htmlspecialchars($s['service_name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($services) > $preview_count): ?>
                                                <span class="service-pill" style="background: #e2e8f0; color: #475569; font-weight: 700;">
                                                    +<?= count($services) - $preview_count ?> more
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">No sub-services configured yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="event-card-stats">
                                    <div><strong><?= (int)$t['bookings_count'] ?></strong> Bookings</div>
                                    <div>&bull;</div>
                                    <div><strong><?= count($suppliers) ?></strong> Verified Vendors</div>
                                    <div>&bull;</div>
                                    <div>ID: <strong>#<?= $t['event_type_id'] ?></strong></div>
                                </div>
                            </div>

                            <div class="event-card-footer">
                                <button type="button" class="btn btn-outline btn-sm view-services-btn"
                                        style="font-weight: 700; border-color: var(--primary); color: var(--primary);"
                                        data-type-id="<?= $t['event_type_id']; ?>"
                                        data-type-name="<?= htmlspecialchars($t['type_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-type-desc="<?= htmlspecialchars($t['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-is-active="<?= $is_act ? '1' : '0'; ?>"
                                        data-services='<?= htmlspecialchars(json_encode($services), ENT_QUOTES, 'UTF-8'); ?>'
                                        data-suppliers='<?= htmlspecialchars(json_encode($suppliers), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <i class="fas fa-list-check"></i> Services (<?= count($services) ?>)
                                </button>

                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <!-- Toggle Active Switch Form -->
                                    <form method="POST" action="EventTypes.php" style="display:inline; margin:0;">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="event_type_id" value="<?= $t['event_type_id']; ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" title="<?= $is_act ? 'Deactivate Category' : 'Activate Category'; ?>" style="padding: 6px 10px;">
                                            <i class="fas <?= $is_act ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Edit Button -->
                                    <button type="button" class="btn btn-outline btn-sm edit-type-btn"
                                            data-type='<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8'); ?>'
                                            title="Edit Category Details" style="padding: 6px 10px;">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <!-- Delete Button -->
                                    <form method="POST" action="EventTypes.php" style="display:inline; margin:0;" onsubmit="return confirm('Are you sure you want to delete category \'<?= htmlspecialchars(addslashes($t['type_name'])); ?>\'?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_event_type">
                                        <input type="hidden" name="event_type_id" value="<?= $t['event_type_id']; ?>">
                                        <input type="hidden" name="type_name" value="<?= htmlspecialchars($t['type_name']); ?>">
                                        <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Category" style="padding: 6px 10px;">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: #fff; border-radius: 18px; border: 1px dashed var(--card-border);">
                        <i class="fas fa-layer-group" style="font-size: 40px; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-heading);">No Event Categories Found</h3>
                        <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Adjust your search filters or create a new celebration category.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. COMPACT DATA TABLE VIEW -->
            <div class="dashboard-panel" id="tableViewContainer" style="display: none;">
                <div class="dashboard-table-container">
                    <table class="dashboard-table" id="typesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event Category</th>
                                <th>Description</th>
                                <th>Service Offerings</th>
                                <th>Active Bookings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($event_types)): ?>
                                <?php foreach ($event_types as $t): ?>
                                    <?php 
                                    $is_act = ((int)$t['is_active'] === 1);
                                    $theme = get_category_theme($t['type_name']);
                                    $services = $services_by_type[$t['event_type_id']] ?? [];
                                    $suppliers = $type_suppliers_map[$t['event_type_id']] ?? [];
                                    ?>
                                    <tr>
                                        <td><strong>#<?= $t['event_type_id']; ?></strong></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 8px; background: <?= $theme['gradient'] ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;">
                                                    <i class="fas <?= $theme['icon'] ?>"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; font-size: 14px; color: var(--text-heading);">
                                                        <?= htmlspecialchars($t['type_name']); ?>
                                                    </div>
                                                    <small style="color: var(--text-muted);">Updated <?= date('M j, Y', strtotime($t['updated_at'])); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="max-width: 320px; line-height: 1.4; color: var(--text-muted); font-size: 13px;">
                                                <?= htmlspecialchars($t['description'] ?: 'No description provided.'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline btn-sm view-services-btn"
                                                    data-type-id="<?= $t['event_type_id']; ?>"
                                                    data-type-name="<?= htmlspecialchars($t['type_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-type-desc="<?= htmlspecialchars($t['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-is-active="<?= $is_act ? '1' : '0'; ?>"
                                                    data-services='<?= htmlspecialchars(json_encode($services), ENT_QUOTES, 'UTF-8'); ?>'
                                                    data-suppliers='<?= htmlspecialchars(json_encode($suppliers), ENT_QUOTES, 'UTF-8'); ?>'>
                                                <i class="fas fa-list"></i> <?= (int)$t['services_count']; ?> Services
                                            </button>
                                        </td>
                                        <td>
                                            <strong><?= (int)$t['bookings_count']; ?></strong> reservations
                                        </td>
                                        <td>
                                            <span class="badge <?= $is_act ? 'badge-success' : 'badge-danger'; ?>">
                                                <?= $is_act ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <!-- Toggle Active -->
                                                <form method="POST" action="EventTypes.php" style="display:inline;">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="event_type_id" value="<?= $t['event_type_id']; ?>">
                                                    <button type="submit" class="btn btn-outline btn-sm" title="Toggle Active / Inactive">
                                                        <i class="fas <?= $is_act ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?= $is_act ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>

                                                <!-- Edit Button -->
                                                <button type="button" class="btn btn-outline btn-sm edit-type-btn"
                                                        data-type='<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8'); ?>'
                                                        title="Edit Category">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <!-- Delete Button -->
                                                <form method="POST" action="EventTypes.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete \'<?= htmlspecialchars(addslashes($t['type_name'])); ?>\'?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_event_type">
                                                    <input type="hidden" name="event_type_id" value="<?= $t['event_type_id']; ?>">
                                                    <input type="hidden" name="type_name" value="<?= htmlspecialchars($t['type_name']); ?>">
                                                    <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Category">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        <i class="fas fa-layer-group" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                        No event categories found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </main>

    <!-- Modal: Add Event Type -->
    <div class="admin-modal" id="addTypeModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add Event Category</h3>
                <button type="button" class="modal-close" onclick="closeModal('addTypeModal')">&times;</button>
            </div>
            <form method="POST" action="EventTypes.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_event_type">
                <div class="modal-body">
                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Category Name *</label>
                        <input type="text" name="type_name" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Corporate Gala, Anniversary, Reunion" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Description</label>
                        <textarea name="description" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border); min-height:80px;" placeholder="Summary of this celebration format..."></textarea>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" checked> Make available immediately for client reservations
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addTypeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Create Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Event Type -->
    <div class="admin-modal" id="editTypeModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Event Category</h3>
                <button type="button" class="modal-close" onclick="closeModal('editTypeModal')">&times;</button>
            </div>
            <form method="POST" action="EventTypes.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_event_type">
                <input type="hidden" name="event_type_id" id="editTypeId" value="">
                <div class="modal-body">
                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Category Name *</label>
                        <input type="text" name="type_name" id="editTypeName" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Description</label>
                        <textarea name="description" id="editTypeDesc" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border); min-height:80px;"></textarea>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="editTypeActive" value="1"> Active & Available
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editTypeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: 360° Category Dossier & Service Catalog -->
    <div class="admin-modal" id="servicesModal">
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div id="servicesModalIconBadge" style="width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h3 id="servicesModalTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-heading);">Category Services</h3>
                        <p id="servicesModalSubtitle" style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">Service Architecture & Contracted Suppliers</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('servicesModal')">&times;</button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <!-- Category Summary Box -->
                <div id="servicesCategoryBox" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); letter-spacing: 0.5px;">CATEGORY IDENTITY</span>
                        <span id="servicesCategoryStatusBadge"></span>
                    </div>
                    <p id="servicesCategoryDesc" style="margin: 0; font-size: 13px; color: #475569; line-height: 1.5;"></p>
                </div>

                <!-- Configured Services Section -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--text-heading); text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-concierge-bell" style="color: var(--primary);"></i> Configured Service Offerings
                        </h4>
                        <span id="servicesCountBadge" class="badge badge-primary" style="font-size: 11px;"></span>
                    </div>
                    <div id="servicesListContainer">
                        <!-- Injected by JS -->
                    </div>
                </div>

                <!-- Verified Vendors Catering to this Category -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--text-heading); text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-store" style="color: var(--primary);"></i> Verified Suppliers Offering Services
                        </h4>
                        <a href="Suppliers.php" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 10px;">
                            <i class="fas fa-external-link-alt fa-xs"></i> Suppliers Directory
                        </a>
                    </div>
                    <div id="categorySuppliersContainer">
                        <!-- Injected by JS -->
                    </div>
                </div>

                <!-- Add New Service to this Event Type -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px;">
                    <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 800; color: var(--text-heading);">
                        <i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add Service Offering to this Category
                    </h4>
                    <form method="POST" action="EventTypes.php">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="add_service">
                        <input type="hidden" name="event_type_id" id="serviceEventTypeId" value="">

                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label class="form-label" style="display:block; font-size: 12px; font-weight:700; margin-bottom:4px;">Service Name *</label>
                                <input type="text" name="service_name" class="form-input" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Drone Cinematography, Ice Carving" required>
                            </div>
                            <div>
                                <label class="form-label" style="display:block; font-size: 12px; font-weight:700; margin-bottom:4px;">Typical Capacity</label>
                                <input type="number" name="typical_capacity" class="form-input" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. 300">
                            </div>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label class="form-label" style="display:block; font-size: 12px; font-weight:700; margin-bottom:4px;">Service Description</label>
                            <input type="text" name="description" class="form-input" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="Brief summary of requirements, equipment, or culinary scope...">
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                <input type="checkbox" name="is_required" value="1"> Mandatory component for this celebration format
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700;">
                                <i class="fas fa-plus"></i> Add Service Offering
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('servicesModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        // View Mode Switcher
        function setViewMode(mode) {
            localStorage.setItem('eventTypesViewMode', mode);
            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            if (mode === 'grid') {
                document.getElementById('btnViewGrid').classList.add('active');
                document.getElementById('gridViewContainer').style.display = 'grid';
                document.getElementById('tableViewContainer').style.display = 'none';
            } else {
                document.getElementById('btnViewTable').classList.add('active');
                document.getElementById('gridViewContainer').style.display = 'none';
                document.getElementById('tableViewContainer').style.display = 'block';
            }
        }

        // Initialize view mode from localStorage (defaults to grid)
        window.addEventListener('DOMContentLoaded', () => {
            const savedMode = localStorage.getItem('eventTypesViewMode') || 'grid';
            setViewMode(savedMode);
        });

        document.getElementById('openAddTypeBtn').addEventListener('click', () => openModal('addTypeModal'));

        // Edit Event Type
        document.querySelectorAll('.edit-type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const t = JSON.parse(btn.getAttribute('data-type'));
                document.getElementById('editTypeId').value = t.event_type_id;
                document.getElementById('editTypeName').value = t.type_name || '';
                document.getElementById('editTypeDesc').value = t.description || '';
                document.getElementById('editTypeActive').checked = (parseInt(t.is_active) === 1);
                openModal('editTypeModal');
            });
        });

        // Category themes lookup in JS
        function getCategoryThemeJs(name) {
            const n = (name || '').toLowerCase();
            if (n.includes('wed')) return { icon: 'fa-ring', gradient: 'linear-gradient(135deg, #ec4899, #be185d)' };
            if (n.includes('birth')) return { icon: 'fa-cake-candles', gradient: 'linear-gradient(135deg, #f59e0b, #d97706)' };
            if (n.includes('dj') || n.includes('party')) return { icon: 'fa-compact-disc', gradient: 'linear-gradient(135deg, #8b5cf6, #6d28d9)' };
            if (n.includes('corp') || n.includes('gala') || n.includes('conf')) return { icon: 'fa-briefcase', gradient: 'linear-gradient(135deg, #3b82f6, #1d4ed8)' };
            if (n.includes('together') || n.includes('reunion')) return { icon: 'fa-champagne-glasses', gradient: 'linear-gradient(135deg, #10b981, #047857)' };
            if (n.includes('hotel') || n.includes('venue')) return { icon: 'fa-hotel', gradient: 'linear-gradient(135deg, #06b6d4, #0e7490)' };
            return { icon: 'fa-layer-group', gradient: 'linear-gradient(135deg, #8b5cf6, #4f46e5)' };
        }

        // View Services & Category Dossier
        document.querySelectorAll('.view-services-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const typeId = btn.getAttribute('data-type-id');
                const typeName = btn.getAttribute('data-type-name');
                const typeDesc = btn.getAttribute('data-type-desc') || 'No description provided.';
                const isActive = (btn.getAttribute('data-is-active') === '1');
                const services = JSON.parse(btn.getAttribute('data-services') || '[]');
                const suppliers = JSON.parse(btn.getAttribute('data-suppliers') || '[]');

                const theme = getCategoryThemeJs(typeName);

                const iconBadge = document.getElementById('servicesModalIconBadge');
                iconBadge.style.background = theme.gradient;
                iconBadge.innerHTML = `<i class="fas ${theme.icon}"></i>`;

                document.getElementById('servicesModalTitle').innerText = `${typeName} Services Catalog`;
                document.getElementById('servicesModalSubtitle').innerText = `Category #${typeId} &bull; Service Breakdown & Vendor Capabilities`;
                document.getElementById('serviceEventTypeId').value = typeId;

                document.getElementById('servicesCategoryDesc').innerText = typeDesc;
                document.getElementById('servicesCategoryStatusBadge').innerHTML = isActive 
                    ? '<span class="badge badge-success">Active & Bookable</span>'
                    : '<span class="badge badge-danger">Inactive</span>';

                document.getElementById('servicesCountBadge').innerText = `${services.length} Total Services`;

                // Render services table
                let html = '';
                if (services.length > 0) {
                    html += '<table class="dashboard-table" style="font-size: 13px;">';
                    html += '<thead><tr><th>Service Name</th><th>Description</th><th>Requirement</th><th>Typical Capacity</th><th>Action</th></tr></thead><tbody>';
                    services.forEach(s => {
                        const isReq = (parseInt(s.is_required) === 1);
                        html += `<tr>
                            <td>
                                <strong>${s.service_name}</strong>
                            </td>
                            <td style="color: var(--text-muted);">${s.description || '&mdash;'}</td>
                            <td>
                                ${isReq 
                                    ? '<span class="service-pill mandatory" style="font-size:11px;"><i class="fas fa-asterisk fa-xs"></i> Mandatory</span>' 
                                    : '<span class="service-pill" style="font-size:11px;">Optional</span>'}
                            </td>
                            <td>${s.typical_capacity ? s.typical_capacity + ' guests' : '&mdash;'}</td>
                            <td>
                                <form method="POST" action="EventTypes.php" style="margin:0;" onsubmit="return confirm('Remove service \'${s.service_name}\' from this category?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_service">
                                    <input type="hidden" name="service_id" value="${s.service_id}">
                                    <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Service"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                } else {
                    html = '<div style="text-align:center; padding: 25px; color: var(--text-muted); background: #f8fafc; border-radius: 10px; border: 1px dashed #cbd5e1;"><i class="fas fa-concierge-bell fa-2x" style="color:#cbd5e1; margin-bottom:6px; display:block;"></i>No sub-services configured for this category yet. Use the form below to add the first component.</div>';
                }
                document.getElementById('servicesListContainer').innerHTML = html;

                // Render suppliers list
                let supHtml = '';
                if (suppliers.length > 0) {
                    supHtml += '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">';
                    suppliers.forEach(sup => {
                        supHtml += `
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 700; font-size: 13px; color: var(--text-heading);">${sup.business_name}</div>
                                    <small style="color: #64748b;">${sup.category || 'Specialist'}</small>
                                </div>
                                <a href="Suppliers.php?view_id=${sup.supplier_id}" target="_blank" class="btn btn-outline btn-sm" style="padding: 3px 8px; font-size: 11px;" title="View Supplier Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        `;
                    });
                    supHtml += '</div>';
                } else {
                    supHtml = '<p style="margin: 0; color: #64748b; font-size: 13px;">No verified suppliers currently mapped to this event format.</p>';
                }
                document.getElementById('categorySuppliersContainer').innerHTML = supHtml;

                openModal('servicesModal');
            });
        });

        window.onclick = function(e) {
            ['addTypeModal', 'editTypeModal', 'servicesModal'].forEach(id => {
                const m = document.getElementById(id);
                if (e.target === m) closeModal(id);
            });
        }

        // PDF Export
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'event_types_catalog.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // CSV Export
        document.getElementById("downloadCsv").addEventListener("click", function () {
            const table = document.getElementById("typesTable");
            let csv = [];
            for (let row of table.rows) {
                let cols = [];
                for (let i = 0; i < row.cells.length - 1; i++) {
                    let text = row.cells[i].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    cols.push('"' + text.replace(/"/g, '""') + '"');
                }
                csv.push(cols.join(","));
            }
            const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "event_types.csv";
            link.click();
        });
    </script>
</body>
</html>
