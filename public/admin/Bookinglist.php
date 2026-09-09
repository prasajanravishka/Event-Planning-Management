<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'bookings';

// 1. Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Bookinglist.php');
        exit();
    }
    $booking_id = trim($_POST['booking_id'] ?? '');
    $new_status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

    if (in_array($new_status, $allowed_statuses) && !empty($booking_id)) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE BookingID = ?");
        $stmt->bind_param("ss", $new_status, $booking_id);
        if ($stmt->execute()) {
            set_flash_message('success', "Booking #{$booking_id} status updated to " . ucfirst($new_status) . ".");
        } else {
            set_flash_message('error', "Failed to update status: " . $stmt->error);
        }
        $stmt->close();
    }
    header('Location: Bookinglist.php');
    exit();
}

// 2. Handle Booking Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_booking') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Bookinglist.php');
        exit();
    }
    $booking_id = trim($_POST['booking_id'] ?? '');
    if (!empty($booking_id)) {
        $stmt = $conn->prepare("DELETE FROM bookings WHERE BookingID = ?");
        $stmt->bind_param("s", $booking_id);
        if ($stmt->execute()) {
            set_flash_message('success', "Booking #{$booking_id} has been deleted.");
        } else {
            set_flash_message('error', "Failed to delete booking: " . $stmt->error);
        }
        $stmt->close();
    }
    header('Location: Bookinglist.php');
    exit();
}

// 3. Handle Service-Supplier Assignment / Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_supplier') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Bookinglist.php');
        exit();
    }
    $booking_id = trim($_POST['booking_id'] ?? '');
    $service_id = (int)($_POST['service_id'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $listing_id = !empty($_POST['listing_id']) ? (int)$_POST['listing_id'] : null;
    $custom_notes = trim($_POST['custom_notes'] ?? '');
    $assigned_cost = (float)($_POST['assigned_cost'] ?? 0);

    if (!empty($booking_id) && $service_id > 0 && $supplier_id > 0) {
        $ins = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost) 
                               VALUES (?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE supplier_id = VALUES(supplier_id), listing_id = VALUES(listing_id), custom_notes = VALUES(custom_notes), assigned_cost = VALUES(assigned_cost)");
        $ins->bind_param("siiisd", $booking_id, $service_id, $supplier_id, $listing_id, $custom_notes, $assigned_cost);
        if ($ins->execute()) {
            set_flash_message('success', "Supplier assigned to service component successfully.");
        } else {
            set_flash_message('error', "Failed to assign supplier: " . $ins->error);
        }
        $ins->close();
    }
    header('Location: Bookinglist.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_assignment') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Bookinglist.php');
        exit();
    }
    $bs_id = (int)($_POST['booking_service_id'] ?? 0);
    if ($bs_id > 0) {
        $del = $conn->prepare("DELETE FROM booking_services WHERE id = ?");
        $del->bind_param("i", $bs_id);
        $del->execute();
        $del->close();
        set_flash_message('success', "Service-supplier assignment removed.");
    }
    header('Location: Bookinglist.php');
    exit();
}

// 3. Search, Filter & Pagination Parameters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$type_filter = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(BookingID LIKE ? OR user_name LIKE ? OR Place LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = &$status_filter;
    $types .= "s";
}

