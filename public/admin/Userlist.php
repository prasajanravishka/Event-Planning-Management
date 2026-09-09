<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'users';

// 1. Handle User Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Userlist.php');
        exit();
    }
    $del_user_id = (int)($_POST['user_id'] ?? 0);
    $del_username = trim($_POST['username'] ?? '');

    if ($del_user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $del_user_id);
        if ($stmt->execute()) {
            set_flash_message('success', "User account '{$del_username}' has been deleted.");
        } else {
            set_flash_message('error', "Failed to delete user: " . $stmt->error);
        }
        $stmt->close();
    }
    header('Location: Userlist.php');
    exit();
}

// 2. Search, Filter & Pagination for Customers (role = 'buyer')
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Strictly customers (buyers)
$where_clauses = ["u.role = 'buyer'"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u.username LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $types .= "sss";
}

$where_sql = implode(" AND ", $where_clauses);

// Count total customers
$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM users u WHERE {$where_sql}");
if (!empty($types)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Fetch customer accounts with booking and review statistics
$sql = "SELECT u.id, u.username, u.fullname, u.email, u.role, u.created_at,
               (SELECT COUNT(*) FROM bookings b WHERE b.user_name = u.username OR b.user_id = u.id) AS client_bookings_count,
               (SELECT MAX(b.EventDate) FROM bookings b WHERE b.user_name = u.username OR b.user_id = u.id) AS latest_event_date,
               (SELECT COUNT(*) FROM ratings r WHERE r.user_id = u.id) AS reviews_count
        FROM users u
        WHERE {$where_sql}
        ORDER BY u.id DESC
        LIMIT ? OFFSET ?";

$params[] = &$limit;
$params[] = &$offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Preload bookings for listed users to display in Client Dossier
$user_bookings_map = [];
$usernames = array_column($users, 'username');
if (!empty($usernames)) {
    $escaped_usernames = array_map(function($u) use ($conn) {
        return "'" . $conn->real_escape_string($u) . "'";
    }, $usernames);
    $in_usernames = implode(',', $escaped_usernames);
    $b_res = $conn->query("
        SELECT BookingID, user_name, EventType, EventDate, Place, NumberOfGuests, status, created_at, DayNight
        FROM bookings 
        WHERE user_name IN ($in_usernames)
        ORDER BY EventDate DESC
    ");
    if ($b_res) {
        while ($b = $b_res->fetch_assoc()) {
            $user_bookings_map[$b['user_name']][] = $b;
        }
    }
}

foreach ($users as &$u) {
    $u['bookings'] = $user_bookings_map[$u['username']] ?? [];
}
unset($u);

// Fetch total suppliers count for directory segmented switcher
$total_suppliers_count = 0;
$sup_count_res = $conn->query("SELECT COUNT(*) as c FROM suppliers");
if ($sup_count_res) {
    $total_suppliers_count = (int)$sup_count_res->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Directory - EVENTFLARE Admin</title>
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
                <h1>Customer Directory</h1>
                <p>Manage registered customer accounts, view booking histories, and moderate access.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <!-- Directory Segmented Navigation Switcher -->
        <div class="directory-segment-bar">
            <a href="Userlist.php" class="directory-segment-pill active">
                <i class="fas fa-user-friends"></i> Customers
                <span class="segment-badge"><?= $total_rows; ?></span>
            </a>
            <a href="Suppliers.php" class="directory-segment-pill">
                <i class="fas fa-store"></i> Suppliers
                <span class="segment-badge"><?= $total_suppliers_count; ?></span>
            </a>
        </div>

        <?= render_flash_message(); ?>

        <!-- Toolbar -->
        <div class="dashboard-toolbar">
            <form method="GET" action="Userlist.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search customer name, username, email..." value="<?= htmlspecialchars($search); ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Search Customers</button>
                <?php if (!empty($search)): ?>
                    <a href="Userlist.php" class="btn btn-outline btn-sm">Reset</a>
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

        <!-- Customers Table Panel -->
        <div class="dashboard-panel" id="makepdf">
            <div class="dashboard-table-container">
                <table class="dashboard-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Customer Profile</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Bookings Activity</th>
                            <th>Latest Event Date</th>
                            <th>Registered On</th>
                            <th>Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong>#<?= $u['id']; ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(99, 102, 241, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">
                                                <?= strtoupper(substr($u['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($u['username']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-muted);">Customer Account</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['fullname']); ?></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($u['email']); ?>" style="color: inherit; text-decoration: none;">
                                            <i class="fas fa-envelope fa-xs" style="color: var(--primary);"></i> <?= htmlspecialchars($u['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ((int)$u['client_bookings_count'] > 0): ?>
                                            <a href="Bookinglist.php?search=<?= urlencode($u['username']); ?>" 
                                               class="badge badge-primary" 
                                               style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
                                               title="View all bookings by this customer">
                                                <i class="fas fa-calendar-check"></i>
                                                <strong><?= (int)$u['client_bookings_count']; ?></strong> Bookings
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 13px; color: var(--text-muted);">0 Bookings</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= !empty($u['latest_event_date']) ? date('M j, Y', strtotime($u['latest_event_date'])) : '<span style="color: var(--text-muted); font-size: 12px;">None scheduled</span>'; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <!-- View Client Dossier Button -->
                                            <button type="button" class="btn btn-outline btn-sm view-client-btn" 
                                                    data-client="<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>"
                                                    title="View Client Profile & Reservation History">
                                                <i class="fas fa-eye"></i> View
                                            </button>

                                            <!-- Delete User -->
                                            <form method="POST" action="Userlist.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete customer <?= htmlspecialchars($u['username']); ?>? Any associated client data will be removed.');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']); ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Client Account">
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
                                    <i class="fas fa-users-slash" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                    No customer accounts found matching your query.
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
                        Showing <?= min($total_rows, $offset + 1); ?> to <?= min($total_rows, $offset + count($users)); ?> of <?= $total_rows; ?> customers
                    </div>
                    <div class="pagination-controls">
                        <a href="?page=<?= max(1, $page - 1); ?>&search=<?= urlencode($search); ?>" 
                           class="pagination-btn <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            &larr; Prev
                        </a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>" 
                               class="pagination-btn <?= ($i === $page) ? 'active' : ''; ?>">
                                <?= $i; ?>
                            </a>
                        <?php endfor; ?>
                        <a href="?page=<?= min($total_pages, $page + 1); ?>&search=<?= urlencode($search); ?>" 
                           class="pagination-btn <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            Next &rarr;
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal: View Client Dossier -->
    <div class="admin-modal" id="viewClientModal">
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(99, 102, 241, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <h3 id="vcModalTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-heading);">Client Dossier</h3>
                        <p id="vcModalSubtitle" style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">Registered Client Profile & Reservation History</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('viewClientModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewClientBody" style="max-height: 75vh; overflow-y: auto;">
                <!-- Populated dynamically by JS -->
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <button type="button" class="btn btn-outline btn-sm" id="printClientDossierBtn" style="border-color: var(--primary); color: var(--primary); font-weight: 700;">
                    <i class="fas fa-print"></i> Print Client Record
                </button>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="#" id="vcAllBookingsBtn" class="btn btn-primary btn-sm" style="display: none;">
                        <i class="fas fa-calendar-alt"></i> All Bookings in Console
                    </a>
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('viewClientModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        // Close on backdrop click
        document.querySelectorAll('.admin-modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal(modal.id);
            });
        });

        let activeDossierClient = null;

        // View Client Click
        document.querySelectorAll('.view-client-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const c = JSON.parse(btn.getAttribute('data-client'));
                activeDossierClient = c;
                renderClientDossier(c);
                openModal('viewClientModal');
            });
        });

        function renderClientDossier(c) {
            document.getElementById('vcModalTitle').innerText = c.fullname || c.username;
            document.getElementById('vcModalSubtitle').innerText = `@${c.username} • Client ID #${c.id} • Registered ${c.created_at ? new Date(c.created_at).toLocaleDateString() : 'N/A'}`;

            const allBookingsBtn = document.getElementById('vcAllBookingsBtn');
            const bookingsCount = parseInt(c.client_bookings_count || 0);
            if (bookingsCount > 0) {
                allBookingsBtn.style.display = 'inline-flex';
                allBookingsBtn.href = `Bookinglist.php?search=${encodeURIComponent(c.username)}`;
            } else {
                allBookingsBtn.style.display = 'none';
            }

            const bookings = c.bookings || [];
            let bookingsHtml = '';
            if (bookings.length > 0) {
                bookingsHtml = `
                    <div style="overflow-x: auto; margin-top: 10px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                                    <th style="padding: 10px 12px;">Booking ID</th>
                                    <th style="padding: 10px 12px;">Event Type</th>
                                    <th style="padding: 10px 12px;">Date & Session</th>
                                    <th style="padding: 10px 12px;">Location</th>
                                    <th style="padding: 10px 12px;">Guests</th>
                                    <th style="padding: 10px 12px;">Status</th>
                                    <th style="padding: 10px 12px; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${bookings.map(b => `
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 10px 12px; font-weight: 700; color: var(--primary);">
                                            #${b.BookingID}
                                        </td>
                                        <td style="padding: 10px 12px; font-weight: 600;">
                                            ${escapeHtml(b.EventType)}
                                        </td>
                                        <td style="padding: 10px 12px;">
                                            <div>${b.EventDate}</div>
                                            <small style="color: #64748b;">${escapeHtml(b.DayNight || 'Day')}</small>
                                        </td>
                                        <td style="padding: 10px 12px; color: #475569;">
                                            <i class="fas fa-map-marker-alt fa-xs" style="color: #ef4444;"></i> ${escapeHtml(b.Place || '-')}
                                        </td>
                                        <td style="padding: 10px 12px; font-weight: 600;">
                                            ${b.NumberOfGuests}
                                        </td>
                                        <td style="padding: 10px 12px;">
                                            <span class="status-pill status-${b.status || 'pending'}" style="font-size: 11px; padding: 2px 8px;">
                                                ${(b.status || 'pending').toUpperCase()}
                                            </span>
                                        </td>
                                        <td style="padding: 10px 12px; text-align: right;">
                                            <a href="Bookinglist.php?search=${b.BookingID}" target="_blank" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 3px 8px;" title="Open in Booking Console">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                bookingsHtml = `
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 25px; text-align: center; color: #64748b; font-size: 13px; margin-top: 10px;">
                        <i class="fas fa-calendar-times" style="font-size: 26px; color: #cbd5e1; margin-bottom: 6px; display: block;"></i>
                        No event reservations placed by this client yet.
                    </div>
                `;
            }

            document.getElementById('viewClientBody').innerHTML = `
                <!-- Quick Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">TOTAL BOOKINGS</small>
                        <div style="font-size: 20px; font-weight: 800; color: var(--primary); margin-top: 4px;">${c.client_bookings_count || 0}</div>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #166534; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">ACCOUNT STATUS</small>
                        <div style="font-size: 14px; font-weight: 800; color: #15803d; margin-top: 6px; text-transform: uppercase;">
                            <i class="fas fa-check-circle"></i> Active
                        </div>
                    </div>
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #1e40af; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">ROLE</small>
                        <div style="font-size: 14px; font-weight: 800; color: #2563eb; margin-top: 6px; text-transform: uppercase;">
                            ${escapeHtml(c.role || 'Client')}
                        </div>
                    </div>
                    <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #6b21a8; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">LATEST EVENT</small>
                        <div style="font-size: 13px; font-weight: 700; color: #6b21a8; margin-top: 6px;">
                            ${c.latest_event_date ? new Date(c.latest_event_date).toLocaleDateString() : 'None scheduled'}
                        </div>
                    </div>
                </div>

                <!-- Profile & Account Details Cards -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-id-badge"></i> Client Identity Profile
                        </div>
                        <div style="display: grid; gap: 10px; font-size: 13px;">
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">FULL NAME</strong>
                                <span style="font-weight: 700; font-size: 15px; color: var(--text-heading);">${escapeHtml(c.fullname || 'N/A')}</span>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">USERNAME</strong>
                                <span style="font-weight: 600; color: var(--primary);">@${escapeHtml(c.username)}</span>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">CLIENT ACCOUNT ID</strong>
                                <span style="font-weight: 600;">#USR-${c.id}</span>
                            </div>
                        </div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-address-card"></i> Contact & System Details
                        </div>
                        <div style="display: grid; gap: 10px; font-size: 13px;">
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">EMAIL ADDRESS</strong>
                                <a href="mailto:${escapeHtml(c.email)}" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                    <i class="fas fa-envelope fa-xs"></i> ${escapeHtml(c.email)}
                                </a>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">REGISTERED DATE</strong>
                                <span style="font-weight: 600; color: var(--text-heading);">${c.created_at ? new Date(c.created_at).toLocaleString() : 'N/A'}</span>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">COMMUNICATION</strong>
                                <a href="Messages.php?search=${encodeURIComponent(c.username)}" style="font-size: 12px; font-weight: 600; color: #2563eb; text-decoration: none;">
                                    <i class="fas fa-comments fa-xs"></i> Check Contact Inquiries &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Reservations History -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-calendar-check"></i> Client Event Reservations (${bookings.length})
                        </div>
                        ${bookings.length > 0 ? `
                            <a href="Bookinglist.php?search=${encodeURIComponent(c.username)}" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 10px;">
                                <i class="fas fa-external-link-alt fa-xs"></i> View in Bookings
                            </a>
                        ` : ''}
                    </div>
                    ${bookingsHtml}
                </div>
            `;
        }

        // Print Client Dossier
        document.getElementById('printClientDossierBtn').addEventListener('click', () => {
            if (!activeDossierClient) return;
            const c = activeDossierClient;
            const bookings = c.bookings || [];

            let bookingsRows = '';
            if (bookings.length > 0) {
                bookingsRows = bookings.map(b => `
                    <tr>
                        <td><strong>#${b.BookingID}</strong></td>
                        <td>${b.EventType}</td>
                        <td>${b.EventDate} (${b.DayNight || 'Day'})</td>
                        <td>${b.Place || '-'}</td>
                        <td>${b.NumberOfGuests}</td>
                        <td>${(b.status || 'pending').toUpperCase()}</td>
                    </tr>
                `).join('');
            } else {
                bookingsRows = `<tr><td colspan="6" style="text-align:center; color:#94a3b8;">No event reservations booked yet.</td></tr>`;
            }

            const win = window.open('', '', 'width=850,height=700');
            win.document.write(`
                <html>
                <head>
                    <title>Client Record - ${c.fullname || c.username}</title>
                    <style>
                        body { font-family: sans-serif; padding: 30px; color: #1e293b; line-height: 1.5; }
                        h1 { color: #4f46e5; margin: 0; font-size: 24px; }
                        h3 { color: #0f172a; margin: 25px 0 10px; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
                        th { background: #f8fafc; font-weight: 700; color: #475569; }
                    </style>
                </head>
                <body>
                    <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #6366f1; padding-bottom: 12px;">
                        <div>
                            <h1>EVENTFLARE</h1>
                            <p style="margin:4px 0 0; color:#64748b; font-size:12px;">Official Client Account Dossier</p>
                        </div>
                        <div style="text-align:right;">
                            <strong style="font-size:16px;">#USR-${c.id}</strong><br>
                            <small>Printed: ${new Date().toLocaleDateString()}</small>
                        </div>
                    </div>

                    <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0;">
                        <div>
                            <strong>Full Name:</strong> ${c.fullname || 'N/A'}<br>
                            <strong>Username:</strong> @${c.username}<br>
                            <strong>Account Role:</strong> ${c.role || 'Client'}<br>
                        </div>
                        <div>
                            <strong>Email:</strong> ${c.email}<br>
                            <strong>Total Bookings:</strong> ${c.client_bookings_count || 0}<br>
                            <strong>Registered:</strong> ${c.created_at || 'N/A'}<br>
                        </div>
                    </div>

                    <h3>Reservation & Booking History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Event Type</th>
                                <th>Date & Timing</th>
                                <th>Location</th>
                                <th>Guests</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>${bookingsRows}</tbody>
                    </table>
                </body>
                </html>
            `);
            win.document.close();
            win.focus();
            win.print();
            win.close();
        });

        // PDF Export
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'customer_directory.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // CSV Export
        document.getElementById("downloadCsv").addEventListener("click", function () {
            const table = document.getElementById("usersTable");
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
            link.download = "customers_list.csv";
            link.click();
        });
    </script>
</body>
</html>
