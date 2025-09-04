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
        $email = sanitizeInput($_POST['email']);
        $ime_prezime = sanitizeInput($_POST['ime_prezime']);
        $tip_korisnika = sanitizeInput($_POST['tip_korisnika']);
        $datum_rodjenja = $_POST['datum_rodjenja'] ?? null;
        $adresa = sanitizeInput($_POST['adresa'] ?? '');
        $grad = sanitizeInput($_POST['grad'] ?? '');
        $telefon = sanitizeInput($_POST['telefon'] ?? '');
        $strucna_sprema = sanitizeInput($_POST['strucna_sprema'] ?? '');
        
        $password = !empty($_POST['password']) ? sanitizeInput($_POST['password']) : generateRandomPassword();
        
        if (empty($email) || empty($ime_prezime) || empty($tip_korisnika)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $data = [
                'email' => $email,
                'password' => md5($password),
                'ime_prezime' => $ime_prezime,
                'tip_korisnika' => $tip_korisnika,
                'datum_rodjenja' => $datum_rodjenja ?: null,
                'adresa' => $adresa,
                'grad' => $grad,
                'telefon' => $telefon,
                'strucna_sprema' => ($tip_korisnika === 'radnik') ? $strucna_sprema : null
            ];
            
            if ($db->insert('users', $data)) {
                if (empty($_POST['password'])) {
                    $message = "Korisnik je uspešno dodat. Generisana lozinka: <strong>{$password}</strong>";
                } else {
                    $message = "Korisnik je uspešno dodat sa zadatom lozinkom.";
                }
            } else {
                $error = 'Greška prilikom dodavanja korisnika.';
            }
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $email = sanitizeInput($_POST['email']);
        $ime_prezime = sanitizeInput($_POST['ime_prezime']);
        $tip_korisnika = sanitizeInput($_POST['tip_korisnika']);
        $datum_rodjenja = $_POST['datum_rodjenja'] ?? null;
        $adresa = sanitizeInput($_POST['adresa'] ?? '');
        $grad = sanitizeInput($_POST['grad'] ?? '');
        $telefon = sanitizeInput($_POST['telefon'] ?? '');
        $strucna_sprema = sanitizeInput($_POST['strucna_sprema'] ?? '');
        $password = sanitizeInput($_POST['password'] ?? '');
        
        if (empty($email) || empty($ime_prezime) || empty($tip_korisnika)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $data = [
                'email' => $email,
                'ime_prezime' => $ime_prezime,
                'tip_korisnika' => $tip_korisnika,
                'datum_rodjenja' => $datum_rodjenja ?: null,
                'adresa' => $adresa,
                'grad' => $grad,
                'telefon' => $telefon,
                'strucna_sprema' => ($tip_korisnika === 'radnik') ? $strucna_sprema : null
            ];
            
            // Add password to update if provided
            if (!empty($password)) {
                $data['password'] = md5($password);
            }
            
            // Debug - remove this after testing
            error_log("Update data: " . print_r($data, true));
            error_log("Update where: id = {$id}");
            
            if ($db->update('users', $data, 'id = ?', [$id])) {
                $message = 'Korisnik je uspešno ažuriran.';
            } else {
                $error = 'Greška prilikom ažuriranja korisnika.';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id && $id !== $currentUser['id']) {
            if ($db->delete('users', 'id = ?', [$id])) {
                $message = 'Korisnik je uspešno obrisan.';
            } else {
                $error = 'Greška prilikom brisanja korisnika.';
            }
        }
    }
}

