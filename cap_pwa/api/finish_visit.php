<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log start
error_log("CAP finish_visit.php: Request started");

try {
    require_once '../../cap_admin/includes/config.php';
    require_once '../../cap_admin/includes/database.php';
    require_once '../../cap_admin/includes/auth.php';
    require_once '../../cap_admin/includes/functions.php';
    
    error_log("CAP finish_visit.php: All includes loaded successfully");
} catch (Exception $e) {
    error_log("CAP finish_visit.php: Include error - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    error_log("CAP finish_visit.php: User not authenticated");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $auth->getCurrentUser();
error_log("CAP finish_visit.php: User authenticated - ID: " . $currentUser['id'] . ", Type: " . $currentUser['type']);

if ($currentUser['type'] === 'upravitelj') {
    error_log("CAP finish_visit.php: Access denied for upravitelj");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
error_log("CAP finish_visit.php: Input data: " . json_encode($input));

$posetaId = (int)($input['poseta_id'] ?? 0);
$selectedUsluge = $input['usluge'] ?? [];
$napomene = trim($input['napomene'] ?? '');

if (!$posetaId) {
    error_log("CAP finish_visit.php: Missing poseta_id");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing poseta_id']);
    exit;
}

error_log("CAP finish_visit.php: Processing visit ID: " . $posetaId . " with services: " . json_encode($selectedUsluge));

$db = Database::getInstance();

try {
    error_log("CAP finish_visit.php: Starting database transaction");
    $db->getConnection()->beginTransaction();
    
    // Verify this visit belongs to current user and is active
    error_log("CAP finish_visit.php: Checking visit for user " . $currentUser['id'] . " with status 'u_toku'");
    $poseta = $db->fetchOne("
        SELECT p.*, s.ime_prezime as sticanik_ime 
        FROM posete p
        INNER JOIN sticenike s ON p.sticanik_id = s.id
        WHERE p.id = ? AND p.korisnik_id = ? AND p.status = 'u_toku'
    ", [$posetaId, $currentUser['id']]);
    
    if (!$poseta) {
        error_log("CAP finish_visit.php: Visit not found or not active for ID: " . $posetaId);
        $db->getConnection()->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'Poseta nije pronađena ili nije aktivna.'
        ]);
        exit;
    }
    
    error_log("CAP finish_visit.php: Found active visit for sticenik: " . $poseta['sticanik_ime']);
    
    // Calculate duration
    $startTime = new DateTime($poseta['datum_posete'] . ' ' . $poseta['vreme_pocetka']);
    $endTime = new DateTime();
    $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
    $durationMinutes = round($duration / 60);
    
    // Update visit to completed
    $updateData = [
        'status' => 'zavrsena',
        'vreme_kraja' => $endTime->format('H:i:s'),
        'ukupno_vreme' => $durationMinutes,
        'napomene' => $napomene,
        'sinhronizovano' => 1
    ];
    
    if (!$db->update('posete', $updateData, 'id = ?', [$posetaId])) {
        throw new Exception('Greška prilikom ažuriranja posete.');
    }
    
    // Add selected services
    if (!empty($selectedUsluge)) {
        // Remove existing services for this visit
        $db->delete('poseta_usluge', 'poseta_id = ?', [$posetaId]);
        
        // Add new services
        foreach ($selectedUsluge as $uslugaId) {
            $db->insert('poseta_usluge', [
                'poseta_id' => $posetaId,
                'usluga_id' => (int)$uslugaId
            ]);
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Poseta je uspešno završena za ' . $poseta['sticanik_ime'] . ' (trajanje: ' . minutesToHours($durationMinutes) . ')',
        'data' => [
            'poseta_id' => $posetaId,
            'sticanik_ime' => $poseta['sticanik_ime'],
            'duration' => minutesToHours($durationMinutes),
            'duration_minutes' => $durationMinutes
        ]
    ]);
    
} catch (Exception $e) {
    $db->getConnection()->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Došlo je do greške: ' . $e->getMessage()
    ]);
}
?>