<?php
// Set header immediately
header('Content-Type: application/json');

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../../cap_admin/includes/config.php';
    require_once '../../cap_admin/includes/database.php';
    require_once '../../cap_admin/includes/auth.php';
    require_once '../../cap_admin/includes/functions.php';
    
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $currentUser = $auth->getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true);
    $actions = $input['actions'] ?? [];
    
    if (empty($actions)) {
        echo json_encode(['success' => true, 'message' => 'No actions to sync', 'synced' => 0]);
        exit;
    }
    
    $db = Database::getInstance();
    $syncedCount = 0;
    $errors = [];
    
    foreach ($actions as $action) {
        try {
            if ($action['action_type'] === 'finish_visit') {
                $data = $action['data'];
                $posetaId = (int)($data['poseta_id'] ?? 0);
                
                if (!$posetaId) {
                    $errors[] = "Invalid poseta_id";
                    continue;
                }
                
                // Check if visit exists
                $poseta = $db->fetchOne("SELECT * FROM posete WHERE id = ?", [$posetaId]);
                
                if ($poseta) {
                    // Only update if not already finished
                    if ($poseta['status'] !== 'zavrsena') {
                        $db->getConnection()->beginTransaction();
                        
                        // Calculate end time
                        $timestamp = isset($action['timestamp']) ? intval($action['timestamp'] / 1000) : time();
                        
                        // Update visit
                        $updateData = [
                            'status' => 'zavrsena',
                            'vreme_kraja' => date('H:i:s', $timestamp),
                            'napomene' => $data['napomene'] ?? '',
                            'sinhronizovano' => 1
                        ];
                        
                        // Calculate duration if we have start time
                        if ($poseta['vreme_pocetka']) {
                            $startTime = strtotime($poseta['datum_posete'] . ' ' . $poseta['vreme_pocetka']);
                            $duration = round(($timestamp - $startTime) / 60);
                            if ($duration > 0) {
                                $updateData['ukupno_vreme'] = $duration;
                            }
                        }
                        
                        $db->update('posete', $updateData, 'id = ?', [$posetaId]);
                        
                        // Add services if provided
                        if (!empty($data['usluge']) && is_array($data['usluge'])) {
                            // Remove existing services
                            $db->delete('poseta_usluge', 'poseta_id = ?', [$posetaId]);
                            
                            // Add new services
                            foreach ($data['usluge'] as $uslugaId) {
                                if (is_numeric($uslugaId)) {
                                    $db->insert('poseta_usluge', [
                                        'poseta_id' => $posetaId,
                                        'usluga_id' => (int)$uslugaId
                                    ]);
                                }
                            }
                        }
                        
                        $db->getConnection()->commit();
                        $syncedCount++;
                    } else {
                        $errors[] = "Visit $posetaId already finished";
                    }
                } else {
                    $errors[] = "Visit $posetaId not found";
                }
                
            } else if ($action['action_type'] === 'start_visit') {
                $data = $action['data'];
                $posetaId = (int)($data['poseta_id'] ?? 0);
                
                if (!$posetaId) {
                    $errors[] = "Invalid poseta_id for start_visit";
                    continue;
                }
                
                $poseta = $db->fetchOne("SELECT * FROM posete WHERE id = ?", [$posetaId]);
                
                if ($poseta && $poseta['status'] === 'zakazana') {
                    $timestamp = isset($action['timestamp']) ? intval($action['timestamp'] / 1000) : time();
                    
                    $updateData = [
                        'status' => 'u_toku',
                        'vreme_pocetka' => date('H:i:s', $timestamp),
                        'sinhronizovano' => 1
                    ];
                    
                    $db->update('posete', $updateData, 'id = ?', [$posetaId]);
                    $syncedCount++;
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "Error processing action: " . $e->getMessage();
            error_log("CAP sync error: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Synced $syncedCount action(s)",
        'synced' => $syncedCount,
        'total' => count($actions),
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("CAP sync_offline_data.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>