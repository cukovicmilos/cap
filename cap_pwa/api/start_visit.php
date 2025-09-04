<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';
require_once '../../cap_admin/includes/auth.php';

header('Content-Type: application/json');

// CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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

$input = json_decode(file_get_contents('php://input'), true);
error_log("CAP start_visit.php: Input data: " . json_encode($input));
$posetaId = (int)($input['poseta_id'] ?? 0);
error_log("CAP start_visit.php: Poseta ID: " . $posetaId . ", User ID: " . $currentUser['id']);

if (!$posetaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing poseta_id']);
    exit;
}

$db = Database::getInstance();

try {
    // Check if user already has an active visit
    error_log("CAP start_visit.php: Checking for active visit for user: " . $currentUser['id']);
    $aktivnaPoseta = $db->fetchOne("
        SELECT id FROM posete 
        WHERE korisnik_id = ? AND status = 'u_toku'
    ", [$currentUser['id']]);
    
    if ($aktivnaPoseta) {
        error_log("CAP start_visit.php: User already has active visit: " . $aktivnaPoseta['id']);
        echo json_encode([
            'success' => false, 
            'message' => 'Već imate aktivnu posetu u toku. Završite je pre pokretanja nove.'
        ]);
        exit;
    }
    
    error_log("CAP start_visit.php: No active visit found, checking requested visit");
    
    // Verify this visit belongs to current user and is scheduled
    error_log("CAP start_visit.php: Looking for visit with ID: $posetaId, User: {$currentUser['id']}, Status: zakazana");
    $poseta = $db->fetchOne("
        SELECT p.*, s.ime_prezime as sticanik_ime 
        FROM posete p
        INNER JOIN sticenike s ON p.sticanik_id = s.id
        WHERE p.id = ? AND p.korisnik_id = ? AND p.status = 'zakazana'
    ", [$posetaId, $currentUser['id']]);
    
    // Also check what status the visit actually has
    $actualVisit = $db->fetchOne("SELECT id, status, korisnik_id FROM posete WHERE id = ?", [$posetaId]);
    if ($actualVisit) {
        error_log("CAP start_visit.php: Found visit with status: {$actualVisit['status']}, user: {$actualVisit['korisnik_id']}");
    } else {
        error_log("CAP start_visit.php: No visit found with ID: $posetaId");
    }
    
    if (!$poseta) {
        error_log("CAP start_visit.php: Visit not found or not available for starting. Visit ID: $posetaId");
        echo json_encode([
            'success' => false, 
            'message' => 'Poseta nije pronađena ili nije dostupna za pokretanje.'
        ]);
        exit;
    }
    
    error_log("CAP start_visit.php: Visit found, attempting to start: " . $poseta['id']);
    
    // Start the visit
    $startTime = date('H:i:s');
    $updateData = [
        'status' => 'u_toku',
        'vreme_pocetka' => $startTime,
        'sinhronizovano' => 1
    ];
    
    if ($db->update('posete', $updateData, 'id = ?', [$posetaId])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Poseta je uspešno pokrenuta!',
            'data' => [
                'poseta_id' => $posetaId,
                'sticanik_ime' => $poseta['sticanik_ime'],
                'start_time' => $startTime,
                'start_timestamp' => time() * 1000 // JavaScript timestamp
            ]
        ]);
    } else {
        throw new Exception('Failed to update poseta');
    }
    
} catch (Exception $e) {
    error_log("CAP start_visit.php error: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>