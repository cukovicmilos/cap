const CACHE_NAME = 'cap-pwa-v1.2.9';
const API_CACHE_NAME = 'cap-api-cache-v1.2.9';

// Essential files za offline rad
const STATIC_ASSETS = [
    './',
    './index.php',
    './login.php', 
    './offline.html',
    './manifest.json',
    './css/tailwind.css',
    './js/offline-storage.js',
    '../global_assets/favicon.png',
    '../global_assets/icon-192.png',
    '../global_assets/cap_logo.png'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('CAP SW: Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('CAP SW: Caching static assets...');
                // Add cache-busting to ensure fresh offline.html
                const assetsWithCacheBusting = STATIC_ASSETS.map(asset => {
                    if (asset === './offline.html') {
                        return asset + '?v=' + Date.now();
                    }
                    return asset;
                });
                return cache.addAll(assetsWithCacheBusting);
            })
            .then(() => {
                console.log('CAP SW: All assets cached successfully');
                self.skipWaiting();
            })
            .catch(error => {
                console.error('CAP SW: Install error:', error);
                // Log details about which assets failed
                STATIC_ASSETS.forEach(asset => {
                    fetch(asset).catch(e => console.error('CAP SW: Failed to fetch:', asset, e));
                });
            })
    );
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('CAP SW: Activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
                        console.log('CAP SW: Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - handle all requests
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    console.log('CAP SW: Fetch request for:', url.pathname, 'Method:', request.method, 'Mode:', request.mode);

    // Force offline.html for navigation when offline (or if we can't reach server)
    if (request.mode === 'navigate') {
        console.log('CAP SW: Navigation request detected for:', url.pathname);
        if (url.pathname.includes('index.php') || url.pathname.endsWith('/') || url.pathname.includes('cap_pwa')) {
            console.log('CAP SW: Match found, testing connectivity for:', url.pathname);
            event.respondWith(handleNavigation(request));
            return;
        } else {
            console.log('CAP SW: Navigation path not matched:', url.pathname);
        }
    }

    // Handle API requests posebno
    if (url.pathname.includes('/api/')) {
        event.respondWith(handleApiRequest(request));
        return;
    }
    
    // Skip CDN requests and external hosts, but handle our PHP files
    if (url.hostname.includes('cdn.') || 
        url.hostname !== location.hostname) {
        return; // Let browser handle these requests normally
    }

    // Handle static assets i stranice
    event.respondWith(
        caches.match(request)
            .then(response => {
                if (response) {
                    return response;
                }
                
                // Try matching without query parameters for cached assets
                const urlWithoutQuery = new URL(request.url);
                urlWithoutQuery.search = '';
                return caches.match(urlWithoutQuery.href);
            })
            .then(response => {
                if (response) {
                    return response;
                }

                return fetch(request, {
                    redirect: 'follow' // Allow redirects
                })
                    .then(response => {
                        // Cache successful GET responses only, skip external CDNs and redirects
                        if (response && response.status === 200 && 
                            request.method === 'GET' &&
                            !url.hostname.includes('cdn.') && 
                            response.type !== 'opaqueredirect') {
                            const responseClone = response.clone();
                            caches.open(CACHE_NAME)
                                .then(cache => cache.put(request, responseClone))
                                .catch(err => console.log('Cache put failed:', err));
                        }
                        return response;
                    })
                    .catch(async (fetchError) => {
                        console.log('Fetch failed for:', request.url, fetchError);
                        
                        // Offline fallback for navigation requests OR when really offline
                        if (request.mode === 'navigate' || !navigator.onLine) {
                            console.log('CAP SW: Serving offline fallback page for:', request.url, 'mode:', request.mode, 'onLine:', navigator.onLine);
                            
                            // First try to serve our custom offline.html
                            try {
                                console.log('CAP SW: Looking for offline.html');
                                const cache = await caches.open(CACHE_NAME);
                                
                                // Try multiple paths for offline.html
                                let offlinePage = await cache.match('./offline.html');
                                if (!offlinePage) {
                                    offlinePage = await cache.match('/cap/cap_pwa/offline.html');
                                }
                                if (!offlinePage) {
                                    offlinePage = await cache.match(self.registration.scope + 'offline.html');
                                }
                                if (!offlinePage) {
                                    // Try without leading slash
                                    offlinePage = await cache.match('offline.html');
                                }
                                
                                if (offlinePage) {
                                    console.log('CAP SW: Found and serving offline.html');
                                    return offlinePage;
                                } else {
                                    console.log('CAP SW: offline.html not found, trying cache-busted version');
                                    
                                    // Try to find cache-busted version
                                    const cachedRequests = await cache.keys();
                                    console.log('CAP SW: All cached URLs:', cachedRequests.map(req => req.url));
                                    
                                    const offlineRequest = cachedRequests.find(req => 
                                        req.url.includes('offline.html')
                                    );
                                    
                                    if (offlineRequest) {
                                        console.log('CAP SW: Found cache-busted offline.html:', offlineRequest.url);
                                        offlinePage = await cache.match(offlineRequest);
                                        if (offlinePage) {
                                            console.log('CAP SW: Serving cache-busted offline.html');
                                            return offlinePage;
                                        }
                                    }
                                }
                                
                                console.log('CAP SW: No offline.html found, trying cached index.php');
                                
                                // Fallback to cached index.php if offline.html not available
                                let cachedIndex = await cache.match('./index.php');
                                if (!cachedIndex) {
                                    cachedIndex = await cache.match('/cap/cap_pwa/index.php');
                                }
                                if (!cachedIndex) {
                                    cachedIndex = await cache.match(self.registration.scope + 'index.php');
                                }
                                
                                if (cachedIndex) {
                                    console.log('CAP SW: Found and serving cached index.php for offline navigation');
                                    return cachedIndex;
                                } else {
                                    console.log('CAP SW: No cached index.php found either');
                                }
                            } catch (e) {
                                console.log('CAP SW: Error loading offline pages:', e);
                            }
                            
                            // If no cached index.php, serve simple offline page without external resources
                            return new Response(`
                                <html>
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>CAP - Offline</title>
                                    <style>
                                        body { font-family: Arial, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
                                        .container { max-width: 400px; margin: 100px auto; text-align: center; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                                        .logo { width: 80px; height: 80px; margin: 0 auto 20px; background: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold; }
                                        .error-title { color: #dc2626; font-size: 24px; font-weight: bold; margin-bottom: 16px; }
                                        .error-text { color: #6b7280; margin-bottom: 20px; }
                                        .retry-btn { background: #dc2626; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 10px; font-size: 16px; }
                                        .retry-btn:hover { background: #b91c1c; }
                                        .offline-btn { background: #374151; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 10px; font-size: 16px; }
                                        .offline-btn:hover { background: #111827; }
                                    </style>
                                </head>
                                <body>
                                    <div class="container">
                                        <div class="logo">CAP</div>
                                        <h1 class="error-title">Nema internet konekcije</h1>
                                        <p class="error-text">Aplikacija radi offline sa ograničenim funkcijama.</p>
                                        <button class="retry-btn" onclick="location.reload()">Pokušaj ponovo</button>
                                        <button class="offline-btn" onclick="window.location.href='/cap/cap_pwa/index.php'">Nastavi offline</button>
                                    </div>
                                    <script>
                                        // Auto-redirect to cached index if available after 3 seconds
                                        setTimeout(() => {
                                            window.location.href = '/cap/cap_pwa/index.php';
                                        }, 3000);
                                    </script>
                                </body>
                                </html>
                            `, {
                                headers: { 'Content-Type': 'text/html' }
                            });
                        }
                        
                        // For other requests, return a basic error response
                        return new Response('Service Unavailable', { status: 503 });
                    });
            })
    );
});

