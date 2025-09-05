<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';

header('Content-Type: application/json');

$db = Database::getInstance();

// Create a test visit with status 'u_toku' for testing finish_visit
$testData = [
    'korisnik_id' => 2, // Test user
    'sticanik_id' => 1, // Test sticenik
    'datum_posete' => date('Y-m-d'),
    'vreme_pocetka' => date('H:i:s', strtotime('-10 minutes')),
    'status' => 'u_toku',
    'sinhronizovano' => 0,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

try {
    $result = $db->insert('posete', $testData);
    $visitId = $db->getConnection()->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Test visit created successfully',
        'visit_id' => $visitId,
        'data' => $testData
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creating test visit: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>