if (!empty($type_filter)) {
    $where_clauses[] = "EventType = ?";
    $params[] = &$type_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Count total matching
$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM bookings WHERE {$where_sql}");
if (!empty($types)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Fetch distinct event types for filter
$event_types = [];
$et_res = $conn->query("SELECT DISTINCT EventType FROM bookings WHERE EventType IS NOT NULL AND EventType != ''");
if ($et_res) {
    while ($r = $et_res->fetch_assoc()) {
        $event_types[] = $r['EventType'];
    }
}

// Fetch bookings with limit & offset (joined with users, event_extras, budgets)
$fetch_sql = "SELECT b.*, 
                     u.fullname as client_fullname, u.email as client_email,
                     ee.equipment, ee.food_style,
                     bg.total_budget, bg.food_budget, bg.total_spent, bg.remaining_budget, bg.variance
              FROM bookings b
              LEFT JOIN users u ON b.user_name = u.username
              LEFT JOIN event_extras ee ON b.BookingID = ee.booking_id
              LEFT JOIN budgets bg ON b.BookingID = bg.booking_id
              WHERE {$where_sql} 
              ORDER BY b.EventDate DESC, b.BookingID DESC 
              LIMIT ? OFFSET ?";
$params[] = &$limit;
$params[] = &$offset;
$types .= "ii";

$stmt = $conn->prepare($fetch_sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch assigned services & suppliers per booking
$booking_services_map = [];
$bs_query = "SELECT bs.id as bs_id, bs.booking_id, bs.service_id, bs.supplier_id, bs.listing_id, 
                    bs.custom_notes, bs.assigned_cost, bs.status as assignment_status,
                    s.service_name, s.is_required,
                    sup.business_name, sup.contact_phone, sup.location as supplier_location,
                    sl.title as listing_title, sl.price_type
             FROM booking_services bs
             JOIN services s ON bs.service_id = s.service_id
             LEFT JOIN suppliers sup ON bs.supplier_id = sup.id
             LEFT JOIN supplier_listings sl ON bs.listing_id = sl.listing_id
             ORDER BY s.priority_rank ASC, bs.id ASC";
$bs_res = $conn->query($bs_query);
if ($bs_res) {
    while ($row = $bs_res->fetch_assoc()) {
        $bid = $row['booking_id'];
        if (!isset($booking_services_map[$bid])) {
            $booking_services_map[$bid] = [];
        }
        $booking_services_map[$bid][] = $row;
    }
}

// Fetch all active suppliers and services for the assignment modal
$all_suppliers = [];
$sup_res = $conn->query("SELECT id, business_name, category, contact_phone FROM suppliers ORDER BY business_name ASC");
if ($sup_res) {
    while ($row = $sup_res->fetch_assoc()) {
        $all_suppliers[] = $row;
    }
}

$all_services = [];
$svc_res = $conn->query("SELECT s.service_id, s.service_name, et.type_name 
                         FROM services s 
                         JOIN event_types et ON s.event_type_id = et.event_type_id 
                         ORDER BY et.type_name, s.service_name");
if ($svc_res) {
    while ($row = $svc_res->fetch_assoc()) {
        $all_services[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - EVENTFLARE Admin</title>
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
                <h1>Event Bookings</h1>
                <p>Track client reservations, manage approval workflows, and export reports.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- Toolbar -->
        <div class="dashboard-toolbar">
            <form method="GET" action="Bookinglist.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search ID, Client, Place..." value="<?= htmlspecialchars($search); ?>">
                </div>

                <select name="status" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?= ($status_filter === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="in_progress" <?= ($status_filter === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?= ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?= ($status_filter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>

                <select name="type" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Event Types</option>
                    <?php foreach ($event_types as $et): ?>
                        <option value="<?= htmlspecialchars($et); ?>" <?= ($type_filter === $et) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($et); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter)): ?>
                    <a href="Bookinglist.php" class="btn btn-outline btn-sm">Reset</a>
                <?php endif; ?>
            </form>

            <div class="toolbar-actions" style="display: flex; gap: 10px;">
                <button id="downloadPdf" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button id="downloadCsv" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>

        <!-- Bookings Table Panel -->
        <div class="dashboard-panel" id="makepdf">
            <div class="dashboard-table-container">
                <table class="dashboard-table" id="bookingsTable">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Client</th>
                            <th>Event Type</th>
                            <th>Location</th>
                            <th>Guests</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Workflow & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $b): ?>
                                <?php 
                                    $st = $b['status'] ?: 'pending'; 
                                    $badge_class = match($st) {
                                        'confirmed' => 'badge-confirmed',
                                        'in_progress' => 'badge-in_progress',
                                        'completed' => 'badge-completed',
                                        'cancelled' => 'badge-cancelled',
                                        default => 'badge-pending'
                                    };
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($b['BookingID']); ?></strong></td>
                                    <td>
                                        <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                                        <?= htmlspecialchars($b['user_name']); ?>
                                    </td>
                                    <td><?= htmlspecialchars($b['EventType']); ?></td>
                                    <td><i class="fas fa-map-marker-alt fa-xs" style="color: #ef4444;"></i> <?= htmlspecialchars($b['Place']); ?></td>
                                    <td><?= (int)$b['NumberOfGuests']; ?></td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($b['EventDate'])); ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($b['DayNight']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_class; ?>"><?= ucfirst(str_replace('_', ' ', $st)); ?></span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <!-- Inline Status Dropdown -->
                                            <form method="POST" action="Bookinglist.php" class="status-select-form">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['BookingID']); ?>">
                                                <select name="status" class="status-select" onchange="this.form.submit()">
                                                    <option value="pending" <?= ($st === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="confirmed" <?= ($st === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="in_progress" <?= ($st === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="completed" <?= ($st === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?= ($st === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>

                                            <!-- Detail Modal Trigger -->
                                            <?php 
                                                $b_services = $booking_services_map[$b['BookingID']] ?? [];
                                            ?>
                                            <button type="button" class="btn btn-outline btn-sm view-details-btn" 
                                                    data-booking='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8'); ?>'
                                                    data-services='<?= htmlspecialchars(json_encode($b_services), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="View Full Booking Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Delete Action -->
                                            <form method="POST" action="Bookinglist.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete Booking #<?= htmlspecialchars($b['BookingID']); ?>?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_booking">
                                                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['BookingID']); ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Booking">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-calendar-times" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                    No bookings found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?= min($total_rows, $offset + 1); ?> to <?= min($total_rows, $offset + count($bookings)); ?> of <?= $total_rows; ?> bookings
                    </div>
                    <div class="pagination-controls">
                        <a href="?page=<?= max(1, $page - 1); ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status_filter); ?>&type=<?= urlencode($type_filter); ?>" 
                           class="pagination-btn <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            &larr; Prev
                        </a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status_filter); ?>&type=<?= urlencode($type_filter); ?>" 
                               class="pagination-btn <?= ($i === $page) ? 'active' : ''; ?>">
                                <?= $i; ?>
                            </a>
                        <?php endfor; ?>
                        <a href="?page=<?= min($total_pages, $page + 1); ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status_filter); ?>&type=<?= urlencode($type_filter); ?>" 
                           class="pagination-btn <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            Next &rarr;
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- 360-Degree Reservation Dossier Modal -->
    <div class="admin-modal" id="bookingDetailModal">
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div>
                        <h3 id="dossierModalTitle" style="margin: 0; font-size: 17px; font-weight: 700;">Reservation Dossier</h3>
                        <small id="dossierModalSub" style="color: var(--text-muted); font-size: 12px;"></small>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeDetailModal()">&times;</button>
            </div>
            
            <div class="modal-body" id="bookingDetailBody">
                <!-- Injected via JavaScript -->
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <div id="dossierWorkflowActions" style="display: flex; gap: 8px;">
                    <!-- Status Action Buttons injected via JS -->
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline btn-sm" id="dossierPrintBtn">
                        <i class="fas fa-print"></i> Print Voucher
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeDetailModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Supplier Modal -->
    <div class="admin-modal" id="assignSupplierModal">
        <div class="modal-dialog" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-handshake" style="color: var(--primary);"></i> Assign Vendor to Service</h3>
                <button type="button" class="modal-close" onclick="closeAssignModal()">&times;</button>
            </div>
            <form method="POST" action="Bookinglist.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="assign_supplier">
                <input type="hidden" name="booking_id" id="assignBookingId">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px;">SERVICE COMPONENT</label>
                        <select name="service_id" id="assignServiceId" class="toolbar-select" style="width:100%;" required>
                            <option value="">Select Service Offering...</option>
                            <?php foreach ($all_services as $s): ?>
                                <option value="<?= $s['service_id']; ?>"><?= htmlspecialchars($s['type_name'] . ' &rarr; ' . $s['service_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px;">SUPPLIER / VENDOR</label>
                        <select name="supplier_id" id="assignSupplierId" class="toolbar-select" style="width:100%;" required>
                            <option value="">Select Registered Supplier...</option>
                            <?php foreach ($all_suppliers as $sup): ?>
                                <option value="<?= $sup['id']; ?>"><?= htmlspecialchars($sup['business_name'] . ' (' . $sup['category'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px;">ALLOCATED / AGREED COST (Rs.)</label>
                        <input type="number" step="0.01" name="assigned_cost" id="assignCost" class="toolbar-select" style="width:100%;" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; font-size: 12px;">CUSTOM INSTRUCTIONS / PACKAGE DETAILS</label>
                        <textarea name="custom_notes" id="assignNotes" class="toolbar-select" style="width:100%; height:70px; resize:none;" placeholder="Notes regarding schedule, delivery, or custom setup..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Save Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Voucher Print Template -->
    <div class="voucher-print-wrap" id="voucherPrintArea"></div>

    <script>
        // 360-Degree Dossier Modal Handler
        const modal = document.getElementById('bookingDetailModal');
        const modalBody = document.getElementById('bookingDetailBody');
        const modalTitle = document.getElementById('dossierModalTitle');
        const modalSub = document.getElementById('dossierModalSub');
        const workflowActions = document.getElementById('dossierWorkflowActions');
        const printBtn = document.getElementById('dossierPrintBtn');
        const assignModal = document.getElementById('assignSupplierModal');
        let currentBookingData = null;
        let currentBookingServices = [];

        function openAssignModal(bookingId) {
            document.getElementById('assignBookingId').value = bookingId;
            assignModal.classList.add('show');
        }

        function closeAssignModal() {
            assignModal.classList.remove('show');
        }

        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const data = JSON.parse(btn.getAttribute('data-booking'));
                const services = JSON.parse(btn.getAttribute('data-services') || '[]');
                currentBookingData = data;
                currentBookingServices = services;

                modalTitle.innerText = `Reservation Dossier: #${data.BookingID}`;
                modalSub.innerText = `Client: ${data.client_fullname || data.user_name} • Booked on ${data.created_at || 'Recent'}`;

                // Determine Timeline Progress
                const st = data.status || 'pending';
                const isCancelled = (st === 'cancelled');

                let pendingClass = 'completed';
                let confirmedClass = (st === 'confirmed' || st === 'in_progress' || st === 'completed') ? 'completed' : (st === 'pending' ? 'active' : '');
                let inProgClass = (st === 'in_progress' || st === 'completed') ? 'completed' : (st === 'confirmed' ? 'active' : '');
                let completedClass = (st === 'completed') ? 'completed' : (st === 'in_progress' ? 'active' : '');

                let stepperHtml = '';
                if (isCancelled) {
                    stepperHtml = `
                        <div class="stepper-container" style="background: #fef2f2; border-color: #fee2e2;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; color: #b91c1c; font-weight: 700; font-size: 14px;">
                                <i class="fas fa-ban fa-lg"></i> This reservation was CANCELLED.
                            </div>
                        </div>
                    `;
                } else {
                    stepperHtml = `
                        <div class="stepper-container">
                            <div class="stepper-steps">
                                <div class="stepper-step ${pendingClass}">
                                    <div class="stepper-icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="stepper-label">Submitted</div>
                                </div>
                                <div class="stepper-step ${confirmedClass}">
                                    <div class="stepper-icon"><i class="fas fa-check"></i></div>
                                    <div class="stepper-label">Confirmed</div>
                                </div>
                                <div class="stepper-step ${inProgClass}">
                                    <div class="stepper-icon"><i class="fas fa-spinner"></i></div>
                                    <div class="stepper-label">In Progress</div>
                                </div>
                                <div class="stepper-step ${completedClass}">
                                    <div class="stepper-icon"><i class="fas fa-flag-checkered"></i></div>
                                    <div class="stepper-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    `;
                }

                // Countdown logic
                const eventDate = new Date(data.EventDate);
                const today = new Date();
                today.setHours(0,0,0,0);
                const diffTime = eventDate - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                let countdownBadge = '';
                if (diffDays > 0) {
                    countdownBadge = `<span class="countdown-chip"><i class="fas fa-hourglass-start"></i> Happens in ${diffDays} day(s)</span>`;
                } else if (diffDays === 0) {
                    countdownBadge = `<span class="countdown-chip" style="background:#fef3c7; color:#b45309; border-color:#fde68a;"><i class="fas fa-bell"></i> Event is TODAY</span>`;
                } else {
                    countdownBadge = `<span class="countdown-chip past"><i class="fas fa-history"></i> Passed (${Math.abs(diffDays)}d ago)</span>`;
                }

                // Financial formatting
                let budgetHtml = '<div style="color:var(--text-muted); font-size:13px;">No linked budget log yet.</div>';
                if (data.total_budget && parseFloat(data.total_budget) > 0) {
                    const tb = Number(data.total_budget).toLocaleString(undefined, {minimumFractionDigits: 2});
                    const ts = Number(data.total_spent || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                    const rb = Number(data.remaining_budget || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                    const vr = Number(data.variance || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                    const varColor = (parseFloat(data.variance) > 0) ? '#ef4444' : '#10b981';

                    budgetHtml = `
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="dossier-item">
                                <span class="dossier-item-label">TOTAL BUDGET</span>
                                <span class="dossier-item-value" style="color:var(--primary); font-size:14px;">Rs. ${tb}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">TOTAL SPENT</span>
                                <span class="dossier-item-value">Rs. ${ts}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">REMAINING BALANCE</span>
                                <span class="dossier-item-value" style="color:#10b981;">Rs. ${rb}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">BUDGET VARIANCE</span>
                                <span class="dossier-item-value" style="color:${varColor};">${vr}</span>
                            </div>
                        </div>
                    `;
                }

                // Build Services & Suppliers Matrix Table
                let servicesTableRows = '';
                if (services.length > 0) {
                    services.forEach(s => {
                        const costDisplay = s.assigned_cost > 0 
                            ? 'Rs. ' + Number(s.assigned_cost).toLocaleString(undefined, {minimumFractionDigits: 2})
                            : (s.price_type ? s.price_type.replace('_', ' ') : 'Included');

                        servicesTableRows += `
                            <tr>
                                <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9;">
                                    <strong>${s.service_name}</strong>
                                    ${s.is_required == 1 ? '<span style="color:#ef4444; font-size:11px; margin-left:4px;">*Required</span>' : ''}
                                </td>
                                <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9;">
                                    <div style="font-weight: 700; color: #1e293b;">
                                        <i class="fas fa-store fa-xs" style="color:var(--primary);"></i> ${s.business_name || '<em style="color:#94a3b8;">Unassigned</em>'}
                                    </div>
                                    ${s.listing_title ? `<small style="color:#64748b; display:block;">Pkg: ${s.listing_title}</small>` : ''}
                                </td>
                                <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9;">
                                    ${s.contact_phone ? `<a href="tel:${s.contact_phone}" style="color:var(--primary); text-decoration:none; font-size:12px;"><i class="fas fa-phone fa-xs"></i> ${s.contact_phone}</a>` : '<span style="color:#94a3b8; font-size:12px;">N/A</span>'}
                                    ${s.supplier_location ? `<small style="color:#94a3b8; display:block;">${s.supplier_location}</small>` : ''}
                                </td>
                                <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-weight: 600; font-size: 13px;">
                                    ${costDisplay}
                                </td>
                                <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9;">
                                    <span class="badge ${s.assignment_status === 'confirmed' ? 'badge-confirmed' : 'badge-pending'}">
                                        ${s.assignment_status || 'Assigned'}
                                    </span>
                                </td>
                                <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: right;">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this supplier assignment?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="remove_assignment">
                                        <input type="hidden" name="booking_service_id" value="${s.bs_id}">
                                        <button type="submit" class="btn btn-danger-outline btn-sm" style="padding: 4px 8px; font-size: 11px;" title="Remove Assignment">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    servicesTableRows = `
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">
                                <i class="fas fa-info-circle" style="color: #cbd5e1; margin-right: 6px;"></i>
                                No suppliers allocated yet for this reservation. Click below to assign vendors.
                            </td>
                        </tr>
                    `;
                }

                // Render Dossier Grid
                modalBody.innerHTML = `
                    ${stepperHtml}

                    <div class="dossier-grid">
                        <!-- Card 1: Client & Contact Profile -->
                        <div class="dossier-card">
                            <div class="dossier-card-title" style="color: #3b82f6;">
                                <i class="fas fa-user-circle"></i> Client Profile
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">FULL NAME</span>
                                <span class="dossier-item-value">${data.client_fullname || data.user_name}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">ACCOUNT USERNAME</span>
                                <span class="dossier-item-value">@${data.user_name}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">EMAIL ADDRESS</span>
                                <span class="dossier-item-value">
                                    ${data.client_email ? `<a href="mailto:${data.client_email}" style="color:var(--primary); text-decoration:none;"><i class="fas fa-envelope fa-xs"></i> ${data.client_email}</a>` : 'Not provided'}
                                </span>
                            </div>
                        </div>

                        <!-- Card 2: Event Logistics & Countdown -->
                        <div class="dossier-card">
                            <div class="dossier-card-title" style="color: #8b5cf6;">
                                <i class="fas fa-map-marked-alt"></i> Event Logistics
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">CATEGORY & TIMING</span>
                                <span class="dossier-item-value">${data.EventType} • ${data.DayNight || 'Day'}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">SCHEDULED DATE</span>
                                <span class="dossier-item-value" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    ${data.EventDate} ${countdownBadge}
                                </span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">GUESTS & VENUE</span>
                                <span class="dossier-item-value">${data.NumberOfGuests} Guests • ${data.Place}</span>
                            </div>
                        </div>

                        <!-- Card 3: Catering & Production Setup -->
                        <div class="dossier-card">
                            <div class="dossier-card-title" style="color: #f59e0b;">
                                <i class="fas fa-utensils"></i> Catering & Equipment
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">FOOD PREFERENCES</span>
                                <span class="dossier-item-value">${data.FoodPreferences || 'Standard Catering'}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">CULINARY STYLE</span>
                                <span class="dossier-item-value">${data.food_style || 'Not specified'}</span>
                            </div>
                            <div class="dossier-item">
                                <span class="dossier-item-label">REQUESTED EQUIPMENT</span>
                                <span class="dossier-item-value">${data.equipment ? `<span class="badge" style="background:#ede9fe; color:#6d28d9;"><i class="fas fa-tools fa-xs"></i> ${data.equipment}</span>` : 'Standard Venue A/V'}</span>
                            </div>
                        </div>

                        <!-- Card 4: Financial Audit Summary -->
                        <div class="dossier-card">
                            <div class="dossier-card-title" style="color: #10b981;">
                                <i class="fas fa-wallet"></i> Financial Allocation
                            </div>
                            ${budgetHtml}
                        </div>
                    </div>

                    <!-- Card 5: Service Components & Supplier Allocation Table -->
                    <div style="background: #ffffff; border: 1px solid var(--card-border); border-radius: 14px; padding: 18px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                            <div>
                                <h4 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--text-heading); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-layer-group" style="color: var(--primary);"></i>
                                    Event Services & Supplier Allocations
                                </h4>
                                <small style="color: var(--text-muted); font-size: 12px;">Specific vendors contracted for each event requirement</small>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAssignModal('${data.BookingID}')">
                                <i class="fas fa-plus"></i> Assign Supplier
                            </button>
                        </div>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="background: #f8fafc; text-align: left; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                                        <th style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">Service Component</th>
                                        <th style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">Assigned Supplier & Package</th>
                                        <th style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">Contact Phone</th>
                                        <th style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">Agreed Cost</th>
                                        <th style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">Status</th>
                                        <th style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${servicesTableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Client Special Instructions -->
                    <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: 12px; padding: 16px;">
                        <span class="dossier-item-label" style="font-weight:700; color:var(--text-muted); margin-bottom: 6px;">SPECIAL NOTES & INSTRUCTIONS</span>
                        <p style="margin: 0; font-size: 13px; line-height: 1.6; color: var(--text-heading);">
                            ${data.ExtraDetails ? data.ExtraDetails.replace(/\n/g, '<br>') : '<em>No additional client notes provided for this reservation.</em>'}
                        </p>
                    </div>
                `;

                // Quick Workflow Buttons in Footer
                let actionButtons = '';
                if (st !== 'confirmed' && st !== 'cancelled') {
                    actionButtons += `
                        <form method="POST" style="display:inline;">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="booking_id" value="${data.BookingID}">
                            <input type="hidden" name="status" value="confirmed">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Confirm</button>
                        </form>
                    `;
                }
                if (st !== 'in_progress' && st !== 'completed' && st !== 'cancelled') {
                    actionButtons += `
                        <form method="POST" style="display:inline;">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="booking_id" value="${data.BookingID}">
                            <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#2563eb; border-color:#93c5fd;"><i class="fas fa-spinner"></i> In Progress</button>
                        </form>
                    `;
                }
                if (st !== 'completed' && st !== 'cancelled') {
                    actionButtons += `
                        <form method="POST" style="display:inline;">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="booking_id" value="${data.BookingID}">
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#15803d; border-color:#86efac;"><i class="fas fa-flag-checkered"></i> Complete</button>
                        </form>
                    `;
                }
                if (st !== 'cancelled') {
                    actionButtons += `
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel reservation #${data.BookingID}?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="booking_id" value="${data.BookingID}">
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-danger-outline btn-sm"><i class="fas fa-ban"></i> Cancel</button>
                        </form>
                    `;
                }
                workflowActions.innerHTML = actionButtons;

                modal.classList.add('show');
            });
        });

        function closeDetailModal() {
            modal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target === modal) closeDetailModal();
            if (e.target === assignModal) closeAssignModal();
        }

        // Printable Voucher Generator with Full Supplier Roster
        printBtn.addEventListener('click', () => {
            if (!currentBookingData) return;
            const b = currentBookingData;
            const services = currentBookingServices;
            const printArea = document.getElementById('voucherPrintArea');

            let supplierVoucherRows = '';
            if (services.length > 0) {
                services.forEach(s => {
                    supplierVoucherRows += `
                        <tr>
                            <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 13px;">${s.service_name}</td>
                            <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 13px;">
                                <strong>${s.business_name || 'Vendor Pending'}</strong>
                                ${s.listing_title ? ` &bull; ${s.listing_title}` : ''}
                            </td>
                            <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 13px;">${s.contact_phone || 'On file'}</td>
                            <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 600;">
                                ${s.assigned_cost > 0 ? 'Rs. ' + Number(s.assigned_cost).toLocaleString(undefined, {minimumFractionDigits: 2}) : 'Included'}
                            </td>
                        </tr>
                    `;
                });
            } else {
                supplierVoucherRows = `
                    <tr>
                        <td colspan="4" style="padding: 14px; text-align: center; border: 1px solid #e2e8f0; color: #64748b;">
                            No individual vendors contracted yet. Standard in-house venue coordination applies.
                        </td>
                    </tr>
                `;
            }

            printArea.innerHTML = `
                <div style="max-width: 800px; margin: 0 auto; font-family: sans-serif; color: #1e293b;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #8b5cf6; padding-bottom: 15px; margin-bottom: 25px;">
                        <div>
                            <h1 style="margin: 0; color: #6d28d9; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">EVENTFLARE</h1>
                            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Official Event Itinerary & Supplier Master Voucher</p>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 18px; font-weight: 800; color: #0f172a;">#${b.BookingID}</div>
                            <div style="font-size: 12px; color: #64748b;">Issued: ${new Date().toLocaleDateString()}</div>
                            <span style="display:inline-block; margin-top:4px; padding: 2px 10px; border-radius: 12px; background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                ${b.status || 'Pending'}
                            </span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <h4 style="margin: 0 0 10px 0; color: #475569; font-size: 12px; text-transform: uppercase;">Client Information</h4>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a;">${b.client_fullname || b.user_name}</div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 4px;">Username: @${b.user_name}</div>
                            <div style="font-size: 13px; color: #64748b;">Email: ${b.client_email || 'On file'}</div>
                        </div>

                        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <h4 style="margin: 0 0 10px 0; color: #475569; font-size: 12px; text-transform: uppercase;">Event Schedule & Venue</h4>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a;">${b.EventType} (${b.DayNight})</div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 4px;">Date: <strong>${b.EventDate}</strong></div>
                            <div style="font-size: 13px; color: #64748b;">Venue: ${b.Place} • ${b.NumberOfGuests} Guests</div>
                        </div>
                    </div>

                    <!-- Contracted Suppliers Table in Voucher -->
                    <h4 style="margin: 0 0 10px 0; color: #475569; font-size: 13px; text-transform: uppercase;">Contracted Suppliers & Service Roster</h4>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                        <thead>
                            <tr style="background: #f1f5f9; text-align: left;">
                                <th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-size: 12px;">Service Component</th>
                                <th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-size: 12px;">Contracted Supplier / Package</th>
                                <th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-size: 12px;">Contact Phone</th>
                                <th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-size: 12px;">Agreed Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${supplierVoucherRows}
                        </tbody>
                    </table>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                        <thead>
                            <tr style="background: #f1f5f9; text-align: left;">
                                <th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-size: 12px;">Component</th>
                                <th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-size: 12px;">Specification & Requirements</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 13px;">Catering Service</td>
                                <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 13px;">${b.FoodPreferences || 'Standard'} • Style: ${b.food_style || 'Default'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 13px;">Production Equipment</td>
                                <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 13px;">${b.equipment || 'Standard House Package'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 13px;">Special Client Instructions</td>
                                <td style="padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 13px;">${b.ExtraDetails || 'None'}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
                        <div style="text-align: center; width: 220px;">
                            <div style="border-bottom: 1px solid #000; height: 35px;"></div>
                            <div style="font-size: 12px; margin-top: 5px; color: #64748b;">Client Signature</div>
                        </div>
                        <div style="text-align: center; width: 220px;">
                            <div style="border-bottom: 1px solid #000; height: 35px;"></div>
                            <div style="font-size: 12px; margin-top: 5px; color: #64748b;">Event Coordinator / Organizer</div>
                        </div>
                    </div>
                </div>
            `;
            window.print();
        });

        // PDF Export
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'event_bookings.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // CSV Export
        document.getElementById("downloadCsv").addEventListener("click", function () {
            const table = document.getElementById("bookingsTable");
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
            link.download = "bookings_list.csv";
            link.click();
        });
    </script>
</body>
</html>
