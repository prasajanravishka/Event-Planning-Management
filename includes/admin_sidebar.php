<?php
/**
 * Admin Sidebar — Shared include for all admin dashboard pages.
 * 
 * Usage: include this file in admin pages after setting $active_page.
 * Valid $active_page values: 'dashboard', 'users', 'suppliers', 'bookings', 'budgets', 'messages', 'staff'
 */
if (!isset($active_page)) {
    $active_page = '';
}

// Fetch badge counts if DB connection is active
$badge_pending_bookings = 0;
$badge_unread_messages = 0;
$badge_customers = 0;
$badge_suppliers = 0;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $b_res = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'");
    if ($b_res) {
        $badge_pending_bookings = (int)$b_res->fetch_assoc()['c'];
    }
    $m_res = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0");
    if ($m_res) {
        $badge_unread_messages = (int)$m_res->fetch_assoc()['c'];
    }
    $c_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'buyer'");
    if ($c_res) {
        $badge_customers = (int)$c_res->fetch_assoc()['c'];
    }
    $s_res = $conn->query("SELECT COUNT(*) as c FROM suppliers");
    if ($s_res) {
        $badge_suppliers = (int)$s_res->fetch_assoc()['c'];
    }
}

// Current logged-in user display info
$current_admin_user = $_SESSION['login_user'] ?? 'Admin';
$admin_initial = strtoupper(substr($current_admin_user, 0, 1));
?>
<!-- Mobile Drawer Toggle Header -->
<div class="mobile-admin-header">
    <button type="button" class="sidebar-toggle-btn" id="adminSidebarToggle" aria-label="Toggle navigation">
        <i class="fas fa-bars"></i>
    </button>
    <a href="Dashboard.php" class="mobile-brand">EVENTFLARE</a>
    <div class="mobile-user-avatar">
        <i class="fas fa-user-shield"></i>
    </div>
</div>

<aside class="dashboard-sidebar" id="adminSidebar">
    <div class="sidebar-top">
        <a href="Dashboard.php" class="sidebar-logo">
            <img src="../assets/images/logo.jpg" alt="Eventflare Logo">
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">EVENTFLARE</span>
                <span class="sidebar-brand-badge">ADMIN CONSOLE</span>
            </div>
        </a>
        <button type="button" class="sidebar-close-btn" id="adminSidebarClose" aria-label="Close sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-section-header">Core Console</li>
        <li>
            <a href="Dashboard.php" <?= ($active_page === 'dashboard') ? 'class="active"' : '' ?>>
                <i class="fas fa-chart-pie"></i> Overview
            </a>
        </li>
        <li>
            <a href="EventTypes.php" <?= ($active_page === 'event_types') ? 'class="active"' : '' ?>>
                <i class="fas fa-layer-group"></i> Event Types
            </a>
        </li>

        <li class="sidebar-section-header">People & Accounts</li>
        <li>
            <a href="Userlist.php" <?= ($active_page === 'users') ? 'class="active"' : '' ?>>
                <i class="fas fa-user-friends"></i> Customers
                <?php if ($badge_customers > 0): ?>
                    <span class="sidebar-badge badge-neutral"><?= $badge_customers ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="Suppliers.php" <?= ($active_page === 'suppliers') ? 'class="active"' : '' ?>>
                <i class="fas fa-store"></i> Suppliers
                <?php if ($badge_suppliers > 0): ?>
                    <span class="sidebar-badge badge-neutral"><?= $badge_suppliers ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-section-header">Event Operations</li>
        <li>
            <a href="Bookinglist.php" <?= ($active_page === 'bookings') ? 'class="active"' : '' ?>>
                <i class="fas fa-calendar-check"></i> Bookings
                <?php if ($badge_pending_bookings > 0): ?>
                    <span class="sidebar-badge badge-warning"><?= $badge_pending_bookings ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="Budgets.php" <?= ($active_page === 'budgets') ? 'class="active"' : '' ?>>
                <i class="fas fa-wallet"></i> Budgets
            </a>
        </li>

        <li class="sidebar-section-header">Platform & Security</li>
        <li>
            <a href="Messages.php" <?= ($active_page === 'messages') ? 'class="active"' : '' ?>>
                <i class="fas fa-envelope"></i> Inquiries
                <?php if ($badge_unread_messages > 0): ?>
                    <span class="sidebar-badge badge-primary"><?= $badge_unread_messages ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="AdminStaff.php" <?= ($active_page === 'staff') ? 'class="active"' : '' ?>>
                <i class="fas fa-user-shield"></i> Staff & Admins
            </a>
        </li>
    </ul>

    <div class="sidebar-bottom">
        <!-- Integrated Authenticated User Identity Card -->
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?= $admin_initial; ?>
                <span class="sidebar-status-dot" title="Active Session"></span>
            </div>
            <div class="sidebar-user-meta">
                <div class="sidebar-user-name" title="<?= htmlspecialchars($current_admin_user); ?>">
                    <?= htmlspecialchars($current_admin_user); ?>
                </div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
            <div class="sidebar-user-actions">
                <a href="../Home.php" class="sidebar-icon-btn" title="View Live Site" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <a href="../logout.php" class="sidebar-icon-btn logout" title="Sign Out">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <a href="../Home.php" class="sidebar-link-home-pill" target="_blank">
            <i class="fas fa-globe"></i> View Public Website
        </a>
    </div>
</aside>

<div class="sidebar-backdrop" id="adminSidebarBackdrop"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const closeBtn = document.getElementById('adminSidebarClose');
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('adminSidebarBackdrop');

    function openSidebar() {
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
});
</script>
