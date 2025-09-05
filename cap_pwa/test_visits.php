<?php
require_once '../cap_admin/includes/config.php';
require_once '../cap_admin/includes/database.php';
require_once '../cap_admin/includes/auth.php';

$auth = new Auth();
$currentUser = $auth->getCurrentUser();

echo "<h2>Current User:</h2>";
echo "ID: " . ($currentUser['id'] ?? 'Not logged in') . "<br>";
echo "Name: " . ($currentUser['name'] ?? 'N/A') . "<br>";
echo "Type: " . ($currentUser['type'] ?? 'N/A') . "<br><br>";

$db = Database::getInstance();
$danas = date('Y-m-d');

echo "<h2>Today's Date: $danas</h2><br>";

// Get ALL visits for today
echo "<h2>ALL visits for today:</h2>";
$allVisits = $db->fetchAll("
    SELECT p.*, s.ime_prezime as sticanik_ime
    FROM posete p
    INNER JOIN sticenike s ON p.sticanik_id = s.id
    WHERE DATE(p.datum_posete) = ?
    ORDER BY p.korisnik_id, p.vreme_pocetka ASC
", [$danas]);

echo "Total visits in database for today: " . count($allVisits) . "<br><br>";

foreach ($allVisits as $visit) {
    echo "ID: " . $visit['id'] . 
         " | User ID: " . $visit['korisnik_id'] . 
         " | Štićenik: " . $visit['sticanik_ime'] . 
         " | Status: " . $visit['status'] . 
         " | Time: " . $visit['vreme_pocetka'] . "<br>";
}

if ($currentUser) {
    echo "<br><h2>Visits for current user (ID: " . $currentUser['id'] . "):</h2>";
    $userVisits = $db->fetchAll("
        SELECT p.*, s.ime_prezime as sticanik_ime
        FROM posete p
        INNER JOIN sticenike s ON p.sticanik_id = s.id
        WHERE p.korisnik_id = ? AND DATE(p.datum_posete) = ?
        ORDER BY p.vreme_pocetka ASC
    ", [$currentUser['id'], $danas]);
    
    echo "Total visits for current user: " . count($userVisits) . "<br><br>";
    
    foreach ($userVisits as $visit) {
        echo "ID: " . $visit['id'] . 
             " | Štićenik: " . $visit['sticanik_ime'] . 
             " | Status: " . $visit['status'] . 
             " | Time: " . $visit['vreme_pocetka'] . "<br>";
    }
}
?>