<?php
// Database configuration
define('DB_HOST', '127.0.0.1:8889');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'cap_db');

// Global settings
define('SITE_URL', 'http://127.0.0.1:8888/cap/');
define('ADMIN_URL', SITE_URL . 'cap_admin/');
define('PWA_URL', SITE_URL . 'cap_pwa/');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>