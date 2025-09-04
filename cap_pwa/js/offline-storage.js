/**
 * CAP Offline Storage Manager
 * Manages offline data storage and synchronization
 */

class OfflineStorageManager {
    constructor() {
        this.dbName = 'cap-offline-storage';
        this.dbVersion = 1;
        this.db = null;
        this.init();
    }

    async init() {
        try {
            this.db = await this.openDB();
            console.log('CAP Offline Storage: Initialized successfully');
        } catch (error) {
            console.error('CAP Offline Storage: Initialization failed:', error);
        }
    }

    openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Store za posete
                if (!db.objectStoreNames.contains('visits')) {
                    const visitStore = db.createObjectStore('visits', { 
                        keyPath: 'id',
                        autoIncrement: true 
                    });
                    visitStore.createIndex('status', 'status', { unique: false });
                    visitStore.createIndex('date', 'date', { unique: false });
                    visitStore.createIndex('user_id', 'user_id', { unique: false });
                    visitStore.createIndex('sticanik_id', 'sticanik_id', { unique: false });
                }
                
                // Store za štićenike
                if (!db.objectStoreNames.contains('sticenike')) {
                    const sticenikStore = db.createObjectStore('sticenike', { 
                        keyPath: 'id' 
                    });
                    sticenikStore.createIndex('user_id', 'user_id', { unique: false });
                }
                
                // Store za usluge
                if (!db.objectStoreNames.contains('services')) {
                    db.createObjectStore('services', { 
                        keyPath: 'id' 
                    });
                }
                
