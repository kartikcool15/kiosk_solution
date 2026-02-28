<?php

/**
 * Content Automation System - Main File
 * Coordinates all content automation modules
 */

if (!defined('ABSPATH')) exit;

// Load all module dependencies
require_once dirname(__FILE__) . '/api-fetcher.php';
require_once dirname(__FILE__) . '/post-processor.php';
require_once dirname(__FILE__) . '/chatgpt-processor.php';
require_once dirname(__FILE__) . '/content-sync.php';

class Kiosk_Content_Automation
{
    private $content_sync;
    private $chatgpt_processor;
    private $api_fetcher;

    public function __construct()
    {
        // Initialize modules
        $this->api_fetcher = new Kiosk_API_Fetcher();
        $this->chatgpt_processor =new Kiosk_ChatGPT_Processor();
        $this->content_sync = new Kiosk_Content_Sync();

        // Register custom fields
        add_action('init', array($this, 'register_custom_fields'));

        // Add meta box for custom fields
        add_action('add_meta_boxes', array($this, 'add_custom_fields_meta_box'));
        add_action('save_post', array($this, 'save_custom_fields_meta_box'));

        // Enable native custom fields meta box
        add_filter('default_hidden_meta_boxes', array($this, 'show_custom_fields_meta_box'), 10, 2);
        add_action('admin_head', array($this, 'enable_custom_fields_support'));

        // Setup cron schedule
        add_filter('cron_schedules', array($this, 'custom_cron_schedules'));

        // Hook into cron (legacy compatibility)
        add_action('kiosk_fetch_content_cron', array($this, 'fetch_and_publish_content'));

        // Background processing for ChatGPT
        add_action('kiosk_process_chatgpt_queue', array($this->chatgpt_processor, 'process_queue'));

        // Admin AJAX handlers
        add_action('wp_ajax_kiosk_manual_sync', array($this, 'manual_sync'));
        add_action('wp_ajax_kiosk_force_full_sync', array($this, 'force_full_sync'));
        add_action('wp_ajax_kiosk_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_kiosk_fetch_single_post', array($this, 'fetch_single_post_ajax'));
        add_action('wp_ajax_kiosk_process_chatgpt_now', array($this, 'manual_process_chatgpt'));
        
        // New sync mode AJAX handlers
        add_action('wp_ajax_kiosk_sync_recent_created', array($this, 'ajax_sync_recent_created'));
        add_action('wp_ajax_kiosk_sync_recent_modified', array($this, 'ajax_sync_recent_modified'));
        add_action('wp_ajax_kiosk_resync_content', array($this, 'ajax_resync_content'));
        add_action('wp_ajax_kiosk_update_all_posts', array($this, 'ajax_update_all_posts'));

        // Legacy AJAX handlers
        add_action('wp_ajax_kiosk_fix_post_slugs', array($this, 'fix_post_slugs_from_chatgpt'));
        add_action('wp_ajax_kiosk_update_post_content_from_json', array($this, 'update_post_content_from_json'));

        // Date field sync: when custom date fields are updated, sync back to JSON
        add_action('updated_post_meta', array($this, 'sync_date_field_to_json'), 10, 4);

        // Admin bar manual cron trigger
        add_action('admin_bar_menu', array($this, 'add_cron_trigger_to_admin_bar'), 999);
        add_action('wp_ajax_kiosk_manual_trigger_cron', array($this, 'manual_trigger_cron'));
        add_action('admin_footer', array($this, 'add_cron_trigger_script'));
        add_action('wp_footer', array($this, 'add_cron_trigger_script'));
    }

