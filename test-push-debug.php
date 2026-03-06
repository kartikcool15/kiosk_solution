<?php
/**
 * Push Notification Debug Script
 * 
 * Run this file directly in browser to see detailed diagnostic information
 * URL: https://mponline.local/wp-content/themes/railways/test-push-debug.php
 */

// Load WordPress
require_once('../../../../../wp-load.php');

// Security check - only for administrators
if (!current_user_can('manage_options')) {
    die('Access denied. Admin only.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Push Notification Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        .section { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #2271b1; }
        .error { border-left-color: #d63638; }
        .success { border-left-color: #46b450; }
        .warning { border-left-color: #f0b849; }
        h2 { margin-top: 0; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🔔 Push Notification Debug Information</h1>
    
    <?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'push_subscriptions';
    
    // Check VAPID keys
    $vapid_public = get_option('push_vapid_public_key');
    $vapid_private = get_option('push_vapid_private_key');
    $notifications_enabled = get_option('push_notifications_enabled', true);
    
    echo '<div class="section ' . (empty($vapid_public) || empty($vapid_private) ? 'error' : 'success') . '">';
    echo '<h2>VAPID Keys Configuration</h2>';
    echo '<table>';
    echo '<tr><th>Setting</th><th>Status</th></tr>';
    echo '<tr><td>Public Key Set</td><td>' . (!empty($vapid_public) ? '✓ Yes (' . strlen($vapid_public) . ' characters)' : '✗ No') . '</td></tr>';
    echo '<tr><td>Private Key Set</td><td>' . (!empty($vapid_private) ? '✓ Yes (' . strlen($vapid_private) . ' characters)' : '✗ No') . '</td></tr>';
    echo '<tr><td>Notifications Enabled</td><td>' . ($notifications_enabled ? '✓ Yes' : '✗ No') . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // Check web-push library
    echo '<div class="section ' . (class_exists('Minishlink\WebPush\WebPush') ? 'success' : 'error') . '">';
    echo '<h2>Web Push Library</h2>';
    if (class_exists('Minishlink\WebPush\WebPush')) {
        echo '<p>✓ Library is installed and loaded</p>';
        try {
            $reflection = new ReflectionClass('Minishlink\WebPush\WebPush');
            echo '<p>Location: ' . $reflection->getFileName() . '</p>';
        } catch (Exception $e) {
            echo '<p>Could not get library location</p>';
        }
    } else {
        echo '<p>✗ Library NOT found. Run: <code>composer require minishlink/web-push</code></p>';
    }
    echo '</div>';
    
    // Check subscriptions
    $subscriptions = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
    echo '<div class="section ' . (count($subscriptions) > 0 ? 'success' : 'warning') . '">';
    echo '<h2>Subscriptions</h2>';
    echo '<p>Total subscriptions: <strong>' . count($subscriptions) . '</strong></p>';
    
    if (count($subscriptions) > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Endpoint</th><th>Public Key</th><th>Auth Token</th><th>Created</th></tr>';
        foreach ($subscriptions as $sub) {
            echo '<tr>';
            echo '<td>' . esc_html($sub['id']) . '</td>';
            echo '<td>' . esc_html(substr($sub['endpoint'], 0, 50)) . '...</td>';
            echo '<td>' . (!empty($sub['public_key']) ? '✓ (' . strlen($sub['public_key']) . ' chars)' : '✗ Missing') . '</td>';
            echo '<td>' . (!empty($sub['auth_token']) ? '✓ (' . strlen($sub['auth_token']) . ' chars)' : '✗ Missing') . '</td>';
            echo '<td>' . esc_html($sub['created_at']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // Test VAPID key format
    if (!empty($vapid_public) && !empty($vapid_private)) {
        echo '<div class="section">';
        echo '<h2>VAPID Key Format Test</h2>';
        
        // Check if keys are URL-safe base64
        $public_valid = preg_match('/^[A-Za-z0-9_-]+$/', $vapid_public);
        $private_valid = preg_match('/^[A-Za-z0-9_-]+$/', $vapid_private);
        
        echo '<p>Public Key Format: ' . ($public_valid ? '✓ Valid URL-safe base64' : '✗ Invalid format') . '</p>';
        echo '<p>Private Key Format: ' . ($private_valid ? '✓ Valid URL-safe base64' : '✗ Invalid format') . '</p>';
        
        // Try to create WebPush instance
        if (class_exists('Minishlink\WebPush\WebPush')) {
            try {
                $auth = array(
                    'VAPID' => array(
                        'subject' => get_bloginfo('url'),
                        'publicKey' => $vapid_public,
                        'privateKey' => $vapid_private,
                    ),
                );
                $webPush = new \Minishlink\WebPush\WebPush($auth);
                echo '<p style="color: #46b450;">✓ Successfully created WebPush instance with VAPID keys</p>';
            } catch (Exception $e) {
                echo '<p style="color: #d63638;">✗ Failed to create WebPush instance: ' . esc_html($e->getMessage()) . '</p>';
                echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
            }
        }
        echo '</div>';
    }
    
    // Check recent logs
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        echo '<div class="section">';
        echo '<h2>Recent Push Notification Logs (Last 30)</h2>';
        
        $lines = array();
        try {
            $file = new SplFileObject($log_file);
            $file->seek(PHP_INT_MAX);
            $total_lines = $file->key();
            $start = max(0, $total_lines - 200);
            $file->seek($start);
            
            while (!$file->eof()) {
                $line = $file->fgets();
                if (stripos($line, 'Push Notification') !== false) {
                    $lines[] = $line;
                }
            }
            
            $recent = array_slice($lines, -30);
            if (count($recent) > 0) {
                echo '<pre style="max-height: 400px; overflow-y: auto;">';
                foreach ($recent as $line) {
                    echo esc_html($line);
                }
                echo '</pre>';
            } else {
                echo '<p>No push notification logs found in debug.log</p>';
            }
        } catch (Exception $e) {
            echo '<p>Error reading log file: ' . esc_html($e->getMessage()) . '</p>';
        }
        echo '</div>';
    } else {
        echo '<div class="section warning">';
        echo '<h2>Debug Log</h2>';
        echo '<p>⚠ debug.log file not found at: ' . esc_html($log_file) . '</p>';
        echo '<p>Enable debug logging in wp-config.php:</p>';
        echo '<pre>define(\'WP_DEBUG\', true);\ndefine(\'WP_DEBUG_LOG\', true);\ndefine(\'WP_DEBUG_DISPLAY\', false);</pre>';
        echo '</div>';
    }
    
    // Last send stats
    $last_send = get_option('push_notification_last_send');
    if ($last_send) {
        echo '<div class="section">';
        echo '<h2>Last Notification Send Stats</h2>';
        echo '<table>';
        echo '<tr><th>Field</th><th>Value</th></tr>';
        foreach ($last_send as $key => $value) {
            echo '<tr><td>' . esc_html($key) . '</td><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    ?>
    
    <div class="section">
        <h2>Next Steps</h2>
        <ol>
            <li>Verify all sections above show ✓ (green checkmarks)</li>
            <li>Go to Settings → Push Notifications and click "Send Test Notification"</li>
            <li>Reload this page to see the new logs</li>
            <li>Check the "Recent Push Notification Logs" section for error details</li>
        </ol>
    </div>
    
    <p><a href="<?php echo admin_url('options-general.php?page=push-notifications'); ?>">← Back to Push Notifications Settings</a></p>
</body>
</html>
