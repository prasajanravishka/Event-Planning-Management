<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'staff';

$error = "";
$success_message = "";

// 1. Handle Admin Creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid. Please try again.');
        header("Location: AdminStaff.php");
        exit();
    }

    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $full_name = htmlspecialchars(trim($_POST['fullname'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';

    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        set_flash_message('error', 'All fields are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Invalid email format.');
    } elseif (strlen($password) < 6) {
        set_flash_message('error', 'Password must be at least 6 characters long.');
    } elseif ($password !== $confirm_password) {
        set_flash_message('error', 'Passwords do not match.');
    } else {
        // Check uniqueness of username and email
        $check_stmt = $conn->prepare("SELECT id FROM admin WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            set_flash_message('error', 'An admin with this username or email already exists.');
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $ins_stmt = $conn->prepare("INSERT INTO admin (username, fullname, email, password) VALUES (?, ?, ?, ?)");
            $ins_stmt->bind_param("ssss", $username, $full_name, $email, $hashed_password);

            if ($ins_stmt->execute()) {
                set_flash_message('success', "New administrator '{$username}' created successfully!");
            } else {
                set_flash_message('error', "Database error: " . $ins_stmt->error);
            }
            $ins_stmt->close();
        }
        $check_stmt->close();
    }
    header("Location: AdminStaff.php");
    exit();
}

// 2. Handle Admin Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header("Location: AdminStaff.php");
        exit();
    }

    $del_id = (int)($_POST['admin_id'] ?? 0);
    $del_username = trim($_POST['admin_username'] ?? '');

    // Prevent self-deletion
    if ($del_username === $_SESSION['login_user']) {
        set_flash_message('error', 'You cannot delete your own active administrator account!');
    } else {
        // Ensure at least 1 admin remains
        $count_res = $conn->query("SELECT COUNT(*) as c FROM admin");
        $total_admins = $count_res ? (int)$count_res->fetch_assoc()['c'] : 0;

        if ($total_admins <= 1) {
            set_flash_message('error', 'Cannot delete the only remaining administrator account.');
        } else {
            $del_stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
            $del_stmt->bind_param("i", $del_id);
            if ($del_stmt->execute()) {
                set_flash_message('success', "Admin account '{$del_username}' has been deleted.");
            } else {
                set_flash_message('error', "Failed to delete: " . $del_stmt->error);
            }
            $del_stmt->close();
        }
    }
    header("Location: AdminStaff.php");
    exit();
}

// 3. Fetch all admins
$admin_list = [];
$res = $conn->query("SELECT id, username, fullname, email, created_at FROM admin ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admin_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & Admins - EVENTFLARE Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Administrator Staff</h1>
                <p>Manage system administrators, organizers, and security credentials.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <div class="panel-grid" style="grid-template-columns: 1fr 380px;">
            <!-- Current Admins Table -->
            <div class="dashboard-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Active Administrators (<?= count($admin_list); ?>)</h2>
                </div>

                <div class="dashboard-table-container">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admin_list as $adm): ?>
                                <tr>
                                    <td><strong>#<?= $adm['id']; ?></strong></td>
                                    <td>
                                        <span class="badge badge-admin">
                                            <i class="fas fa-shield-alt fa-xs"></i> <?= htmlspecialchars($adm['username']); ?>
                                        </span>
                                        <?php if ($adm['username'] === $_SESSION['login_user']): ?>
                                            <small style="color: var(--primary); font-weight:700; margin-left: 5px;">(You)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($adm['fullname']); ?></td>
                                    <td><?= htmlspecialchars($adm['email']); ?></td>
                                    <td><?= date('M j, Y', strtotime($adm['created_at'])); ?></td>
                                    <td>
                                        <?php if ($adm['username'] !== $_SESSION['login_user']): ?>
                                            <form method="POST" action="AdminStaff.php" onsubmit="return confirm('Are you sure you want to delete admin <?= htmlspecialchars($adm['username']); ?>?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="admin_id" value="<?= $adm['id']; ?>">
                                                <input type="hidden" name="admin_username" value="<?= htmlspecialchars($adm['username']); ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Remove Admin">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">Current Session</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create New Admin Panel -->
            <div class="dashboard-panel">
                <h2><i class="fas fa-user-plus" style="color: var(--primary);"></i> Add New Admin</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
                    Grant administrative console permissions to an authorized organizer.
                </p>

                <form action="AdminStaff.php" method="POST">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create_admin">

                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Username *</label>
                        <input type="text" name="username" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);" placeholder="e.g. sarah_admin" required>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Full Name *</label>
                        <input type="text" name="fullname" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);" placeholder="e.g. Sarah Jenkins" required>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email Address *</label>
                        <input type="email" name="email" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);" placeholder="admin@eventflare.com" required>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Password *</label>
                        <input type="password" name="password" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);" placeholder="Minimum 6 characters" required>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Confirm Password *</label>
                        <input type="password" name="confirm-password" class="form-input" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--card-border);" placeholder="Re-enter password" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 700; border-radius: 8px;">
                        <i class="fas fa-plus-circle"></i> Create Administrator
                    </button>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