    /**
     * Register custom post meta fields
     */
    public function register_custom_fields()
    {
        // Source Post ID (to track already imported posts)
        register_post_meta('post', 'kiosk_source_post_id', array(
            'type' => 'integer',
            'description' => 'Source Post ID from API',
            'single' => true,
            'show_in_rest' => true,
        ));

        // Full ChatGPT processed JSON
        register_post_meta('post', 'kiosk_chatgpt_json', array(
            'type' => 'string',
            'description' => 'Full ChatGPT Processed JSON Data',
            'single' => true,
            'show_in_rest' => true,
        ));

        // Raw post data for ChatGPT processing queue
        register_post_meta('post', 'kiosk_raw_post_data', array(
            'type' => 'string',
            'description' => 'Raw Post Data for ChatGPT Queue',
            'single' => true,
            'show_in_rest' => false,
        ));

        // Processing status
        register_post_meta('post', 'kiosk_processing_status', array(
            'type' => 'string',
            'description' => 'ChatGPT Processing Status (pending, processing, completed, failed)',
            'single' => true,
            'show_in_rest' => true,
        ));

        // Date Custom Fields for easier sorting and querying
        register_post_meta('post', 'kiosk_start_date', array(
            'type' => 'string',
            'description' => 'Start Date (Application Start Date)',
            'single' => true,
            'show_in_rest' => true,
        ));

        register_post_meta('post', 'kiosk_last_date', array(
            'type' => 'string',
            'description' => 'Last Date (Application End Date)',
            'single' => true,
            'show_in_rest' => true,
        ));

        register_post_meta('post', 'kiosk_exam_date', array(
            'type' => 'string',
            'description' => 'Exam Date',
            'single' => true,
            'show_in_rest' => true,
        ));

        register_post_meta('post', 'kiosk_admit_card_date', array(
            'type' => 'string',
            'description' => 'Admit Card Release Date',
            'single' => true,
            'show_in_rest' => true,
        ));

        register_post_meta('post', 'kiosk_result_date', array(
            'type' => 'string',
            'description' => 'Result Date',
            'single' => true,
            'show_in_rest' => true,
        ));

        register_post_meta('post', 'kiosk_counselling_date', array(
            'type' => 'string',
            'description' => 'Counselling Date',
            'single' => true,
            'show_in_rest' => true,
        ));

        register_post_meta('post', 'kiosk_interview_date', array(
            'type' => 'string',
            'description' => 'Interview Date',
            'single' => true,
            'show_in_rest' => true,
        ));
    }

