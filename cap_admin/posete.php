<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

// Handle messages from redirects
$message = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'bulk_delete') {
        $selected_posete = $_POST['selected_posete'] ?? [];
        
        if (!empty($selected_posete)) {
            try {
                $db->getConnection()->beginTransaction();
                
                // Delete related poseta_usluge first (foreign key constraint)
                $placeholders = str_repeat('?,', count($selected_posete) - 1) . '?';
                $db->query("DELETE FROM poseta_usluge WHERE poseta_id IN ($placeholders)", $selected_posete);
                
                // Delete posete
                $deletedCount = 0;
                foreach ($selected_posete as $poseta_id) {
                    $result = $db->delete('posete', 'id = ?', [(int)$poseta_id]);
                    if ($result) $deletedCount++;
                }
                
                $db->getConnection()->commit();
                $message = "Uspešno obrisano $deletedCount poseta.";
                
                // POST-Redirect-GET pattern
                header("Location: posete.php?msg=" . urlencode($message));
                exit;
                
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $error = "Greška prilikom brisanja poseta: " . $e->getMessage();
                header("Location: posete.php?error=" . urlencode($error));
                exit;
            }
        } else {
            $error = "Niste označili nijednu posetu za brisanje.";
            header("Location: posete.php?error=" . urlencode($error));
            exit;
        }
    } elseif ($action === 'generate_posete') {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        if (empty($start_date) || empty($end_date)) {
            $error = 'Molimo izaberite datum početka i kraja.';
        } else {
            // Generate visits based on frequency for each sticanik
            $sticenike = $db->fetchAll("
                SELECT s.id, s.ime_prezime, uo.dani_nedelje, 
                       GROUP_CONCAT(ks.korisnik_id, ',') as assigned_users
                FROM sticenike s
                LEFT JOIN ucestalost_odlaska uo ON s.ucestalost_odlaska_id = uo.id
                LEFT JOIN korisnik_sticenike ks ON s.id = ks.sticanik_id
                WHERE uo.dani_nedelje IS NOT NULL
                GROUP BY s.id
            ");
            
            $generated = 0;
            $current_date = new DateTime($start_date);
            $end_date_obj = new DateTime($end_date);
            
            while ($current_date <= $end_date_obj) {
                $day_of_week = $current_date->format('N'); // 1=Monday, 7=Sunday
                
                foreach ($sticenike as $sticanik) {
                    $dani_nedelje = json_decode($sticanik['dani_nedelje'], true);
                    
                    if (in_array($day_of_week, $dani_nedelje)) {
                        $assigned_users = explode(',', $sticanik['assigned_users']);
                        
                        foreach ($assigned_users as $user_id) {
                            if (empty($user_id)) continue;
                            
                            // Check if visit already exists
                            $existing = $db->fetchOne("
                                SELECT id FROM posete 
                                WHERE korisnik_id = ? AND sticanik_id = ? AND datum_posete = ?
                            ", [$user_id, $sticanik['id'], $current_date->format('Y-m-d')]);
                            
                            if (!$existing) {
                                $visit_data = [
                                    'korisnik_id' => $user_id,
                                    'sticanik_id' => $sticanik['id'],
                                    'datum_posete' => $current_date->format('Y-m-d'),
                                    'vreme_pocetka' => '09:00:00', // Default start time
                                    'status' => 'zakazana'
                                ];
                                
                                if ($db->insert('posete', $visit_data)) {
                                    $generated++;
                                }
                            }
                        }
                    }
                }
                
                $current_date->add(new DateInterval('P1D'));
            }
            
            $message = "Generisano je {$generated} novih poseta.";
        }
        
        // POST-Redirect-GET pattern to prevent re-submission
        header("Location: posete.php?msg=" . urlencode($message));
        exit;
    }
    
    if ($action === 'add_individual') {
        $korisnik_id = (int)$_POST['korisnik_id'];
        $sticanik_id = (int)$_POST['sticanik_id'];
        $datum_posete = $_POST['datum_posete'];
        $vreme_pocetka = $_POST['vreme_pocetka'];
        
        if (empty($korisnik_id) || empty($sticanik_id) || empty($datum_posete) || empty($vreme_pocetka)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $visit_data = [
                'korisnik_id' => $korisnik_id,
                'sticanik_id' => $sticanik_id,
                'datum_posete' => $datum_posete,
                'vreme_pocetka' => $vreme_pocetka,
                'status' => 'zakazana'
            ];
            
            if ($db->insert('posete', $visit_data)) {
                $message = 'Poseta je uspešno zakazana.';
                header("Location: posete.php?msg=" . urlencode($message));
                exit;
            } else {
                $error = 'Greška prilikom zakazivanja posete.';
                header("Location: posete.php?error=" . urlencode($error));
                exit;
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            if ($db->delete('posete', 'id = ?', [$id])) {
                $message = 'Poseta je uspešno obrisana.';
                header("Location: posete.php?msg=" . urlencode($message));
                exit;
            } else {
                $error = 'Greška prilikom brisanja posete.';
                header("Location: posete.php?error=" . urlencode($error));
                exit;
            }
        }
    }
}

// Get filter parameters
$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_korisnik = $_GET['filter_korisnik'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($filter_date) {
    $where_conditions[] = "DATE(p.datum_posete) = ?";
    $params[] = $filter_date;
}

if ($filter_status) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if ($filter_korisnik) {
    $where_conditions[] = "p.korisnik_id = ?";
    $params[] = $filter_korisnik;
}

$where_clause = empty($where_conditions) ? "1=1" : implode(" AND ", $where_conditions);

$posete = $db->fetchAll("
    SELECT p.*, u.ime_prezime as korisnik_ime, s.ime_prezime as sticanik_ime, s.adresa as sticanik_adresa,
           (SELECT COUNT(*) FROM posete p2 WHERE p2.korisnik_id = p.korisnik_id AND p2.sticanik_id = p.sticanik_id) as ukupno_poseta,
           (SELECT MIN(datum_posete) FROM posete p3 WHERE p3.korisnik_id = p.korisnik_id AND p3.sticanik_id = p.sticanik_id) as prva_poseta,
           (SELECT MAX(datum_posete) FROM posete p4 WHERE p4.korisnik_id = p.korisnik_id AND p4.sticanik_id = p.sticanik_id) as poslednja_poseta,
           (SELECT COUNT(*) FROM posete p5 WHERE p5.korisnik_id = p.korisnik_id AND p5.sticanik_id = p.sticanik_id AND p5.status = 'zakazana') as zakazane_posete,
           (SELECT COUNT(*) FROM posete p6 WHERE p6.korisnik_id = p.korisnik_id AND p6.sticanik_id = p.sticanik_id AND p6.status = 'zavrsena') as zavrsene_posete
    FROM posete p
    LEFT JOIN users u ON p.korisnik_id = u.id
    LEFT JOIN sticenike s ON p.sticanik_id = s.id
    WHERE {$where_clause}
    ORDER BY p.datum_posete DESC, p.vreme_pocetka ASC
", $params);

$korisnici = $db->fetchAll("SELECT * FROM users WHERE tip_korisnika IN ('radnik', 'volonter') ORDER BY ime_prezime");
$sticenike = $db->fetchAll("SELECT * FROM sticenike ORDER BY ime_prezime");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posete - CAP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
    <style>
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 300px;
            background-color: #1f2937;
            color: white;
            text-align: left;
            border-radius: 8px;
            padding: 16px;
            position: fixed;
            z-index: 9999;
            top: auto;
            left: auto;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.875rem;
            line-height: 1.5;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid #374151;
            pointer-events: none;
        }
        
        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }
        
        
        .tooltip-trigger {
            cursor: help;
            text-decoration: underline;
            text-decoration-style: dotted;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2" style="font-family: 'Poppins', sans-serif;">
                        Posete
                    </h1>
                    <p class="text-gray-600">Upravljanje posetama štićenicima</p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="showGenerateForm()" class="btn-primary">
                        Generiši posete
                    </button>
                    <button onclick="showIndividualForm()" class="btn-secondary">
                        Dodaj individualnu posetu
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <?php showAlert($message, 'success'); ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <?php showAlert($error, 'error'); ?>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Filteri</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
                        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="form-input">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="filter_status" class="form-input">
                            <option value="">Svi statusi</option>
                            <option value="zakazana" <?php echo $filter_status === 'zakazana' ? 'selected' : ''; ?>>Zakazana</option>
                            <option value="u_toku" <?php echo $filter_status === 'u_toku' ? 'selected' : ''; ?>>U toku</option>
                            <option value="zavrsena" <?php echo $filter_status === 'zavrsena' ? 'selected' : ''; ?>>Završena</option>
                            <option value="otkazana" <?php echo $filter_status === 'otkazana' ? 'selected' : ''; ?>>Otkazana</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Radnik/Volonter</label>
                        <select name="filter_korisnik" class="form-input">
                            <option value="">Svi korisnici</option>
                            <?php foreach ($korisnici as $korisnik): ?>
                                <option value="<?php echo $korisnik['id']; ?>" <?php echo $filter_korisnik == $korisnik['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($korisnik['ime_prezime']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary w-full">Filtriraj</button>
                    </div>
                </form>
            </div>
            
            <!-- Generate Visits Modal -->
            <div id="generateForm" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Generiši posete po rasporedu</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="generate_posete">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum početka *</label>
                                    <input type="date" name="start_date" required class="form-input" value="">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum kraja *</label>
                                    <input type="date" name="end_date" required class="form-input" value="">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">
                                    Sistem će generisati posete za sve štićenike koji imaju definisanu učestalost odlaska, 
                                    u periodu između zadatih datuma. Postojeće posete neće biti duplicirane.
                                </p>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideGenerateForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Generiši posete
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Individual Visit Modal -->
            <div id="individualForm" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Dodaj individualnu posetu</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_individual">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Radnik/Volonter *</label>
                                    <select name="korisnik_id" required class="form-input">
                                        <option value="">Izaberite korisnika</option>
                                        <?php foreach ($korisnici as $korisnik): ?>
                                            <option value="<?php echo $korisnik['id']; ?>">
                                                <?php echo htmlspecialchars($korisnik['ime_prezime']); ?> (<?php echo ucfirst($korisnik['tip_korisnika']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Štićenik *</label>
                                    <select name="sticanik_id" required class="form-input">
                                        <option value="">Izaberite štićenika</option>
                                        <?php foreach ($sticenike as $sticanik): ?>
                                            <option value="<?php echo $sticanik['id']; ?>">
                                                <?php echo htmlspecialchars($sticanik['ime_prezime']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum posete *</label>
                                    <input type="date" name="datum_posete" required class="form-input" value="">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Vreme početka *</label>
                                    <input type="time" name="vreme_pocetka" required class="form-input" value="09:00">
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideIndividualForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Zakaži posetu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div id="bulkActions" class="card mb-4 bg-blue-50 border-blue-200 hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span class="text-blue-700 font-medium">
                            <span id="selectedCount">0</span> poseta označeno
                        </span>
                    </div>
                    <div class="flex space-x-2">
                        <button type="button" onclick="bulkDelete()" class="btn-danger text-sm px-3 py-1">
                            🗑️ Obriši označene
                        </button>
                        <button type="button" onclick="clearSelection()" class="btn-secondary text-sm px-3 py-1">
                            Poništi označavanje
                        </button>
                    </div>
                </div>
            </div>

            <!-- Visits List -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th>Datum</th>
                                <th>Vreme</th>
                                <th>Radnik/Volonter</th>
                                <th>Štićenik</th>
                                <th>Adresa</th>
                                <th>Status</th>
                                <th>Trajanje</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($posete)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-gray-500 py-8">
                                    Nema poseta za zadati filter.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($posete as $poseta): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_posete[]" value="<?php echo $poseta['id']; ?>" 
                                               class="poseta-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="font-medium">
                                        <?php echo formatDate($poseta['datum_posete']); ?>
                                    </td>
                                    <td>
                                        <?php echo formatTime($poseta['vreme_pocetka']); ?>
                                        <?php if ($poseta['vreme_kraja']): ?>
                                            <br><small class="text-gray-500">do <?php echo formatTime($poseta['vreme_kraja']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $korisnik_stats = $db->fetchOne("
                                            SELECT COUNT(*) as ukupno_poseta_korisnik,
                                                   COUNT(CASE WHEN status = 'zavrsena' THEN 1 END) as zavrsene_korisnik,
                                                   COUNT(CASE WHEN status = 'zakazana' THEN 1 END) as zakazane_korisnik,
                                                   SUM(ukupno_vreme) as ukupno_vreme_korisnik
                                            FROM posete WHERE korisnik_id = ?
                                        ", [$poseta['korisnik_id']]);
                                        ?>
                                        <div class="tooltip">
                                            <span class="tooltip-trigger"><?php echo htmlspecialchars($poseta['korisnik_ime'] ?? 'N/A'); ?></span>
                                            <div class="tooltiptext">
                                                <div class="font-semibold text-blue-200 mb-2">👤 Statistike radnika</div>
                                                <div class="space-y-1">
                                                    <div><strong>Ukupno poseta:</strong> <?php echo $korisnik_stats['ukupno_poseta_korisnik']; ?></div>
                                                    <div><strong>Zakazane:</strong> <?php echo $korisnik_stats['zakazane_korisnik']; ?></div>
                                                    <div><strong>Završene:</strong> <?php echo $korisnik_stats['zavrsene_korisnik']; ?></div>
                                                    <div><strong>Ukupno vreme:</strong> <?php echo minutesToHours($korisnik_stats['ukupno_vreme_korisnik'] ?? 0); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tooltip">
                                            <span class="tooltip-trigger"><?php echo htmlspecialchars($poseta['sticanik_ime'] ?? 'N/A'); ?></span>
                                            <div class="tooltiptext">
                                                <div class="font-semibold text-yellow-200 mb-2">📊 Statistike poseta</div>
                                                <div class="space-y-1">
                                                    <div><strong>Ukupno poseta:</strong> <?php echo $poseta['ukupno_poseta']; ?></div>
                                                    <div><strong>Zakazane:</strong> <?php echo $poseta['zakazane_posete']; ?></div>
                                                    <div><strong>Završene:</strong> <?php echo $poseta['zavrsene_posete']; ?></div>
                                                </div>
                                                <hr class="border-gray-500 my-2">
                                                <div class="space-y-1">
                                                    <div><strong>Prva poseta:</strong><br><?php echo formatDate($poseta['prva_poseta']); ?></div>
                                                    <div><strong>Poslednja:</strong><br><?php echo formatDate($poseta['poslednja_poseta']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="max-w-xs">
                                        <div class="text-sm text-gray-600 truncate">
                                            <?php echo htmlspecialchars($poseta['sticanik_adresa'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'zakazana' => 'bg-blue-100 text-blue-800',
                                            'u_toku' => 'bg-yellow-100 text-yellow-800',
                                            'zavrsena' => 'bg-green-100 text-green-800',
                                            'otkazana' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusLabels = [
                                            'zakazana' => 'Zakazana',
                                            'u_toku' => 'U toku',
                                            'zavrsena' => 'Završena',
                                            'otkazana' => 'Otkazana'
                                        ];
                                        $colorClass = $statusColors[$poseta['status']] ?? 'bg-gray-100 text-gray-800';
                                        $label = $statusLabels[$poseta['status']] ?? $poseta['status'];
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $colorClass; ?>">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($poseta['ukupno_vreme']): ?>
                                            <?php echo minutesToHours($poseta['ukupno_vreme']); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($poseta['status'] === 'zavrsena'): ?>
                                                <a href="view_poseta.php?id=<?php echo $poseta['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-800 text-sm" 
                                                   title="Pregled posete">
                                                    👁️
                                                </a>
                                                <a href="edit_poseta.php?id=<?php echo $poseta['id']; ?>" 
                                                   class="text-green-600 hover:text-green-800 text-sm" 
                                                   title="Uredi posetu">
                                                    ✏️
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($poseta['status'] === 'zakazana'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovu posetu?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $poseta['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                                    Obriši
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function showGenerateForm() {
            document.getElementById('generateForm').classList.remove('hidden');
        }
        
        function hideGenerateForm() {
            document.getElementById('generateForm').classList.add('hidden');
        }
        
        function showIndividualForm() {
            document.getElementById('individualForm').classList.remove('hidden');
        }
        
        function hideIndividualForm() {
            document.getElementById('individualForm').classList.add('hidden');
        }
        
        // Enhanced tooltip positioning
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('.tooltip');
            
            tooltips.forEach(tooltip => {
                const trigger = tooltip.querySelector('.tooltip-trigger');
                const tooltipText = tooltip.querySelector('.tooltiptext');
                
                if (!trigger || !tooltipText) return;
                
                trigger.addEventListener('mouseenter', function(e) {
                    const rect = trigger.getBoundingClientRect();
                    const tooltipRect = tooltipText.getBoundingClientRect();
                    
                    // Calculate position
                    let left = rect.left + (rect.width / 2) - 150; // Center tooltip
                    let top = rect.top - tooltipRect.height - 10; // Above the trigger
                    
                    // Keep tooltip within viewport
                    if (left < 10) left = 10;
                    if (left + 300 > window.innerWidth) left = window.innerWidth - 310;
                    
                    // If tooltip would go above viewport, show it below
                    if (top < 10) {
                        top = rect.bottom + 10;
                    }
                    
                    tooltipText.style.left = left + 'px';
                    tooltipText.style.top = top + 'px';
                    tooltipText.style.visibility = 'visible';
                    tooltipText.style.opacity = '1';
                });
                
                trigger.addEventListener('mouseleave', function() {
                    tooltipText.style.visibility = 'hidden';
                    tooltipText.style.opacity = '0';
                });
            });
        });

        // Bulk selection functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const posetaCheckboxes = document.querySelectorAll('.poseta-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        // Select All functionality
        selectAllCheckbox.addEventListener('change', function() {
            posetaCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });

        // Individual checkbox functionality
        posetaCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkActions();
                
                // Update Select All state
                const checkedCount = document.querySelectorAll('.poseta-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === posetaCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < posetaCheckboxes.length;
            });
        });

        function updateBulkActions() {
            const checkedCount = document.querySelectorAll('.poseta-checkbox:checked').length;
            selectedCount.textContent = checkedCount;
            
            if (checkedCount > 0) {
                bulkActions.classList.remove('hidden');
            } else {
                bulkActions.classList.add('hidden');
            }
        }

        function clearSelection() {
            posetaCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
            updateBulkActions();
        }

        function bulkDelete() {
            const checkedBoxes = document.querySelectorAll('.poseta-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Niste označili nijednu posetu za brisanje.');
                return;
            }
            
            const count = checkedBoxes.length;
            if (confirm(`Da li ste sigurni da želite da obrišete ${count} označenih poseta?\\n\\nOva akcija se ne može poništiti!`)) {
                const selectedIds = Array.from(checkedBoxes).map(checkbox => checkbox.value);
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);
                
                selectedIds.forEach(id => {
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'selected_posete[]';
                    idInput.value = id;
                    form.appendChild(idInput);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>