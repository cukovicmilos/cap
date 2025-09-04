<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();

$db = Database::getInstance();
$posetaId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$posetaId) {
    header('Location: posete.php');
    exit;
}

// Get visit details
$poseta = $db->fetchOne("
    SELECT p.*, 
           u.ime_prezime as korisnik_ime, u.tip_korisnika,
           s.ime_prezime as sticanik_ime, s.adresa as sticanik_adresa
    FROM posete p
    LEFT JOIN users u ON p.korisnik_id = u.id
    LEFT JOIN sticenike s ON p.sticanik_id = s.id
    WHERE p.id = ? AND p.status = 'zavrsena'
", [$posetaId]);

if (!$poseta) {
    header('Location: posete.php');
    exit;
}

// Get current services for this visit
$trenutneUsluge = $db->fetchAll("
    SELECT usluga_id 
    FROM poseta_usluge 
    WHERE poseta_id = ?
", [$posetaId]);
$trenutneUslugeIds = array_column($trenutneUsluge, 'usluga_id');

// Get all available services
$sveUsluge = $db->fetchAll("SELECT id, naziv FROM usluge ORDER BY naziv");

// Handle form submission
if ($_POST) {
    try {
        $db->getConnection()->beginTransaction();
        
        $napomene = trim($_POST['napomene'] ?? '');
        $selectedUsluge = $_POST['usluge'] ?? [];
        
        // Update visit notes
        $updateData = [
            'napomene' => $napomene,
            'sinhronizovano' => 1
        ];
        
        if (!$db->update('posete', $updateData, 'id = ?', [$posetaId])) {
            throw new Exception('Greška prilikom ažuriranja posete.');
        }
        
        // Update services - remove all and add selected
        $db->delete('poseta_usluge', 'poseta_id = ?', [$posetaId]);
        
        if (!empty($selectedUsluge)) {
            foreach ($selectedUsluge as $uslugaId) {
                $db->insert('poseta_usluge', [
                    'poseta_id' => $posetaId,
                    'usluga_id' => (int)$uslugaId
                ]);
            }
        }
        
        $db->getConnection()->commit();
        $success = 'Poseta je uspešno ažurirana.';
        
        // Refresh current services
        $trenutneUsluge = $db->fetchAll("
            SELECT usluga_id 
            FROM poseta_usluge 
            WHERE poseta_id = ?
        ", [$posetaId]);
        $trenutneUslugeIds = array_column($trenutneUsluge, 'usluga_id');
        
        // Update notes in our poseta array
        $poseta['napomene'] = $napomene;
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = 'Došlo je do greške: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Izmena posete - CAP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
</head>
<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Izmena posete</h1>
            <div class="space-x-2">
                <a href="view_poseta.php?id=<?php echo $poseta['id']; ?>" class="btn-secondary">
                    Pregled
                </a>
                <a href="posete.php" class="btn-secondary">
                    Nazad na listu
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <div class="text-red-800"><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                <div class="text-green-800"><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Visit Info (Read-only) -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Informacije o poseti</h2>
                
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="font-medium text-gray-600">Status:</span>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full <?php echo getStatusBadgeClass($poseta['status']); ?>">
                            <?php echo getStatusText($poseta['status']); ?>
                        </span>
                    </div>
                    
                    <div>
                        <span class="font-medium text-gray-600">Datum:</span>
                        <span class="ml-2"><?php echo formatDate($poseta['datum_posete']); ?></span>
                    </div>
                    
                    <?php if ($poseta['vreme_pocetka'] && $poseta['vreme_pocetka'] !== '00:00:00'): ?>
                    <div>
                        <span class="font-medium text-gray-600">Početak:</span>
                        <span class="ml-2"><?php echo formatTime($poseta['vreme_pocetka']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($poseta['vreme_kraja'] && $poseta['vreme_kraja'] !== '00:00:00'): ?>
                    <div>
                        <span class="font-medium text-gray-600">Kraj:</span>
                        <span class="ml-2"><?php echo formatTime($poseta['vreme_kraja']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($poseta['ukupno_vreme']): ?>
                    <div>
                        <span class="font-medium text-gray-600">Trajanje:</span>
                        <span class="ml-2"><?php echo minutesToHours($poseta['ukupno_vreme']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <span class="font-medium text-gray-600">Radnik:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($poseta['korisnik_ime']); ?></span>
                    </div>
                    
                    <div>
                        <span class="font-medium text-gray-600">Štićenik:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($poseta['sticanik_ime']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Izmena podataka</h2>
                
                <form method="POST">
                    <!-- Services Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            Obavljene usluge
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-4">
                            <?php foreach ($sveUsluge as $usluga): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           name="usluge[]" 
                                           value="<?php echo $usluga['id']; ?>"
                                           <?php echo in_array($usluga['id'], $trenutneUslugeIds) ? 'checked' : ''; ?>
                                           class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm"><?php echo htmlspecialchars($usluga['naziv']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Možete da označite sve usluge koje su obavljene tokom ove posete.
                        </p>
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-6">
                        <label for="napomene" class="block text-sm font-medium text-gray-700 mb-2">
                            Napomene
                        </label>
                        <textarea id="napomene" 
                                  name="napomene" 
                                  rows="6"
                                  placeholder="Dodatne napomene o poseti..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($poseta['napomene'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-end space-x-3">
                        <a href="view_poseta.php?id=<?php echo $poseta['id']; ?>" class="btn-secondary">
                            Otkaži
                        </a>
                        <button type="submit" class="btn-primary">
                            Sačuvaj izmene
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>