    /**
     * Add meta box for custom fields
     */
    public function add_custom_fields_meta_box()
    {
        add_meta_box(
            'kiosk_custom_fields',
            'Content Automation Fields',
            array($this, 'render_custom_fields_meta_box'),
            'post',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_custom_fields_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('kiosk_custom_fields_meta_box', 'kiosk_custom_fields_nonce');

        // Get current values
        $source_post_id = get_post_meta($post->ID, 'kiosk_source_post_id', true);
        $processing_status = get_post_meta($post->ID, 'kiosk_processing_status', true);
        $start_date = get_post_meta($post->ID, 'kiosk_start_date', true);
        $last_date = get_post_meta($post->ID, 'kiosk_last_date', true);
        $exam_date = get_post_meta($post->ID, 'kiosk_exam_date', true);
        $admit_card_date = get_post_meta($post->ID, 'kiosk_admit_card_date', true);
        $result_date = get_post_meta($post->ID, 'kiosk_result_date', true);
        $counselling_date = get_post_meta($post->ID, 'kiosk_counselling_date', true);
        $interview_date = get_post_meta($post->ID, 'kiosk_interview_date', true);
        $chatgpt_json = get_post_meta($post->ID, 'kiosk_chatgpt_json', true);

        ?>
        <style>
            .kiosk-field-group {
                margin-bottom: 15px;
            }
            .kiosk-field-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .kiosk-field-group input[type="text"] {
                width: 100%;
                max-width: 400px;
            }
            .kiosk-field-group textarea {
                width: 100%;
                min-height: 200px;
                font-family: monospace;
                font-size: 12px;
            }
            .kiosk-section {
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }
            .kiosk-section:last-child {
                border-bottom: none;
            }
            .kiosk-section h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #23282d;
            }
            .kiosk-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .kiosk-status-pending {
                background: #f0f0f1;
                color: #646970;
            }
            .kiosk-status-processing {
                background: #fcf3cd;
                color: #826200;
            }
            .kiosk-status-completed {
                background: #d7f1dd;
                color: #1e8f3a;
            }
            .kiosk-status-failed {
                background: #f7d9d7;
                color: #d63638;
            }
        </style>

        <div class="kiosk-section">
            <h4>Source Information</h4>
            <div class="kiosk-field-group">
                <label for="kiosk_source_post_id">Source Post ID</label>
                <input type="text" id="kiosk_source_post_id" name="kiosk_source_post_id" 
                       value="<?php echo esc_attr($source_post_id); ?>" readonly 
                       style="background: #f0f0f1;">
                <p class="description">ID from the source API (read-only)</p>
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_processing_status">Processing Status</label>
                <?php if ($processing_status): ?>
                    <span class="kiosk-status-badge kiosk-status-<?php echo esc_attr($processing_status); ?>">
                        <?php echo esc_html($processing_status); ?>
                    </span>
                <?php else: ?>
                    <span class="kiosk-status-badge kiosk-status-pending">Not processed</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="kiosk-section">
            <h4>Important Dates</h4>
            <div class="kiosk-field-group">
                <label for="kiosk_start_date">Application Start Date</label>
                <input type="text" id="kiosk_start_date" name="kiosk_start_date" 
                       value="<?php echo esc_attr($start_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_last_date">Application Last Date</label>
                <input type="text" id="kiosk_last_date" name="kiosk_last_date" 
                       value="<?php echo esc_attr($last_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_exam_date">Exam Date</label>
                <input type="text" id="kiosk_exam_date" name="kiosk_exam_date" 
                       value="<?php echo esc_attr($exam_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_admit_card_date">Admit Card Release Date</label>
                <input type="text" id="kiosk_admit_card_date" name="kiosk_admit_card_date" 
                       value="<?php echo esc_attr($admit_card_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_result_date">Result Date</label>
                <input type="text" id="kiosk_result_date" name="kiosk_result_date" 
                       value="<?php echo esc_attr($result_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_counselling_date">Counselling Date</label>
                <input type="text" id="kiosk_counselling_date" name="kiosk_counselling_date" 
                       value="<?php echo esc_attr($counselling_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_interview_date">Interview Date</label>
                <input type="text" id="kiosk_interview_date" name="kiosk_interview_date" 
                       value="<?php echo esc_attr($interview_date); ?>">
            </div>
        </div>

        <div class="kiosk-section">
            <h4>ChatGPT Processed Data (JSON)</h4>
            <div class="kiosk-field-group">
                <textarea id="kiosk_chatgpt_json" name="kiosk_chatgpt_json" readonly 
                          style="background: #f0f0f1;"><?php echo esc_textarea($chatgpt_json); ?></textarea>
                <p class="description">Full JSON data from ChatGPT processing (read-only)</p>
            </div>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_custom_fields_meta_box($post_id)
    {
        // Check if nonce is set
        if (!isset($_POST['kiosk_custom_fields_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['kiosk_custom_fields_nonce'], 'kiosk_custom_fields_meta_box')) {
            return;
        }

        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save date fields (these are editable)
        $date_fields = array(
            'kiosk_start_date',
            'kiosk_last_date',
            'kiosk_exam_date',
            'kiosk_admit_card_date',
            'kiosk_result_date',
            'kiosk_counselling_date',
            'kiosk_interview_date'
        );

        foreach ($date_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Show native custom fields meta box by default
     */
    public function show_custom_fields_meta_box($hidden, $screen)
    {
        // Make sure the custom fields meta box is not hidden for posts
        if ('post' === $screen->base) {
            // Remove 'postcustom' from hidden meta boxes
            $hidden = array_diff($hidden, array('postcustom'));
        }
        return $hidden;
    }

    /**
     * Enable custom fields support
     */
    public function enable_custom_fields_support()
    {
        // Enable custom fields for block editor
        if (get_current_screen() && get_current_screen()->is_block_editor()) {
            ?>
            <script>
            // Enable custom fields in Gutenberg
            jQuery(document).ready(function($) {
                if (wp.data && wp.data.select('core/edit-post')) {
                    // Check if custom fields panel is available
                    const isCustomFieldsEnabled = wp.data.select('core/edit-post').isFeatureActive('customFields');
                    if (!isCustomFieldsEnabled) {
                        // Try to enable it
                        wp.data.dispatch('core/edit-post').toggleFeature('customFields');
                    }
                }
            });
            </script>
            <?php
        }
    }

    /**
     * Add custom cron schedules
     */
    public function custom_cron_schedules($schedules)
    {
        $schedules['every_5_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'kiosk')
        );
        $schedules['every_30_minutes'] = array(
            'interval' => 1800,
            'display' => __('Every 30 Minutes', 'kiosk')
        );
        $schedules['every_hour'] = array(
            'interval' => 3600,
            'display' => __('Every Hour', 'kiosk')
        );
        $schedules['every_6_hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 Hours', 'kiosk')
        );
        $schedules['every_12_hours'] = array(
            'interval' => 43200,
            'display' => __('Every 12 Hours', 'kiosk')
        );
        return $schedules;
    }

    /**
     * Hook to sync when a date custom field is updated
     */
    public function sync_date_field_to_json($meta_id, $post_id, $meta_key, $meta_value)
    {
        // Only sync for date-related custom fields
        $date_fields = array(
            'kiosk_start_date',
            'kiosk_last_date',
            'kiosk_exam_date',
            'kiosk_admit_card_date',
            'kiosk_result_date',
            'kiosk_counselling_date',
            'kiosk_interview_date'
        );

        if (in_array($meta_key, $date_fields)) {
            $this->sync_dates_from_fields_to_json($post_id);
        }
    }

    /**
     * Sync dates from custom fields back to ChatGPT JSON
     */
    private function sync_dates_from_fields_to_json($post_id)
    {
        $chatgpt_json = get_post_meta($post_id, 'kiosk_chatgpt_json', true);
        if (empty($chatgpt_json)) {
            return false;
        }

        $chatgpt_data = json_decode($chatgpt_json, true);
        if (!is_array($chatgpt_data)) {
            return false;
        }

        // Initialize dates array if it doesn't exist
        if (!isset($chatgpt_data['dates'])) {
            $chatgpt_data['dates'] = array();
        }

        // Update JSON with custom field values (custom fields take priority)
        $date_mapping = array(
            'kiosk_start_date' => 'start_date',
            'kiosk_last_date' => 'last_date',
            'kiosk_exam_date' => 'exam_date',
            'kiosk_admit_card_date' => 'admit_card_date',
            'kiosk_result_date' => 'result_date',
            'kiosk_counselling_date' => 'counselling_date',
            'kiosk_interview_date' => 'interview_date'
        );

        foreach ($date_mapping as $meta_key => $json_key) {
            $date_value = get_post_meta($post_id, $meta_key, true);
            if ($date_value) {
                $chatgpt_data['dates'][$json_key] = $date_value;
            }
        }

        // Save updated JSON
        $updated_json = wp_json_encode($chatgpt_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        update_post_meta($post_id, 'kiosk_chatgpt_json', $updated_json);

        return true;
    }

    /**
     * Legacy method: Fetch and publish content (for backwards compatibility and cron)
     */
    public function fetch_and_publish_content($force_full = false)
    {
        return $this->content_sync->fetch_and_publish_content($force_full);
    }

    /**
     * AJAX: Sync Recently Created Posts
     */
    public function ajax_sync_recent_created()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $settings = get_option('kiosk_automation_settings', array());
        $per_page = isset($settings['posts_per_sync']) ? intval($settings['posts_per_sync']) : 10;
        
        $categories = array();
        if (isset($settings['filter_by_categories']) && $settings['filter_by_categories']) {
            $categories = isset($settings['source_categories']) ? $settings['source_categories'] : array();
        }

        $result = $this->content_sync->fetch_recently_created($per_page, $categories);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Sync Recently Modified Posts
     */
    public function ajax_sync_recent_modified()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $settings = get_option('kiosk_automation_settings', array());
        $per_page = isset($settings['posts_per_sync']) ? intval($settings['posts_per_sync']) : 10;
        
        $categories = array();
        if (isset($settings['filter_by_categories']) && $settings['filter_by_categories']) {
            $categories = isset($settings['source_categories']) ? $settings['source_categories'] : array();
        }

        $result = $this->content_sync->fetch_recently_modified($per_page, $categories);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Resync Post Content (remap from existing ChatGPT JSON)
     */
    public function ajax_resync_content()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->content_sync->resync_post_content();
        wp_send_json_success($result);
    }

    /**
     * AJAX: Update All Posts (re-process with ChatGPT)
     */
    public function ajax_update_all_posts()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->content_sync->update_all_posts();
        wp_send_json_success($result);
    }

    /**
     * Manual sync handler (AJAX) - Legacy
     */
    public function manual_sync()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->fetch_and_publish_content();
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            $last_sync = get_option('kiosk_last_sync', array());
            wp_send_json_success($last_sync);
        }
    }

    /**
     * Force full sync - ignores date filters (AJAX) - Legacy
     */
    public function force_full_sync()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->fetch_and_publish_content(true);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            $last_sync = get_option('kiosk_last_sync', array());
            wp_send_json_success($last_sync);
        }
    }

    /**
     * Manual ChatGPT processing trigger (AJAX)
     */
    public function manual_process_chatgpt()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Get count of pending posts before processing
        $pending_query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('draft', 'publish'),
            'meta_query' => array(
                array(
                    'key' => 'kiosk_processing_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        $pending_before = $pending_query->found_posts;

        // Process the queue
        $this->chatgpt_processor->process_queue();

        // Get results
        $last_processing = get_option('kiosk_last_chatgpt_processing', array());
        $last_processing['pending_before'] = $pending_before;

        wp_send_json_success($last_processing);
    }

    /**
     * Test API connection (AJAX)
     */
    public function test_api_connection()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->api_fetcher->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Fetch single post for testing (AJAX)
     */
    public function fetch_single_post_ajax()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error('Please provide a valid post ID');
        }

        $settings = get_option('kiosk_automation_settings', array());
        $api_base_url = isset($settings['api_base_url']) ? rtrim($settings['api_base_url'], '/') : 'https://sarkariresult.com.cm/wp-json/wp/v2';

        $url = add_query_arg(array(
            '_embed' => 1,
            'acf_format' => 'standard'
        ), $api_base_url . '/posts/' . $post_id);

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            wp_send_json_error('API returned error code: ' . $response_code);
        }

        $post_data = json_decode($body, true);

        if (!$post_data || !isset($post_data['id'])) {
            wp_send_json_error('Failed to fetch post');
        }

        wp_send_json_success(array(
            'post_data' => $post_data,
            'acf_available' => isset($post_data['acf']) && !empty($post_data['acf'])
        ));
    }

    /**
     * Fix post slugs from ChatGPT data (AJAX) - Legacy
     */
    public function fix_post_slugs_from_chatgpt()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->content_sync->resync_post_content();
        wp_send_json_success($result);
    }

    /**
     * Update post content from JSON (AJAX) - Legacy
     */
    public function update_post_content_from_json()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $result = $this->content_sync->resync_post_content();
        wp_send_json_success($result);
    }

    /**
     * Add manual cron trigger button to admin bar
     */
    public function add_cron_trigger_to_admin_bar($wp_admin_bar)
    {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get settings and check if automation is enabled
        $settings = get_option('kiosk_automation_settings', array());
        if (empty($settings['enabled'])) {
            return;
        }

        // Check when the last cron ran
        $last_sync = get_option('kiosk_last_sync', array());
        $last_sync_time = isset($last_sync['timestamp']) ? absint($last_sync['timestamp']) : 0;
        $time_since_last = ($last_sync_time > 0) ? human_time_diff($last_sync_time, current_time('timestamp')) : 'Never';
        
        // Check next scheduled cron
        $next_cron = wp_next_scheduled('kiosk_fetch_content_cron');
        $next_run = ($next_cron && is_numeric($next_cron)) ? human_time_diff(current_time('timestamp'), $next_cron) : 'Not scheduled';

        $wp_admin_bar->add_node(array(
            'id'    => 'kiosk-manual-cron',
            'title' => '<span class="ab-icon dashicons dashicons-update"></span> Run Sync',
            'href'  => '#',
            'meta'  => array(
                'title' => sprintf('Last sync: %s ago | Next: in %s', $time_since_last, $next_run),
                'class' => 'kiosk-manual-cron-trigger'
            )
        ));
    }

    /**
     * AJAX handler for manual cron trigger
     */
    public function manual_trigger_cron()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
        }

        // Check if automation is enabled
        $settings = get_option('kiosk_automation_settings', array());
        if (empty($settings['enabled'])) {
            wp_send_json_error(array('message' => 'Content automation is not enabled'));
        }

        // Trigger the cron function immediately
        $result = $this->fetch_and_publish_content();

        // Get the updated last sync info
        $last_sync = get_option('kiosk_last_sync', array());
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(array(
                'message' => 'Sync completed successfully!',
                'last_sync' => $last_sync,
                'result' => $result
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'Sync completed!',
                'last_sync' => $last_sync
            ));
        }
    }

    /**
     * Add JavaScript for manual cron trigger
     */
    public function add_cron_trigger_script()
    {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('kiosk_automation_settings', array());
        if (empty($settings['enabled'])) {
            return;
        }
        ?>
        <style>
            #wp-admin-bar-kiosk-manual-cron .ab-icon:before {
                content: "\f463";
                top: 2px;
            }
            #wp-admin-bar-kiosk-manual-cron.running .ab-icon {
                animation: kiosk-spin 1s linear infinite;
            }
            @keyframes kiosk-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#wp-admin-bar-kiosk-manual-cron a').on('click', function(e) {
                e.preventDefault();
                
                if ($(this).parent().hasClass('running')) {
                    return;
                }

                if (!confirm('This will run the content sync now. Continue?')) {
                    return;
                }

                var $button = $(this).parent();
                $button.addClass('running');

                $.ajax({
                    url: ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'kiosk_manual_trigger_cron',
                        nonce: '<?php echo wp_create_nonce('kiosk_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        $button.removeClass('running');
                        
                        if (response.success) {
                            var result = response.data.result || {};
                            var msg = '✓ Sync completed!\n\n';
                            if (result.added) msg += 'Added: ' + result.added + '\n';
                            if (result.updated) msg += 'Updated: ' + result.updated + '\n';
                            if (result.skipped) msg += 'Skipped: ' + result.skipped + '\n';
                            if (result.queued_for_processing) msg += 'Queued for ChatGPT: ' + result.queued_for_processing;
                            alert(msg);
                            location.reload();
                        } else {
                            alert('✗ Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        $button.removeClass('running');
                        alert('✗ Failed to trigger sync. Please try again.');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize the main class
new Kiosk_Content_Automation();

// Initialize admin columns (in admin context)
if (is_admin()) {
    require_once get_template_directory() . '/module/admin/admin-columns.php';
    new Kiosk_Admin_Columns();
}
