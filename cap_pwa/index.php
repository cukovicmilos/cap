<?php
// Always prevent caching for fresh data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once '../cap_admin/includes/config.php';
require_once '../cap_admin/includes/database.php';
require_once '../cap_admin/includes/auth.php';
require_once '../cap_admin/includes/functions.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();

// Redirect admins to admin panel
if ($currentUser['type'] === 'upravitelj') {
    header('Location: ../cap_admin/index.php');
    exit;
}

$db = Database::getInstance();

// Get today's visits
$danas = date('Y-m-d');
$posete = $db->fetchAll("
    SELECT p.*, 
           s.ime_prezime as sticanik_ime,
           s.adresa as sticanik_adresa,
           s.telefon as sticanik_telefon
    FROM posete p
    INNER JOIN sticenike s ON p.sticanik_id = s.id
    WHERE p.korisnik_id = ? AND DATE(p.datum_posete) = ?
    ORDER BY p.vreme_pocetka ASC
", [$currentUser['id'], $danas]);

// Check if there's an active visit
$aktivnaPoseta = null;
foreach ($posete as $poseta) {
    if ($poseta['status'] === 'u_toku') {
        $aktivnaPoseta = $poseta;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Početna - CAP</title>
    <link rel="stylesheet" href="css/tailwind.css?v=<?php echo time(); ?>">
    <script src="js/offline-storage.js?v=<?php echo time(); ?>"></script>
    <link rel="icon" type="image/png" href="../global_assets/favicon.png?v=<?php echo time(); ?>">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#E24135">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CAP">
    <link rel="apple-touch-icon" href="../global_assets/cap_logo.png">
    
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <!-- PWA VERSION: OFFLINE-FIXED-<?php echo time(); ?> -->
    <!-- DEBUG: FINAL-FIXES-APPLIED-v1.4.2-FORCE-REFRESH -->
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="-1">
    <style>
        .status-badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }
        .status-zakazana { 
            background-color: #dbeafe; 
            color: #1e40af; 
        }
        .status-u_toku { 
            background-color: #fef3c7; 
            color: #92400e; 
        }
        .status-zavrsena { 
            background-color: #dcfce7; 
            color: #166534; 
        }
        .status-otkazana { 
            background-color: #fee2e2; 
            color: #991b1b; 
        }
        .btn-primary { 
            background-color: #dc2626; 
            color: white; 
            padding: 0.5rem 1rem; 
            border-radius: 0.375rem; 
            font-weight: 500; 
        }
        .btn-primary:hover { background-color: #b91c1c; }
        
        .btn-secondary { 
            background-color: #4b5563; 
            color: white; 
            padding: 0.5rem 1rem; 
            border-radius: 0.375rem; 
            font-weight: 500; 
        }
        .btn-secondary:hover { background-color: #374151; }
        
        .btn-tertiary { 
            background-color: #e5e7eb; 
            color: #374151; 
            padding: 0.5rem 1rem; 
            border-radius: 0.375rem; 
            font-weight: 500; 
            border: 1px solid #d1d5db; 
        }
        .btn-tertiary:hover { background-color: #d1d5db; }
        .card { @apply bg-white rounded-lg shadow-sm p-4 mb-4; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-red-600 text-white shadow-lg">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="../global_assets/cap_logo.png" alt="CAP Logo" class="h-8 w-auto">
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Offline Status Indicator -->
                    <div id="offlineStatus" class="flex items-center text-xs opacity-90" style="display: none;">
                        <div class="w-2 h-2 bg-yellow-400 rounded-full mr-1"></div>
                        <span id="offlineText">Offline</span>
                    </div>
                    
                    <!-- Sync Offline Data Button -->
                    <button onclick="syncOfflineData()" id="syncOfflineBtn" 
                            class="text-xs bg-red-700 px-2 py-1 rounded hover:bg-red-800 transition-colors"
                            title="Sinhronizuj offline podatke">
                        <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                        Sync
                    </button>
                    
                    <span class="text-sm opacity-90"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                    <a href="logout.php" class="text-sm bg-red-700 px-3 py-1 rounded hover:bg-red-800">
                        Odjava
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="px-4 py-6">
        <!-- Active Visit Alert with Timer -->
        <?php if ($aktivnaPoseta): ?>
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg shadow-md" id="activeVisitAlert" style="background: linear-gradient(to right, #dbeafe, #eff6ff);">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center mb-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse mr-2"></div>
                        <h3 class="font-medium text-blue-900">Poseta u toku</h3>
                    </div>
                    <p class="text-blue-700 font-medium"><?php echo htmlspecialchars($aktivnaPoseta['sticanik_ime']); ?></p>
                    <div class="flex items-center space-x-4 mt-2">
                        <p class="text-sm text-blue-600">
                            Početo: <?php echo formatTime($aktivnaPoseta['vreme_pocetka']); ?>
                        </p>
                        <div class="text-sm text-blue-600">
                            Trajanje: <span id="aktivniTimer" class="font-mono font-bold text-blue-800">00:00:00</span>
                        </div>
                    </div>
                </div>
                <div>
                    <button onclick="zavrsiPosetu(<?php echo $aktivnaPoseta['id']; ?>)" 
                            class="btn-primary shadow-md hover:shadow-lg transition-shadow">
                        Završi posetu
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Today's Schedule -->
        <div class="card">
            <h2 class="text-xl font-bold mb-4">Danas - <?php echo formatDate($danas); ?></h2>
            
            <?php if (empty($posete)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                    <p>Nemate zakazanih poseta za danas</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($posete as $poseta): ?>
                        <div class="border border-gray-200 rounded-lg p-4 visit-card" data-visit-id="<?php echo $poseta['id']; ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-medium text-lg"><?php echo htmlspecialchars($poseta['sticanik_ime']); ?></h3>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($poseta['sticanik_adresa']); ?></p>
                                    <?php if ($poseta['sticanik_telefon']): ?>
                                        <p class="text-sm text-gray-500">
                                            <a href="tel:<?php echo htmlspecialchars($poseta['sticanik_telefon']); ?>" 
                                               class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($poseta['sticanik_telefon']); ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="status-badge 
                                    <?php 
                                    echo match($poseta['status']) {
                                        'zakazana' => 'status-zakazana',
                                        'u_toku' => 'status-u_toku', 
                                        'zavrsena' => 'status-zavrsena',
                                        'otkazana' => 'status-otkazana',
                                        default => 'status-zakazana'
                                    };
                                    ?>">
                                    <?php 
                                    echo match($poseta['status']) {
                                        'zakazana' => 'Zakazana',
                                        'u_toku' => 'U toku',
                                        'zavrsena' => 'Završena',
                                        default => 'Nepoznato'
                                    };
                                    ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <div>
                                    <?php if ($poseta['vreme_pocetka']): ?>
                                        <span>Početak: <?php echo formatTime($poseta['vreme_pocetka']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($poseta['vreme_kraja']): ?>
                                        <span class="ml-4">Kraj: <?php echo formatTime($poseta['vreme_kraja']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <?php if ($poseta['status'] === 'zakazana' && !$aktivnaPoseta): ?>
                                        <button onclick="pocniPosetu(<?php echo $poseta['id']; ?>)" 
                                                class="btn-tertiary text-sm px-3 py-1">
                                            Počni posetu
                                        </button>
                                    <?php elseif ($poseta['status'] === 'u_toku'): ?>
                                        <button onclick="zavrsiPosetu(<?php echo $poseta['id']; ?>)" 
                                                class="btn-secondary text-sm px-3 py-1">
                                            Završi posetu
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Stats -->
        <div class="grid grid-cols-3 gap-4 mt-6">
            <div class="card text-center">
                <div class="text-2xl font-bold text-red-600">
                    <?php echo count($posete); ?>
                </div>
                <div class="text-sm text-gray-600">Ukupno danas</div>
            </div>
            <div class="card text-center">
                <div class="text-2xl font-bold text-green-600">
                    <?php echo count(array_filter($posete, fn($p) => $p['status'] === 'zavrsena')); ?>
                </div>
                <div class="text-sm text-gray-600">Završene</div>
            </div>
            <div class="card text-center">
                <div class="text-2xl font-bold text-yellow-600">
                    <?php echo count(array_filter($posete, fn($p) => $p['status'] === 'zakazana')); ?>
                </div>
                <div class="text-sm text-gray-600">Na čekanju</div>
            </div>
        </div>
    </main>

    <script>
        // console.log('CAP: Page loaded with v1.4.2 - <?php echo time(); ?>');
        
        // Force reload if old version detected in cache
        const expectedVersion = 'v1.4.2-FORCE-REFRESH';
        const currentTime = <?php echo time(); ?>;
        const storedTime = localStorage.getItem('cap_last_reload');
        
        // Disabled auto-reload to prevent interrupting sync
        // if (!storedTime || (currentTime - storedTime > 5)) { // 5 seconds cooldown
        //     localStorage.setItem('cap_last_reload', currentTime);
        //     console.log('CAP: Forcing page reload for fresh version');
        //     setTimeout(() => {
        //         window.location.reload(true); // Hard reload
        //     }, 100);
        // }
        
        let activeVisitTimerInterval = null;

        // Toast notification system
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full z-50 max-w-sm ${getToastClasses(type)}`;
            
            toast.innerHTML = `
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        ${getToastIcon(type)}
                    </div>
                    <div class="flex-1 text-sm font-medium">
                        ${message}
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.remove('translate-x-full'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('translate-x-full');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed top-0 right-0 p-4 z-50';
            document.body.appendChild(container);
            return container;
        }
        
        function getToastClasses(type) {
            const classes = {
                success: 'bg-green-100 border border-green-400 text-green-700',
                error: 'bg-red-100 border border-red-400 text-red-700',
                warning: 'bg-yellow-100 border border-yellow-400 text-yellow-700',
                info: 'bg-blue-100 border border-blue-400 text-blue-700'
            };
            return classes[type] || classes.info;
        }
        
        function getToastIcon(type) {
            const icons = {
                success: '<svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
                error: '<svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>',
                warning: '<svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
                info: '<svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
            };
            return icons[type] || icons.info;
        }

        function startActiveVisitTimer(startTime) {
            const timerElement = document.getElementById('aktivniTimer');
            if (!timerElement) return;

            // Clear any existing timer
            if (activeVisitTimerInterval) {
                clearInterval(activeVisitTimerInterval);
            }

            function updateTimer() {
                const now = new Date().getTime();
                const elapsed = now - startTime;
                
                // Calculate hours, minutes, seconds
                const hours = Math.floor(elapsed / (1000 * 60 * 60));
                const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
                
                // Format with leading zeros
                const formattedTime = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');
                
                timerElement.textContent = formattedTime;
                
                // Add visual effects based on duration
                if (elapsed > 3 * 60 * 60 * 1000) { // Over 3 hours - red
                    timerElement.className = 'font-mono font-bold text-red-600';
                } else if (elapsed > 2 * 60 * 60 * 1000) { // Over 2 hours - orange
                    timerElement.className = 'font-mono font-bold text-orange-600';
                } else if (elapsed > 1 * 60 * 60 * 1000) { // Over 1 hour - yellow
                    timerElement.className = 'font-mono font-bold text-yellow-600';
                } else {
                    timerElement.className = 'font-mono font-bold text-blue-800';
                }
            }

            // Update immediately
            updateTimer();
            
            // Update every second
            activeVisitTimerInterval = setInterval(updateTimer, 1000);
        }

        function stopActiveVisitTimer() {
            if (activeVisitTimerInterval) {
                clearInterval(activeVisitTimerInterval);
                activeVisitTimerInterval = null;
            }
        }

        // Track active requests to prevent double calls
        const activeRequests = new Set();
        
        async function pocniPosetu(posetaId) {
            console.log('CAP: pocniPosetu v2.3 called - with offline check for visit:', posetaId);
            console.log('CAP: navigator.onLine =', navigator.onLine);
            
            // Prevent duplicate calls
            if (activeRequests.has(`start_${posetaId}`)) {
                console.log('CAP: pocniPosetu already in progress for visit:', posetaId);
                return;
            }
            
            if (!confirm('Da li ste sigurni da želite da počnete posetu?')) {
                return;
            }
            
            // Mark request as active
            activeRequests.add(`start_${posetaId}`);
            
            // Find the button and show loading state
            const button = document.querySelector(`button[onclick="pocniPosetu(${posetaId})"]`);
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Pokretanje...
            `;
            
            // Prepare visit data for both online and offline cases
            const visitData = {
                id: posetaId,
                action: 'start_visit',
                user_id: <?php echo $currentUser['id']; ?>,
                timestamp: Date.now(),
                status: 'u_toku',
                vreme_pocetka: new Date().toTimeString().split(' ')[0].substring(0,5),
                synced: false  // Mark as not synced for offline tracking
            };
            
            // Check if offline first
            if (!navigator.onLine) {
                try {
                    // Check if OfflineStorage is available
                    if (typeof OfflineStorage === 'undefined') {
                        throw new Error('OfflineStorage nije učitan - offline storage.js fajl nedostaje');
                    }
                    
                    // Store offline action via unified sync system
                    await OfflineStorage.addToSyncQueue({
                        url: 'api/start_visit.php',
                        method: 'POST',
                        data: { poseta_id: posetaId },
                        action_type: 'start_visit'
                    });
                    
                    // Store visit data locally
                    await OfflineStorage.storeVisit(visitData);
                    
                    showToast('Poseta je pokrenuta offline. Podaci će biti sinhronizovani kada se vrati internet.', 'warning');
                    
                    // Update UI optimistically without reload
                    updateUIForActiveVisit(posetaId, visitData);
                    
                } catch (offlineError) {
                    console.error('CAP: Offline storage error:', offlineError);
                    console.error('CAP: OfflineStorage available?', typeof OfflineStorage);
                    showToast(`Greška pri čuvanju offline podataka: ${offlineError.message}`, 'error');
                    
                    // Update UI even if storage fails - user should see active visit
                    console.log('CAP: Updating UI despite storage error');
                    updateUIForActiveVisit(posetaId, visitData);
                } finally {
                    // Clean up active request tracking  
                    activeRequests.delete(`start_${posetaId}`);
                    
                    // Restore button state
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
                return; // Exit early for offline case
            }
            
            // Online case - try API call
            console.log('CAP: Making API call to start_visit.php for visit:', posetaId);
            try {
                const response = await fetch('api/start_visit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ poseta_id: posetaId })
                });
                
                console.log('CAP: API response status:', response.status, response.statusText);
                const result = await response.json();
                console.log('CAP: API result:', result);
                
                if (result.success) {
                    // Show success toast
                    showToast(result.message, 'success');
                    
                    // Update page dynamically - reload after short delay to show new state
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(result.message, 'error');
                }
                
            } catch (error) {
                console.error('CAP: Error starting visit:', error);
                console.log('CAP: Error type:', error.constructor.name);
                console.log('CAP: navigator.onLine in catch:', navigator.onLine);
                
                // Handle offline case
                if (!navigator.onLine || error.message.includes('Failed to fetch')) {
                    console.log('CAP: Treating as offline error, going to offline storage');
                    try {
                        // Check if OfflineStorage is available
                        if (typeof OfflineStorage === 'undefined') {
                            throw new Error('OfflineStorage nije učitan - offline storage.js fajl nedostaje');
                        }
                        
                        // Store offline action via unified sync system
                        await OfflineStorage.addToSyncQueue({
                            url: 'api/start_visit.php',
                            method: 'POST',
                            data: { poseta_id: posetaId },
                            action_type: 'start_visit'
                        });
                        
                        // Store visit data locally
                        await OfflineStorage.storeVisit(visitData);
                        
                        showToast('Poseta je pokrenuta offline. Podaci će biti sinhronizovani kada se vrati internet.', 'warning');
                        
                        // Update UI optimistically without reload
                        updateUIForActiveVisit(posetaId, visitData);
                        
                    } catch (offlineError) {
                        console.error('Offline storage error:', offlineError);
                        showToast('Greška pri čuvanju offline podataka.', 'error');
                        
                        // Update UI even if storage fails - user should see active visit
                        console.log('CAP: Updating UI despite storage error (fallback)');
                        updateUIForActiveVisit(posetaId, visitData);
                    }
                } else {
                    showToast('Greška prilikom pokretanja posete. Pokušajte ponovo.', 'error');
                }
            } finally {
                // Clean up active request tracking
                activeRequests.delete(`start_${posetaId}`);
                
                // Restore button state
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        async function zavrsiPosetu(posetaId) {
            console.log('CAP: zavrsiPosetu v2.2 called for visit:', posetaId);
            
            // Prevent duplicate calls
            if (activeRequests.has(`finish_${posetaId}`)) {
                console.log('CAP: zavrsiPosetu already in progress for visit:', posetaId);
                return;
            }
            
            activeRequests.add(`finish_${posetaId}`);
            
            // Stop timer first
            stopActiveVisitTimer();
            
            // Check if offline first
            if (!navigator.onLine) {
                // Loading services from local storage
                try {
                    const localServices = await OfflineStorage.getServices();
                    if (localServices && localServices.length > 0) {
                        // Found services in local storage
                        showFinishVisitModal(posetaId, localServices);
                        showToast('Učitane su lokalno sačuvane usluge (offline).', 'warning');
                    } else {
                        // No services found in local storage
                        // Show modal without services
                        showFinishVisitModal(posetaId, []);
                        showToast('Nema dostupnih usluga offline. Možete završiti posetu bez izbora usluga.', 'warning');
                    }
                } catch (offlineError) {
                    console.error('Offline services error:', offlineError);
                    // Show modal without services as fallback
                    showFinishVisitModal(posetaId, []);
                    showToast('Završite posetu bez izbora usluga (offline).', 'warning');
                }
                
                // Clean up active request tracking for offline case
                activeRequests.delete(`finish_${posetaId}`);
                return; // Exit early for offline case
            }
            
            // Online case - get available services for the modal
            try {
                const response = await fetch('api/get_services.php', {
                    credentials: 'same-origin'
                });
                const servicesData = await response.json();
                
                if (!servicesData.success) {
                    activeRequests.delete(`finish_${posetaId}`);
                    showToast('Greška prilikom učitavanja usluga.', 'error');
                    return;
                }
                
                // Show modal with services
                showFinishVisitModal(posetaId, servicesData.data);
                
                // Clean up tracking when modal opens successfully
                activeRequests.delete(`finish_${posetaId}`);
                
            } catch (error) {
                console.error('Error loading services:', error);
                
                // Handle offline case - load services from local storage
                if (error.message.includes('Failed to fetch')) {
                    try {
                        const localServices = await OfflineStorage.getServices();
                        if (localServices && localServices.length > 0) {
                            showFinishVisitModal(posetaId, localServices);
                            showToast('Učitane su lokalno sačuvane usluge (offline).', 'warning');
                        } else {
                            // Show modal without services
                            showFinishVisitModal(posetaId, []);
                            showToast('Nema dostupnih usluga offline. Možete završiti posetu bez izbora usluga.', 'warning');
                        }
                    } catch (offlineError) {
                        console.error('Offline services error:', offlineError);
                        // Show modal without services as fallback
                        showFinishVisitModal(posetaId, []);
                        showToast('Završite posetu bez izbora usluga (offline).', 'warning');
                    }
                } else {
                    showToast('Greška prilikom učitavanja usluga.', 'error');
                }
                
                // Clean up tracking at end of function
                activeRequests.delete(`finish_${posetaId}`);
            }
        }

        function showFinishVisitModal(posetaId, services) {
            // Create modal HTML
            const modalHTML = `
                <div id="finishVisitModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                    <div class="bg-white rounded-lg max-w-md w-full max-h-screen overflow-hidden flex flex-col">
                        <!-- Header -->
                        <div class="p-6 pb-4 border-b border-gray-200">
                            <h3 class="text-lg font-bold">Završavanje posete</h3>
                        </div>
                        
                        <!-- Content - scrollable -->
                        <div class="flex-1 overflow-y-auto p-6 pt-4">
                            <!-- Services Selection -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Obavljene usluge (opciono)
                                </label>
                                <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3">
                                    ${services.map(service => `
                                        <label class="flex items-center">
                                            <input type="checkbox" name="usluge[]" value="${service.id}" class="mr-2 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                            <span class="text-sm">${service.naziv}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <!-- Notes -->
                            <div class="mb-4">
                                <label for="napomene" class="block text-sm font-medium text-gray-700 mb-2">
                                    Napomene (opciono)
                                </label>
                                <textarea id="napomene" rows="4" placeholder="Dodatne napomene o poseti..." 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                            </div>
                        </div>
                        
                        <!-- Actions - fixed at bottom -->
                        <div class="p-6 pt-4 border-t border-gray-200">
                            <div class="flex space-x-3">
                                <button onclick="submitFinishVisit(${posetaId})" 
                                        class="flex-1 bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 font-medium">
                                    Završi posetu
                                </button>
                                <button onclick="closeFinishVisitModal()" 
                                        class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 font-medium">
                                    Otkaži
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        async function submitFinishVisit(posetaId) {
            const modal = document.getElementById('finishVisitModal');
            const submitButton = modal.querySelector('button[onclick*="submitFinishVisit"]');
            const originalText = submitButton.innerHTML;
            
            // Collect selected services and notes outside try block
            const selectedServices = [];
            const checkboxes = modal.querySelectorAll('input[name="usluge[]"]:checked');
            checkboxes.forEach(checkbox => {
                selectedServices.push(parseInt(checkbox.value));
            });
            const napomene = modal.querySelector('#napomene').value.trim();
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<div class="spinner"></div> Završavam...';
            
            try {
                // Check Service Worker status
                if (!navigator.onLine) {
                    console.log('CAP: Offline - checking Service Worker status');
                    if ('serviceWorker' in navigator) {
                        console.log('CAP: Service Worker available, controller:', navigator.serviceWorker.controller);
                    } else {
                        console.log('CAP: Service Worker not available');
                    }
                }
                
                // Send AJAX request with credentials
                const response = await fetch('api/finish_visit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin', // Include cookies for session
                    body: JSON.stringify({
                        poseta_id: posetaId,
                        usluge: selectedServices,
                        napomene: napomene
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Check if this is an offline response
                    if (result.offline === true) {
                        console.log('CAP: Received offline response from Service Worker');
                        
                        // Store offline action via unified sync system
                        await OfflineStorage.addToSyncQueue({
                            url: 'api/finish_visit.php',
                            method: 'POST',
                            data: {
                                poseta_id: posetaId,
                                usluge: selectedServices,
                                napomene: napomene
                            },
                            action_type: 'finish_visit'
                        });
                        
                        // Update visit data locally
                        const finishedVisitData = {
                            id: posetaId,
                            status: 'zavrsena',
                            vreme_kraja: new Date().toTimeString().split(' ')[0].substring(0,5),
                            usluge: selectedServices,
                            napomene: napomene,
                            user_id: <?php echo $currentUser['id']; ?>,
                            timestamp: Date.now(),
                            synced: false
                        };
                        
                        await OfflineStorage.storeVisit(finishedVisitData);
                        
                        showToast('Poseta je završena offline. Podaci će biti sinhronizovani kada se vrati internet.', 'warning');
                        closeFinishVisitModal();
                        
                        // Remove active visit alert and stop timer
                        const activeAlert = document.getElementById('activeVisitAlert');
                        if (activeAlert) {
                            activeAlert.remove();
                        }
                        stopActiveVisitTimer();
                        
                        // Update visit card in UI
                        const visitCards = document.querySelectorAll('.visit-card');
                        visitCards.forEach(card => {
                            const button = card.querySelector(`button[onclick*="zavrsiPosetu(${posetaId})"]`);
                            if (button) {
                                // Remove the button
                                button.remove();
                                
                                // Update status badge
                                const statusBadge = card.querySelector('.status-badge');
                                if (statusBadge) {
                                    statusBadge.textContent = 'Završena (OFFLINE)';
                                    statusBadge.className = 'status-badge status-zavrsena';
                                }
                            }
                        });
                    } else {
                        // Normal online success
                        showToast(result.message, 'success');
                        closeFinishVisitModal(true); // Skip timer restart since visit is finished
                        
                        // Reload page to show updated state
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showToast(result.message, 'error');
                }
                
            } catch (error) {
                console.error('Error finishing visit:', error);
                
                // Handle offline case
                if (!navigator.onLine || error.message.includes('Failed to fetch')) {
                    try {
                        // Store offline action via unified sync system
                        await OfflineStorage.addToSyncQueue({
                            url: 'api/finish_visit.php',
                            method: 'POST',
                            data: {
                                poseta_id: posetaId,
                                usluge: selectedServices,
                                napomene: napomene
                            },
                            action_type: 'finish_visit'
                        });
                        
                        // Update visit data locally
                        const finishedVisitData = {
                            id: posetaId,
                            status: 'zavrsena',
                            vreme_kraja: new Date().toTimeString().split(' ')[0].substring(0,5),
                            usluge: selectedServices,
                            napomene: napomene,
                            user_id: <?php echo $currentUser['id']; ?>,
                            timestamp: Date.now(),
                            synced: false  // Mark as not synced for offline tracking
                        };
                        
                        await OfflineStorage.storeVisit(finishedVisitData);
                        
                        showToast('Poseta je završena offline. Podaci će biti sinhronizovani kada se vrati internet.', 'warning');
                        closeFinishVisitModal();
                        
                        // Remove active visit alert and stop timer
                        const activeAlert = document.getElementById('activeVisitAlert');
                        if (activeAlert) {
                            activeAlert.remove();
                        }
                        stopActiveVisitTimer();
                        
                        // Update visit card in UI
                        const visitCards = document.querySelectorAll('.visit-card');
                        visitCards.forEach(card => {
                            const button = card.querySelector(`button[onclick*="zavrsiPosetu(${posetaId})"]`);
                            if (button) {
                                // Remove the button
                                button.remove();
                                
                                // Update status badge
                                const statusBadge = card.querySelector('.status-badge');
                                if (statusBadge) {
                                    statusBadge.textContent = 'Završena (OFFLINE)';
                                    statusBadge.className = 'status-badge status-zavrsena';
                                }
                            }
                        });
                        
                    } catch (offlineError) {
                        console.error('Offline storage error:', offlineError);
                        showToast('Greška pri čuvanju offline podataka.', 'error');
                    }
                } else {
                    showToast('Greška prilikom završavanja posete. Pokušajte ponovo.', 'error');
                }
            } finally {
                // Restore button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        }

        function closeFinishVisitModal(skipTimerRestart = false) {
            const modal = document.getElementById('finishVisitModal');
            if (modal) {
                modal.remove();
            }
            // Only restart timer if not explicitly skipped (e.g., when visit is finished)
            if (!skipTimerRestart) {
                <?php if ($aktivnaPoseta): ?>
                const startTime = <?php echo $startTimestamp ?? 'null'; ?>;
                if (startTime) {
                    startActiveVisitTimer(startTime);
                }
                <?php endif; ?>
            }
        }

        // Cleanup timer on page unload
        window.addEventListener('beforeunload', function() {
            stopActiveVisitTimer();
        });

        // Preload services for offline use
        async function preloadServices() {
            if (navigator.onLine) {
                try {
                    const response = await fetch('api/get_services.php', {
                    credentials: 'same-origin'
                });
                    const servicesData = await response.json();
                    
                    if (servicesData.success && typeof OfflineStorage !== 'undefined') {
                        await OfflineStorage.storeServices(servicesData.data);
                        console.log('CAP: Services preloaded for offline use');
                    }
                } catch (error) {
                    console.log('CAP: Could not preload services:', error);
                }
            }
        }

        // Clean up old visits
        async function cleanupOldVisits() {
            if (typeof OfflineStorage !== 'undefined') {
                try {
                    const deletedCount = await OfflineStorage.cleanupVisits(<?php echo $currentUser['id']; ?>, 7);
                    if (deletedCount > 0) {
                        console.log(`CAP: Cleaned up ${deletedCount} old/invalid visits`);
                    }
                } catch (error) {
                    console.log('CAP: Could not cleanup visits:', error);
                }
            }
        }
        
        // Initialize timer for active visit if exists
        <?php if ($aktivnaPoseta): ?>
        document.addEventListener('DOMContentLoaded', function() {
            <?php 
            $startTime = new DateTime($aktivnaPoseta['datum_posete'] . ' ' . $aktivnaPoseta['vreme_pocetka']);
            $startTimestamp = $startTime->getTimestamp() * 1000; // Convert to milliseconds for JavaScript
            ?>
            const startTime = <?php echo $startTimestamp; ?>;
            startActiveVisitTimer(startTime);
        });
        <?php endif; ?>

        // ===== PWA & OFFLINE FUNCTIONALITY =====
        
        let isOnline = navigator.onLine;
        let reallyOnline = navigator.onLine; // Start with navigator.onLine value
        let serviceWorkerRegistration = null;
        let autoSyncInterval = null;
        let connectivityTestInterval = null;
        let lastDataUpdate = 0;
        let syncInProgress = false;
        
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            // Service Worker support detected
            
            // Function to register SW
            async function registerServiceWorker() {
                // Starting Service Worker registration
                try {
                    // Clear old service worker caches only (not IndexedDB or localStorage)
                    if ('caches' in window) {
                        const cacheNames = await caches.keys();
                        // Only delete caches that start with 'cap-' to preserve other data
                        const oldCaches = cacheNames.filter(name => name.startsWith('cap-'));
                        await Promise.all(oldCaches.map(name => caches.delete(name)));
                        // Old service worker caches cleared
                    }
                    
                    // Force unregister old SW first
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    // Found existing Service Workers
                    for (let registration of registrations) {
                        // Unregistering old SW
                        await registration.unregister();
                    }
                    // Old Service Workers unregistered
                    
                    // Wait a bit for cleanup
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    
                    // Clear HTTP cache via programmatic method
                    if ('serviceWorker' in navigator && 'caches' in window) {
                        // Clear all HTTP caches including browser cache
                        try {
                            await caches.delete('v1');
                            await caches.delete('v2'); 
                            await caches.delete('v3');
                            // Clear any potential service worker caches
                            const keys = await caches.keys();
                            for (const key of keys) {
                                await caches.delete(key);
                            }
                        } catch (e) {}
                    }
                    
                    const swTimestamp = Date.now();
                    // Registering SW with timestamp
                    
                    try {
                        serviceWorkerRegistration = await navigator.serviceWorker.register('./sw.js?v=' + swTimestamp, {
                            updateViaCache: 'none' // Force no cache for SW
                        });
                        // SW registration object created
                    } catch (regError) {
                        console.error('CAP: SW registration error:', regError);
                        throw regError;
                    }
                    
                    // Force update check immediately
                    await serviceWorkerRegistration.update();
                    // Service Worker registered and updated successfully
                    
                    // Wait for Service Worker to become active
                    if (!navigator.serviceWorker.controller) {
                        // Waiting for SW to be ready
                        await navigator.serviceWorker.ready;
                        // Service Worker is ready
                    } else {
                        // Service Worker controller already active
                    }
                    
                    // Listen for SW messages
                    navigator.serviceWorker.addEventListener('message', handleSWMessage);
                    // SW message listener added
                    
                    // Cleanup old visits first, then preload services
                    setTimeout(async () => {
                        // Starting cleanup and preload
                        await cleanupOldVisits();
                        await preloadServices();
                        // Cleanup and preload complete
                    }, 1000);
                    
                    // Test real connectivity before starting sync
                    // Testing real connectivity
                    const isReallyOnline = await testRealConnectivity();
                    reallyOnline = isReallyOnline;
                    // Connectivity test complete
                    
                    if (isReallyOnline) {
                        // Starting auto-sync
                        startAutoSync();
                    } else {
                        // Offline, skipping auto-sync
                        // Force redirect to offline.html if we loaded index.php while offline
                        if (window.location.pathname.includes('index.php')) {
                            console.log('CAP: Redirecting to offline.html for better offline experience');
                            setTimeout(() => {
                                window.location.href = 'offline.html';
                            }, 1000);
                        }
                    }
                    
                    // Check for updates
                    serviceWorkerRegistration.addEventListener('updatefound', () => {
                        const newWorker = serviceWorkerRegistration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                showToast('Nova verzija aplikacije je dostupna. Restartujte aplikaciju.', 'info');
                                // Auto refresh after 2 seconds
                                setTimeout(() => {
                                    if (confirm('Dostupna je nova verzija aplikacije. Osvežiti sada?')) {
                                        window.location.reload();
                                    }
                                }, 2000);
                            }
                        });
                    });
                    
                } catch (error) {
                    console.error('CAP: Service Worker registration failed:', error);
                }
            }
            
            // Call registration function immediately if page already loaded
            if (document.readyState === 'complete') {
                // Page already loaded, registering SW immediately
                registerServiceWorker();
            } else {
                // Waiting for page load to register SW
                window.addEventListener('load', registerServiceWorker);
            }
        } else {
            console.error('CAP: Service Worker not supported in this browser');
        }
        
        // Handle SW messages
        function handleSWMessage(event) {
            const { type, action } = event.data;
            
            switch (type) {
                case 'SYNC_SUCCESS':
                    showToast(`Sinhronizovana akcija: ${action.data.action || 'nepoznata'}`, 'success');
                    updateOfflineStatus();
                    break;
            }
        }
        
        // Online/Offline status handling
        function updateOfflineStatus() {
            const offlineStatus = document.getElementById('offlineStatus');
            const offlineText = document.getElementById('offlineText');
            
            if (!navigator.onLine) {
                offlineStatus.style.display = 'flex';
                offlineStatus.querySelector('.w-2').className = 'w-2 h-2 bg-red-400 rounded-full mr-1';
                offlineText.textContent = 'Offline';
                isOnline = false;
            } else {
                // Check for pending sync actions
                checkPendingActions().then(pendingCount => {
                    if (pendingCount > 0) {
                        offlineStatus.style.display = 'flex';
                        offlineStatus.querySelector('.w-2').className = 'w-2 h-2 bg-yellow-400 rounded-full mr-1 animate-pulse';
                        offlineText.textContent = `Sinhronizuje ${pendingCount}`;
                    } else {
                        offlineStatus.style.display = 'none';
                    }
                });
                isOnline = true;
            }
        }
        
        // Check pending actions count via unified system
        async function checkPendingActions() {
            if (typeof OfflineStorage === 'undefined') {
                return 0;
            }
            
            // Check if Service Worker is ready
            if (!serviceWorkerRegistration || !serviceWorkerRegistration.active) {
                return 0;
            }
            
            try {
                const pendingItems = await OfflineStorage.getPendingSyncItems();
                return Array.isArray(pendingItems) ? pendingItems.length : 0;
            } catch (error) {
                // Only log error if it's not the "Service Worker nije dostupan" error
                if (!error.message.includes('Service Worker nije dostupan')) {
                    console.log('Could not get pending actions:', error);
                }
                return 0;
            }
        }
        
        // Force sync
        async function forceSyncIfNeeded() {
            if (!serviceWorkerRegistration || !serviceWorkerRegistration.active) {
                return;
            }
            
            return new Promise((resolve) => {
                const messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = (event) => {
                    resolve(event.data.success);
                };
                
                serviceWorkerRegistration.active.postMessage({
                    type: 'FORCE_SYNC'
                }, [messageChannel.port2]);
            });
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            stopAutoSync();
            
            // Stop connectivity testing
            if (connectivityTestInterval) {
                clearInterval(connectivityTestInterval);
            }
        });
        
        // Update UI to show active visit without page reload
        function updateUIForActiveVisit(posetaId, visitData) {
            console.log('CAP: Updating UI for active visit:', posetaId);
            
            // Find the visit card and update it
            const visitCards = document.querySelectorAll('.visit-card');
            console.log('CAP: Found', visitCards.length, 'visit cards');
            
            visitCards.forEach(card => {
                const cardVisitId = card.getAttribute('data-visit-id');
                console.log('CAP: Checking card with visit ID:', cardVisitId, 'against', posetaId);
                if (cardVisitId == posetaId) {
                    const button = card.querySelector(`button[onclick*="pocniPosetu(${posetaId})"]`);
                    console.log('CAP: Found button for visit', posetaId, ':', button);
                    if (button) {
                        // Change "Počni posetu" to "Završi posetu"
                        button.onclick = null;
                        button.setAttribute('onclick', `zavrsiPosetu(${posetaId})`);
                        button.textContent = 'Završi posetu';
                        button.className = 'btn-secondary text-sm px-3 py-1';
                        console.log('CAP: Button updated to "Završi posetu"');
                        
                        // Update status badge
                        const statusBadge = card.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.textContent = 'U toku (OFFLINE)';
                            statusBadge.className = 'status-badge status-u_toku';
                            console.log('CAP: Status badge updated to "U toku (OFFLINE)"');
                        }
                    } else {
                        console.log('CAP: Button not found for visit', posetaId);
                    }
                }
            });
            
            // Create and show active visit alert with timer
            const existingAlert = document.getElementById('activeVisitAlert');
            console.log('CAP: Existing alert found:', existingAlert);
            if (!existingAlert) {
                console.log('CAP: Creating new active visit alert');
                const mainContent = document.querySelector('main');
                const alertHTML = `
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg shadow-md" id="activeVisitAlert" style="background: linear-gradient(to right, #dbeafe, #eff6ff);">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse" style="margin-right: 12px; border: 1px solid rgba(255, 255, 255, 0.5);"></div>
                                    <h3 class="font-medium text-blue-900">Poseta u toku (OFFLINE)</h3>
                                </div>
                                <p class="text-blue-700 font-medium" id="activeSticenic">Učitavanje...</p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <p class="text-sm text-blue-600">
                                        Početo: <span id="activeStartTime">${visitData.vreme_pocetka}</span>
                                    </p>
                                    <div class="text-sm text-blue-600">
                                        Trajanje: <span id="aktivniTimer" class="font-mono font-bold text-blue-800">00:00:00</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button onclick="zavrsiPosetu(${posetaId})" 
                                        class="btn-primary shadow-md hover:shadow-lg transition-shadow">
                                    Završi posetu
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Insert at the beginning of main content
                mainContent.insertAdjacentHTML('afterbegin', alertHTML);
                
                // Start timer
                const startTime = Date.now();
                startActiveVisitTimer(startTime);
                
                // Try to get sticanik name from the visit card
                visitCards.forEach(card => {
                    const button = card.querySelector(`button[onclick*="zavrsiPosetu(${posetaId})"]`);
                    if (button) {
                        const sticenilName = card.querySelector('h3')?.textContent?.trim();
                        if (sticenilName) {
                            document.getElementById('activeSticenic').textContent = sticenilName;
                        }
                    }
                });
            }
        }
        
        // Update UI when visit is finished offline
        function updateUIForFinishedVisit(posetaId) {
            console.log('CAP: Updating UI for finished visit:', posetaId);
            
            // Remove active visit alert
            const activeAlert = document.getElementById('activeVisitAlert');
            if (activeAlert) {
                activeAlert.remove();
            }
            
            // Stop timer
            stopActiveVisitTimer();
            
            // Find and update visit card
            const visitCards = document.querySelectorAll('.visit-card');
            visitCards.forEach(card => {
                const button = card.querySelector(`button[onclick*="zavrsiPosetu(${posetaId})"]`);
                if (button) {
                    // Remove the button or disable it
                    button.remove();
                    
                    // Update status badge
                    const statusBadge = card.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.textContent = 'Završena (OFFLINE)';
                        statusBadge.className = 'status-badge status-zavrsena';
                    }
                }
            });
        }
        
        // Auto-save active visit when going offline
        async function autoSaveActiveVisit(visitId) {
            try {
                const visitData = {
                    id: visitId,
                    status: 'u_toku',
                    user_id: <?php echo $currentUser['id']; ?>,
                    auto_saved_at: Date.now(),
                    note: 'Auto-saved during offline transition'
                };
                
                await OfflineStorage.storeVisit(visitData);
                console.log('Active visit auto-saved offline');
                
            } catch (error) {
                console.error('Failed to auto-save active visit:', error);
            }
        }
        
        // Check for active visits stored locally and update UI
        async function checkAndUpdateOfflineActiveVisits() {
            try {
                const visits = await OfflineStorage.getAllVisits();
                const currentUserId = <?php echo $currentUser['id']; ?>;
                
                // Find active visits for current user
                const activeVisits = visits.filter(v => 
                    v.user_id === currentUserId && v.status === 'u_toku'
                );
                
                console.log('CAP: Found', activeVisits.length, 'active offline visits');
                
                // Update UI for each active visit
                activeVisits.forEach(visit => {
                    const visitCards = document.querySelectorAll('.visit-card');
                    visitCards.forEach(card => {
                        const cardVisitId = card.getAttribute('data-visit-id');
                        if (cardVisitId == visit.id) {
                            // Find the button
                            const button = card.querySelector('button[onclick*="pocniPosetu"]');
                            if (button) {
                                // Change to "Završi posetu"
                                button.onclick = null;
                                button.setAttribute('onclick', `zavrsiPosetu(${visit.id})`);
                                button.textContent = 'Završi posetu';
                                button.className = 'btn-secondary text-sm px-3 py-1';
                                console.log('CAP: Updated button for offline active visit:', visit.id);
                            }
                            
                            // Update status badge
                            const statusBadge = card.querySelector('.status-badge');
                            if (statusBadge && statusBadge.textContent !== 'U toku') {
                                statusBadge.textContent = 'U toku (OFFLINE)';
                                statusBadge.className = 'status-badge status-u_toku';
                                console.log('CAP: Updated status badge for offline active visit:', visit.id);
                            }
                        }
                    });
                });
            } catch (error) {
                console.error('CAP: Error checking offline active visits:', error);
            }
        }
        
        // Refresh visit data when online
        async function refreshVisitData() {
            if (!navigator.onLine) return;
            
            try {
                console.log('CAP: Refreshing visit data from server...');
                // Add cache busting parameter and reload
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('_refresh', Date.now());
                window.location.href = currentUrl.href;
            } catch (error) {
                console.error('CAP: Error refreshing data:', error);
            }
        }
        
        // Enhanced fetch function za API calls sa offline support
        async function enhancedFetch(url, options = {}) {
            try {
                const response = await fetch(url, options);
                
                // Update offline status after successful request
                if (response.ok) {
                    updateOfflineStatus();
                }
                
                return response;
            } catch (error) {
                console.log('CAP: Enhanced fetch failed, might be offline:', error);
                updateOfflineStatus();
                throw error;
            }
        }
        
        // Store current user for offline access
        localStorage.setItem('cap_current_user', JSON.stringify({
            id: <?php echo $currentUser['id']; ?>,
            name: '<?php echo addslashes($currentUser['name']); ?>',
            type: '<?php echo $currentUser['type']; ?>'
        }));
        
        // Initialize offline status
        document.addEventListener('DOMContentLoaded', async () => {
            // Cleanup old visits FIRST, before any other operations
            await cleanupOldVisits();
            
            // Check for locally stored active visits when offline
            if (!navigator.onLine) {
                await checkAndUpdateOfflineActiveVisits();
            }
            
            // Wait a bit before first status check to allow Service Worker to register
            setTimeout(() => {
                updateOfflineStatus();
                
                // Periodically check status
                setInterval(updateOfflineStatus, 30000); // every 30 seconds
            }, 1000);
            
            // Handle online/offline events
            window.addEventListener('online', async () => {
                console.log('CAP: Navigator says back online, testing real connectivity...');
                isOnline = true;
                
                // Test real connectivity before starting sync
                const isReallyOnline = await testRealConnectivity();
                
                if (isReallyOnline) {
                    console.log('CAP: Confirmed really back online - starting sync');
                    reallyOnline = true;
                    
                    // Update UI to show online status
                    updateOfflineStatus();
                    
                    // Sync offline data immediately
                    await syncOfflineData();
                    
                    // Force sync any pending offline actions
                    forceSyncIfNeeded();
                    
                    // Start auto-sync (this will stop any existing one first)
                    startAutoSync();
                    
                    // Show success notification
                    showToast('Internet konekcija je vraćena!', 'success');
                    
                    // Trigger immediate page refresh to get latest data
                    setTimeout(() => {
                        console.log('CAP: Refreshing page to get latest data after reconnect');
                        window.location.reload();
                    }, 1000);
                    
                } else {
                    console.log('CAP: Navigator says online but connectivity test failed');
                    reallyOnline = false;
                    showToast('Navigator kaže online, ali nema stvarnu konekciju.', 'warning');
                }
            });
            
            window.addEventListener('offline', async () => {
                console.log('CAP: Navigator says offline, stopping auto-sync');
                isOnline = false;
                reallyOnline = false;
                updateOfflineStatus();
                
                // Stop auto-sync when offline
                stopAutoSync();
                
                // Start periodic connectivity testing while offline
                startOfflineConnectivityTesting();
                
                showToast('Nema internet konekcije. Pokušavam auto-reconnect...', 'warning');
                
                // Auto-save ongoing visit state
                <?php if ($aktivnaPoseta): ?>
                await autoSaveActiveVisit(<?php echo $aktivnaPoseta['id']; ?>);
                <?php endif; ?>
                
                // Update UI for offline active visits
                await checkAndUpdateOfflineActiveVisits();
            });
        });
        
        // ===== CONNECTIVITY TESTING =====
        
        // Start periodic connectivity testing while offline
        function startOfflineConnectivityTesting() {
            if (connectivityTestInterval) {
                clearInterval(connectivityTestInterval);
            }
            
            console.log('CAP: Starting offline connectivity testing every 10 seconds');
            
            connectivityTestInterval = setInterval(async () => {
                if (!reallyOnline) {
                    console.log('CAP: Testing connectivity while offline...');
                    const isBack = await testRealConnectivity();
                    
                    if (isBack) {
                        console.log('CAP: Connection restored! Triggering online event');
                        reallyOnline = true;
                        isOnline = true;
                        
                        // Stop offline testing
                        clearInterval(connectivityTestInterval);
                        connectivityTestInterval = null;
                        
                        // Trigger reconnection logic
                        handleReconnection();
                    }
                }
            }, 10000); // 10 seconds
        }
        
        // Handle reconnection after offline
        async function handleReconnection() {
            console.log('CAP: Handling reconnection after offline');
            
            // Update UI
            updateOfflineStatus();
            
            // Sync offline data immediately
            await syncOfflineData();
            
            // Sync any pending actions
            forceSyncIfNeeded();
            
            // Start auto-sync
            startAutoSync();
            
            // Show success notification
            showToast('Konekcija je vraćena automatski!', 'success');
            
            // Refresh page to get latest data
            setTimeout(() => {
                console.log('CAP: Refreshing page after auto-reconnect');
                window.location.reload();
            }, 2000);
        }
        
        // Test real internet connectivity (not just network interface)
        async function testRealConnectivity() {
            if (!navigator.onLine) {
                // Navigator offline
                return false;
            }
            
            try {
                // Test with a simple endpoint that should always work
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); // 3 second timeout
                
                const response = await fetch('api/get_services.php?ping=1', {
                    method: 'HEAD',
                    cache: 'no-cache',
                    signal: controller.signal,
                    credentials: 'same-origin'
                });
                
                clearTimeout(timeoutId);
                
                const isReallyOnline = response.ok;
                // Connectivity test complete
                return isReallyOnline;
                
            } catch (error) {
                console.log('CAP: Connectivity test failed:', error.name);
                return false;
            }
        }
        
        // ===== AUTO-SYNC FUNCTIONALITY =====
        
        // Start automatic data synchronization
        function startAutoSync() {
            // Starting auto-sync
            
            // Don't start auto-sync if offline
            if (!reallyOnline) {
                console.log('CAP: Cannot start auto-sync - really offline');
                return;
            }
            
            if (autoSyncInterval) {
                clearInterval(autoSyncInterval);
            }
            
            // Starting auto-sync every 3 seconds
            
            // Initial sync only if online
            syncDataFromServer();
            
            // Set interval for continuous sync - only when really online
            autoSyncInterval = setInterval(async () => {
                // Skip connectivity test if we're already online to reduce overhead
                if (navigator.onLine) {
                    if (!syncInProgress) {
                        // Auto-sync triggered
                        syncDataFromServer();
                    }
                } else {
                    // Test real connectivity only when navigator says offline
                    const isOnline = await testRealConnectivity();
                    reallyOnline = isOnline;
                    
                    if (reallyOnline && !syncInProgress) {
                        syncDataFromServer();
                    } else {
                        console.log('CAP: Skipping sync - offline');
                    }
                }
            }, 3000); // 3 seconds for more responsive updates
        }
        
        // Stop auto-sync
        function stopAutoSync() {
            if (autoSyncInterval) {
                console.log('CAP: Stopping auto-sync');
                clearInterval(autoSyncInterval);
                autoSyncInterval = null;
            }
            
            // Also stop connectivity testing if running
            if (connectivityTestInterval) {
                console.log('CAP: Stopping connectivity testing');
                clearInterval(connectivityTestInterval);
                connectivityTestInterval = null;
            }
        }
        
        // Sync data from server using API - no page refresh
        async function syncDataFromServer() {
            // Skip sync if offline (trust navigator.onLine for performance)
            if (!navigator.onLine) {
                console.log('CAP: Skipping sync - offline');
                return;
            }
            
            // Prevent concurrent sync calls
            if (syncInProgress) {
                return; // Don't log to reduce console noise
            }
            
            syncInProgress = true;
            
            
            try {
                // Fetching visits from server
                const response = await fetch('api/get_visits.php?t=' + Date.now(), {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache'
                    },
                    credentials: 'same-origin'
                });
                
                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success) {
                        // Got fresh data from server
                        
                        // Update UI with new data
                        await updateUIWithNewData(result.data);
                        
                        // Smart merge data offline - preserve local-only visits
                        if (typeof OfflineStorage !== 'undefined') {
                            // Get existing local visits first
                            const existingVisits = await OfflineStorage.getTodaysVisits(<?php echo $currentUser['id']; ?>, '<?php echo date('Y-m-d'); ?>');
                            console.log('CAP: Found', existingVisits.length, 'existing local visits');
                            
                            // Find local visits that have been modified offline
                            const localModifiedVisits = existingVisits.filter(local => {
                                // Check if this is a local active visit (started offline)
                                if (local.status === 'u_toku' && (!local.synced || local.synced === false)) {
                                    console.log('CAP: Found local active visit:', local.id, 'status:', local.status);
                                    return true;
                                }
                                
                                // Check if this is a local-only visit not on server
                                const existsOnServer = result.data.posete.some(server => 
                                    server.id && server.id === local.id
                                );
                                const isLocalOnly = !existsOnServer && (!local.synced || local.synced === false);
                                
                                if (isLocalOnly) {
                                    console.log('CAP: Local-only visit found:', local.id, 'synced:', local.synced);
                                }
                                
                                return isLocalOnly;
                            });
                            
                            console.log('CAP: Found', localModifiedVisits.length, 'local modified visits to preserve');
                            if (localModifiedVisits.length > 0) {
                                console.log('CAP: Local modified visit IDs:', localModifiedVisits.map(v => ({id: v.id, status: v.status})));
                            }
                            
                            // Store server visits but preserve local modifications
                            for (const poseta of result.data.posete) {
                                // Check if we have a local modification for this visit
                                const localModified = localModifiedVisits.find(local => {
                                    // Match by server ID or if local ID contains server ID
                                    return (local.server_id && local.server_id === poseta.id) || 
                                           (local.id && (local.id === poseta.id || String(local.id).includes(String(poseta.id))));
                                });
                                
                                if (localModified && localModified.status === 'u_toku') {
                                    // Preserve local active status - don't overwrite with server data
                                    console.log('CAP: Preserving local active status for visit:', poseta.id);
                                    await OfflineStorage.storeVisit({
                                        ...poseta,
                                        status: 'u_toku', // Keep local active status
                                        vreme_pocetka: localModified.vreme_pocetka || poseta.vreme_pocetka,
                                        user_id: <?php echo $currentUser['id']; ?>,
                                        date: '<?php echo date('Y-m-d'); ?>',
                                        synced: false // Mark as not synced so it will be synced to server
                                    });
                                } else {
                                    // Store server data as-is
                                    await OfflineStorage.storeVisit({
                                        ...poseta,
                                        user_id: <?php echo $currentUser['id']; ?>,
                                        date: '<?php echo date('Y-m-d'); ?>',
                                        synced: true
                                    });
                                }
                            }
                            
                            // Restore local-only visits that don't exist on server
                            const localOnlyVisits = localModifiedVisits.filter(local => {
                                return !result.data.posete.some(server => 
                                    server.id === local.id || 
                                    (local.server_id && local.server_id === server.id)
                                );
                            });
                            
                            for (const localVisit of localOnlyVisits) {
                                console.log('CAP: Preserving local-only visit:', localVisit.id);
                                await OfflineStorage.storeVisit(localVisit);
                            }
                        }
                        
                    }
                    
                    lastDataUpdate = Date.now();
                }
                
            } catch (error) {
                console.log('CAP: Sync error:', error);
                // Don't show error notifications for sync failures to avoid spam
            } finally {
                syncInProgress = false;
                
            }
        }
        
        // Update UI with new data without page reload
        async function updateUIWithNewData(data) {
            const { posete, aktivna_poseta, statistics } = data;
            
            // Update visit cards
            updateVisitCards(posete);
            
            // Update or create active visit alert
            updateActiveVisitAlert(aktivna_poseta);
            
            // Update statistics
            updateStatistics(statistics);
            
            // Update active visit timer if needed
            if (aktivna_poseta) {
                const startTime = new Date(aktivna_poseta.datum_posete + ' ' + aktivna_poseta.vreme_pocetka).getTime();
                if (!activeVisitTimerInterval) {
                    startActiveVisitTimer(startTime);
                }
            } else {
                stopActiveVisitTimer();
            }
        }
        
        // Sync offline data TO server (complement to syncDataFromServer)
        async function syncOfflineData() {
            console.log('[' + new Date().toLocaleTimeString() + '] Starting offline data sync');
            
            try {
                // First sync any visits that were started offline
                if (typeof OfflineStorage !== 'undefined') {
                    const existingVisits = await OfflineStorage.getTodaysVisits(
                        <?php echo $currentUser['id']; ?>, 
                        new Date().toISOString().split('T')[0]
                    );
                    
                    // Find visits that are active locally but not synced
                    const unsyncedActiveVisits = existingVisits.filter(visit => 
                        visit.status === 'u_toku' && (!visit.synced || visit.synced === false)
                    );
                    
                    console.log('CAP: Found', unsyncedActiveVisits.length, 'unsynced active visits');
                    
                    // Sync each active visit to server
                    for (const visit of unsyncedActiveVisits) {
                        const visitId = visit.server_id || visit.id;
                        if (visitId && !String(visitId).startsWith('local_')) {
                            console.log('CAP: Syncing active visit to server:', visitId);
                            try {
                                const response = await fetch('api/start_visit.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ poseta_id: visitId })
                                });
                                
                                if (response.ok) {
                                    const result = await response.json();
                                    if (result.success) {
                                        // Mark as synced in local storage
                                        await OfflineStorage.storeVisit({
                                            ...visit,
                                            synced: true
                                        });
                                        console.log('CAP: Successfully synced active visit:', visitId);
                                    }
                                }
                            } catch (error) {
                                console.error('CAP: Failed to sync active visit:', visitId, error);
                            }
                        }
                    }
                }
                
                // Get offline actions from various storage mechanisms
                let offlineActions = [];
                
                // Try IndexedDB first
                if (typeof idb !== 'undefined') {
                    try {
                        const db = await idb.openDB('cap_offline', 1);
                        const tx = db.transaction('actions', 'readonly');
                        const actions = await tx.store.getAll();
                        offlineActions = [...offlineActions, ...actions];
                        console.log('[' + new Date().toLocaleTimeString() + '] Found ' + actions.length + ' actions in IndexedDB');
                    } catch (e) {
                        console.log('[' + new Date().toLocaleTimeString() + '] IndexedDB not available:', e.message);
                    }
                }
                
                // Fallback to localStorage
                const localActions = JSON.parse(localStorage.getItem('pendingActions') || '[]');
                if (localActions.length > 0) {
                    offlineActions = [...offlineActions, ...localActions];
                    console.log('[' + new Date().toLocaleTimeString() + '] Found ' + localActions.length + ' actions in localStorage');
                }
                
                if (offlineActions.length === 0) {
                    console.log('[' + new Date().toLocaleTimeString() + '] No offline actions to sync');
                    showToast('Nema offline podataka za sinhronizaciju', 'info');
                    return;
                }
                
                // Send to server
                const response = await fetch('api/sync_offline_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        actions: offlineActions
                    })
                });
                
                const result = await response.json();
                console.log('[' + new Date().toLocaleTimeString() + '] Sync result:', result);
                
                if (result.success) {
                    // Clear synced data from storage
                    if (typeof idb !== 'undefined') {
                        try {
                            const db = await idb.openDB('cap_offline', 1);
                            const tx = db.transaction('actions', 'readwrite');
                            await tx.store.clear();
                        } catch (e) {
                            console.log('[' + new Date().toLocaleTimeString() + '] Could not clear IndexedDB');
                        }
                    }
                    
                    localStorage.removeItem('pendingActions');
                    
                    showToast(`Sinhronizovano ${result.synced} od ${result.total} akcija`, 'success');
                    
                    if (result.errors && result.errors.length > 0) {
                        console.warn('[' + new Date().toLocaleTimeString() + '] Sync errors:', result.errors);
                    }
                    
                    // Refresh data from server to get updated state
                    await syncDataFromServer();
                    
                } else {
                    showToast('Greška pri sinhronizaciji: ' + result.message, 'error');
                }
                
            } catch (error) {
                console.error('[' + new Date().toLocaleTimeString() + '] Sync error:', error);
                showToast('Greška pri sinhronizaciji offline podataka', 'error');
            }
        }
        
        // Update visit cards in the UI
        function updateVisitCards(posete) {
            const container = document.querySelector('.space-y-3');
            // Updating visit cards
            if (!container) {
                console.error('CAP: Visit container not found!');
                return;
            }
            
            if (!posete || posete.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                        <p>Nemate zakazanih poseta za danas</p>
                    </div>
                `;
                return;
            }
            
            const visitCardsHTML = posete.map(poseta => `
                <div class="border border-gray-200 rounded-lg p-4 visit-card" data-visit-id="${poseta.id}">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="font-medium text-lg">${escapeHtml(poseta.sticanik_ime)}</h3>
                            <p class="text-gray-600">${escapeHtml(poseta.sticanik_adresa)}</p>
                            ${poseta.sticanik_telefon ? `
                                <p class="text-sm text-gray-500">
                                    <a href="tel:${escapeHtml(poseta.sticanik_telefon)}" 
                                       class="text-blue-600 hover:text-blue-800">
                                        ${escapeHtml(poseta.sticanik_telefon)}
                                    </a>
                                </p>
                            ` : ''}
                        </div>
                        <span class="status-badge ${getStatusClass(poseta.status)}">
                            ${getStatusText(poseta.status)}
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <div>
                            ${poseta.vreme_pocetka ? `<span>Početak: ${poseta.vreme_pocetka}</span>` : ''}
                            ${poseta.vreme_kraja ? `<span class="ml-4">Kraj: ${poseta.vreme_kraja}</span>` : ''}
                        </div>
                        
                        <div>
                            ${getActionButton(poseta)}
                        </div>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = visitCardsHTML;
        }
        
        // Update active visit alert
        function updateActiveVisitAlert(aktivnaPoseta) {
            const existingAlert = document.getElementById('activeVisitAlert');
            
            if (!aktivnaPoseta) {
                // Remove active visit alert if no active visit
                if (existingAlert) {
                    existingAlert.remove();
                }
                return;
            }
            
            if (!existingAlert) {
                // Create new active visit alert
                const alertHTML = `
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg shadow-md" id="activeVisitAlert" style="background: linear-gradient(to right, #dbeafe, #eff6ff);">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse mr-2"></div>
                                    <h3 class="font-medium text-blue-900">Poseta u toku</h3>
                                </div>
                                <p class="text-blue-700 font-medium">${escapeHtml(aktivnaPoseta.sticanik_ime)}</p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <p class="text-sm text-blue-600">
                                        Početo: ${aktivnaPoseta.vreme_pocetka}
                                    </p>
                                    <div class="text-sm text-blue-600">
                                        Trajanje: <span id="aktivniTimer" class="font-mono font-bold text-blue-800">00:00:00</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button onclick="zavrsiPosetu(${aktivnaPoseta.id})" 
                                        class="btn-primary shadow-md hover:shadow-lg transition-shadow">
                                    Završi posetu
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                const main = document.querySelector('main');
                // Insert at the very beginning of main content
                main.insertAdjacentHTML('afterbegin', alertHTML);
                
                // Start timer
                const startTime = new Date(aktivnaPoseta.datum_posete + ' ' + aktivnaPoseta.vreme_pocetka).getTime();
                startActiveVisitTimer(startTime);
            }
        }
        
        // Update statistics
        function updateStatistics(stats) {
            const statElements = document.querySelectorAll('.card .text-2xl');
            if (statElements.length >= 3) {
                statElements[0].textContent = stats.ukupno;
                statElements[1].textContent = stats.zavrsene;
                statElements[2].textContent = stats.na_cekanju;
            }
        }
        
        // Helper functions
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function getStatusClass(status) {
            const classes = {
                'zakazana': 'status-zakazana',
                'u_toku': 'status-u_toku', 
                'zavrsena': 'status-zavrsena',
                'otkazana': 'status-otkazana'
            };
            return classes[status] || 'status-zakazana';
        }
        
        function getStatusText(status) {
            const texts = {
                'zakazana': 'Zakazana',
                'u_toku': 'U toku',
                'zavrsena': 'Završena',
                'otkazana': 'Otkazana'
            };
            return texts[status] || 'Nepoznato';
        }
        
        function getActionButton(poseta) {
            if (poseta.status === 'zakazana') {
                return `<button onclick="pocniPosetu(${poseta.id})" class="btn-tertiary text-sm px-3 py-1">Počni posetu</button>`;
            } else if (poseta.status === 'u_toku') {
                return `<button onclick="zavrsiPosetu(${poseta.id})" class="btn-secondary text-sm px-3 py-1">Završi posetu</button>`;
            }
            return '';
        }
        
        
        // Background sync registration
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            navigator.serviceWorker.ready.then(registration => {
                return registration.sync.register('cap-background-sync');
            }).catch(error => {
                console.log('CAP: Background sync not supported or failed:', error);
            });
        }

    </script>
</body>
</html>