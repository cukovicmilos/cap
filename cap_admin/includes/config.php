<?php
// Database configuration (SQLite)
define('DB_PATH', '/Users/' . get_current_user() . '/Dropbox/work/studio present/no_code/cap/cap_db.sqlite');

// Global settings
define('SITE_URL', 'http://127.0.0.1:8888/cap/');
define('ADMIN_URL', SITE_URL . 'cap_admin/');
define('PWA_URL', SITE_URL . 'cap_pwa/');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>