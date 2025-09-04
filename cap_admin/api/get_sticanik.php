<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$db = Database::getInstance();
$id = (int)$_GET['id'];

try {
    // Get sticanik basic info
    $sticanik = $db->fetchOne("SELECT * FROM sticenike WHERE id = ?", [$id]);
    
    if (!$sticanik) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Štićenik not found']);
        exit;
    }
    
    // Get assigned usluge
    $usluge = $db->fetchAll("
        SELECT usluga_id 
        FROM sticanik_usluge 
        WHERE sticanik_id = ?
    ", [$id]);
    
    // Get assigned korisnici
    $korisnici = $db->fetchAll("
        SELECT korisnik_id 
        FROM korisnik_sticenike 
        WHERE sticanik_id = ?
    ", [$id]);
    
    echo json_encode([
        'success' => true,
        'sticanik' => $sticanik,
        'usluge' => array_column($usluge, 'usluga_id'),
        'korisnici' => array_column($korisnici, 'korisnik_id')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>