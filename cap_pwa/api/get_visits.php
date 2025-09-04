<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';
require_once '../../cap_admin/includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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
    // Get today's visits
    $danas = date('Y-m-d');
    $posete = $db->fetchAll("
        SELECT p.*, 
               s.ime_prezime as sticanik_ime,
               s.adresa as sticanik_adresa,
               s.telefon as sticanik_telefon
        FROM posete p
        INNER JOIN sticenike s ON p.sticanik_id = s.id
        WHERE p.korisnik_id = ? AND DATE(p.datum_posete) = ?
        ORDER BY p.vreme_pocetka ASC
    ", [$currentUser['id'], $danas]);

    // Find active visit
    $aktivnaPoseta = null;
    foreach ($posete as $poseta) {
        if ($poseta['status'] === 'u_toku') {
            $aktivnaPoseta = $poseta;
            break;
        }
    }

    // Calculate statistics
    $statistics = [
        'ukupno' => count($posete),
        'zavrsene' => count(array_filter($posete, fn($p) => $p['status'] === 'zavrsena')),
        'na_cekanju' => count(array_filter($posete, fn($p) => $p['status'] === 'zakazana'))
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'posete' => $posete,
            'aktivna_poseta' => $aktivnaPoseta,
            'statistics' => $statistics
        ]
    ]);
    
} catch (Exception $e) {
    error_log("CAP get_visits.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error'
    ]);
}
?>