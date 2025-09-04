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
        
        if (empty($naziv)) {
            $error = 'Molimo unesite naziv nivoa invaliditeta.';
        } else {
            $data = ['naziv' => $naziv];
            
            if ($db->insert('nivoi_invaliditeta', $data)) {
                $message = 'Nivo invaliditeta je uspešno dodan.';
            } else {
                $error = 'Greška prilikom dodavanja nivoa invaliditeta.';
            }
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $naziv = sanitizeInput($_POST['naziv']);
        
        if (empty($naziv)) {
            $error = 'Molimo unesite naziv nivoa invaliditeta.';
        } else {
            $data = ['naziv' => $naziv];
            
            if ($db->update('nivoi_invaliditeta', $data, 'id = ?', [$id])) {
                $message = 'Nivo invaliditeta je uspešno ažuriran.';
            } else {
                $error = 'Greška prilikom ažuriranja nivoa invaliditeta.';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            // Check if nivo is used by any sticanik
            $used = $db->fetchOne("SELECT COUNT(*) as count FROM sticenike WHERE nivo_invaliditeta_id = ?", [$id]);
            
            if ($used['count'] > 0) {
                $error = 'Ne možete obrisati nivo invaliditeta koji se koristi kod štićenika.';
            } else {
                if ($db->delete('nivoi_invaliditeta', 'id = ?', [$id])) {
                    $message = 'Nivo invaliditeta je uspešno obrisan.';
                } else {
                    $error = 'Greška prilikom brisanja nivoa invaliditeta.';
                }
            }
        }
    }
}

$nivoi = $db->fetchAll("SELECT * FROM nivoi_invaliditeta ORDER BY naziv");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nivoi invaliditeta - CAP Admin</title>
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
                        Nivoi invaliditeta
                    </h1>
                    <p class="text-gray-600">Upravljanje nivoima invaliditeta prema zakonu R. Srbije</p>
                </div>
                <button onclick="showAddForm()" class="btn-primary">
                    Dodaj nivo
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
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Dodaj novi nivo invaliditeta</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naziv nivoa invaliditeta *</label>
                                <input type="text" name="naziv" required class="form-input" 
                                       placeholder="Npr. I kategorija - 100% invaliditet">
                            </div>
                            
                            <div class="mb-4 p-3 bg-blue-50 rounded">
                                <p class="text-sm text-blue-700">
                                    <strong>Primer naziva prema zakonu R. Srbije:</strong><br>
                                    • I kategorija - 100% invaliditet<br>
                                    • II kategorija - 75% invaliditet<br>
                                    • III kategorija - 50% invaliditet<br>
                                    • IV kategorija - 25% invaliditet
                                </p>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideAddForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Dodaj nivo
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
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Uredi nivo invaliditeta</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" id="edit_id">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naziv nivoa invaliditeta *</label>
                                <input type="text" name="naziv" id="edit_naziv" required class="form-input">
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideEditForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Ažuriraj nivo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Levels List -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Naziv nivoa invaliditeta</th>
                                <th>Broj štićenika</th>
                                <th>Datum kreiranja</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($nivoi)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-gray-500 py-8">
                                    Nema definisanih nivoa invaliditeta.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($nivoi as $nivo): ?>
                                <?php
                                $brojSticenike = $db->fetchOne("SELECT COUNT(*) as count FROM sticenike WHERE nivo_invaliditeta_id = ?", [$nivo['id']]);
                                ?>
                                <tr>
                                    <td class="font-medium">
                                        <?php echo htmlspecialchars($nivo['naziv']); ?>
                                    </td>
                                    <td>
                                        <span class="px-2 py-1 text-sm rounded-full bg-blue-100 text-blue-800">
                                            <?php echo $brojSticenike['count']; ?> štićenika
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($nivo['created_at'], 'd.m.Y H:i'); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button onclick="showEditForm(<?php echo htmlspecialchars(json_encode($nivo)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                                Uredi
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovaj nivo invaliditeta?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $nivo['id']; ?>">
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
            
            <!-- Info Card -->
            <div class="card mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Informacije o nivoima invaliditeta</h3>
                
                <div class="prose prose-sm max-w-none">
                    <p>Nivoi invaliditeta se definišu prema zakonu Republike Srbije o profesionalnoj rehabilitaciji i zapošljavanju osoba sa invaliditetom.</p>
                    
                    <h4 class="font-medium text-gray-900 mt-4 mb-2">Standardne kategorije:</h4>
                    <ul class="list-disc list-inside space-y-1 text-gray-700">
                        <li><strong>I kategorija (100%):</strong> Potpuna nesposobnost za rad</li>
                        <li><strong>II kategorija (75%):</strong> Visoka nesposobnost za rad</li>
                        <li><strong>III kategorija (50%):</strong> Srednja nesposobnost za rad</li>
                        <li><strong>IV kategorija (25%):</strong> Mala nesposobnost za rad</li>
                    </ul>
                    
                    <p class="mt-4 text-sm text-gray-600">
                        <strong>Napomena:</strong> Možete dodati i prilagođene nazive nivoa invaliditeta prema potrebama vaše organizacije.
                    </p>
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
        
        function showEditForm(nivo) {
            document.getElementById('edit_id').value = nivo.id;
            document.getElementById('edit_naziv').value = nivo.naziv;
            
            document.getElementById('editForm').classList.remove('hidden');
        }
        
        function hideEditForm() {
            document.getElementById('editForm').classList.add('hidden');
        }
    </script>
</body>
</html>