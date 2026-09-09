<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'dashboard';

// Fetch statistics
$stats = [
    'users' => 0,
    'buyers' => 0,
    'suppliers' => 0,
    'bookings' => 0,
    'pending_bookings' => 0,
    'budget' => 0,
    'messages' => 0,
    'unread_messages' => 0
];

if ($conn) {
    // Users breakdown
    $res = $conn->query("SELECT COUNT(*) as c, SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) as s, SUM(CASE WHEN role = 'buyer' THEN 1 ELSE 0 END) as b FROM users");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['users'] = (int)$row['c'];
        $stats['suppliers'] = (int)($row['s'] ?? 0);
        $stats['buyers'] = (int)($row['b'] ?? 0);
    }

    // Bookings breakdown
    $res = $conn->query("SELECT COUNT(*) as c, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as p FROM bookings");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['bookings'] = (int)$row['c'];
        $stats['pending_bookings'] = (int)($row['p'] ?? 0);
    }

    // Budgets aggregate
    $res = $conn->query("SELECT SUM(total_budget) as s FROM budgets");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['budget'] = (float)($row['s'] ?? 0);
    }

    // Contact Messages breakdown
    $res = $conn->query("SELECT COUNT(*) as c, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as u FROM contact_messages");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['messages'] = (int)$row['c'];
        $stats['unread_messages'] = (int)($row['u'] ?? 0);
    }

    // Event Types breakdown
    $stats['event_types'] = 0;
    $stats['active_event_types'] = 0;
    $res = $conn->query("SELECT COUNT(*) as c, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as a FROM event_types");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['event_types'] = (int)$row['c'];
        $stats['active_event_types'] = (int)($row['a'] ?? 0);
    }
}

// Fetch monthly booking data for chart
$chart_labels = [];
$chart_data = [];
$chart_query = $conn->query("SELECT DATE_FORMAT(EventDate, '%b') AS month_label, MONTH(EventDate) AS m, COUNT(*) AS cnt FROM bookings WHERE YEAR(EventDate) = YEAR(CURDATE()) GROUP BY m, month_label ORDER BY m");
if ($chart_query) {
    while ($cr = $chart_query->fetch_assoc()) {
        $chart_labels[] = $cr['month_label'];
        $chart_data[] = (int)$cr['cnt'];
    }
}
if (empty($chart_labels)) {
    $chart_labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $chart_data = [0,0,0,0,0,0,0,0,0,0,0,0];
}