// Handle API requests sa offline support
async function handleApiRequest(request) {
    const method = request.method;
    const url = new URL(request.url);
    
    // Clone request early for potential cache use
    const requestForCache = request.clone();
    
    try {
        // Pokušaj mrežni zahtev
        console.log('CAP SW: Making API request to:', request.url);
        const response = await fetch(request);
        console.log('CAP SW: API response status:', response.status);
        
        if (response.ok) {
            // Cache GET responses
            if (method === 'GET') {
                const cache = await caches.open(API_CACHE_NAME);
                cache.put(requestForCache, response.clone());
            }
            
            return response;
        }
        
        // Don't treat HTTP errors as network failures - pass them through
        if (response.status >= 400 && response.status < 600) {
            console.log('CAP SW: API returned HTTP error:', response.status, 'for:', request.url);
            return response; // Pass HTTP errors through to the application
        }
        
        throw new Error(`HTTP ${response.status}`);
        
    } catch (error) {
        console.log('CAP SW: API request failed, going offline:', error);
        
        // GET zahtevi - pokušaj iz cache
        if (method === 'GET') {
            const cachedResponse = await caches.match(request);
            if (cachedResponse) {
                return cachedResponse;
            }
        }
        
        // POST zahtevi - sačuvaj za kasnije
        if (method === 'POST') {
            return await handleOfflinePost(request);
        }
        
        // Return offline response
        return new Response(
            JSON.stringify({
                success: false,
                message: 'Nema internet konekcije. Podaci su sačuvani lokalno.',
                offline: true
            }),
            {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Process online POST requests
async function processOnlinePost(request, response) {
    try {
        const requestData = await request.json();
        const responseData = await response.json();
        
        // Sačuvaj u IndexedDB za offline pristup
        if (requestData.poseta_id) {
            await storeVisitData({
                id: requestData.poseta_id,
                action: getActionType(request.url),
                data: requestData,
                response: responseData,
                timestamp: Date.now(),
                synced: true
            });
        }
        
    } catch (error) {
        console.error('CAP SW: Error processing online POST:', error);
    }
}

// Handle offline POST requests
async function handleOfflinePost(request) {
    try {
        const requestData = await request.clone().json();
        
        // Sačuvaj akciju za background sync
        await storeOfflineAction({
            url: request.url,
            method: request.method,
            data: requestData,
            timestamp: Date.now(),
            synced: false
        });
        
        // Optimistic response
        return new Response(
            JSON.stringify({
                success: true,
                message: 'Akcija je sačuvana lokalno. Biće sinhronizovana kada se vrati internet.',
                offline: true,
                pending_sync: true
            }),
            {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            }
        );
        
    } catch (error) {
        return new Response(
            JSON.stringify({
                success: false,
                message: 'Greška pri čuvanju offline podataka.'
            }),
            {
                status: 500,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Background sync za pending actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'cap-background-sync') {
        console.log('CAP SW: Background sync triggered');
        event.waitUntil(syncPendingActions());
    }
});

// Sync pending actions
async function syncPendingActions() {
    try {
        const actions = await getPendingActions();
        
        for (const action of actions) {
            try {
                const response = await fetch(action.url, {
                    method: action.method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(action.data)
                });
                
                if (response.ok) {
                    // Mark as synced
                    action.synced = true;
                    action.sync_timestamp = Date.now();
                    await updateOfflineAction(action);
                    
                    console.log('CAP SW: Successfully synced action:', action.id);
                    
                    // Obavesti main thread o uspešnom sync-u
                    self.clients.matchAll().then(clients => {
                        clients.forEach(client => {
                            client.postMessage({
                                type: 'SYNC_SUCCESS',
                                action: action
                            });
                        });
                    });
                }
                
            } catch (error) {
                console.error('CAP SW: Failed to sync action:', error);
            }
        }
        
        // Očisti sinhronizovane akcije
        await cleanupSyncedActions();
        
    } catch (error) {
        console.error('CAP SW: Sync process error:', error);
    }
}

// IndexedDB operations
async function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('cap-offline-db', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('offline_actions')) {
                const store = db.createObjectStore('offline_actions', { 
                    keyPath: 'id', 
                    autoIncrement: true 
                });
                store.createIndex('synced', 'synced', { unique: false });
            }
            
            if (!db.objectStoreNames.contains('visit_data')) {
                db.createObjectStore('visit_data', { keyPath: 'id' });
            }
        };
    });
}

async function storeOfflineAction(action) {
    const db = await openDB();
    const transaction = db.transaction(['offline_actions'], 'readwrite');
    const store = transaction.objectStore('offline_actions');
    
    return new Promise((resolve, reject) => {
        const request = store.add(action);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function storeVisitData(data) {
    const db = await openDB();
    const transaction = db.transaction(['visit_data'], 'readwrite');
    const store = transaction.objectStore('visit_data');
    
    return new Promise((resolve, reject) => {
        const request = store.put(data);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getPendingActions() {
    const db = await openDB();
    const transaction = db.transaction(['offline_actions'], 'readonly');
    const store = transaction.objectStore('offline_actions');
    
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => {
            // Filter for non-synced actions
            const actions = request.result.filter(action => !action.synced);
            resolve(actions);
        };
        request.onerror = () => reject(request.error);
    });
}

async function updateOfflineAction(action) {
    const db = await openDB();
    const transaction = db.transaction(['offline_actions'], 'readwrite');
    const store = transaction.objectStore('offline_actions');
    
    return new Promise((resolve, reject) => {
        const request = store.put(action);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function cleanupSyncedActions() {
    const db = await openDB();
    const transaction = db.transaction(['offline_actions'], 'readwrite');
    const store = transaction.objectStore('offline_actions');
    
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => {
            const allActions = request.result;
            const syncedActions = allActions.filter(action => action.synced);
            const deletePromises = syncedActions
                .filter(action => action.sync_timestamp && Date.now() - action.sync_timestamp > 24 * 60 * 60 * 1000) // starije od 24h
                .map(action => {
                    return new Promise((deleteResolve) => {
                        const deleteRequest = store.delete(action.id);
                        deleteRequest.onsuccess = () => deleteResolve();
                        deleteRequest.onerror = () => deleteResolve(); // ignore errors
                    });
                });
            
            Promise.all(deletePromises).then(resolve);
        };
        request.onerror = () => reject(request.error);
    });
}

// Helper functions
function getActionType(url) {
    if (url.includes('start_visit')) return 'start_visit';
    if (url.includes('finish_visit')) return 'finish_visit';
    if (url.includes('get_services')) return 'get_services';
    return 'unknown';
}

// Message handler
self.addEventListener('message', (event) => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'GET_OFFLINE_STATUS':
            getPendingActions().then(actions => {
                event.ports[0].postMessage({
                    isOnline: navigator.onLine,
                    pendingActions: actions.length,
                    actions: actions
                });
            });
            break;
            
        case 'FORCE_SYNC':
            syncPendingActions().then(() => {
                event.ports[0].postMessage({ success: true });
            }).catch(error => {
                event.ports[0].postMessage({ success: false, error: error.message });
            });
            break;
            
        case 'ADD_TO_SYNC_QUEUE':
            storeOfflineAction(data).then(result => {
                event.ports[0].postMessage({ success: true, result: result });
            }).catch(error => {
                event.ports[0].postMessage({ success: false, error: error.message });
            });
            break;
            
        case 'GET_PENDING_SYNC_ITEMS':
            getPendingActions().then(actions => {
                event.ports[0].postMessage({ success: true, result: actions });
            }).catch(error => {
                event.ports[0].postMessage({ success: false, error: error.message });
            });
            break;
    }
});

// Force immediate activation
self.skipWaiting();

// Handle navigation requests - decide between index.php and offline.html
async function handleNavigation(request) {
    console.log('CAP SW: Handling navigation request:', request.url);
    
    // Test connectivity by trying a simple API call with timeout
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2000); // 2 second timeout
        
        const testResponse = await fetch('./api/get_services.php?ping=1', {
            method: 'HEAD',
            cache: 'no-cache',
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (testResponse.ok) {
            console.log('CAP SW: Server reachable, serving cached index.php or fetching fresh');
            // Server is reachable, try to serve fresh or cached index.php
            try {
                const freshResponse = await fetch(request);
                return freshResponse;
            } catch (e) {
                // Can't fetch fresh, serve from cache
                console.log('CAP SW: Can\'t fetch fresh, serving cached index.php');
                const cache = await caches.open(CACHE_NAME);
                const cached = await cache.match(request);
                return cached || serveOfflinePage();
            }
        } else {
            throw new Error('Server not reachable');
        }
    } catch (error) {
        console.log('CAP SW: Server not reachable, serving offline.html:', error.message);
        return serveOfflinePage();
    }
}

// Helper function to serve offline.html
async function serveOfflinePage() {
    try {
        console.log('CAP SW: Looking for offline.html');
        const cache = await caches.open(CACHE_NAME);
        
        // Try multiple paths for offline.html - including cache-busted version
        let offlinePage = await cache.match('./offline.html');
        if (!offlinePage) {
            offlinePage = await cache.match('/cap/cap_pwa/offline.html');
        }
        if (!offlinePage) {
            offlinePage = await cache.match(self.registration.scope + 'offline.html');
        }
        if (!offlinePage) {
            offlinePage = await cache.match('offline.html');
        }
        if (!offlinePage) {
            // Try to find cache-busted version
            const cachedRequests = await cache.keys();
            const offlineRequest = cachedRequests.find(req => 
                req.url.includes('offline.html')
            );
            if (offlineRequest) {
                console.log('CAP SW: Found cache-busted offline.html:', offlineRequest.url);
                offlinePage = await cache.match(offlineRequest);
            }
        }
        
        if (offlinePage) {
            console.log('CAP SW: Found and serving offline.html');
            return offlinePage;
        } else {
            console.log('CAP SW: offline.html not found in cache');
            
            // Debug - list all cached URLs
            const cachedRequests = await cache.keys();
            console.log('CAP SW: All cached URLs:', cachedRequests.map(req => req.url));
            
            // Fallback to generic offline page
            return new Response(`
                <html>
                <head>
                    <title>CAP - Offline</title>
                    <style>body { font-family: Arial; text-align: center; padding: 50px; }</style>
                </head>
                <body>
                    <h1>CAP - Offline</h1>
                    <p>Nema internet konekcije. Molimo pokušajte ponovo.</p>
                    <button onclick="location.reload()">Pokušaj ponovo</button>
                </body>
                </html>
            `, { headers: { 'Content-Type': 'text/html' } });
        }
    } catch (e) {
        console.log('CAP SW: Error serving offline page:', e);
        return new Response('Offline', { headers: { 'Content-Type': 'text/plain' } });
    }
}

console.log('CAP Service Worker v1.2.7 loaded successfully - Smart navigation with connectivity test');