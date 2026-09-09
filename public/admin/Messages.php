<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'messages';

// 1. Handle Message Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Messages.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    $msg_id = (int)($_POST['message_id'] ?? 0);

    if ($action === 'toggle_read' && $msg_id > 0) {
        $stmt = $conn->prepare("UPDATE contact_messages SET is_read = IF(is_read = 1, 0, 1) WHERE id = ?");
        $stmt->bind_param("i", $msg_id);
        $stmt->execute();
        $stmt->close();
        set_flash_message('success', 'Message status updated.');
    } elseif ($action === 'mark_all_read') {
        $conn->query("UPDATE contact_messages SET is_read = 1");
        set_flash_message('success', 'All inquiries marked as read.');
    } elseif ($action === 'delete_message' && $msg_id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $msg_id);
        if ($stmt->execute()) {
            set_flash_message('success', 'Inquiry deleted successfully.');
        } else {
            set_flash_message('error', 'Failed to delete inquiry.');
        }
        $stmt->close();
    }
    header('Location: Messages.php');
    exit();
}

// 2. Filter & Search
$filter = $_GET['filter'] ?? 'all'; // 'all', 'unread', 'read'
$search = trim($_GET['search'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter === 'unread') {
    $where_clauses[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where_clauses[] = "is_read = 1";
}

if (!empty($search)) {
    $where_clauses[] = "(firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $types .= "sssss";
}

$where_sql = implode(" AND ", $where_clauses);

// Count breakdown
$unread_cnt = 0;
$total_cnt = 0;
$cnt_res = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM contact_messages");
if ($cnt_res && $row = $cnt_res->fetch_assoc()) {
    $total_cnt = (int)$row['total'];
    $unread_cnt = (int)($row['unread'] ?? 0);
}

// Fetch inquiries
$sql = "SELECT * FROM contact_messages WHERE {$where_sql} ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries Inbox - EVENTFLARE Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .inbox-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding-bottom: 15px;
        }
        .inbox-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            background: #fff;
            border: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition-smooth);
        }
        .inbox-tab.active, .inbox-tab:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .message-card.unread {
            border-left: 5px solid var(--primary);
            background: #fbfbfe;
        }
        .unread-dot {
            height: 10px;
            width: 10px;
            background-color: var(--primary);
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Contact Inquiries</h1>
                <p>Manage customer questions, event requests, and communication logs.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- Inbox Tabs & Bulk Actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
            <div class="inbox-nav" style="margin-bottom: 0; border: none; padding: 0;">
                <a href="Messages.php?filter=all" class="inbox-tab <?= ($filter === 'all') ? 'active' : ''; ?>">
                    All Messages <span class="badge" style="padding: 2px 8px; font-size: 11px; background: rgba(0,0,0,0.06);"><?= $total_cnt; ?></span>
                </a>
                <a href="Messages.php?filter=unread" class="inbox-tab <?= ($filter === 'unread') ? 'active' : ''; ?>">
                    Unread <span class="badge" style="padding: 2px 8px; font-size: 11px; background: rgba(239, 68, 68, 0.15); color: #dc2626;"><?= $unread_cnt; ?></span>
                </a>
                <a href="Messages.php?filter=read" class="inbox-tab <?= ($filter === 'read') ? 'active' : ''; ?>">
                    Read Inquiries
                </a>
            </div>

            <?php if ($unread_cnt > 0): ?>
                <form method="POST" action="Messages.php" style="margin:0;">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline btn-sm">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Search Toolbar -->
        <div class="dashboard-toolbar">
            <form method="GET" action="Messages.php" style="display:flex; gap: 10px; flex: 1;">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                <div class="toolbar-search" style="max-width: 500px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search sender, email, phone or message text..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="Messages.php?filter=<?= htmlspecialchars($filter); ?>" class="btn btn-outline btn-sm">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Messages Feed -->
        <div class="messages-container" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $row): ?>
                    <?php $is_unread = ((int)$row['is_read'] === 0); ?>
                    <div class="message-card <?= $is_unread ? 'unread' : ''; ?>" style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--card-border); margin-bottom: 20px;">
                        <div class="msg-header">
                            <div class="msg-sender" style="font-size: 17px; display: flex; align-items: center;">
                                <?php if ($is_unread): ?>
                                    <span class="unread-dot" title="Unread Message"></span>
                                <?php endif; ?>
                                <i class="fas fa-user-circle" style="color: var(--primary); margin-right: 8px;"></i>
                                <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?>
                            </div>
                            <div class="msg-date" style="font-size: 13px;">
                                <i class="far fa-clock"></i> <?= date('M j, Y, g:i a', strtotime($row['created_at'])); ?>
                            </div>
                        </div>

                        <div class="msg-contact" style="margin-top: 10px; margin-bottom: 15px; display: flex; gap: 12px; flex-wrap: wrap;">
                            <span style="background: rgba(139, 92, 246, 0.08); padding: 6px 12px; border-radius: 8px; font-size: 13px;">
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($row['email']); ?>
                            </span>
                            <span style="background: rgba(16, 185, 129, 0.08); color: #059669; padding: 6px 12px; border-radius: 8px; font-size: 13px;">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($row['phone']); ?>
                            </span>
                        </div>

                        <div class="msg-body" style="background: #f8fafc; padding: 16px; border-radius: 10px; border-left: 4px solid <?= $is_unread ? 'var(--primary)' : '#cbd5e1'; ?>; font-size: 14px; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($row['message'])); ?>
                        </div>

                        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 15px; border-top: 1px solid rgba(0,0,0,0.04); padding-top: 15px;">
                            <!-- Reply via mailto -->
                            <a href="mailto:<?= htmlspecialchars($row['email']); ?>?subject=<?= urlencode('Regarding your inquiry on EVENTFLARE'); ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-reply"></i> Reply by Email
                            </a>

                            <!-- Toggle Read -->
                            <form method="POST" action="Messages.php" style="margin:0;">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_read">
                                <input type="hidden" name="message_id" value="<?= $row['id']; ?>">
                                <button type="submit" class="btn btn-outline btn-sm">
                                    <i class="fas <?= $is_unread ? 'fa-envelope-open' : 'fa-envelope'; ?>"></i> 
                                    <?= $is_unread ? 'Mark as Read' : 'Mark as Unread'; ?>
                                </button>
                            </form>

                            <!-- Delete -->
                            <form method="POST" action="Messages.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_message">
                                <input type="hidden" name="message_id" value="<?= $row['id']; ?>">
                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Inquiry">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="dashboard-panel" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                    <h3>No inquiries found</h3>
                    <p style="font-size: 14px;">There are currently no contact submissions matching your selection.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