$korisnici = $db->fetchAll("SELECT * FROM users WHERE tip_korisnika != 'upravitelj' ORDER BY ime_prezime");
$strucneSpreme = getStrucnaSpremaSrbija();
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Korisnici - CAP Admin</title>
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
                        Korisnici
                    </h1>
                    <p class="text-gray-600">Upravljanje radnicima i volonterima</p>
                </div>
                <button onclick="showAddForm()" class="btn-primary">
                    Dodaj korisnika
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
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Dodaj novog korisnika</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                    <input type="email" name="email" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ime i prezime *</label>
                                    <input type="text" name="ime_prezime" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tip korisnika *</label>
                                    <select name="tip_korisnika" required class="form-input" onchange="toggleStrucnaSprama(this)">
                                        <option value="">Izaberite tip</option>
                                        <option value="radnik">Radnik</option>
                                        <option value="volonter">Volonter</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum rođenja</label>
                                    <input type="date" name="datum_rodjenja" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Grad</label>
                                    <input type="text" name="grad" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                                    <input type="text" name="telefon" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lozinka</label>
                                    <input type="password" name="password" class="form-input" placeholder="Ostavite prazno za auto-generisanu">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Adresa</label>
                                <textarea name="adresa" rows="2" class="form-input"></textarea>
                            </div>
                            
                            <div id="strucna_sprema_field" class="mb-4 hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stručna sprema</label>
                                <select name="strucna_sprema" class="form-input">
                                    <option value="">Izaberite stručnu spremu</option>
                                    <?php foreach ($strucneSpreme as $sprema): ?>
                                        <option value="<?php echo htmlspecialchars($sprema); ?>">
                                            <?php echo htmlspecialchars($sprema); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideAddForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Dodaj korisnika
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form Modal -->
            <div id="editForm" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Uredi korisnika</h3>
                        
                        <form method="POST" action="" id="editUserForm">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" id="edit_id">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                    <input type="email" name="email" id="edit_email" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ime i prezime *</label>
                                    <input type="text" name="ime_prezime" id="edit_ime_prezime" required class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tip korisnika *</label>
                                    <select name="tip_korisnika" id="edit_tip_korisnika" required class="form-input" onchange="toggleEditStrucnaSprama(this)">
                                        <option value="">Izaberite tip</option>
                                        <option value="radnik">Radnik</option>
                                        <option value="volonter">Volonter</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum rođenja</label>
                                    <input type="date" name="datum_rodjenja" id="edit_datum_rodjenja" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Grad</label>
                                    <input type="text" name="grad" id="edit_grad" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                                    <input type="text" name="telefon" id="edit_telefon" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nova lozinka</label>
                                    <input type="password" name="password" class="form-input" placeholder="Ostavite prazno da ne menjate">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Adresa</label>
                                <textarea name="adresa" id="edit_adresa" rows="2" class="form-input"></textarea>
                            </div>
                            
                            <div id="edit_strucna_sprema_field" class="mb-4 hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stručna sprema</label>
                                <select name="strucna_sprema" id="edit_strucna_sprema" class="form-input">
                                    <option value="">Izaberite stručnu spremu</option>
                                    <?php foreach ($strucneSpreme as $sprema): ?>
                                        <option value="<?php echo htmlspecialchars($sprema); ?>">
                                            <?php echo htmlspecialchars($sprema); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideEditForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Ažuriraj korisnika
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
                                <th>Email</th>
                                <th>Tip</th>
                                <th>Grad</th>
                                <th>Telefon</th>
                                <th>Stručna sprema</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($korisnici)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-gray-500 py-8">
                                    Nema registrovanih korisnika.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($korisnici as $korisnik): ?>
                                <tr>
                                    <td class="font-medium">
                                        <?php echo htmlspecialchars($korisnik['ime_prezime']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($korisnik['email']); ?></td>
                                    <td>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php echo $korisnik['tip_korisnika'] === 'radnik' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo ucfirst($korisnik['tip_korisnika']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($korisnik['grad'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($korisnik['telefon'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($korisnik['strucna_sprema'] ?? ''); ?></td>
                                    <td>
                                        <div class="flex items-center space-x-2">
                                            <button onclick="showEditForm(<?php echo htmlspecialchars(json_encode($korisnik)); ?>)" 
                                                    class="text-green-600 hover:text-green-800 text-sm"
                                                    title="Uredi korisnika">
                                                ✏️
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovog korisnika?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $korisnik['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm"
                                                        title="Obriši korisnika">
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
        
        function showEditForm(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_ime_prezime').value = user.ime_prezime;
            document.getElementById('edit_tip_korisnika').value = user.tip_korisnika;
            document.getElementById('edit_datum_rodjenja').value = user.datum_rodjenja;
            document.getElementById('edit_grad').value = user.grad || '';
            document.getElementById('edit_telefon').value = user.telefon || '';
            document.getElementById('edit_adresa').value = user.adresa || '';
            document.getElementById('edit_strucna_sprema').value = user.strucna_sprema || '';
            
            toggleEditStrucnaSprama(document.getElementById('edit_tip_korisnika'));
            document.getElementById('editForm').classList.remove('hidden');
        }
        
        function hideEditForm() {
            document.getElementById('editForm').classList.add('hidden');
        }
        
        function toggleStrucnaSprama(select) {
            const field = document.getElementById('strucna_sprema_field');
            if (select.value === 'radnik') {
                field.classList.remove('hidden');
            } else {
                field.classList.add('hidden');
            }
        }
        
        function toggleEditStrucnaSprama(select) {
            const field = document.getElementById('edit_strucna_sprema_field');
            if (select.value === 'radnik') {
                field.classList.remove('hidden');
            } else {
                field.classList.add('hidden');
            }
        }
    </script>
</body>
</html>