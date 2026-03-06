</main>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
</footer>
<?php wp_footer(); ?>

<script>
    // Update sidebar notification state
    function updateSidebarNotificationState() {
        const bellEmpty = document.getElementById('fcm-bell-empty');
        const bellFilled = document.getElementById('fcm-bell-filled');
        const sidebarText = document.getElementById('fcm-sidebar-text');
        const sidebarItem = document.getElementById('fcm-sidebar-item');

        // Exit if elements don't exist
        if (!bellEmpty || !bellFilled || !sidebarText || !sidebarItem) {
            console.log('Notification elements not found');
            return;
        }

        const isSubscribed = localStorage.getItem('fcm_subscribed') === 'true';
        console.log('Current subscription state:', isSubscribed);

        if (isSubscribed) {
            bellEmpty.style.display = 'none';
            bellFilled.style.display = 'block';
            sidebarText.textContent = 'Notification Enabled';
            sidebarItem.classList.add('active');
        } else {
            bellEmpty.style.display = 'block';
            bellFilled.style.display = 'none';
            sidebarText.textContent = 'Enable Notification';
            sidebarItem.classList.remove('active');
        }
    }

    // Handle notification subscription directly
    async function handleNotificationClick(e) {
        e.preventDefault();

        const sidebarText = document.getElementById('fcm-sidebar-text');
        const isSubscribed = localStorage.getItem('fcm_subscribed') === 'true';

        console.log('Notification clicked, current state:', isSubscribed);

        try {
            if (!window.firebaseNotifications) {
                alert('Firebase notifications not initialized');
                return;
            }

            if (isSubscribed) {
                // Unsubscribe
                if (sidebarText) sidebarText.textContent = 'Unsubscribing...';

                await window.firebaseNotifications.unsubscribe();
                localStorage.removeItem('fcm_subscribed');

                alert('✅ Unsubscribed from notifications');
                console.log('Unsubscribed successfully');

            } else {
                // Subscribe
                if (sidebarText) sidebarText.textContent = 'Subscribing...';

                await window.firebaseNotifications.subscribe();
                localStorage.setItem('fcm_subscribed', 'true');

                alert('✅ Subscribed to notifications');
                console.log('Subscribed successfully');
            }

            // Update UI
            updateSidebarNotificationState();

        } catch (error) {
            console.error('Notification error:', error);
            alert('❌ ' + error.message);

            // Reset text
            updateSidebarNotificationState();
        }
    }

    // Initialize state on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing notification state');
        updateSidebarNotificationState();

        // Handle click - attach after DOM is ready
        const sidebarLink = document.getElementById('fcm-sidebar-link');
        if (sidebarLink) {
            console.log('Notification link found, attaching click handler');
            sidebarLink.addEventListener('click', handleNotificationClick);
        } else {
            console.log('Notification link not found');
        }
    });

    // Update when subscription changes (for other buttons if they exist)
    document.addEventListener('fcm-subscription-changed', function() {
        console.log('Subscription changed event received');
        updateSidebarNotificationState();
    });
</script>

</body>

</html>