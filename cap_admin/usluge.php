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
        $naziv = sanitizeInput($_POST['naziv']);
        $prosecno_vreme = (int)$_POST['prosecno_vreme'];
        
        if (empty($naziv) || empty($prosecno_vreme)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $data = [
                'naziv' => $naziv,
                'prosecno_vreme' => $prosecno_vreme
            ];
            
            if ($db->insert('usluge', $data)) {
                $message = 'Usluga je uspešno dodana.';
            } else {
                $error = 'Greška prilikom dodavanja usluge.';
            }
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $naziv = sanitizeInput($_POST['naziv']);
        $prosecno_vreme = (int)$_POST['prosecno_vreme'];
        
        if (empty($naziv) || empty($prosecno_vreme)) {
            $error = 'Molimo popunite sva obavezna polja.';
        } else {
            $data = [
                'naziv' => $naziv,
                'prosecno_vreme' => $prosecno_vreme
            ];
            
            if ($db->update('usluge', $data, 'id = ?', [$id])) {
                $message = 'Usluga je uspešno ažurirana.';
            } else {
                $error = 'Greška prilikom ažuriranja usluge.';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            // Check if usluga is used by any sticanik
            $used = $db->fetchOne("SELECT COUNT(*) as count FROM sticanik_usluge WHERE usluga_id = ?", [$id]);
            
            if ($used['count'] > 0) {
                $error = 'Ne možete obrisati uslugu koja se koristi kod štićenika.';
            } else {
                if ($db->delete('usluge', 'id = ?', [$id])) {
                    $message = 'Usluga je uspešno obrisana.';
                } else {
                    $error = 'Greška prilikom brisanja usluge.';
                }
            }
        }
    }
}

$usluge = $db->fetchAll("SELECT * FROM usluge ORDER BY naziv");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usluge - CAP Admin</title>
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
                        Usluge
                    </h1>
                    <p class="text-gray-600">Upravljanje uslugama koje se pružaju štićenicima</p>
                </div>
                <button onclick="showAddForm()" class="btn-primary">
                    Dodaj uslugu
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
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Dodaj novu uslugu</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naziv usluge *</label>
                                <input type="text" name="naziv" required class="form-input" placeholder="Npr. Kupovina">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prosečno vreme trajanja (minuti) *</label>
                                <input type="number" name="prosecno_vreme" required class="form-input" min="1" placeholder="Npr. 60">
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideAddForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Dodaj uslugu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form Modal -->
            <div id="editForm" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Uredi uslugu</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" id="edit_id">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naziv usluge *</label>
                                <input type="text" name="naziv" id="edit_naziv" required class="form-input">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prosečno vreme trajanja (minuti) *</label>
                                <input type="number" name="prosecno_vreme" id="edit_prosecno_vreme" required class="form-input" min="1">
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideEditForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Ažuriraj uslugu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Services List -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Naziv usluge</th>
                                <th>Prosečno vreme (min)</th>
                                <th>Prosečno vreme (h:m)</th>
                                <th>Datum kreiranja</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usluge)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-8">
                                    Nema definisanih usluga.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($usluge as $usluga): ?>
                                <tr>
                                    <td class="font-medium">
                                        <?php echo htmlspecialchars($usluga['naziv']); ?>
                                    </td>
                                    <td><?php echo $usluga['prosecno_vreme']; ?> min</td>
                                    <td><?php echo minutesToHours($usluga['prosecno_vreme']); ?></td>
                                    <td><?php echo formatDate($usluga['created_at'], 'd.m.Y H:i'); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button onclick="showEditForm(<?php echo htmlspecialchars(json_encode($usluga)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                                Uredi
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovu uslugu?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $usluga['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                                    Obriši
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
            
            <!-- Usage Statistics -->
            <div class="card mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Statistike korišćenja</h3>
                
                <?php
                $usage_stats = $db->fetchAll("
                    SELECT u.naziv, COUNT(su.sticanik_id) as broj_sticenike,
                           COUNT(pu.poseta_id) as broj_poseta
                    FROM usluge u
                    LEFT JOIN sticanik_usluge su ON u.id = su.usluga_id
                    LEFT JOIN poseta_usluge pu ON u.id = pu.usluga_id
                    GROUP BY u.id, u.naziv
                    ORDER BY broj_sticenike DESC
                ");
                ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($usage_stats as $stat): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($stat['naziv']); ?></h4>
                        <div class="mt-2 text-sm text-gray-600">
                            <p>Štićenici: <span class="font-medium"><?php echo $stat['broj_sticenike']; ?></span></p>
                            <p>Ukupno poseta: <span class="font-medium"><?php echo $stat['broj_poseta']; ?></span></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
        
        function showEditForm(usluga) {
            document.getElementById('edit_id').value = usluga.id;
            document.getElementById('edit_naziv').value = usluga.naziv;
            document.getElementById('edit_prosecno_vreme').value = usluga.prosecno_vreme;
            
            document.getElementById('editForm').classList.remove('hidden');
        }
        
        function hideEditForm() {
            document.getElementById('editForm').classList.add('hidden');
        }
    </script>
</body>
</html>