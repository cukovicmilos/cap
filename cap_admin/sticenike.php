<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $ime_prezime = sanitizeInput($_POST['ime_prezime']);
        $datum_rodjenja = $_POST['datum_rodjenja'];
        $adresa = sanitizeInput($_POST['adresa']);
        $grad = sanitizeInput($_POST['grad']);
        $telefon = sanitizeInput($_POST['telefon'] ?? '');
        $penzioner = isset($_POST['penzioner']) ? 1 : 0;
        $nivo_invaliditeta_id = (int)$_POST['nivo_invaliditeta_id'] ?: null;
        $ucestalost_odlaska_id = (int)$_POST['ucestalost_odlaska_id'] ?: null;
        $placa_participaciju = isset($_POST['placa_participaciju']) ? 1 : 0;
        $kako_je_postao_korisnik = sanitizeInput($_POST['kako_je_postao_korisnik'] ?? '');
        $usluge = $_POST['usluge'] ?? [];
        $dodeljeni_korisnici = $_POST['dodeljeni_korisnici'] ?? [];
        
        if (empty($ime_prezime) || empty($datum_rodjenja) || empty($adresa) || empty($grad)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $db->getConnection()->beginTransaction();
            
            try {
                $data = [
                    'ime_prezime' => $ime_prezime,
                    'datum_rodjenja' => $datum_rodjenja,
                    'adresa' => $adresa,
                    'grad' => $grad,
                    'telefon' => $telefon,
                    'penzioner' => $penzioner,
                    'nivo_invaliditeta_id' => $nivo_invaliditeta_id,
                    'ucestalost_odlaska_id' => $ucestalost_odlaska_id,
                    'placa_participaciju' => $placa_participaciju,
                    'kako_je_postao_korisnik' => $kako_je_postao_korisnik
                ];
                
                $sticanik_id = $db->insert('sticenike', $data);
                
                if ($sticanik_id && !empty($usluge)) {
                    foreach ($usluge as $usluga_id) {
                        $db->insert('sticanik_usluge', [
                            'sticanik_id' => $sticanik_id,
                            'usluga_id' => $usluga_id
                        ]);
                    }
                }
                
                if ($sticanik_id && !empty($dodeljeni_korisnici)) {
                    foreach ($dodeljeni_korisnici as $korisnik_id) {
                        $db->insert('korisnik_sticenike', [
                            'korisnik_id' => $korisnik_id,
                            'sticanik_id' => $sticanik_id
                        ]);
                    }
                }
                
                $db->getConnection()->commit();
                $message = 'Štićenik je uspešno dodat.';
                
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $error = 'Greška prilikom dodavanja štićenika.';
            }
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $ime_prezime = sanitizeInput($_POST['ime_prezime']);
        $datum_rodjenja = $_POST['datum_rodjenja'];
        $adresa = sanitizeInput($_POST['adresa']);
        $grad = sanitizeInput($_POST['grad']);
        $telefon = sanitizeInput($_POST['telefon'] ?? '');
        $penzioner = isset($_POST['penzioner']) ? 1 : 0;
        $nivo_invaliditeta_id = (int)$_POST['nivo_invaliditeta_id'] ?: null;
        $ucestalost_odlaska_id = (int)$_POST['ucestalost_odlaska_id'] ?: null;
        $placa_participaciju = isset($_POST['placa_participaciju']) ? 1 : 0;
        $kako_je_postao_korisnik = sanitizeInput($_POST['kako_je_postao_korisnik'] ?? '');
        $usluge = $_POST['usluge'] ?? [];
        $dodeljeni_korisnici = $_POST['dodeljeni_korisnici'] ?? [];
        
        if (empty($ime_prezime) || empty($datum_rodjenja) || empty($adresa) || empty($grad)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $db->getConnection()->beginTransaction();
            
            try {
                $data = [
                    'ime_prezime' => $ime_prezime,
                    'datum_rodjenja' => $datum_rodjenja,
                    'adresa' => $adresa,
                    'grad' => $grad,
                    'telefon' => $telefon,
                    'penzioner' => $penzioner,
                    'nivo_invaliditeta_id' => $nivo_invaliditeta_id,
                    'ucestalost_odlaska_id' => $ucestalost_odlaska_id,
                    'placa_participaciju' => $placa_participaciju,
                    'kako_je_postao_korisnik' => $kako_je_postao_korisnik
                ];
                
                if ($db->update('sticenike', $data, 'id = ?', [$id])) {
                    // Update usluge
                    $db->delete('sticanik_usluge', 'sticanik_id = ?', [$id]);
                    if (!empty($usluge)) {
                        foreach ($usluge as $usluga_id) {
                            $db->insert('sticanik_usluge', [
                                'sticanik_id' => $id,
                                'usluga_id' => $usluga_id
                            ]);
                        }
                    }
                    
                    // Update dodeljeni korisnici
                    $db->delete('korisnik_sticenike', 'sticanik_id = ?', [$id]);
                    if (!empty($dodeljeni_korisnici)) {
                        foreach ($dodeljeni_korisnici as $korisnik_id) {
                            $db->insert('korisnik_sticenike', [
                                'korisnik_id' => $korisnik_id,
                                'sticanik_id' => $id
                            ]);
                        }
                    }
                    
                    $db->getConnection()->commit();
                    $message = 'Štićenik je uspešno ažuriran.';
                } else {
                    throw new Exception('Update failed');
                }
                
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $error = 'Greška prilikom ažuriranja štićenika.';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            if ($db->delete('sticenike', 'id = ?', [$id])) {
                $message = 'Štićenik je uspešno obrisan.';
            } else {
                $error = 'Greška prilikom brisanja štićenika.';
            }
        }
    }
}

