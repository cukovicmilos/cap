<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';

header('Content-Type: application/json');

$db = Database::getInstance();

// Check visits with status 'u_toku'
$activePosete = $db->fetchAll("
    SELECT p.*, s.ime_prezime as sticanik_ime, u.ime_prezime as username
    FROM posete p
    INNER JOIN sticenike s ON p.sticanik_id = s.id
    INNER JOIN users u ON p.korisnik_id = u.id
    WHERE p.status = 'u_toku'
    ORDER BY p.id DESC
    LIMIT 10
");

// Check visits with status 'zakazana' 
$zakazanePosete = $db->fetchAll("
    SELECT p.*, s.ime_prezime as sticanik_ime, u.ime_prezime as username
    FROM posete p
    INNER JOIN sticenike s ON p.sticanik_id = s.id
    INNER JOIN users u ON p.korisnik_id = u.id
    WHERE p.status = 'zakazana' AND p.datum_posete = ?
    ORDER BY p.id DESC
    LIMIT 10
", [date('Y-m-d')]);

echo json_encode([
    'active_visits' => $activePosete,
    'scheduled_visits' => $zakazanePosete,
    'counts' => [
        'active' => count($activePosete),
        'scheduled' => count($zakazanePosete)
    ]
], JSON_PRETTY_PRINT);
?>