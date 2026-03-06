<?php
/**
 * Firebase Cloud Messaging Integration
 * Handles push notifications using Firebase
 */

class Firebase_Notifications {
    
    private $table_name;
    private $logs_table_name;
    private $firebase_config;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'fcm_tokens';
        $this->logs_table_name = $wpdb->prefix . 'fcm_notification_logs';
        
        // Initialize hooks
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_fcm_subscribe', array($this, 'handle_subscribe'));
        add_action('wp_ajax_nopriv_fcm_subscribe', array($this, 'handle_subscribe'));
        add_action('wp_ajax_fcm_unsubscribe', array($this, 'handle_unsubscribe'));
        add_action('wp_ajax_nopriv_fcm_unsubscribe', array($this, 'handle_unsubscribe'));
        add_action('wp_ajax_fcm_send_test', array($this, 'handle_test_notification'));
        add_action('wp_ajax_fcm_track_click', array($this, 'handle_track_click'));
        add_action('wp_ajax_nopriv_fcm_track_click', array($this, 'handle_track_click'));
        
        // Post publish hook
        add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);
        
        // Widget
        add_action('widgets_init', array($this, 'register_widget'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $this->create_database_table();
        $this->load_firebase_config();
    }
    
    /**
     * Load Firebase configuration from WordPress options
     */
    private function load_firebase_config() {
        $this->firebase_config = array(
            'apiKey' => get_option('fcm_api_key', ''),
            'authDomain' => get_option('fcm_auth_domain', ''),
            'projectId' => get_option('fcm_project_id', ''),
            'storageBucket' => get_option('fcm_storage_bucket', ''),
            'messagingSenderId' => get_option('fcm_messaging_sender_id', ''),
            'appId' => get_option('fcm_app_id', ''),
            'vapidKey' => get_option('fcm_vapid_key', ''),
            'serviceAccountJson' => get_option('fcm_service_account_json', '')
        );
    }
    
    /**
     * Create database table for storing FCM tokens
     */
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tokens table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token text NOT NULL,
            device_info text,
            categories text,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_active datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token(191))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Notification logs table
        $logs_sql = "CREATE TABLE IF NOT EXISTS {$this->logs_table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notification_title varchar(255) NOT NULL,
            notification_body text,
            post_id bigint(20),
            post_url varchar(500),
            total_recipients int DEFAULT 0,
            successful_sends int DEFAULT 0,
            failed_sends int DEFAULT 0,
            clicks int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($logs_sql);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Firebase SDK
        wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js', array(), '10.8.0', false);
        wp_enqueue_script('firebase-messaging', 'https://www.gstatic.com/firebasejs/10.8.0/firebase-messaging-compat.js', array('firebase-app'), '10.8.0', false);
        
        // Custom Firebase notifications script
        wp_enqueue_script(
            'firebase-notifications',
            get_template_directory_uri() . '/assets/firebase-notifications.js',
            array('firebase-app', 'firebase-messaging'),
            '1.0.0',
            true
        );
        
        // Get site icon or use default
        $site_icon = get_site_icon_url(192);
        if (empty($site_icon)) {
            // Fallback to a simple notification bell icon as data URI
            $site_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23667eea"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>');
        }
        
        // Localize script with config
        wp_localize_script('firebase-notifications', 'fcmConfig', array(
            'firebaseConfig' => array(
                'apiKey' => $this->firebase_config['apiKey'],
                'authDomain' => $this->firebase_config['authDomain'],
                'projectId' => $this->firebase_config['projectId'],
                'storageBucket' => $this->firebase_config['storageBucket'],
                'messagingSenderId' => $this->firebase_config['messagingSenderId'],
                'appId' => $this->firebase_config['appId']
            ),
            'vapidKey' => $this->firebase_config['vapidKey'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fcm_nonce'),
            'defaultIcon' => $site_icon
        ));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook !== 'settings_page_fcm-settings') {
            return;
        }
        
        wp_enqueue_script('fcm-admin', get_template_directory_uri() . '/assets/fcm-admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('fcm-admin', get_template_directory_uri() . '/assets/fcm-admin.css', array(), '1.0.0');
        
        wp_localize_script('fcm-admin', 'fcmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fcm_nonce')
        ));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main settings page
        add_options_page(
            'Firebase Notifications',
            'Firebase Notifications',
            'manage_options',
            'fcm-settings',
            array($this, 'render_admin_page')
        );
        
        // Analytics submenu under Settings
        add_options_page(
            'Notification Analytics',
            'FCM Analytics',
            'manage_options',
            'fcm-analytics',
            array($this, 'render_analytics_page')
        );
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if (isset($_POST['fcm_save_settings']) && check_admin_referer('fcm_settings_nonce')) {
            $this->save_settings();
        }
        
        global $wpdb;
        $total_tokens = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"));
        
        include get_template_directory() . '/module/firebase-notifications/admin-page.php';
    }
    
    /**
     * Save Firebase settings
     */
    private function save_settings() {
        update_option('fcm_api_key', sanitize_text_field($_POST['fcm_api_key']));
        update_option('fcm_auth_domain', sanitize_text_field($_POST['fcm_auth_domain']));
        update_option('fcm_project_id', sanitize_text_field($_POST['fcm_project_id']));
        update_option('fcm_storage_bucket', sanitize_text_field($_POST['fcm_storage_bucket']));
        update_option('fcm_messaging_sender_id', sanitize_text_field($_POST['fcm_messaging_sender_id']));
        update_option('fcm_app_id', sanitize_text_field($_POST['fcm_app_id']));
        update_option('fcm_vapid_key', sanitize_text_field($_POST['fcm_vapid_key']));
        update_option('fcm_service_account_json', wp_unslash($_POST['fcm_service_account_json']));
        
        $this->load_firebase_config();
        
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    /**
     * Handle subscription AJAX request
     */
    public function handle_subscribe() {
        check_ajax_referer('fcm_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token']);
        $device_info = sanitize_text_field($_POST['device_info']);
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Token is required'));
            return;
        }
        
        global $wpdb;
        
        // Check if token already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE token = %s",
            $token
        ));
        
        if ($existing) {
            // Update existing token
            $wpdb->update(
                $this->table_name,
                array(
                    'device_info' => $device_info,
                    'last_active' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new token
            $wpdb->insert(
                $this->table_name,
                array(
                    'token' => $token,
                    'device_info' => $device_info,
                    'subscribed_at' => current_time('mysql'),
                    'last_active' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );
        }
        
        wp_send_json_success(array('message' => 'Subscribed successfully'));
    }
    
    /**
     * Handle unsubscribe AJAX request
     */
    public function handle_unsubscribe() {
        check_ajax_referer('fcm_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token']);
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Token is required'));
            return;
        }
        
        global $wpdb;
        
        $wpdb->delete(
            $this->table_name,
            array('token' => $token),
            array('%s')
        );
        
        wp_send_json_success(array('message' => 'Unsubscribed successfully'));
    }
    
    /**
     * Get OAuth 2.0 access token from service account
     */
    private function get_access_token() {
        $service_account_json = $this->firebase_config['serviceAccountJson'];
        
        if (empty($service_account_json)) {
            error_log('❌ FCM: Service account JSON not configured in WordPress settings');
            error_log('❌ FCM: Go to Settings → FCM Settings to add service account JSON');
            return false;
        }
        
        $service_account = json_decode($service_account_json, true);
        
        if (!$service_account || !isset($service_account['private_key']) || !isset($service_account['client_email'])) {
            error_log('❌ FCM: Invalid service account JSON - missing private_key or client_email');
            return false;
        }
        
        error_log('🔔 FCM: Service account email: ' . $service_account['client_email']);
        
        // Create JWT
        $now = time();
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        );
        
        $payload = array(
            'iss' => $service_account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        );
        
        $segments = array();
        $segments[] = $this->base64url_encode(json_encode($header));
        $segments[] = $this->base64url_encode(json_encode($payload));
        $signing_input = implode('.', $segments);
        
        // Sign with private key
        $signature = '';
        openssl_sign($signing_input, $signature, $service_account['private_key'], 'SHA256');
        $segments[] = $this->base64url_encode($signature);
        
        $jwt = implode('.', $segments);
        
        // Exchange JWT for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('FCM OAuth Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['access_token'])) {
            return $result['access_token'];
        }
        
        error_log('FCM OAuth Error: ' . $body);
        return false;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Send notification via Firebase Cloud Messaging HTTP v1 API
     */
    public function send_fcm_notification($tokens, $title, $body, $data = array()) {
        global $wpdb;
        
        error_log('🔔 FCM: send_fcm_notification called with ' . count((array)$tokens) . ' tokens');
        error_log('🔔 FCM: Title: ' . $title);
        error_log('🔔 FCM: Body: ' . $body);
        
        $project_id = $this->firebase_config['projectId'];
        
        if (empty($project_id)) {
            error_log('❌ FCM: Project ID not configured');
            return false;
        }
        
        error_log('🔔 FCM: Project ID: ' . $project_id);
        error_log('🔔 FCM: Getting access token...');
        
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            error_log('❌ FCM: Failed to get access token');
            return false;
        }
        
        error_log('✅ FCM: Access token obtained successfully');
        
        // Get site icon or use default
        $notification_icon = get_site_icon_url(192);
        if (empty($notification_icon)) {
            // Use a data URI for notification bell icon
            $notification_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23667eea"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>');
        }
        
        $success_count = 0;
        $failure_count = 0;
        
        // FCM HTTP v1 API requires sending to one token at a time
        $tokens_array = is_array($tokens) ? $tokens : array($tokens);
        
        error_log('🔔 FCM: Sending to ' . count($tokens_array) . ' tokens...');
        
        foreach ($tokens_array as $index => $token) {
            error_log('🔔 FCM: Sending to token #' . ($index + 1) . ': ' . substr($token, 0, 20) . '...');
            
            // Prepare notification payload
            $message = array(
                'message' => array(
                    'token' => $token,
                    'notification' => array(
                        'title' => $title,
                        'body' => $body
                    ),
                    'data' => array_map('strval', $data), // FCM v1 requires string values
                    'webpush' => array(
                        'notification' => array(
                            'icon' => $notification_icon
                        ),
                        'fcm_options' => array(
                            'link' => $data['url'] ?? home_url('/')
                        )
                    )
                )
            );
            
            error_log('🔔 FCM: Payload: ' . json_encode($message));
            
            // Send request to FCM v1 API
            $response = wp_remote_post("https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send", array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($message),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                error_log('❌ FCM Error for token #' . ($index + 1) . ': ' . $response->get_error_message());
                $failure_count++;
                continue;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                $success_count++;
                error_log('✅ FCM Success for token #' . ($index + 1) . ': ' . $response_body);
            } else {
                $failure_count++;
                error_log('❌ FCM Failed for token #' . ($index + 1) . ' (HTTP ' . $response_code . '): ' . $response_body);
                
                // Auto-remove unregistered tokens
                $response_data = json_decode($response_body, true);
                if (isset($response_data['error']['details'][0]['errorCode']) && 
                    $response_data['error']['details'][0]['errorCode'] === 'UNREGISTERED') {
                    $wpdb->delete($this->table_name, array('token' => $token), array('%s'));
                    error_log('🗑️ FCM: Removed unregistered token from database');
                }
            }
        }
        
        // Log notification send to database
        $wpdb->insert(
            $this->logs_table_name,
            array(
                'notification_title' => $title,
                'notification_body' => $body,
                'post_id' => isset($data['postId']) ? intval($data['postId']) : 0,
                'post_url' => isset($data['url']) ? $data['url'] : '',
                'total_recipients' => count($tokens_array),
                'successful_sends' => $success_count,
                'failed_sends' => $failure_count,
                'clicks' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s')
        );
        
        error_log('📊 FCM: Logged notification #' . $wpdb->insert_id . ' to database');
        
        return array(
            'success' => $success_count,
            'failure' => $failure_count,
            'log_id' => $wpdb->insert_id
        );
    }
    
    /**
     * Handle test notification
     */
    public function handle_test_notification() {
        check_ajax_referer('fcm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        global $wpdb;
        
        $tokens = $wpdb->get_col("SELECT token FROM {$this->table_name}");
        
        if (empty($tokens)) {
            wp_send_json_error(array('message' => 'No subscribers found'));
            return;
        }
        
        $result = $this->send_fcm_notification(
            $tokens,
            'Test Notification',
            'This is a test notification from ' . get_bloginfo('name'),
            array(
                'url' => home_url('/'),
                'postId' => '0'
            )
        );
        
        if ($result && isset($result['success']) && $result['success'] > 0) {
            wp_send_json_success(array(
                'message' => 'Test notification sent',
                'sent' => $result['success'],
                'failed' => $result['failure']
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to send notification'));
        }
    }
    
    /**
     * Handle notification click tracking
     */
    public function handle_track_click() {
        global $wpdb;
        
        $post_id = isset($_POST['postId']) ? intval($_POST['postId']) : 0;
        
        if ($post_id > 0) {
            // Find the most recent notification for this post
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT id, clicks FROM {$this->logs_table_name} WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
                $post_id
            ));
            
            if ($log) {
                $wpdb->update(
                    $this->logs_table_name,
                    array('clicks' => $log->clicks + 1),
                    array('id' => $log->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        wp_send_json_success(array('tracked' => true));
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        global $wpdb;
        
        // Get statistics (ensure we always have numeric values, never NULL)
        $total_subscribers = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"));
        $total_notifications = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->logs_table_name}"));
        $total_delivered = intval($wpdb->get_var("SELECT SUM(successful_sends) FROM {$this->logs_table_name}") ?: 0);
        $total_clicks = intval($wpdb->get_var("SELECT SUM(clicks) FROM {$this->logs_table_name}") ?: 0);
        $total_failed = intval($wpdb->get_var("SELECT SUM(failed_sends) FROM {$this->logs_table_name}") ?: 0);
        
        // Get recent notifications (last 50)
        $recent_notifications = $wpdb->get_results(
            "SELECT * FROM {$this->logs_table_name} ORDER BY created_at DESC LIMIT 50"
        );
        
        // Calculate click-through rate
        $ctr = $total_delivered > 0 ? round(($total_clicks / $total_delivered) * 100, 2) : 0;
        
        include get_template_directory() . '/module/firebase-notifications/analytics-page.php';
    }
    
    /**
     * Send notification when post is published
     */
    public function on_post_status_change($new_status, $old_status, $post) {
        error_log('🔔 FCM: Post status change detected - New: ' . $new_status . ', Old: ' . $old_status . ', Post ID: ' . $post->ID . ', Type: ' . $post->post_type);
        
        // Only send notifications for NEW publishes (not updates)
        if ($new_status !== 'publish') {
            error_log('🔔 FCM: Skipping - new status is not publish: ' . $new_status);
            return;
        }
        
        // Skip if post was already published (this is an update, not a new publish)
        if ($old_status === 'publish') {
            error_log('🔔 FCM: Skipping - post already published (this is an update)');
            return;
        }
        
        // Only for specific post types (skip revisions, auto-drafts, etc.)
        if (!in_array($post->post_type, array('post'))) {
            error_log('🔔 FCM: Skipping - wrong post type: ' . $post->post_type);
            return;
        }
        
        // Skip revisions
        if (wp_is_post_revision($post->ID)) {
            error_log('🔔 FCM: Skipping - this is a revision');
            return;
        }
        
        // Skip auto-drafts
        if ($old_status === 'auto-draft') {
            // Allow only if it's a real publish, not an auto-save
            if (!isset($_POST['publish']) && !isset($_POST['save'])) {
                error_log('🔔 FCM: Skipping - auto-draft without explicit publish action');
                // Actually, let's allow auto-draft -> publish, this is normal
                // return;
            }
        }
        
        global $wpdb;
        
        $tokens = $wpdb->get_col("SELECT token FROM {$this->table_name}");
        
        error_log('🔔 FCM: Found ' . count($tokens) . ' subscriber tokens');
        
        if (empty($tokens)) {
            error_log('🔔 FCM: No subscribers found - notification not sent');
            return;
        }
        
        $title = get_the_title($post->ID);
        $excerpt = wp_trim_words(get_the_excerpt($post->ID), 20);
        
        error_log('🔔 FCM: ✅ SENDING notification for NEW PUBLISH - Title: ' . $title . ' (Old status: ' . $old_status . ' → New status: ' . $new_status . ')');
        
        $result = $this->send_fcm_notification(
            $tokens,
            'New Post: ' . $title,
            $excerpt,
            array(
                'url' => get_permalink($post->ID),
                'postId' => (string)$post->ID,
                'category' => get_the_category($post->ID)[0]->name ?? ''
            )
        );
        
        if ($result) {
            error_log('🔔 FCM: Notification sent successfully - Success: ' . $result['success'] . ', Failed: ' . $result['failure']);
        } else {
            error_log('🔔 FCM: Notification sending failed');
        }
    }
    
    /**
     * Register notification widget
     */
    public function register_widget() {
        register_widget('FCM_Widget');
    }
}

/**
 * Firebase Notifications Widget
 */
class FCM_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'fcm_widget',
            'Firebase Notifications',
            array('description' => 'Subscribe to push notifications')
        );
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        ?>
        <div class="fcm-widget">
            <p><?php echo esc_html($instance['description'] ?? 'Get notifications when we publish new content'); ?></p>
            <button id="fcm-subscribe-btn" class="button">Subscribe to Notifications</button>
            <button id="fcm-unsubscribe-btn" class="button" style="display:none;">Unsubscribe</button>
            <div id="fcm-status" style="margin-top: 10px;"></div>
        </div>
        <?php
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Push Notifications';
        $description = !empty($instance['description']) ? $instance['description'] : 'Get notifications when we publish new content';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('description'); ?>">Description:</label>
            <textarea class="widefat" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>"><?php echo esc_textarea($description); ?></textarea>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['description'] = (!empty($new_instance['description'])) ? sanitize_text_field($new_instance['description']) : '';
        return $instance;
    }
}

// Initialize
new Firebase_Notifications();