try {
    // Prvo pokušaj osnovni query
    $sticenike = $db->fetchAll("
        SELECT s.*, ni.naziv as nivo_invaliditeta, uo.naziv as ucestalost_odlaska
        FROM sticenike s
        LEFT JOIN nivoi_invaliditeta ni ON s.nivo_invaliditeta_id = ni.id
        LEFT JOIN ucestalost_odlaska uo ON s.ucestalost_odlaska_id = uo.id
        ORDER BY s.ime_prezime
    ");
    
    // Dodaj usluge i korisnike naknadno
    if ($sticenike) {
        for ($i = 0; $i < count($sticenike); $i++) {
            $usluge = $db->fetchAll("
                SELECT u.naziv 
                FROM sticanik_usluge su 
                JOIN usluge u ON su.usluga_id = u.id 
                WHERE su.sticanik_id = ?
            ", [$sticenike[$i]['id']]);
            $sticenike[$i]['usluge_names'] = implode(', ', array_column($usluge, 'naziv')) ?: 'Nema usluga';
            
            $korisnici = $db->fetchAll("
                SELECT DISTINCT us.ime_prezime 
                FROM korisnik_sticenike ks 
                JOIN users us ON ks.korisnik_id = us.id 
                WHERE ks.sticanik_id = ?
            ", [$sticenike[$i]['id']]);
            $sticenike[$i]['dodeljeni_radnici'] = implode(', ', array_column($korisnici, 'ime_prezime')) ?: 'Nije dodeljen';
        }
    }
    
    if (!$sticenike) {
        error_log("Sticenike query returned empty result");
        $sticenike = [];
    }
} catch (Exception $e) {
    error_log("Error fetching sticenike: " . $e->getMessage());
    $sticenike = [];
}

$usluge = $db->fetchAll("SELECT * FROM usluge ORDER BY naziv");
$nivoi_invaliditeta = $db->fetchAll("SELECT * FROM nivoi_invaliditeta ORDER BY naziv");
$ucestalost_odlaska = $db->fetchAll("SELECT * FROM ucestalost_odlaska ORDER BY naziv");
$korisnici = $db->fetchAll("SELECT * FROM users WHERE tip_korisnika IN ('radnik', 'volonter') ORDER BY ime_prezime");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Štićenici - CAP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2" style="font-family: 'Poppins', sans-serif;">
                        Štićenici
                    </h1>
                    <p class="text-gray-600">Upravljanje štićenicima</p>
                </div>
                <button onclick="showAddForm()" class="btn-primary">
                    Dodaj štićenika
                </button>
            </div>
            
            <?php if ($message): ?>
                <?php showAlert($message, 'success'); ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <?php showAlert($error, 'error'); ?>
            <?php endif; ?>
            
            <!-- Add Form Modal -->
            <div id="addForm" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Dodaj novog štićenika</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ime i prezime *</label>
                                    <input type="text" name="ime_prezime" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum rođenja *</label>
                                    <input type="date" name="datum_rodjenja" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Grad *</label>
                                    <input type="text" name="grad" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                                    <input type="text" name="telefon" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nivo invaliditeta</label>
                                    <select name="nivo_invaliditeta_id" class="form-input">
                                        <option value="">Izaberite nivo</option>
                                        <?php foreach ($nivoi_invaliditeta as $nivo): ?>
                                            <option value="<?php echo $nivo['id']; ?>">
                                                <?php echo htmlspecialchars($nivo['naziv']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Učestalost odlaska</label>
                                    <select name="ucestalost_odlaska_id" class="form-input">
                                        <option value="">Izaberite učestalost</option>
                                        <?php foreach ($ucestalost_odlaska as $ucestalost): ?>
                                            <option value="<?php echo $ucestalost['id']; ?>">
                                                <?php echo htmlspecialchars($ucestalost['naziv']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Adresa *</label>
                                <textarea name="adresa" rows="2" required class="form-input"></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Usluge koje se pružaju</label>
                                    <div class="space-y-2 max-h-32 overflow-y-auto border rounded p-2">
                                        <?php foreach ($usluge as $usluga): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="usluge[]" value="<?php echo $usluga['id']; ?>" class="mr-2">
                                            <?php echo htmlspecialchars($usluga['naziv']); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Dodeljeni radnici/volonteri</label>
                                    <div class="space-y-2 max-h-32 overflow-y-auto border rounded p-2">
                                        <?php foreach ($korisnici as $korisnik): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="dodeljeni_korisnici[]" value="<?php echo $korisnik['id']; ?>" class="mr-2">
                                            <?php echo htmlspecialchars($korisnik['ime_prezime']); ?>
                                            <span class="text-xs text-gray-500 ml-1">(<?php echo ucfirst($korisnik['tip_korisnika']); ?>)</span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="penzioner" class="mr-2">
                                        Penzioner
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="placa_participaciju" class="mr-2">
                                        Plaća participaciju
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kako je postao korisnik</label>
                                    <textarea name="kako_je_postao_korisnik" rows="3" class="form-input"></textarea>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideAddForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Dodaj štićenika
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form Modal -->
            <div id="editSticanikForm" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Uredi štićenika</h3>
                        
                        <form method="POST" action="" id="editSticanikFormElement">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" id="edit_sticanik_id">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ime i prezime *</label>
                                    <input type="text" name="ime_prezime" id="edit_sticanik_ime_prezime" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum rođenja *</label>
                                    <input type="date" name="datum_rodjenja" id="edit_sticanik_datum_rodjenja" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Grad *</label>
                                    <input type="text" name="grad" id="edit_sticanik_grad" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                                    <input type="text" name="telefon" id="edit_sticanik_telefon" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nivo invaliditeta</label>
                                    <select name="nivo_invaliditeta_id" id="edit_sticanik_nivo_invaliditeta_id" class="form-input">
                                        <option value="">Izaberite nivo</option>
                                        <?php foreach ($nivoi_invaliditeta as $nivo): ?>
                                            <option value="<?php echo $nivo['id']; ?>">
                                                <?php echo htmlspecialchars($nivo['naziv']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Učestalost odlaska</label>
                                    <select name="ucestalost_odlaska_id" id="edit_sticanik_ucestalost_odlaska_id" class="form-input">
                                        <option value="">Izaberite učestalost</option>
                                        <?php foreach ($ucestalost_odlaska as $ucestalost): ?>
                                            <option value="<?php echo $ucestalost['id']; ?>">
                                                <?php echo htmlspecialchars($ucestalost['naziv']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Adresa *</label>
                                <textarea name="adresa" id="edit_sticanik_adresa" rows="2" required class="form-input"></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Usluge koje se pružaju</label>
                                    <div id="edit_usluge_list" class="space-y-2 max-h-32 overflow-y-auto border rounded p-2">
                                        <?php foreach ($usluge as $usluga): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="usluge[]" value="<?php echo $usluga['id']; ?>" class="mr-2 edit-usluga-checkbox">
                                            <?php echo htmlspecialchars($usluga['naziv']); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Dodeljeni radnici/volonteri</label>
                                    <div id="edit_korisnici_list" class="space-y-2 max-h-32 overflow-y-auto border rounded p-2">
                                        <?php foreach ($korisnici as $korisnik): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="dodeljeni_korisnici[]" value="<?php echo $korisnik['id']; ?>" class="mr-2 edit-korisnik-checkbox">
                                            <?php echo htmlspecialchars($korisnik['ime_prezime']); ?>
                                            <span class="text-xs text-gray-500 ml-1">(<?php echo ucfirst($korisnik['tip_korisnika']); ?>)</span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="penzioner" id="edit_sticanik_penzioner" class="mr-2">
                                        Penzioner
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="placa_participaciju" id="edit_sticanik_placa_participaciju" class="mr-2">
                                        Plaća participaciju
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kako je postao korisnik</label>
                                    <textarea name="kako_je_postao_korisnik" id="edit_sticanik_kako_je_postao_korisnik" rows="3" class="form-input"></textarea>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideEditSticanikForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Ažuriraj štićenika
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Users List -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ime i prezime</th>
                                <th>Datum rođenja</th>
                                <th>Grad</th>
                                <th>Telefon</th>
                                <th>Penzioner</th>
                                <th>Usluge</th>
                                <th>Dodeljeni radnici</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sticenike)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-gray-500 py-8">
                                    Nema registrovanih štićenika.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($sticenike as $sticanik): ?>
                                <tr>
                                    <td class="font-medium">
                                        <?php echo htmlspecialchars($sticanik['ime_prezime']); ?>
                                    </td>
                                    <td><?php echo formatDate($sticanik['datum_rodjenja']); ?></td>
                                    <td><?php echo htmlspecialchars($sticanik['grad']); ?></td>
                                    <td><?php echo htmlspecialchars($sticanik['telefon'] ?? ''); ?></td>
                                    <td>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php echo $sticanik['penzioner'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $sticanik['penzioner'] ? 'Da' : 'Ne'; ?>
                                        </span>
                                    </td>
                                    <td class="max-w-xs">
                                        <div class="text-sm text-gray-600 truncate">
                                            <?php echo htmlspecialchars($sticanik['usluge_names'] ?? 'Nema usluga'); ?>
                                        </div>
                                    </td>
                                    <td class="max-w-xs">
                                        <div class="text-sm text-gray-600 truncate">
                                            <?php echo htmlspecialchars($sticanik['dodeljeni_radnici'] ?? 'Nije dodeljen'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center space-x-2">
                                            <button onclick="showEditSticanikForm(<?php echo $sticanik['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-800 text-sm"
                                                    title="Uredi štićenika">
                                                ✏️
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovog štićenika?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $sticanik['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm"
                                                        title="Obriši štićenika">
                                                    🗑️
                                                </button>
                                            </form>
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
        function showAddForm() {
            document.getElementById('addForm').classList.remove('hidden');
        }
        
        function hideAddForm() {
            document.getElementById('addForm').classList.add('hidden');
        }
        
        async function showEditSticanikForm(sticanikId) {
            try {
                // Fetch sticanik data
                const response = await fetch(`api/get_sticanik.php?id=${sticanikId}`);
                const data = await response.json();
                
                if (data.success) {
                    const sticanik = data.sticanik;
                    
                    // Populate form fields
                    document.getElementById('edit_sticanik_id').value = sticanik.id;
                    document.getElementById('edit_sticanik_ime_prezime').value = sticanik.ime_prezime;
                    document.getElementById('edit_sticanik_datum_rodjenja').value = sticanik.datum_rodjenja;
                    document.getElementById('edit_sticanik_grad').value = sticanik.grad;
                    document.getElementById('edit_sticanik_telefon').value = sticanik.telefon || '';
                    document.getElementById('edit_sticanik_adresa').value = sticanik.adresa;
                    document.getElementById('edit_sticanik_nivo_invaliditeta_id').value = sticanik.nivo_invaliditeta_id || '';
                    document.getElementById('edit_sticanik_ucestalost_odlaska_id').value = sticanik.ucestalost_odlaska_id || '';
                    document.getElementById('edit_sticanik_penzioner').checked = sticanik.penzioner == 1;
                    document.getElementById('edit_sticanik_placa_participaciju').checked = sticanik.placa_participaciju == 1;
                    document.getElementById('edit_sticanik_kako_je_postao_korisnik').value = sticanik.kako_je_postao_korisnik || '';
                    
                    // Clear and set checkboxes for usluge
                    document.querySelectorAll('.edit-usluga-checkbox').forEach(cb => cb.checked = false);
                    if (data.usluge) {
                        data.usluge.forEach(uslugaId => {
                            const checkbox = document.querySelector(`.edit-usluga-checkbox[value="${uslugaId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    
                    // Clear and set checkboxes for korisnici
                    document.querySelectorAll('.edit-korisnik-checkbox').forEach(cb => cb.checked = false);
                    if (data.korisnici) {
                        data.korisnici.forEach(korisnikId => {
                            const checkbox = document.querySelector(`.edit-korisnik-checkbox[value="${korisnikId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    
                    // Show modal
                    document.getElementById('editSticanikForm').classList.remove('hidden');
                    
                } else {
                    alert('Greška pri učitavanju podataka štićenika');
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('Greška pri učitavanju podataka štićenika');
            }
        }
        
        function hideEditSticanikForm() {
            document.getElementById('editSticanikForm').classList.add('hidden');
        }
    </script>
</body>
</html>