// Booking status distribution
$status_counts = ['pending' => 0, 'confirmed' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
$status_res = $conn->query("SELECT status, COUNT(*) as c FROM bookings GROUP BY status");
if ($status_res) {
    while ($sr = $status_res->fetch_assoc()) {
        $st = $sr['status'] ?: 'pending';
        $status_counts[$st] = (int)$sr['c'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview - EVENTFLARE Admin Console</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Executive Overview</h1>
                <p>Real-time platform activity, client reservations, and vendor operations.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- Key Metrics Grid -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['users']); ?></h3>
                    <p>Total Registered Users</p>
                    <small style="color: var(--text-muted); font-size: 12px;">
                        <?= $stats['buyers']; ?> Clients &bull; <?= $stats['suppliers']; ?> Suppliers
                    </small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['bookings']); ?></h3>
                    <p>Total Bookings</p>
                    <small style="color: #f59e0b; font-weight: 700; font-size: 12px;">
                        <i class="fas fa-clock"></i> <?= $stats['pending_bookings']; ?> Pending Confirmation
                    </small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fas fa-store"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['suppliers']); ?></h3>
                    <p>Active Vendors</p>
                    <small style="color: var(--primary); font-weight: 600; font-size: 12px;">
                        <a href="Suppliers.php" style="color: inherit; text-decoration: underline;">Manage Suppliers &rarr;</a>
                    </small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['messages']); ?></h3>
                    <p>Contact Inquiries</p>
                    <small style="color: <?= $stats['unread_messages'] > 0 ? '#ef4444' : '#10b981'; ?>; font-weight: 700; font-size: 12px;">
                        <?= $stats['unread_messages']; ?> Unread Messages
                    </small>
                </div>
            </div>
        </div>

        <!-- Charts and Panels Grid -->
        <div class="panel-grid">
            <!-- Monthly Trends Chart -->
            <div class="dashboard-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Booking Trends (<?= date('Y'); ?>)</h2>
                    <a href="Bookinglist.php" class="btn btn-outline btn-sm">View All Bookings</a>
                </div>
                <canvas id="bookingsChart" height="110"></canvas>
            </div>

            <!-- Booking Status Lifecycle Breakdown -->
            <div class="dashboard-panel">
                <h2>Booking Statuses</h2>
                <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff; border-radius: 10px; border: 1px solid var(--card-border);">
                        <span class="badge badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
                        <strong><?= $status_counts['pending']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff; border-radius: 10px; border: 1px solid var(--card-border);">
                        <span class="badge badge-confirmed"><i class="fas fa-check-circle"></i> Confirmed</span>
                        <strong><?= $status_counts['confirmed']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff; border-radius: 10px; border: 1px solid var(--card-border);">
                        <span class="badge badge-in_progress"><i class="fas fa-spinner fa-spin"></i> In Progress</span>
                        <strong><?= $status_counts['in_progress']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff; border-radius: 10px; border: 1px solid var(--card-border);">
                        <span class="badge badge-completed"><i class="fas fa-flag-checkered"></i> Completed</span>
                        <strong><?= $status_counts['completed']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff; border-radius: 10px; border: 1px solid var(--card-border);">
                        <span class="badge badge-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>
                        <strong><?= $status_counts['cancelled']; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Inquiries and Quick Shortcuts -->
        <div class="panel-grid">
            <div class="dashboard-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Recent Contact Inquiries</h2>
                    <a href="Messages.php" class="btn btn-outline btn-sm">Inbox (<?= $stats['messages']; ?>)</a>
                </div>
                <div class="dashboard-table-container">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Contact</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $msg_res = $conn->query("SELECT id, firstname, lastname, email, is_read, created_at FROM contact_messages ORDER BY id DESC LIMIT 5");
                            if ($msg_res && $msg_res->num_rows > 0) {
                                while ($m = $msg_res->fetch_assoc()) {
                                    $is_unread = ((int)$m['is_read'] === 0);
                                    echo "<tr>
                                            <td><strong>" . htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) . "</strong></td>
                                            <td>" . htmlspecialchars($m['email']) . "</td>
                                            <td>" . date('M j, g:i a', strtotime($m['created_at'])) . "</td>
                                            <td>" . ($is_unread ? "<span class='badge badge-warning'>Unread</span>" : "<span class='badge badge-success'>Read</span>") . "</td>
                                            <td><a href='Messages.php' class='btn btn-outline btn-sm'>Inspect</a></td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align:center; color: var(--text-muted);'>No inquiries received yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Action Hub -->
            <div class="dashboard-panel">
                <h2>Administrative Shortcuts</h2>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                    <a href="Suppliers.php" class="btn btn-outline" style="text-align: left; padding: 14px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-store fa-lg" style="color: var(--primary);"></i>
                        <div>
                            <div style="font-weight: 700;">Vendor Directory</div>
                            <small style="color: var(--text-muted);">Manage suppliers, edit listings, and update services</small>
                        </div>
                    </a>

                    <a href="Bookinglist.php?status=pending" class="btn btn-outline" style="text-align: left; padding: 14px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-calendar-check fa-lg" style="color: #f59e0b;"></i>
                        <div>
                            <div style="font-weight: 700;">Review Pending Bookings</div>
                            <small style="color: var(--text-muted);"><?= $stats['pending_bookings']; ?> booking(s) waiting for confirmation</small>
                        </div>
                    </a>

                    <a href="Budgets.php" class="btn btn-outline" style="text-align: left; padding: 14px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-wallet fa-lg" style="color: #10b981;"></i>
                        <div>
                            <div style="font-weight: 700;">Financial Logs</div>
                            <small style="color: var(--text-muted);">$<?= number_format($stats['budget'], 2); ?> total budget tracked</small>
                        </div>
                    </a>

                    <a href="EventTypes.php" class="btn btn-outline" style="text-align: left; padding: 14px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-layer-group fa-lg" style="color: #6366f1;"></i>
                        <div>
                            <div style="font-weight: 700;">Event Types & Services</div>
                            <small style="color: var(--text-muted);"><?= $stats['event_types']; ?> categories (<?= $stats['active_event_types']; ?> active)</small>
                        </div>
                    </a>

                    <a href="AdminStaff.php" class="btn btn-outline" style="text-align: left; padding: 14px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-user-shield fa-lg" style="color: #ec4899;"></i>
                        <div>
                            <div style="font-weight: 700;">Admin & Staff Accounts</div>
                            <small style="color: var(--text-muted);">Create and manage organizer console credentials</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('bookingsChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(139, 92, 246, 0.45)');
        gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?= json_encode($chart_data) ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#8b5cf6',
                    pointRadius: 4,
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { borderDash: [4, 4], color: 'rgba(0,0,0,0.06)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>