                // Sync queue je sada u Service Worker-u, ovde ne treba
            };
        });
    }

    // Store visit data
    async storeVisit(visitData) {
        if (!this.db) await this.init();
        
        const transaction = this.db.transaction(['visits'], 'readwrite');
        const store = transaction.objectStore('visits');
        
        // Smart ID handling: for local visits use unique keys, for server visits use server ID
        const visit = {
            ...visitData,
            stored_at: Date.now(),
            synced: visitData.synced !== undefined ? visitData.synced : false
        };
        
        // For server visits (synced: true), use server ID as key
        // For local visits (synced: false), generate unique key to avoid conflicts
        if (visitData.synced === true && visitData.id) {
            visit.id = visitData.id; // Server ID as primary key
        } else if (visitData.synced === false || visitData.synced === undefined) {
            // For local visits, create unique ID based on server ID + timestamp
            visit.id = visitData.id ? `local_${visitData.id}_${Date.now()}` : `local_${Date.now()}`;
            visit.server_id = visitData.id; // Keep reference to server ID
        }
        
        return new Promise((resolve, reject) => {
            const request = store.put(visit);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Get visits for today
    async getTodaysVisits(userId, date = null) {
        if (!this.db) await this.init();
        
        const targetDate = date || new Date().toISOString().split('T')[0];
        const transaction = this.db.transaction(['visits'], 'readonly');
        const store = transaction.objectStore('visits');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => {
                const allVisits = request.result;
                console.log('CAP: All stored visits:', allVisits.map(v => ({ id: v.id, date: v.date, user_id: v.user_id, synced: v.synced })));
                console.log('CAP: Filtering for userId:', userId, 'targetDate:', targetDate);
                
                let visits = allVisits.filter(visit => 
                    visit.user_id === userId && 
                    visit.date === targetDate
                );
                
                console.log('CAP: Filtered visits for today:', visits.length);
                
                // Deduplicate based on server_id - keep most recent version
                const visitMap = new Map();
                visits.forEach(visit => {
                    const key = visit.server_id || visit.id;
                    const existing = visitMap.get(key);
                    
                    if (!existing || visit.stored_at > existing.stored_at) {
                        visitMap.set(key, visit);
                    }
                });
                
                const deduplicatedVisits = Array.from(visitMap.values());
                console.log('CAP: Deduplicated visits:', deduplicatedVisits.length);
                resolve(deduplicatedVisits);
            };
            request.onerror = () => reject(request.error);
        });
    }

    // Store štićenici data
    async storeSticenike(sticenike) {
        if (!this.db) await this.init();
        
        const transaction = this.db.transaction(['sticenike'], 'readwrite');
        const store = transaction.objectStore('sticenike');
        
        const promises = sticenike.map(sticanik => {
            return new Promise((resolve, reject) => {
                const request = store.put({
                    ...sticanik,
                    stored_at: Date.now()
                });
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        });
        
        return Promise.all(promises);
    }

    // Get štićenici for user
    async getSticenike(userId) {
        if (!this.db) await this.init();
        
        const transaction = this.db.transaction(['sticenike'], 'readonly');
        const store = transaction.objectStore('sticenike');
        const index = store.index('user_id');
        
        return new Promise((resolve, reject) => {
            const request = index.getAll(userId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Store services
    async storeServices(services) {
        if (!this.db) await this.init();
        
        const transaction = this.db.transaction(['services'], 'readwrite');
        const store = transaction.objectStore('services');
        
        const promises = services.map(service => {
            return new Promise((resolve, reject) => {
                const request = store.put({
                    ...service,
                    stored_at: Date.now()
                });
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        });
        
        return Promise.all(promises);
    }

    // Get all services
    async getServices() {
        if (!this.db) await this.init();
        
        const transaction = this.db.transaction(['services'], 'readonly');
        const store = transaction.objectStore('services');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Sync operations sada delegiramo na Service Worker
    async addToSyncQueue(action) {
        return this.postMessageToServiceWorker({
            type: 'ADD_TO_SYNC_QUEUE',
            data: action
        });
    }

    // Get pending sync items via Service Worker
    async getPendingSyncItems() {
        return this.postMessageToServiceWorker({
            type: 'GET_PENDING_SYNC_ITEMS'
        });
    }

    // Helper za komunikaciju sa Service Worker
    async postMessageToServiceWorker(message) {
        if (!('serviceWorker' in navigator)) {
            throw new Error('Service Worker nije podržan u ovom browseru');
        }
        
        if (!navigator.serviceWorker.controller) {
            // Wait a bit for Service Worker to activate
            await new Promise(resolve => setTimeout(resolve, 500));
            
            if (!navigator.serviceWorker.controller) {
                throw new Error('Service Worker nije dostupan');
            }
        }
        
        return new Promise((resolve, reject) => {
            const messageChannel = new MessageChannel();
            
            messageChannel.port1.onmessage = (event) => {
                if (event.data.success) {
                    resolve(event.data.result);
                } else {
                    reject(new Error(event.data.error || 'Service Worker greška'));
                }
            };
            
            navigator.serviceWorker.controller.postMessage(message, [messageChannel.port2]);
            
            // Timeout nakon 3 sekunde za brže error handling
            setTimeout(() => {
                reject(new Error('Service Worker timeout'));
            }, 3000);
        });
    }

    // Clean up old and duplicate visits
    async cleanupVisits(userId = null, keepDays = 7) {
        if (!this.db) await this.init();
        
        const transaction = this.db.transaction(['visits'], 'readwrite');
        const store = transaction.objectStore('visits');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = async () => {
                const allVisits = request.result;
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - keepDays);
                const cutoffDateString = cutoffDate.toISOString().split('T')[0];
                
                let deletedCount = 0;
                const keysToDelete = [];
                
                // Find visits to delete
                for (const visit of allVisits) {
                    let shouldDelete = false;
                    
                    // Delete if no date field
                    if (!visit.date) {
                        console.log('CAP: Deleting visit without date:', visit.id);
                        shouldDelete = true;
                    }
                    
                    // Delete if older than keepDays
                    else if (visit.date < cutoffDateString) {
                        console.log('CAP: Deleting old visit:', visit.id, visit.date);
                        shouldDelete = true;
                    }
                    
                    // Delete if wrong user (if userId specified)
                    else if (userId && visit.user_id !== userId) {
                        console.log('CAP: Deleting visit for different user:', visit.id, visit.user_id);
                        shouldDelete = true;
                    }
                    
                    // Delete if invalid format
                    else if (visit.date && !visit.date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        console.log('CAP: Deleting visit with invalid date format:', visit.id, visit.date);
                        shouldDelete = true;
                    }
                    
                    if (shouldDelete) {
                        keysToDelete.push(visit.id);
                        deletedCount++;
                    }
                }
                
                // Delete the visits
                const deletePromises = keysToDelete.map(key => {
                    return new Promise((resolveDelete) => {
                        const deleteRequest = store.delete(key);
                        deleteRequest.onsuccess = () => resolveDelete();
                        deleteRequest.onerror = () => {
                            console.error('CAP: Error deleting visit:', key);
                            resolveDelete();
                        };
                    });
                });
                
                await Promise.all(deletePromises);
                
                console.log(`CAP: Cleanup completed. Deleted ${deletedCount} visits`);
                resolve(deletedCount);
            };
            request.onerror = () => reject(request.error);
        });
    }

    // Get storage statistics
    async getStorageStats() {
        if (!this.db) await this.init();
        
        const stats = {
            visits: 0,
            sticenike: 0,
            services: 0,
            pending_sync: 0
        };
        
        // Count visits
        const visitTransaction = this.db.transaction(['visits'], 'readonly');
        const visitStore = visitTransaction.objectStore('visits');
        stats.visits = await new Promise(resolve => {
            const countRequest = visitStore.count();
            countRequest.onsuccess = () => resolve(countRequest.result);
            countRequest.onerror = () => resolve(0);
        });
        
        // Count štićenici
        const sticenikTransaction = this.db.transaction(['sticenike'], 'readonly');
        const sticenikStore = sticenikTransaction.objectStore('sticenike');
        stats.sticenike = await new Promise(resolve => {
            const countRequest = sticenikStore.count();
            countRequest.onsuccess = () => resolve(countRequest.result);
            countRequest.onerror = () => resolve(0);
        });
        
        // Count services
        const serviceTransaction = this.db.transaction(['services'], 'readonly');
        const serviceStore = serviceTransaction.objectStore('services');
        stats.services = await new Promise(resolve => {
            const countRequest = serviceStore.count();
            countRequest.onsuccess = () => resolve(countRequest.result);
            countRequest.onerror = () => resolve(0);
        });
        
        // Get pending sync count from Service Worker
        try {
            const pendingItems = await this.getPendingSyncItems();
            stats.pending_sync = Array.isArray(pendingItems) ? pendingItems.length : 0;
        } catch (error) {
            console.log('Could not get pending sync count:', error);
            stats.pending_sync = 0;
        }
        
        return stats;
    }
}

// Global instance
window.OfflineStorage = new OfflineStorageManager();