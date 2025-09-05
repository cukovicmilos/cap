<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing finish_visit.php API\n\n";

try {
    // Test includes
    require_once '../../cap_admin/includes/config.php';
    echo "✓ config.php loaded\n";
    
    require_once '../../cap_admin/includes/database.php';
    echo "✓ database.php loaded\n";
    
    require_once '../../cap_admin/includes/auth.php';
    echo "✓ auth.php loaded\n";
    
    require_once '../../cap_admin/includes/functions.php';
    echo "✓ functions.php loaded\n\n";
    
    // Test database connection
    $db = Database::getInstance();
    echo "✓ Database connection established\n";
    
    // Test auth
    $auth = new Auth();
    echo "✓ Auth class instantiated\n\n";
    
    // Test minutesToHours function
    if (function_exists('minutesToHours')) {
        echo "✓ minutesToHours function exists\n";
        echo "  Test: minutesToHours(125) = " . minutesToHours(125) . "\n\n";
    } else {
        echo "✗ minutesToHours function NOT found\n\n";
    }
    
    // Simulate finish_visit.php logic
    echo "Testing finish_visit.php logic:\n";
    
    // Mock login for test
    session_start();
    $_SESSION['user_id'] = 2; // Test user
    
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        echo "✓ User logged in: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
        
        // Test query
        $poseta = $db->fetchOne("
            SELECT p.*, s.ime_prezime as sticanik_ime 
            FROM posete p
            INNER JOIN sticenike s ON p.sticanik_id = s.id
            WHERE p.id = ? AND p.korisnik_id = ? AND p.status = ?
        ", [123, $user['id'], 'u_toku']);
        
        if ($poseta) {
            echo "✓ Found active visit: " . json_encode($poseta) . "\n";
        } else {
            echo "⚠ No active visit found for user " . $user['id'] . "\n";
        }
    } else {
        echo "⚠ No user logged in\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>