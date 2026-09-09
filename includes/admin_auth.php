<?php
/**
 * admin_auth.php — Centralized Admin Authentication Middleware & Security Helpers
 * 
 * Include this at the very top of every admin-protected route.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session inactivity timeout (2 hours = 7200 seconds)
$timeout_duration = 7200;
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: " . (defined('ADMIN_PATH_PREFIX') ? ADMIN_PATH_PREFIX : '') . "Admin.php?error=Session+timed+out.+Please+log+in+again.");
    exit();
}
$_SESSION['admin_last_activity'] = time();

// Enforce Admin Role
if (!isset($_SESSION['login_user']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $redirect_url = (defined('ADMIN_PATH_PREFIX') ? ADMIN_PATH_PREFIX : '') . "Admin.php";
    header("Location: $redirect_url");
    exit();
}

/**
 * Generate or retrieve CSRF token
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF form input
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate submitted CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash alert message
 */
function set_flash_message(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

/**
 * Retrieve and unset flash message
 */
function get_flash_message(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Render flash alert banner if present
 */
function render_flash_message(): string {
    $flash = get_flash_message();
    if (!$flash) {
        return '';
    }
    $icon = match($flash['type']) {
        'success' => 'fas fa-check-circle',
        'error' => 'fas fa-exclamation-circle',
        'warning' => 'fas fa-exclamation-triangle',
        default => 'fas fa-info-circle'
    };
    $class = match($flash['type']) {
        'success' => 'message-success',
        'error' => 'message-error',
        'warning' => 'message-warning',
        default => 'message-info'
    };
    return '<div class="alert-banner ' . $class . '">
                <i class="' . $icon . '"></i>
                <span>' . htmlspecialchars($flash['message']) . '</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
            </div>';
}
