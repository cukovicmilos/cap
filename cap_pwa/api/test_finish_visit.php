<?php
// Test finish_visit with session simulation
session_start();

// Simulate logged in user for testing
$_SESSION['user_id'] = 2;
$_SESSION['user_email'] = 'test@test.com';
$_SESSION['user_name'] = 'Test User';
$_SESSION['user_type'] = 'zaposleni';

require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';
require_once '../../cap_admin/includes/auth.php';
require_once '../../cap_admin/includes/functions.php';

header('Content-Type: application/json');

// Test data
$visitId = 125; // The test visit we just created
$testData = [
    'poseta_id' => $visitId,
    'usluge' => [1, 2], // Some test services
    'napomene' => 'Test završavanje posete'
];

// Make internal call to finish_visit logic
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Auth check failed - user not logged in']);
    exit;
}

$currentUser = $auth->getCurrentUser();
echo "Current user: " . json_encode($currentUser) . "\n\n";

$db = Database::getInstance();

try {
    $db->getConnection()->beginTransaction();
    
    // Check if visit exists
    $poseta = $db->fetchOne("
        SELECT p.*, s.ime_prezime as sticanik_ime 
        FROM posete p
        INNER JOIN sticenike s ON p.sticanik_id = s.id
        WHERE p.id = ? AND p.korisnik_id = ? AND p.status = 'u_toku'
    ", [$visitId, $currentUser['id']]);
    
    if (!$poseta) {
        echo json_encode(['error' => 'Visit not found or not active']);
        exit;
    }
    
    echo "Found visit: " . json_encode($poseta) . "\n\n";
    
    // Calculate duration
    $startTime = new DateTime($poseta['datum_posete'] . ' ' . $poseta['vreme_pocetka']);
    $endTime = new DateTime();
    $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
    $durationMinutes = round($duration / 60);
    
    echo "Duration calculation:\n";
    echo "  Start: " . $startTime->format('Y-m-d H:i:s') . "\n";
    echo "  End: " . $endTime->format('Y-m-d H:i:s') . "\n";
    echo "  Duration minutes: " . $durationMinutes . "\n";
    echo "  Formatted: " . minutesToHours($durationMinutes) . "\n\n";
    
    // Update visit
    $updateData = [
        'status' => 'zavrsena',
        'vreme_kraja' => $endTime->format('H:i:s'),
        'ukupno_vreme' => $durationMinutes,
        'napomene' => $testData['napomene'],
        'sinhronizovano' => 1
    ];
    
    $result = $db->update('posete', $updateData, 'id = ?', [$visitId]);
    echo "Update result: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";
    
    // Add services
    foreach ($testData['usluge'] as $uslugaId) {
        $db->insert('poseta_usluge', [
            'poseta_id' => $visitId,
            'usluga_id' => $uslugaId
        ]);
    }
    echo "Services added successfully\n\n";
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Visit finished successfully',
        'duration' => minutesToHours($durationMinutes)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $db->getConnection()->rollback();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>