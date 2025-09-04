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

if (!$posetaId) {
    header('Location: posete.php');
    exit;
}

// Get visit details with all related data
$poseta = $db->fetchOne("
    SELECT p.*, 
           u.ime_prezime as korisnik_ime, u.tip_korisnika,
           s.ime_prezime as sticanik_ime, s.adresa as sticanik_adresa, s.telefon as sticanik_telefon
    FROM posete p
    LEFT JOIN users u ON p.korisnik_id = u.id
    LEFT JOIN sticenike s ON p.sticanik_id = s.id
    WHERE p.id = ?
", [$posetaId]);

if (!$poseta) {
    header('Location: posete.php');
    exit;
}

// Get services for this visit
$usluge = $db->fetchAll("
    SELECT u.naziv 
    FROM poseta_usluge pu
    JOIN usluge u ON pu.usluga_id = u.id
    WHERE pu.poseta_id = ?
    ORDER BY u.naziv
", [$posetaId]);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pregled posete - CAP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
</head>
<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Pregled posete</h1>
            <div class="space-x-2">
                <?php if ($poseta['status'] === 'zavrsena'): ?>
                    <a href="edit_poseta.php?id=<?php echo $poseta['id']; ?>" class="btn-primary">
                        Uredi posetu
                    </a>
                <?php endif; ?>
                <a href="posete.php" class="btn-secondary">
                    Nazad na listu
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Osnovne informacije</h2>
                
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Status posete:</label>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full <?php echo getStatusBadgeClass($poseta['status']); ?>">
                            <?php echo getStatusText($poseta['status']); ?>
                        </span>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">Datum posete:</label>
                        <span class="ml-2 text-gray-900"><?php echo formatDate($poseta['datum_posete']); ?></span>
                    </div>
                    
                    <?php if ($poseta['vreme_pocetka'] && $poseta['vreme_pocetka'] !== '00:00:00'): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Vreme početka:</label>
                        <span class="ml-2 text-gray-900"><?php echo formatTime($poseta['vreme_pocetka']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($poseta['vreme_kraja'] && $poseta['vreme_kraja'] !== '00:00:00'): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Vreme kraja:</label>
                        <span class="ml-2 text-gray-900"><?php echo formatTime($poseta['vreme_kraja']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($poseta['ukupno_vreme']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Ukupno vreme:</label>
                        <span class="ml-2 text-gray-900"><?php echo minutesToHours($poseta['ukupno_vreme']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">Kreirana:</label>
                        <span class="ml-2 text-gray-900"><?php echo formatDateTime($poseta['created_at']); ?></span>
                    </div>
                    
                    <?php if ($poseta['updated_at'] !== $poseta['created_at']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Poslednja izmena:</label>
                        <span class="ml-2 text-gray-900"><?php echo formatDateTime($poseta['updated_at']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- People Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Učesnici</h2>
                
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-2">Radnik/Volonter:</h3>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($poseta['korisnik_ime']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo ucfirst($poseta['tip_korisnika']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-2">Štićenik:</h3>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($poseta['sticanik_ime']); ?></div>
                            <?php if ($poseta['sticanik_adresa']): ?>
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($poseta['sticanik_adresa']); ?></div>
                            <?php endif; ?>
                            <?php if ($poseta['sticanik_telefon']): ?>
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($poseta['sticanik_telefon']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services -->
            <?php if (!empty($usluge)): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Obavljene usluge</h2>
                <div class="space-y-2">
                    <?php foreach ($usluge as $usluga): ?>
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-gray-700"><?php echo htmlspecialchars($usluga['naziv']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if ($poseta['napomene']): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Napomene</h2>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($poseta['napomene']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>