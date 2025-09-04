<?php
session_start();
require_once '../cap_admin/includes/config.php';
require_once '../cap_admin/includes/database.php';
require_once '../cap_admin/includes/auth.php';

try {
    $auth = new Auth();
    $auth->logout();
} catch (Exception $e) {
    // Even if there's an error, we still want to logout
    session_destroy();
}

header('Location: login.php');
exit;
?>