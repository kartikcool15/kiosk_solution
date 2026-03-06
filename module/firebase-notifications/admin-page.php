<div class="wrap">
    <h1>Firebase Cloud Messaging Settings</h1>
    
    <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1;">
        <h2>📊 Statistics</h2>
        <p><strong>Total Subscribers:</strong> <?php echo number_format($total_tokens); ?></p>
        <p style="margin-top: 15px;">
            <a href="<?php echo admin_url('options-general.php?page=fcm-analytics'); ?>" class="button button-secondary">
                📈 View Detailed Analytics
            </a>
            <button id="fcm-test-btn" class="button button-primary" style="margin-left: 10px;">Send Test Notification to All Subscribers</button>
        </p>
        <div id="fcm-test-result" style="margin-top: 10px;"></div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('fcm_settings_nonce'); ?>
        
        <h2>Firebase Configuration</h2>
        <p>Get these values from your Firebase Console → Project Settings → General</p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="fcm_api_key">API Key</label></th>
                <td>
                    <input type="text" id="fcm_api_key" name="fcm_api_key" value="<?php echo esc_attr(get_option('fcm_api_key')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fcm_auth_domain">Auth Domain</label></th>
                <td>
                    <input type="text" id="fcm_auth_domain" name="fcm_auth_domain" value="<?php echo esc_attr(get_option('fcm_auth_domain')); ?>" class="regular-text">
                    <p class="description">Usually: your-project-id.firebaseapp.com</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fcm_project_id">Project ID</label></th>
                <td>
                    <input type="text" id="fcm_project_id" name="fcm_project_id" value="<?php echo esc_attr(get_option('fcm_project_id')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fcm_storage_bucket">Storage Bucket</label></th>
                <td>
                    <input type="text" id="fcm_storage_bucket" name="fcm_storage_bucket" value="<?php echo esc_attr(get_option('fcm_storage_bucket')); ?>" class="regular-text">
                    <p class="description">Usually: your-project-id.appspot.com</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fcm_messaging_sender_id">Messaging Sender ID</label></th>
                <td>
                    <input type="text" id="fcm_messaging_sender_id" name="fcm_messaging_sender_id" value="<?php echo esc_attr(get_option('fcm_messaging_sender_id')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fcm_app_id">App ID</label></th>
                <td>
                    <input type="text" id="fcm_app_id" name="fcm_app_id" value="<?php echo esc_attr(get_option('fcm_app_id')); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <h2>Cloud Messaging Configuration</h2>
        <p>Get these from Firebase Console → Project Settings → Cloud Messaging</p>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="fcm_vapid_key">VAPID Key (Web Push certificates)</label></th>
                <td>
                    <input type="text" id="fcm_vapid_key" name="fcm_vapid_key" value="<?php echo esc_attr(get_option('fcm_vapid_key')); ?>" class="large-text">
                    <p class="description">Generate in Firebase Console → Project Settings → Cloud Messaging → Web Push certificates</p>
                </td>
            </tr>
        </table>

        <h2>Service Account (Required for HTTP v1 API)</h2>
        <p>The legacy Server Key is deprecated. Download service account JSON from Firebase Console.</p>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="fcm_service_account_json">Service Account JSON</label></th>
                <td>
                    <textarea id="fcm_service_account_json" name="fcm_service_account_json" rows="10" class="large-text code"><?php echo esc_textarea(get_option('fcm_service_account_json')); ?></textarea>
                    <p class="description">
                        <strong>How to get this:</strong><br>
                        1. Go to <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a><br>
                        2. Select your project<br>
                        3. Click ⚙️ (Settings) → Project settings<br>
                        4. Go to "Service accounts" tab<br>
                        5. Click "Generate new private key"<br>
                        6. Download the JSON file and paste its contents here
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings', 'primary', 'fcm_save_settings'); ?>
    </form>

    <hr>

    <h2>Setup Instructions</h2>
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc;">
        <h3>1. Create Firebase Project</h3>
        <ol>
            <li>Go to <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a></li>
            <li>Click "Add project" and follow the steps</li>
            <li>Once created, click on your project</li>
        </ol>

        <h3>2. Add Web App to Firebase</h3>
        <ol>
            <li>In Firebase Console, click the web icon (</>) to add a web app</li>
            <li>Enter a nickname for your app</li>
            <li>Copy the Firebase configuration from the setup screen</li>
            <li>Paste the values into the form above</li>
        </ol>

        <h3>3. Enable Cloud Messaging</h3>
        <ol>
            <li>Go to Project Settings → Cloud Messaging</li>
            <li>In "Web configuration", click "Generate key pair" to create VAPID key</li>
            <li>Copy the VAPID key and paste it above</li>
        </ol>

        <h3>4. Get Service Account Key</h3>
        <ol>
            <li>Go to Project Settings → Service Accounts</li>
            <li>Click "Generate new private key"</li>
            <li>Download the JSON file</li>
            <li>Open it in a text editor and copy ALL the contents</li>
            <li>Paste it in the "Service Account JSON" field above</li>
        </ol>

        <h3>5. Update Service Worker</h3>
        <ol>
            <li>Open <code>/firebase-messaging-sw.js</code> in your site root</li>
            <li>Replace the placeholder Firebase config with your actual values</li>
            <li>Save the file</li>
        </ol>

        <h3>6. Test Notifications</h3>
        <ol>
            <li>Visit your site and click "Subscribe to Notifications" in the sidebar</li>
            <li>Allow notifications when prompted</li>
            <li>Click the "Send Test Notification" button above</li>
        </ol>
    </div>

    <hr>

    <h2>Recent Subscribers</h2>
    <?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'fcm_tokens';
    $recent_tokens = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY subscribed_at DESC LIMIT 10");
    
    if ($recent_tokens) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Subscribed At</th><th>Last Active</th><th>Device Info</th></tr></thead>';
        echo '<tbody>';
        foreach ($recent_tokens as $token) {
            $device_info = json_decode($token->device_info, true);
            echo '<tr>';
            echo '<td>' . esc_html($token->id) . '</td>';
            echo '<td>' . esc_html($token->subscribed_at) . '</td>';
            echo '<td>' . esc_html($token->last_active) . '</td>';
            echo '<td>' . esc_html($device_info['userAgent'] ?? 'Unknown') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No subscribers yet.</p>';
    }
    ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#fcm-test-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $result = $('#fcm-test-result');
        
        $btn.prop('disabled', true).text('Sending...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'fcm_send_test',
                nonce: '<?php echo wp_create_nonce('fcm_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + ' (Sent: ' + response.data.sent + ', Failed: ' + response.data.failed + ')</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Send Test Notification to All Subscribers');
            }
        });
    });
});
</script>
