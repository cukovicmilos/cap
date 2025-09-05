<?php
require_once '../../cap_admin/includes/config.php';
require_once '../../cap_admin/includes/database.php';

header('Content-Type: text/plain');

$db = Database::getInstance();

echo "Checking database structure...\n\n";

try {
    // Get all tables
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        echo "- " . $table['name'] . "\n";
    }
    echo "\n";
    
    // Check users table
    $userTableName = null;
    foreach ($tables as $table) {
        if (strpos($table['name'], 'user') !== false || strpos($table['name'], 'koris') !== false) {
            $userTableName = $table['name'];
            echo "Found user table: " . $userTableName . "\n";
            
            // Get columns
            $columns = $db->fetchAll("PRAGMA table_info(" . $userTableName . ")");
            echo "Columns:\n";
            foreach ($columns as $col) {
                echo "  - " . $col['name'] . " (" . $col['type'] . ")\n";
            }
            echo "\n";
        }
    }
    
    // Check visits table
    if (in_array('posete', array_column($tables, 'name'))) {
        echo "Found 'posete' table\n";
        $columns = $db->fetchAll("PRAGMA table_info(posete)");
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo "  - " . $col['name'] . " (" . $col['type'] . ")\n";
        }
        echo "\n";
        
        // Get sample data
        $sample = $db->fetchAll("SELECT * FROM posete LIMIT 5");
        echo "Sample posete data:\n";
        print_r($sample);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>