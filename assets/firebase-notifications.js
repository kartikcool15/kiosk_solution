/**
 * Firebase Cloud Messaging - Client Side
 * Handles notification subscription and token management
 */

class FirebaseNotifications {
    constructor() {
        this.messaging = null;
        this.currentToken = null;
        this.initialized = false;
        
        // Get Firebase config from WordPress localized script
        this.config = window.fcmConfig || {};
    }

    /**
     * Initialize Firebase
     */
    async init() {
        if (this.initialized) {
            return true;
        }

        try {
            // Check if Firebase is loaded
            if (typeof firebase === 'undefined') {
                console.error('Firebase SDK not loaded');
                return false;
            }

            // Initialize Firebase app first
            if (!firebase.apps.length) {
                firebase.initializeApp(this.config.firebaseConfig);
            }

            // Get Firebase Messaging instance (it will handle service worker registration)
            this.messaging = firebase.messaging();

            // Handle foreground messages (when page is open)
            this.messaging.onMessage((payload) => {
                console.log('✅ Foreground message received:', payload);
                this.showForegroundNotification(payload);
            });

            this.initialized = true;
            console.log('✅ Firebase initialized successfully');
            return true;

        } catch (error) {
            console.error('❌ Error initializing Firebase:', error);
            return false;
        }
    }

    /**
     * Request notification permission and get FCM token
     */
    async subscribe() {
        try {
            console.log('🔔 Starting subscription process...');
            
            // Initialize if not already done
            if (!await this.init()) {
                throw new Error('Firebase initialization failed');
            }

            // Check if notifications are supported
            if (!('Notification' in window)) {
                throw new Error('This browser does not support notifications');
            }

            console.log('Current permission:', Notification.permission);

            // Request permission
            const permission = await Notification.requestPermission();
            console.log('Permission result:', permission);
            
            if (permission !== 'granted') {
                throw new Error('Notification permission denied');
            }

            // Get FCM token (Firebase handles service worker registration automatically)
            console.log('Requesting FCM token...');
            const token = await this.messaging.getToken({
                vapidKey: this.config.vapidKey
            });

            if (!token) {
                throw new Error('No registration token available');
            }

            console.log('✅ FCM Token received:', token);
            this.currentToken = token;

            // Send token to server
            await this.sendTokenToServer(token);
            
            console.log('✅ Subscription completed successfully');

            return token;

        } catch (error) {
            console.error('❌ Error subscribing to notifications:', error);
            throw error;
        }
    }

    /**
     * Unsubscribe from notifications
     */
    async unsubscribe() {
        try {
            if (!this.messaging || !this.currentToken) {
                return true;
            }

            // Delete the token
            await this.messaging.deleteToken();

            // Remove from server
            await this.removeTokenFromServer(this.currentToken);

            this.currentToken = null;
            return true;

        } catch (error) {
            console.error('Error unsubscribing:', error);
            throw error;
        }
    }

