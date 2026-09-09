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

// Fetch total suppliers count for the segmented navigation tab
$total_suppliers_count = 0;
$supp_count_res = $conn->query("SELECT COUNT(*) as c FROM suppliers");
if ($supp_count_res) {
    $total_suppliers_count = (int)$supp_count_res->fetch_assoc()['c'];
}

// Fetch customer accounts with booking statistics
$sql = "SELECT u.id, u.username, u.fullname, u.email, u.role, u.created_at,
               (SELECT COUNT(*) FROM bookings b WHERE b.user_name = u.username OR b.user_id = u.id) AS client_bookings_count,
               (SELECT MAX(b.EventDate) FROM bookings b WHERE b.user_name = u.username OR b.user_id = u.id) AS latest_event_date
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
                <p>Manage registered customer/buyer accounts, view booking histories, and moderate access.</p>
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
                                            <!-- View Bookings Link -->
                                            <?php if ((int)$u['client_bookings_count'] > 0): ?>
                                                <a href="Bookinglist.php?search=<?= urlencode($u['username']); ?>" 
                                                   class="btn btn-outline btn-sm" 
                                                   title="View Customer's Bookings in Booking Console">
                                                    <i class="fas fa-calendar-alt"></i> View Bookings
                                                </a>
                                            <?php endif; ?>

                                            <!-- Delete User -->
                                            <form method="POST" action="Userlist.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete customer <?= htmlspecialchars($u['username']); ?>? Any associated client data will be removed.');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']); ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Customer Account">
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

    <script>
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
