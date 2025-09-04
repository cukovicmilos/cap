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
        $dani_nedelje = $_POST['dani_nedelje'] ?? [];
        
        if (empty($naziv) || empty($dani_nedelje)) {
            $error = 'Molimo unesite naziv i izaberite dane u nedelji.';
        } else {
            $data = [
                'naziv' => $naziv,
                'dani_nedelje' => json_encode(array_map('intval', $dani_nedelje))
            ];
            
            if ($db->insert('ucestalost_odlaska', $data)) {
                $message = 'Učestalost odlaska je uspešno dodana.';
            } else {
                $error = 'Greška prilikom dodavanja učestalosti odlaska.';
            }
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $naziv = sanitizeInput($_POST['naziv']);
        $dani_nedelje = $_POST['dani_nedelje'] ?? [];
        
        if (empty($naziv) || empty($dani_nedelje)) {
            $error = 'Molimo unesite naziv i izaberite dane u nedelji.';
        } else {
            $data = [
                'naziv' => $naziv,
                'dani_nedelje' => json_encode(array_map('intval', $dani_nedelje))
            ];
            
            if ($db->update('ucestalost_odlaska', $data, 'id = ?', [$id])) {
                $message = 'Učestalost odlaska je uspešno ažurirana.';
            } else {
                $error = 'Greška prilikom ažuriranja učestalosti odlaska.';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            // Check if ucestalost is used by any sticanik
            $used = $db->fetchOne("SELECT COUNT(*) as count FROM sticenike WHERE ucestalost_odlaska_id = ?", [$id]);
            
            if ($used['count'] > 0) {
                $error = 'Ne možete obrisati učestalost odlaska koja se koristi kod štićenika.';
            } else {
                if ($db->delete('ucestalost_odlaska', 'id = ?', [$id])) {
                    $message = 'Učestalost odlaska je uspešno obrisana.';
                } else {
                    $error = 'Greška prilikom brisanja učestalosti odlaska.';
                }
            }
        }
    }
}

$ucestalosti = $db->fetchAll("SELECT * FROM ucestalost_odlaska ORDER BY naziv");
$dani_u_nedelji = getDaysOfWeek();
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Učestalost odlaska - CAP Admin</title>
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
                        Učestalost odlaska
                    </h1>
                    <p class="text-gray-600">Upravljanje rasporedom odlaska kod štićenika</p>
                </div>
                <button onclick="showAddForm()" class="btn-primary">
                    Dodaj učestalost
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
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Dodaj novu učestalost odlaska</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naziv učestalosti *</label>
                                <input type="text" name="naziv" required class="form-input" 
                                       placeholder="Npr. 3 puta nedeljno">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Izaberite dane u nedelji *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($dani_u_nedelji as $broj => $naziv_dana): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="dani_nedelje[]" value="<?php echo $broj; ?>" class="mr-2">
                                        <?php echo $naziv_dana; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4 p-3 bg-blue-50 rounded">
                                <p class="text-sm text-blue-700">
                                    <strong>Primer:</strong><br>
                                    • "3 puta nedeljno" - Ponedeljak, Sreda, Petak<br>
                                    • "Dnevno" - svi dani u nedelji<br>
                                    • "Vikend" - Subota, Nedelja
                                </p>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideAddForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Dodaj učestalost
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
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Uredi učestalost odlaska</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" id="edit_id">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Naziv učestalosti *</label>
                                <input type="text" name="naziv" id="edit_naziv" required class="form-input">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Izaberite dane u nedelji *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($dani_u_nedelji as $broj => $naziv_dana): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="dani_nedelje[]" value="<?php echo $broj; ?>" 
                                               class="mr-2 edit-day-checkbox" data-day="<?php echo $broj; ?>">
                                        <?php echo $naziv_dana; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideEditForm()" class="btn-secondary">
                                    Otkaži
                                </button>
                                <button type="submit" class="btn-primary">
                                    Ažuriraj učestalost
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Frequency List -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Naziv učestalosti</th>
                                <th>Dani u nedelji</th>
                                <th>Broj štićenika</th>
                                <th>Datum kreiranja</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ucestalosti)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-8">
                                    Nema definisanih učestalosti odlaska.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($ucestalosti as $ucestalost): ?>
                                <?php
                                $brojSticenike = $db->fetchOne("SELECT COUNT(*) as count FROM sticenike WHERE ucestalost_odlaska_id = ?", [$ucestalost['id']]);
                                $selected_days = json_decode($ucestalost['dani_nedelje'], true) ?? [];
                                ?>
                                <tr>
                                    <td class="font-medium">
                                        <?php echo htmlspecialchars($ucestalost['naziv']); ?>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($selected_days as $day_num): ?>
                                                <?php if (isset($dani_u_nedelji[$day_num])): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                                    <?php echo $dani_u_nedelji[$day_num]; ?>
                                                </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="px-2 py-1 text-sm rounded-full bg-blue-100 text-blue-800">
                                            <?php echo $brojSticenike['count']; ?> štićenika
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($ucestalost['created_at'], 'd.m.Y H:i'); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button onclick="showEditForm(<?php echo htmlspecialchars(json_encode($ucestalost)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                                Uredi
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Da li ste sigurni da želite da obrišete ovu učestalost odlaska?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $ucestalost['id']; ?>">
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
                <h3 class="text-lg font-bold text-gray-900 mb-4">Kako funkcioniše učestalost odlaska</h3>
                
                <div class="prose prose-sm max-w-none">
                    <p>Učestalost odlaska definiše koliko često radnici/volonteri obilaze štićenike tokom nedelje.</p>
                    
                    <h4 class="font-medium text-gray-900 mt-4 mb-2">Automatsko generisanje poseta:</h4>
                    <ul class="list-disc list-inside space-y-1 text-gray-700">
                        <li>Kada definišete učestalost odlaska, sistem može automatski da generiše posete</li>
                        <li>Posete se generišu prema izabranim danima u nedelji</li>
                        <li>Svaki štićenik može imati različitu učestalost odlaska</li>
                        <li>Posete se automatski dodeljuju radnicima/volonterima koji su dodeljeni štićeniku</li>
                    </ul>
                    
                    <p class="mt-4 text-sm text-gray-600">
                        <strong>Napomena:</strong> Nakon kreiranja učestalosti, ne zaboravite da je dodelite štićenicima u njihovim profilima.
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
        
        function showEditForm(ucestalost) {
            document.getElementById('edit_id').value = ucestalost.id;
            document.getElementById('edit_naziv').value = ucestalost.naziv;
            
            // Clear all checkboxes first
            document.querySelectorAll('.edit-day-checkbox').forEach(cb => cb.checked = false);
            
            // Parse and check the selected days
            const selectedDays = JSON.parse(ucestalost.dani_nedelje || '[]');
            selectedDays.forEach(day => {
                const checkbox = document.querySelector(`[data-day="${day}"]`);
                if (checkbox) checkbox.checked = true;
            });
            
            document.getElementById('editForm').classList.remove('hidden');
        }
        
        function hideEditForm() {
            document.getElementById('editForm').classList.add('hidden');
        }
    </script>
</body>
</html>