    /**
     * Send FCM token to WordPress backend
     */
    async sendTokenToServer(token) {
        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'fcm_subscribe',
                    nonce: this.config.nonce,
                    token: token,
                    device_info: JSON.stringify({
                        userAgent: navigator.userAgent,
                        platform: navigator.platform,
                        language: navigator.language
                    })
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data?.message || 'Failed to save token');
            }

            console.log('Token saved to server:', data);
            return data;

        } catch (error) {
            console.error('Error sending token to server:', error);
            throw error;
        }
    }

    /**
     * Remove FCM token from WordPress backend
     */
    async removeTokenFromServer(token) {
        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'fcm_unsubscribe',
                    nonce: this.config.nonce,
                    token: token
                })
            });

            const data = await response.json();
            console.log('Token removed from server:', data);
            return data;

        } catch (error) {
            console.error('Error removing token from server:', error);
            throw error;
        }
    }

    /**
     * Show notification when app is in foreground
     */
    showForegroundNotification(payload) {
        console.log('📢 Showing foreground notification:', payload);
        
        const title = payload.notification?.title || payload.data?.title || 'New Notification';
        const options = {
            body: payload.notification?.body || payload.data?.body || '',
            icon: payload.notification?.icon || payload.data?.icon || this.config.defaultIcon || 'https://govtjobsexams.com/wp-content/uploads/2026/03/Bold-Logo-with-Checkmark-and-Building-Silhouette-1.png',
            data: payload.data,
            requireInteraction: true, // Keep notification visible until user clicks
            tag: payload.data?.postId || 'notification-' + Date.now(),
            vibrate: [200, 100, 200]
            // Remove badge - it's causing 404 and not essential
        };

        console.log('Notification title:', title);
        console.log('Notification options:', options);
        console.log('Notification permission:', Notification.permission);

        // Show notification immediately
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.ready.then((registration) => {
                console.log('✅ Showing via Service Worker');
                registration.showNotification(title, options)
                    .then(() => console.log('✅ Notification displayed successfully'))
                    .catch(err => console.error('❌ Error showing notification:', err));
            });
        } else if (Notification.permission === 'granted') {
            // Fallback to basic notification if service worker not ready
            console.log('⚠️ Showing via fallback Notification API');
            try {
                const notification = new Notification(title, options);
                notification.onclick = () => {
                    window.focus();
                    notification.close();
                    
                    // Track click
                    if (payload.data?.postId) {
                        this.trackNotificationClick(payload.data.postId);
                    }
                    
                    if (payload.data?.url) {
                        window.location.href = payload.data.url;
                    }
                };
                console.log('✅ Fallback notification created');
            } catch (err) {
                console.error('❌ Error creating fallback notification:', err);
            }
        } else {
            console.error('❌ Cannot show notification - permission:', Notification.permission);
        }
    }
    
    /**
     * Track notification click
     */
    trackNotificationClick(postId) {
        fetch(this.config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'fcm_track_click',
                postId: postId
            })
        }).then(response => response.json())
          .then(data => console.log('✅ Click tracked:', data))
          .catch(err => console.error('❌ Failed to track click:', err));
    }

    /**
     * Check current notification permission status
     */
    getPermissionStatus() {
        if (!('Notification' in window)) {
            return 'unsupported';
        }
        return Notification.permission;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Attach to window for global access
    window.firebaseNotifications = new FirebaseNotifications();
    
    // Handle subscribe button if exists
    const subscribeBtn = document.getElementById('fcm-subscribe-btn');
    const unsubscribeBtn = document.getElementById('fcm-unsubscribe-btn');
    const statusDiv = document.getElementById('fcm-status');

    if (subscribeBtn) {
        subscribeBtn.addEventListener('click', async () => {
            try {
                subscribeBtn.disabled = true;
                subscribeBtn.textContent = 'Subscribing...';
                
                await window.firebaseNotifications.subscribe();
                
                // Store subscription state
                localStorage.setItem('fcm_subscribed', 'true');
                
                if (statusDiv) statusDiv.textContent = '✅ Subscribed to notifications';
                if (subscribeBtn) subscribeBtn.style.display = 'none';
                if (unsubscribeBtn) unsubscribeBtn.style.display = 'inline-block';
                
                // Dispatch event for sidebar update
                document.dispatchEvent(new CustomEvent('fcm-subscription-changed'));
                
            } catch (error) {
                if (statusDiv) statusDiv.textContent = '❌ ' + error.message;
                subscribeBtn.disabled = false;
                subscribeBtn.textContent = 'Subscribe to Notifications';
            }
        });
    }

    if (unsubscribeBtn) {
        unsubscribeBtn.addEventListener('click', async () => {
            try {
                unsubscribeBtn.disabled = true;
                unsubscribeBtn.textContent = 'Unsubscribing...';
                
                await window.firebaseNotifications.unsubscribe();
                
                // Remove subscription state
                localStorage.removeItem('fcm_subscribed');
                
                if (statusDiv) statusDiv.textContent = '✅ Unsubscribed from notifications';
                if (unsubscribeBtn) unsubscribeBtn.style.display = 'none';
                if (subscribeBtn) {
                    subscribeBtn.style.display = 'inline-block';
                    subscribeBtn.disabled = false;
                    subscribeBtn.textContent = 'Subscribe to Notifications';
                }
                
                // Dispatch event for sidebar update
                document.dispatchEvent(new CustomEvent('fcm-subscription-changed'));
                
            } catch (error) {
                if (statusDiv) statusDiv.textContent = '❌ ' + error.message;
                unsubscribeBtn.disabled = false;
                unsubscribeBtn.textContent = 'Unsubscribe';
            }
        });
    }
    
    // Check initial subscription state and update UI
    const isSubscribed = localStorage.getItem('fcm_subscribed') === 'true';
    if (isSubscribed) {
        if (subscribeBtn) subscribeBtn.style.display = 'none';
        if (unsubscribeBtn) unsubscribeBtn.style.display = 'inline-block';
        if (statusDiv) statusDiv.textContent = '✅ Notifications enabled';
    } else {
        if (subscribeBtn) subscribeBtn.style.display = 'inline-block';
        if (unsubscribeBtn) unsubscribeBtn.style.display = 'none';
        if (statusDiv) statusDiv.textContent = '';
    }
});
