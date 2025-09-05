<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';

header('Content-Type: application/json');

$db = Database::getInstance();

// Create a test visit with status 'zakazana' (scheduled) for testing start_visit
$testData = [
    'korisnik_id' => 2, // Test user
    'sticanik_id' => 1, // Test sticenik
    'datum_posete' => date('Y-m-d'),
    'vreme_pocetka' => '09:00', // Default scheduled time
    'status' => 'zakazana', // Scheduled status
    'sinhronizovano' => 0,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

try {
    $result = $db->insert('posete', $testData);
    $visitId = $db->getConnection()->lastInsertId();
    
    // Get sticenik name
    $sticenik = $db->fetchOne("SELECT ime_prezime FROM sticenike WHERE id = ?", [$testData['sticanik_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Scheduled visit created successfully',
        'visit_id' => $visitId,
        'sticenik_name' => $sticenik['ime_prezime'] ?? 'Unknown',
        'data' => $testData
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creating scheduled visit: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>