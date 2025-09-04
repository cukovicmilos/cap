<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

$totalSticenike = $db->fetchOne("SELECT COUNT(*) as count FROM sticenike")['count'] ?? 0;
$totalRadnici = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE tip_korisnika IN ('radnik', 'volonter')")['count'] ?? 0;
$totalPoseteToday = $db->fetchOne("SELECT COUNT(*) as count FROM posete WHERE DATE(datum_posete) = CURDATE()")['count'] ?? 0;
$poseteUToku = $db->fetchOne("SELECT COUNT(*) as count FROM posete WHERE status = 'u_toku'")['count'] ?? 0;

// Get sort parameters
$sort_column = $_GET['sort'] ?? 'datum_posete';
$sort_direction = $_GET['dir'] ?? 'ASC';

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Validate sort parameters
$allowed_columns = ['datum_posete', 'radnik_ime', 'sticanik_ime', 'vreme_pocetka', 'status'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'datum_posete';
}

$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// Get total count for pagination
$totalPosete = $db->fetchOne("SELECT COUNT(*) as count FROM posete")['count'];
$totalPages = ceil($totalPosete / $limit);

$recentPosete = $db->fetchAll("
    SELECT p.*, u.ime_prezime as radnik_ime, s.ime_prezime as sticanik_ime 
    FROM posete p
    LEFT JOIN users u ON p.korisnik_id = u.id
    LEFT JOIN sticenike s ON p.sticanik_id = s.id
    ORDER BY {$sort_column} {$sort_direction}
    LIMIT {$limit} OFFSET {$offset}
");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CAP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
    <style>
        .table th a {
            text-decoration: none;
            color: var(--secondary-color);
            transition: color 0.2s ease;
        }
        
        .table th a:hover {
            color: var(--main-color);
        }
        
        .table th {
            cursor: pointer;
            user-select: none;
        }
        
        .table th a {
            display: block;
            padding: 0.25rem 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2" style="font-family: 'Poppins', sans-serif;">
                    Dashboard
                </h1>
                <p class="text-gray-600">Dobrodošli, <?php echo htmlspecialchars($currentUser['name']); ?></p>
            </div>
            
            <!-- Statistike -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full main-bg text-white mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $totalSticenike; ?></p>
                            <p class="text-gray-600">Ukupno štićenika</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full secondary-bg text-white mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $totalRadnici; ?></p>
                            <p class="text-gray-600">Radnici i volonteri</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-500 text-white mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $totalPoseteToday; ?></p>
                            <p class="text-gray-600">Posete danas</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-500 text-white mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $poseteUToku; ?></p>
                            <p class="text-gray-600">Posete u toku</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Poslednje posete -->
            <div class="card">
                <h2 class="text-xl font-bold text-gray-900 mb-4" style="font-family: 'Poppins', sans-serif;">
                    Poslednje posete
                </h2>
                
                <?php if (empty($recentPosete)): ?>
                    <p class="text-gray-500">Nema zabeleženih poseta.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <?php
                                    function getSortLink($column, $label, $current_sort, $current_dir, $page) {
                                        $new_dir = ($current_sort === $column && $current_dir === 'ASC') ? 'DESC' : 'ASC';
                                        $arrow = '';
                                        if ($current_sort === $column) {
                                            $arrow = $current_dir === 'ASC' ? ' ↑' : ' ↓';
                                        }
                                        return "<a href='?sort={$column}&dir={$new_dir}&page={$page}' class='text-gray-700 hover:text-gray-900 font-semibold'>{$label}{$arrow}</a>";
                                    }
                                    ?>
                                    <th><?php echo getSortLink('datum_posete', 'Datum', $sort_column, $sort_direction, $page); ?></th>
                                    <th><?php echo getSortLink('radnik_ime', 'Radnik', $sort_column, $sort_direction, $page); ?></th>
                                    <th><?php echo getSortLink('sticanik_ime', 'Štićenik', $sort_column, $sort_direction, $page); ?></th>
                                    <th><?php echo getSortLink('vreme_pocetka', 'Vreme', $sort_column, $sort_direction, $page); ?></th>
                                    <th><?php echo getSortLink('status', 'Status', $sort_column, $sort_direction, $page); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPosete as $poseta): ?>
                                <tr>
                                    <td><?php echo formatDate($poseta['datum_posete']); ?></td>
                                    <td><?php echo htmlspecialchars($poseta['radnik_ime'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($poseta['sticanik_ime'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        if ($poseta['vreme_pocetka']) {
                                            echo formatTime($poseta['vreme_pocetka']);
                                            if ($poseta['vreme_kraja']) {
                                                echo ' - ' . formatTime($poseta['vreme_kraja']);
                                            }
                                        }
                                        ?>
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
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Prikazuje se <?php echo (($page - 1) * $limit + 1); ?> do <?php echo min($page * $limit, $totalPosete); ?> od <?php echo $totalPosete; ?> poseta
                        </div>
                        
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                                <a href="?sort=<?php echo $sort_column; ?>&dir=<?php echo $sort_direction; ?>&page=<?php echo ($page - 1); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">
                                    ‹ Prethodna
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            // Show page numbers
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            if ($start > 1) {
                                echo '<a href="?sort=' . $sort_column . '&dir=' . $sort_direction . '&page=1" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 hover:bg-gray-50">1</a>';
                                if ($start > 2) {
                                    echo '<span class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300">...</span>';
                                }
                            }
                            
                            for ($i = $start; $i <= $end; $i++) {
                                $activeClass = ($i == $page) ? 'bg-red-500 text-white border-red-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50';
                                echo '<a href="?sort=' . $sort_column . '&dir=' . $sort_direction . '&page=' . $i . '" class="px-3 py-2 text-sm font-medium ' . $activeClass . ' border">' . $i . '</a>';
                            }
                            
                            if ($end < $totalPages) {
                                if ($end < $totalPages - 1) {
                                    echo '<span class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300">...</span>';
                                }
                                echo '<a href="?sort=' . $sort_column . '&dir=' . $sort_direction . '&page=' . $totalPages . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 hover:bg-gray-50">' . $totalPages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?sort=<?php echo $sort_column; ?>&dir=<?php echo $sort_direction; ?>&page=<?php echo ($page + 1); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">
                                    Sledeća ›
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>