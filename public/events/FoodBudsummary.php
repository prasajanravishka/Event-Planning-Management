<?php
/**
 * Redirect shim to the unified Admin Budget Console.
 */
session_start();
if (!isset($_SESSION['login_user']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../admin/Admin.php");
    exit();
}
header("Location: ../admin/Budgets.php");
exit();
