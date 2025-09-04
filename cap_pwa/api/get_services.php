<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';
require_once '../../cap_admin/includes/auth.php';

header('Content-Type: application/json');

// CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $auth->getCurrentUser();

if ($currentUser['type'] === 'upravitelj') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = Database::getInstance();

try {
    $usluge = $db->fetchAll("SELECT id, naziv FROM usluge ORDER BY naziv");
    
    echo json_encode([
        'success' => true,
        'data' => $usluge
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>