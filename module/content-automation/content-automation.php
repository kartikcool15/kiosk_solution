<?php

/**
 * Content Automation System
 * Fetches content from external API and publishes automatically
 */

if (!defined('ABSPATH')) exit;

class Kiosk_Content_Automation
{

    private $api_base_url;

    private function get_api_base_url()
    {
        if (!$this->api_base_url) {
            $settings = get_option('kiosk_automation_settings', array());
            $this->api_base_url = isset($settings['api_base_url']) ? rtrim($settings['api_base_url'], '/') : 'https://sarkariresult.com.cm/wp-json/wp/v2';
        }
        return $this->api_base_url;
    }

    public function __construct()
    {
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

        // Hook into cron
        add_action('kiosk_fetch_content_cron', array($this, 'fetch_and_publish_content'));

        // Background processing for ChatGPT
        add_action('kiosk_process_chatgpt_queue', array($this, 'process_chatgpt_queue'));

        // Admin AJAX handlers
        add_action('wp_ajax_kiosk_manual_sync', array($this, 'manual_sync'));
        add_action('wp_ajax_kiosk_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_kiosk_fetch_single_post', array($this, 'fetch_single_post_ajax'));
        add_action('wp_ajax_kiosk_process_chatgpt_now', array($this, 'manual_process_chatgpt'));
        add_action('wp_ajax_kiosk_fix_post_slugs', array($this, 'fix_post_slugs_from_chatgpt'));
        add_action('wp_ajax_kiosk_update_post_content_from_json', array($this, 'update_post_content_from_json'));

        // Admin column for ChatGPT status
        add_filter('manage_posts_columns', array($this, 'add_chatgpt_status_column'));
        add_action('manage_posts_custom_column', array($this, 'display_chatgpt_status_column'), 10, 2);
        add_action('admin_head', array($this, 'add_chatgpt_status_column_styles'));

        // Row actions for individual post update
        add_filter('post_row_actions', array($this, 'add_update_post_action'), 10, 2);
        add_action('admin_footer', array($this, 'add_update_post_script'));
        add_action('wp_ajax_kiosk_update_individual_post', array($this, 'update_individual_post_ajax'));

        // Bulk actions for updating posts
        add_filter('bulk_actions-edit-post', array($this, 'add_bulk_update_action'));
        add_filter('handle_bulk_actions-edit-post', array($this, 'handle_bulk_update_action'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_update_admin_notice'));

        // Date field sync: when custom date fields are updated, sync back to JSON
        add_action('updated_post_meta', array($this, 'sync_date_field_to_json'), 10, 4);
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
            .kiosk-field-group input[type="text"],
            .kiosk-field-group input[type="date"] {
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
                <input type="date" id="kiosk_start_date" name="kiosk_start_date" 
                       value="<?php echo esc_attr($start_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_last_date">Application Last Date</label>
                <input type="date" id="kiosk_last_date" name="kiosk_last_date" 
                       value="<?php echo esc_attr($last_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_exam_date">Exam Date</label>
                <input type="date" id="kiosk_exam_date" name="kiosk_exam_date" 
                       value="<?php echo esc_attr($exam_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_admit_card_date">Admit Card Release Date</label>
                <input type="date" id="kiosk_admit_card_date" name="kiosk_admit_card_date" 
                       value="<?php echo esc_attr($admit_card_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_result_date">Result Date</label>
                <input type="date" id="kiosk_result_date" name="kiosk_result_date" 
                       value="<?php echo esc_attr($result_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_counselling_date">Counselling Date</label>
                <input type="date" id="kiosk_counselling_date" name="kiosk_counselling_date" 
                       value="<?php echo esc_attr($counselling_date); ?>">
            </div>

            <div class="kiosk-field-group">
                <label for="kiosk_interview_date">Interview Date</label>
                <input type="date" id="kiosk_interview_date" name="kiosk_interview_date" 
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
     * Fetch posts from external API
     */
    private function fetch_posts_from_api($page = 1, $per_page = 10, $categories = array(), $modified_after = '')
    {
        $args = array(
            'page' => $page,
            'per_page' => $per_page,
            '_embed' => 1,
            'acf_format' => 'standard' // Request ACF fields
        );

        // Add category filter if provided
        if (!empty($categories) && is_array($categories)) {
            $args['categories'] = implode(',', $categories);
        }

        // Add modified_after filter if provided
        if (!empty($modified_after)) {
            $args['modified_after'] = $modified_after;
        }

        $url = add_query_arg($args, $this->get_api_base_url() . '/posts');

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            error_log('Kiosk API Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log response for debugging
        if ($response_code !== 200) {
            error_log('Kiosk API Response Code: ' . $response_code);
            error_log('Kiosk API Response Body: ' . substr($body, 0, 500));
            return false;
        }

        $posts = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Kiosk API JSON Error: ' . json_last_error_msg());
            return false;
        }

        return $posts;
    }

    /**
     * Read prompt file
     */
    private function read_prompt_file($filename)
    {
        $file_path = get_template_directory() . '/prompt/' . $filename;

        if (!file_exists($file_path)) {
            return false;
        }

        return file_get_contents($file_path);
    }

    /**
     * Clean HTML and convert to plain text, preserving links
     * Extracts URLs from anchor tags before stripping HTML
     */
    private function clean_and_parse_field($value)
    {
        if (empty($value)) {
            return '';
        }

        // First, extract and preserve links in format: "Link Text (URL)"
        $text = $this->extract_and_preserve_links($value);

        // Strip remaining HTML tags
        $text = wp_strip_all_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        // Check if there are multiple lines
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // Remove empty lines

        // If multiple non-empty lines, return as array
        if (count($lines) > 1) {
            return array_values($lines);
        }

        // Otherwise return as single string
        return trim($text);
    }

    /**
     * Check if URL should be excluded/filtered
     * Returns true if URL should be skipped (WhatsApp, Telegram, SarkariResult, etc.)
     */
    private function should_exclude_url($url)
    {
        if (empty($url)) {
            return true;
        }

        $url_lower = strtolower($url);

        // Excluded domains and patterns
        $excluded_patterns = array(
            'whatsapp.com',
            'wa.me',
            'api.whatsapp.com',
            'chat.whatsapp.com',
            'web.whatsapp.com',
            't.me',
            'telegram.me',
            'telegram.org',
            'sarkariresult.com',
            'www.sarkariresult.com'
        );

        foreach ($excluded_patterns as $pattern) {
            if (strpos($url_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract links from HTML and preserve them with their URLs
     * Converts <a href="url">text</a> to "text (url)"
     */
    private function extract_and_preserve_links($html)
    {
        if (empty($html)) {
            return '';
        }

        // Match all anchor tags and replace them with "text (url)" format
        $pattern = '/<a[^>]*href=[\'"]([^\'"]*)[\'"][^>]*>(.*?)<\/a>/is';
        $html = preg_replace_callback(
            $pattern,
            function ($matches) {
                $url = trim($matches[1]);

                // Skip excluded URLs (WhatsApp, Telegram, SarkariResult)
                if ($this->should_exclude_url($url)) {
                    return ''; // Remove the link entirely
                }

                $text = wp_strip_all_tags($matches[2]);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = trim($text);

                // If link text is generic (like "click here"), just return the URL
                $generic_texts = array('click here', 'here', 'link', 'read more', 'more');
                if (in_array(strtolower($text), $generic_texts)) {
                    return $url;
                }

                // Return in format: "text (url)"
                return !empty($text) ? $text . ' (' . $url . ')' : $url;
            },
            $html
        );

        return $html;
    }

    /**
     * Prepare post data as clean JSON for ChatGPT with custom field mappings
     */
    private function prepare_post_json($post_data)
    {
        $clean_data = array(
            'id' => isset($post_data['id']) ? $post_data['id'] : '',
            'post_date' => isset($post_data['date']) ? $post_data['date'] : '',
            'source_link' => isset($post_data['link']) ? $post_data['link'] : '',
        );

        // Use ACF post_title if available, otherwise use default title
        if (isset($post_data['acf']['post_title']) && !empty($post_data['acf']['post_title'])) {
            $raw_title = wp_strip_all_tags($post_data['acf']['post_title']);
        } else {
            $raw_title = isset($post_data['title']['rendered']) ? wp_strip_all_tags($post_data['title']['rendered']) : '';
        }
        $clean_data['title'] = trim($raw_title);

        // Check if ACF data exists - send ALL ACF fields to ChatGPT
        if (isset($post_data['acf']) && is_array($post_data['acf']) && !empty($post_data['acf'])) {
            // Clean and prepare all ACF fields
            $clean_data['acf_fields'] = array();

            foreach ($post_data['acf'] as $field_key => $field_value) {
                if (empty($field_value)) {
                    continue; // Skip empty fields
                }

                // Clean HTML content from ACF fields
                if (is_string($field_value)) {
                    $clean_data['acf_fields'][$field_key] = $this->clean_and_parse_field($field_value);
                } else {
                    // Keep arrays/objects as is
                    $clean_data['acf_fields'][$field_key] = $field_value;
                }
            }
        } else {
            // Fallback to standard fields if no ACF data
            $clean_data['excerpt'] = isset($post_data['excerpt']['rendered']) ?
                wp_strip_all_tags($post_data['excerpt']['rendered']) : '';
            $clean_data['content'] = isset($post_data['content']['rendered']) ?
                wp_strip_all_tags($post_data['content']['rendered']) : '';
        }

        return json_encode($clean_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Call OpenAI API
     */
    private function call_openai_api($api_key, $model, $system_prompt, $user_prompt)
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $user_prompt
                )
            ),
            'temperature' => 0.1,
            'response_format' => array('type' => 'json_object')
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            error_log('Kiosk Automation: OpenAI API error - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Kiosk Automation: OpenAI API returned code ' . $response_code);
            error_log('Response: ' . wp_remote_retrieve_body($response));
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
            error_log('Kiosk Automation: Invalid OpenAI API response structure');
            return false;
        }

        return $body['choices'][0]['message']['content'];
    }

    /**
     * Map categories from source to local
     * Only uses the first category from source
     * Returns array with both local category IDs and category info
     */
    private function map_categories($source_categories, $return_details = false)
    {
        // Source category mapping: ID => slug
        $source_category_map = array(
            2 => 'admission',
            3 => 'admit-card',
            4 => 'answer-key',
            1 => 'blog',
            170 => 'documents',
            5 => 'latest-job',
            169 => 'new-format',
            6 => 'result',
            7 => 'sarkari-job',
            8 => 'sarkari-yojana'
        );

        $local_category_ids = array();
        $category_details = array();

        // Only use the first category from source
        if (!empty($source_categories) && is_array($source_categories)) {
            $first_category_id = $source_categories[0];

            // Check if we have a slug for this source category
            if (isset($source_category_map[$first_category_id])) {
                $slug = $source_category_map[$first_category_id];

                // Find local category by slug
                $local_term = get_term_by('slug', $slug, 'category');

                if ($local_term && !is_wp_error($local_term)) {
                    $local_category_ids[] = intval($local_term->term_id);

                    if ($return_details) {
                        $category_details[] = array(
                            'id' => $local_term->term_id,
                            'name' => $local_term->name,
                            'slug' => $local_term->slug,
                            'taxonomy' => 'category'
                        );
                    }
                }
            }
        }

        // Default to uncategorized if no mapping found
        if (empty($local_category_ids)) {
            $local_category_ids[] = 1; // Uncategorized

            if ($return_details) {
                $uncategorized = get_term_by('id', 1, 'category');
                if ($uncategorized && !is_wp_error($uncategorized)) {
                    $category_details[] = array(
                        'id' => $uncategorized->term_id,
                        'name' => $uncategorized->name,
                        'slug' => $uncategorized->slug,
                        'taxonomy' => 'category'
                    );
                }
            }
        }

        return $return_details ? $category_details : $local_category_ids;
    }

    /**
     * Get or create organization taxonomy term
     * Returns term ID or false on failure
     */
    private function get_or_create_organization_term($organization_name)
    {
        if (empty($organization_name)) {
            return false;
        }

        // Clean and sanitize the organization name
        $organization_name = trim($organization_name);
        $organization_name = sanitize_text_field($organization_name);

        if (empty($organization_name)) {
            return false;
        }

        // Check if term exists by name
        $term = get_term_by('name', $organization_name, 'organization');

        if ($term && !is_wp_error($term)) {
            return intval($term->term_id);
        }

        // Check by slug as fallback
        $slug = sanitize_title($organization_name);
        $term = get_term_by('slug', $slug, 'organization');

        if ($term && !is_wp_error($term)) {
            return intval($term->term_id);
        }

        // Term doesn't exist, create it
        $result = wp_insert_term($organization_name, 'organization', array(
            'slug' => $slug
        ));

        if (is_wp_error($result)) {
            error_log('Kiosk Automation: Failed to create organization term: ' . $result->get_error_message());
            return false;
        }

        return intval($result['term_id']);
    }

    /**
     * Set organization taxonomy for a post from ChatGPT JSON
     * Extracts organization value from JSON and assigns to post
     */
    private function set_post_organization($post_id, $chatgpt_json)
    {
        if (empty($chatgpt_json)) {
            return false;
        }

        // Decode ChatGPT JSON
        $chatgpt_data = json_decode($chatgpt_json, true);

        if (!is_array($chatgpt_data) || empty($chatgpt_data['organization'])) {
            return false;
        }

        $organization_name = $chatgpt_data['organization'];

        // Get or create the term
        $term_id = $this->get_or_create_organization_term($organization_name);

        if (!$term_id) {
            return false;
        }

        // Set the term for the post (replacing existing terms)
        $result = wp_set_object_terms($post_id, $term_id, 'organization', false);

        if (is_wp_error($result)) {
            error_log('Kiosk Automation: Failed to set organization term for post ' . $post_id . ': ' . $result->get_error_message());
            return false;
        }

        return true;
    }

    /**
     * Set education taxonomy terms for post
     * Only applies to posts in 'latest-job' category
     */
    private function set_post_education($post_id, $chatgpt_json)
    {
        // Check if post is in 'latest-job' category
        $categories = wp_get_post_categories($post_id, array('fields' => 'slugs'));
        if (!in_array('latest-job', $categories)) {
            return false; // Skip if not in latest-job category
        }

        // Parse ChatGPT JSON
        $chatgpt_data = json_decode($chatgpt_json, true);

        if (!is_array($chatgpt_data) || empty($chatgpt_data['education'])) {
            return false;
        }

        $education_values = $chatgpt_data['education'];

        // Ensure it's an array
        if (!is_array($education_values)) {
            $education_values = array($education_values);
        }

        // Get or create terms for each education value
        $term_ids = array();
        foreach ($education_values as $education_name) {
            $term_id = $this->get_or_create_education_term($education_name);
            if ($term_id) {
                $term_ids[] = $term_id;
            }
        }

        if (empty($term_ids)) {
            return false;
        }

        // Set the terms for the post (replacing existing terms)
        $result = wp_set_object_terms($post_id, $term_ids, 'education', false);

        if (is_wp_error($result)) {
            error_log('Kiosk Automation: Failed to set education terms for post ' . $post_id . ': ' . $result->get_error_message());
            return false;
        }

        return true;
    }

    /**
     * Get or create education taxonomy term
     * Returns term ID or false on failure
     */
    private function get_or_create_education_term($education_name)
    {
        if (empty($education_name)) {
            return false;
        }

        // Clean and sanitize the education name
        $education_name = trim($education_name);
        $education_name = sanitize_text_field($education_name);

        if (empty($education_name)) {
            return false;
        }

        // Check if term already exists
        $term = get_term_by('name', $education_name, 'education');

        if ($term && !is_wp_error($term)) {
            return $term->term_id;
        }

        // Create new term
        $result = wp_insert_term($education_name, 'education');

        if (is_wp_error($result)) {
            // Check if error is because term already exists
            if (isset($result->error_data['term_exists'])) {
                return $result->error_data['term_exists'];
            }
            error_log('Kiosk Automation: Failed to create education term "' . $education_name . '": ' . $result->get_error_message());
            return false;
        }

        return $result['term_id'];
    }

    /**
     * Sync dates from ChatGPT JSON to custom fields
     * Called when ChatGPT processes a post
     */
    private function sync_dates_from_json_to_fields($post_id, $chatgpt_json)
    {
        if (empty($chatgpt_json)) {
            return false;
        }

        $chatgpt_data = json_decode($chatgpt_json, true);
        if (!is_array($chatgpt_data) || empty($chatgpt_data['dates'])) {
            return false;
        }

        $dates = $chatgpt_data['dates'];

        // Only update custom fields if they don't already have a value (prioritize manual edits)
        if (!get_post_meta($post_id, 'kiosk_start_date', true) && !empty($dates['start_date'])) {
            update_post_meta($post_id, 'kiosk_start_date', sanitize_text_field($dates['start_date']));
        }

        if (!get_post_meta($post_id, 'kiosk_last_date', true) && !empty($dates['last_date'])) {
            update_post_meta($post_id, 'kiosk_last_date', sanitize_text_field($dates['last_date']));
        }

        if (!get_post_meta($post_id, 'kiosk_exam_date', true) && !empty($dates['exam_date'])) {
            update_post_meta($post_id, 'kiosk_exam_date', sanitize_text_field($dates['exam_date']));
        }

        if (!get_post_meta($post_id, 'kiosk_admit_card_date', true) && !empty($dates['admit_card_date'])) {
            update_post_meta($post_id, 'kiosk_admit_card_date', sanitize_text_field($dates['admit_card_date']));
        }

        if (!get_post_meta($post_id, 'kiosk_result_date', true) && !empty($dates['result_date'])) {
            update_post_meta($post_id, 'kiosk_result_date', sanitize_text_field($dates['result_date']));
        }

        if (!get_post_meta($post_id, 'kiosk_counselling_date', true) && !empty($dates['counselling_date'])) {
            update_post_meta($post_id, 'kiosk_counselling_date', sanitize_text_field($dates['counselling_date']));
        }

        if (!get_post_meta($post_id, 'kiosk_interview_date', true) && !empty($dates['interview_date'])) {
            update_post_meta($post_id, 'kiosk_interview_date', sanitize_text_field($dates['interview_date']));
        }

        return true;
    }

    /**
     * Sync dates from custom fields back to ChatGPT JSON
     * Called when custom fields are manually edited
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
        $start_date = get_post_meta($post_id, 'kiosk_start_date', true);
        if ($start_date) {
            $chatgpt_data['dates']['start_date'] = $start_date;
        }

        $last_date = get_post_meta($post_id, 'kiosk_last_date', true);
        if ($last_date) {
            $chatgpt_data['dates']['last_date'] = $last_date;
        }

        $exam_date = get_post_meta($post_id, 'kiosk_exam_date', true);
        if ($exam_date) {
            $chatgpt_data['dates']['exam_date'] = $exam_date;
        }

        $admit_card_date = get_post_meta($post_id, 'kiosk_admit_card_date', true);
        if ($admit_card_date) {
            $chatgpt_data['dates']['admit_card_date'] = $admit_card_date;
        }

        $result_date = get_post_meta($post_id, 'kiosk_result_date', true);
        if ($result_date) {
            $chatgpt_data['dates']['result_date'] = $result_date;
        }

        $counselling_date = get_post_meta($post_id, 'kiosk_counselling_date', true);
        if ($counselling_date) {
            $chatgpt_data['dates']['counselling_date'] = $counselling_date;
        }

        $interview_date = get_post_meta($post_id, 'kiosk_interview_date', true);
        if ($interview_date) {
            $chatgpt_data['dates']['interview_date'] = $interview_date;
        }

        // Save updated JSON
        $updated_json = wp_json_encode($chatgpt_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        update_post_meta($post_id, 'kiosk_chatgpt_json', $updated_json);

        return true;
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
     * Check if post already exists (checks all post statuses)
     */
    private function post_exists_by_source_id($source_id)
    {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'any', // Check all statuses (draft, publish, pending, etc.)
            'meta_key' => 'kiosk_source_post_id',
            'meta_value' => $source_id,
            'posts_per_page' => 1,
            'fields' => 'ids'
        );

        $query = new WP_Query($args);
        return $query->have_posts() ? $query->posts[0] : false;
    }

    /**
     * Main function to fetch and publish content
     * NEW ASYNC APPROACH: Create posts immediately with ACF data, queue ChatGPT processing for later
     */
    public function fetch_and_publish_content()
    {
        $settings = get_option('kiosk_automation_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : false;

        if (!$enabled) {
            return;
        }

        $per_page = isset($settings['posts_per_sync']) ? intval($settings['posts_per_sync']) : 10;

        // Get category filter if enabled
        $categories = array();
        if (isset($settings['filter_by_categories']) && $settings['filter_by_categories']) {
            $categories = isset($settings['source_categories']) ? $settings['source_categories'] : array();
        }

        // Get last sync timestamp for modified_after parameter
        $last_sync_data = get_option('kiosk_last_sync', array());
        $modified_after = '';

        // Use last successful sync timestamp in ISO 8601 format
        if (isset($last_sync_data['timestamp_iso'])) {
            $modified_after = $last_sync_data['timestamp_iso'];
        }

        $posts = $this->fetch_posts_from_api(1, $per_page, $categories, $modified_after);

        if (!$posts || !is_array($posts)) {
            error_log('Kiosk: No posts fetched from API');
            return;
        }

        $imported_count = 0;
        $updated_count = 0;
        $skipped_count = 0;
        $queued_for_processing = 0;

        foreach ($posts as $post_data) {
            $source_post_id = $post_data['id'];

            // Check if post already exists
            $existing_post_id = $this->post_exists_by_source_id($source_post_id);

            if ($existing_post_id) {
                // Post exists - UPDATE it with new data
                $this->update_existing_post($existing_post_id, $post_data);
                $updated_count++;
                $queued_for_processing++;
                continue;
            }

            // Prepare JSON for later ChatGPT processing (DO NOT process now)
            $prepared_json = $this->prepare_post_json($post_data);

            // Use ACF post_title if available, otherwise use default title
            $title_to_use = isset($post_data['acf']['post_title']) && !empty($post_data['acf']['post_title'])
                ? $post_data['acf']['post_title']
                : $post_data['title']['rendered'];

            // Get featured image
            $featured_image_id = 0;
            if (isset($post_data['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $image_url = $post_data['_embedded']['wp:featuredmedia'][0]['source_url'];
                $featured_image_id = $this->download_and_attach_image($image_url, $title_to_use);
            }

            // Prepare post date - use source post date if available
            $post_date = '';
            $post_date_gmt = '';
            if (isset($post_data['date']) && !empty($post_data['date'])) {
                $post_date = $post_data['date'];
            }
            if (isset($post_data['date_gmt']) && !empty($post_data['date_gmt'])) {
                $post_date_gmt = $post_data['date_gmt'];
            }

            // Create the post as draft (will publish after ChatGPT processing)
            $post_data_array = array(
                'post_title' => sanitize_text_field($title_to_use),
                'post_content' => wp_kses_post($post_data['content']['rendered']),
                'post_excerpt' => isset($post_data['excerpt']['rendered']) ? wp_kses_post($post_data['excerpt']['rendered']) : '',
                'post_status' => 'draft', // Keep as draft until ChatGPT processes it
                'post_type' => 'post',
                'post_category' => $this->map_categories($post_data['categories']),
            );

            // Add post date if available
            if (!empty($post_date)) {
                $post_data_array['post_date'] = $post_date;
            }
            if (!empty($post_date_gmt)) {
                $post_data_array['post_date_gmt'] = $post_date_gmt;
            }

            $new_post_id = wp_insert_post($post_data_array);

            if (!is_wp_error($new_post_id) && $new_post_id > 0) {
                // Set featured image
                if ($featured_image_id > 0) {
                    set_post_thumbnail($new_post_id, $featured_image_id);
                }

                // Save source post ID
                update_post_meta($new_post_id, 'kiosk_source_post_id', $source_post_id);

                // Store complete cleaned ACF JSON for later ChatGPT processing
                // The prepared_json already contains cleaned ACF data in 'acf_fields' key
                update_post_meta($new_post_id, 'kiosk_raw_post_data', $prepared_json);
                update_post_meta($new_post_id, 'kiosk_processing_status', 'pending');

                $imported_count++;
                $queued_for_processing++;
            }
        }

        // Schedule background ChatGPT processing if posts were created
        if ($queued_for_processing > 0) {
            $this->schedule_chatgpt_processing();
        }

        // Log the results with current timestamp in ISO 8601 format
        update_option('kiosk_last_sync', array(
            'time' => current_time('mysql'),
            'timestamp_iso' => current_time('c'), // ISO 8601 format for API
            'imported' => $imported_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'queued_for_chatgpt' => $queued_for_processing
        ));
    }

    /**
     * Update existing post with fresh data from API
     */
    private function update_existing_post($post_id, $post_data)
    {
        // Prepare JSON for ChatGPT processing
        $prepared_json = $this->prepare_post_json($post_data);

        // Use ACF post_title if available
        $title_to_use = isset($post_data['acf']['post_title']) && !empty($post_data['acf']['post_title'])
            ? $post_data['acf']['post_title']
            : $post_data['title']['rendered'];

        // Update the post content and title
        $update_data = array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($title_to_use),
            'post_content' => wp_kses_post($post_data['content']['rendered']),
            'post_excerpt' => isset($post_data['excerpt']['rendered']) ? wp_kses_post($post_data['excerpt']['rendered']) : '',
            'post_status' => 'draft', // Back to draft for re-processing
            'post_category' => $this->map_categories($post_data['categories']),
        );

        wp_update_post($update_data);

        // Update featured image if available
        if (isset($post_data['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            $image_url = $post_data['_embedded']['wp:featuredmedia'][0]['source_url'];
            $featured_image_id = $this->download_and_attach_image($image_url, $title_to_use);
            if ($featured_image_id > 0) {
                set_post_thumbnail($post_id, $featured_image_id);
            }
        }

        // Update meta: raw data for ChatGPT processing
        update_post_meta($post_id, 'kiosk_raw_post_data', $prepared_json);
        update_post_meta($post_id, 'kiosk_processing_status', 'pending');

        // Clear old ChatGPT data to force re-processing
        delete_post_meta($post_id, 'kiosk_chatgpt_json');

        return true;
    }

    /**
     * Schedule ChatGPT processing for pending posts
     */
    private function schedule_chatgpt_processing()
    {
        // Check if ChatGPT processing is enabled
        $settings = get_option('kiosk_automation_settings', array());
        $openai_enabled = isset($settings['openai_enabled']) && $settings['openai_enabled'];

        if (!$openai_enabled) {
            return; // Skip if ChatGPT is disabled
        }

        // Schedule the processing hook if not already scheduled
        if (!wp_next_scheduled('kiosk_process_chatgpt_queue')) {
            wp_schedule_single_event(time() + 60, 'kiosk_process_chatgpt_queue');
        }
    }

    /**
     * Process ChatGPT queue - runs in background
     * Processes posts marked as 'pending' for ChatGPT
     */
    public function process_chatgpt_queue()
    {
        // Check if ChatGPT processing is enabled
        $settings = get_option('kiosk_automation_settings', array());
        $openai_enabled = isset($settings['openai_enabled']) && $settings['openai_enabled'];

        if (!$openai_enabled) {
            return;
        }

        // Get posts that need ChatGPT processing (includes drafts)
        $args = array(
            'post_type' => 'post',
            'post_status' => array('draft', 'publish'), // Include both draft and published posts
            'meta_query' => array(
                array(
                    'key' => 'kiosk_processing_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            ),
            'posts_per_page' => 5, // Process 5 at a time to avoid timeout
            'orderby' => 'date',
            'order' => 'ASC'
        );

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return; // No posts to process
        }

        $processed_count = 0;
        $failed_count = 0;

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Mark as processing
            update_post_meta($post_id, 'kiosk_processing_status', 'processing');

            // Get stored raw post data
            $raw_post_data = get_post_meta($post_id, 'kiosk_raw_post_data', true);

            if (empty($raw_post_data)) {
                update_post_meta($post_id, 'kiosk_processing_status', 'failed');
                $failed_count++;
                continue;
            }

            // Decode the JSON
            $post_data_decoded = json_decode($raw_post_data, true);

            if (!$post_data_decoded) {
                update_post_meta($post_id, 'kiosk_processing_status', 'failed');
                $failed_count++;
                continue;
            }

            // Process with ChatGPT
            $chatgpt_result = $this->process_post_data_with_chatgpt($raw_post_data);

            if ($chatgpt_result !== false) {
                // Store ChatGPT result
                update_post_meta($post_id, 'kiosk_chatgpt_json', $chatgpt_result);
                update_post_meta($post_id, 'kiosk_processing_status', 'completed');

                // Decode ChatGPT result to extract post_title and post_content_summary
                $chatgpt_data = json_decode($chatgpt_result, true);

                // Update post title, content and slug from ChatGPT response
                $update_data = array('ID' => $post_id);
                
                if (!empty($chatgpt_data['post_title'])) {
                    $new_title = sanitize_text_field($chatgpt_data['post_title']);
                    $update_data['post_title'] = $new_title;
                    $update_data['post_name'] = sanitize_title($new_title); // Explicitly set the slug
                }
                
                // Map post_content_summary to post_content
                if (!empty($chatgpt_data['post_content_summary'])) {
                    $update_data['post_content'] = wp_kses_post($chatgpt_data['post_content_summary']);
                }
                
                if (count($update_data) > 1) {
                    wp_update_post($update_data);
                }

                // Set organization taxonomy from ChatGPT JSON
                $this->set_post_organization($post_id, $chatgpt_result);

                // Set education taxonomy for latest-job category posts
                $this->set_post_education($post_id, $chatgpt_result);

                // Sync dates from ChatGPT JSON to custom fields
                $this->sync_dates_from_json_to_fields($post_id, $chatgpt_result);

                // Publish the post now that ChatGPT processing is complete
                wp_publish_post($post_id);

                $processed_count++;
            } else {
                update_post_meta($post_id, 'kiosk_processing_status', 'failed');
                $failed_count++;
            }
        }

        wp_reset_postdata();

        // Log processing results
        update_option('kiosk_last_chatgpt_processing', array(
            'time' => current_time('mysql'),
            'processed' => $processed_count,
            'failed' => $failed_count
        ));

        // Reschedule if there are more posts to process
        $remaining = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('draft', 'publish'), // Include both statuses
            'meta_query' => array(
                array(
                    'key' => 'kiosk_processing_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if ($remaining->have_posts()) {
            wp_schedule_single_event(time() + 120, 'kiosk_process_chatgpt_queue');
        }
    }

    /**
     * Process prepared JSON data with ChatGPT
     * This is called during background processing
     */
    private function process_post_data_with_chatgpt($prepared_json)
    {
        $settings = get_option('kiosk_automation_settings', array());
        $api_key = isset($settings['openai_api_key']) ? trim($settings['openai_api_key']) : '';
        $model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o';

        if (empty($api_key)) {
            error_log('Kiosk Automation: OpenAI API key not configured');
            return false;
        }

        // Read prompt files
        $system_prompt = $this->read_prompt_file('system-prompt.txt');
        $user_prompt_template = $this->read_prompt_file('user-prompt.txt');

        if (!$system_prompt || !$user_prompt_template) {
            error_log('Kiosk Automation: Prompt files not found');
            return false;
        }

        // Replace placeholder in user prompt
        $user_prompt = str_replace('[PASTE CLEANED JSON HERE]', $prepared_json, $user_prompt_template);

        // Call OpenAI API
        $response = $this->call_openai_api($api_key, $model, $system_prompt, $user_prompt);

        if (!$response) {
            error_log('Kiosk Automation: OpenAI API call failed');
            return false;
        }

        return $response;
    }

    /**
     * Download and attach image
     */
    private function download_and_attach_image($image_url, $post_title)
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return 0;
        }

        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        $id = media_handle_sideload($file_array, 0, $post_title);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return 0;
        }

        return $id;
    }

    /**
     * Add ChatGPT status column to posts list
     */
    public function add_chatgpt_status_column($columns)
    {
        // Insert after the title column
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['chatgpt_status'] = 'ChatGPT Status';
            }
        }
        return $new_columns;
    }

    /**
     * Display ChatGPT status column content
     */
    public function display_chatgpt_status_column($column, $post_id)
    {
        if ($column === 'chatgpt_status') {
            $status = get_post_meta($post_id, 'kiosk_processing_status', true);
            $source_id = get_post_meta($post_id, 'kiosk_source_post_id', true);

            // Only show status for imported posts
            if (empty($source_id)) {
                echo '<span style="color: #999;">—</span>';
                return;
            }

            if (empty($status)) {
                echo '<span style="color: #999;">Not Queued</span>';
                return;
            }

            switch ($status) {
                case 'pending':
                    echo '<span class="chatgpt-status-pending">⏳ Pending</span>';
                    break;
                case 'processing':
                    echo '<span class="chatgpt-status-processing">⚙️ Processing</span>';
                    break;
                case 'completed':
                    echo '<span class="chatgpt-status-completed">✅ Completed</span>';
                    break;
                case 'failed':
                    echo '<span class="chatgpt-status-failed">❌ Failed</span>';
                    break;
                default:
                    echo '<span style="color: #999;">' . esc_html($status) . '</span>';
            }
        }
    }

    /**
     * Add styles for ChatGPT status column
     */
    public function add_chatgpt_status_column_styles()
    {
        echo '<style>
            .chatgpt-status-pending { color: #f0ad4e; font-weight: 500; }
            .chatgpt-status-processing { color: #0073aa; font-weight: 500; }
            .chatgpt-status-completed { color: #46b450; font-weight: 500; }
            .chatgpt-status-failed { color: #dc3232; font-weight: 500; }
            .column-chatgpt_status { width: 150px; }
            .kiosk-update-post { color: #2271b1; }
            .kiosk-update-post:hover { color: #135e96; }
            .kiosk-updating { opacity: 0.5; pointer-events: none; }
        </style>';
    }

    /**
     * Add update action to post row actions
     */
    public function add_update_post_action($actions, $post)
    {
        // Only show for posts that have a source post ID
        $source_post_id = get_post_meta($post->ID, 'kiosk_source_post_id', true);

        if (!empty($source_post_id)) {
            $actions['kiosk_update'] = sprintf(
                '<a href="#" class="kiosk-update-post" data-post-id="%d" data-source-id="%d">🔄 Update from Source</a>',
                $post->ID,
                $source_post_id
            );
        }

        return $actions;
    }

    /**
     * Add JavaScript for update post action
     */
    public function add_update_post_script()
    {
        $screen = get_current_screen();
        if ($screen->id !== 'edit-post') {
            return;
        }

    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.kiosk-update-post').on('click', function(e) {
                    e.preventDefault();

                    var $link = $(this);
                    var postId = $link.data('post-id');
                    var sourceId = $link.data('source-id');
                    var $row = $link.closest('tr');

                    if ($link.hasClass('kiosk-updating')) {
                        return;
                    }

                    if (!confirm('Update this post from source ID ' + sourceId + '? This will fetch fresh data from the API and re-process with ChatGPT.')) {
                        return;
                    }

                    $link.addClass('kiosk-updating');
                    $link.text('🔄 Updating...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kiosk_update_individual_post',
                            post_id: postId,
                            source_id: sourceId,
                            nonce: '<?php echo wp_create_nonce('kiosk_update_post'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $link.text('✅ Updated!');
                                $link.css('color', '#46b450');

                                // Update status column if exists
                                var $statusCell = $row.find('.column-chatgpt_status');
                                if ($statusCell.length) {
                                    $statusCell.html('<span class="chatgpt-status-pending">⏳ Pending</span>');
                                }

                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                alert('Error: ' + (response.data.message || 'Failed to update post'));
                                $link.removeClass('kiosk-updating');
                                $link.text('🔄 Update from Source');
                            }
                        },
                        error: function() {
                            alert('AJAX error occurred');
                            $link.removeClass('kiosk-updating');
                            $link.text('🔄 Update from Source');
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Update individual post from source (AJAX)
     */
    public function update_individual_post_ajax()
    {
        check_ajax_referer('kiosk_update_post', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;

        if (!$post_id || !$source_id) {
            wp_send_json_error(array('message' => 'Invalid post ID or source ID'));
        }

        // Fetch fresh data from API
        $url = add_query_arg(array(
            '_embed' => 1,
            'acf_format' => 'standard'
        ), $this->get_api_base_url() . '/posts/' . $source_id);

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'API Error: ' . $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            wp_send_json_error(array('message' => 'API returned error code: ' . $response_code));
        }

        $post_data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$post_data || !isset($post_data['id'])) {
            wp_send_json_error(array('message' => 'Failed to parse API response'));
        }

        // Prepare new JSON
        $prepared_json = $this->prepare_post_json($post_data);

        // Use ACF post_title if available
        $title_to_use = isset($post_data['acf']['post_title']) && !empty($post_data['acf']['post_title'])
            ? $post_data['acf']['post_title']
            : $post_data['title']['rendered'];

        // Update post
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($title_to_use),
            'post_content' => wp_kses_post($post_data['content']['rendered']),
            'post_excerpt' => isset($post_data['excerpt']['rendered']) ? wp_kses_post($post_data['excerpt']['rendered']) : '',
            'post_status' => 'draft', // Back to draft for re-processing
        ));

        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => 'Failed to update post: ' . $update_result->get_error_message()));
        }

        // Update featured image if available
        if (isset($post_data['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            $image_url = $post_data['_embedded']['wp:featuredmedia'][0]['source_url'];
            $featured_image_id = $this->download_and_attach_image($image_url, $title_to_use);
            if ($featured_image_id > 0) {
                set_post_thumbnail($post_id, $featured_image_id);
            }
        }

        // Update meta fields
        update_post_meta($post_id, 'kiosk_raw_post_data', $prepared_json);
        update_post_meta($post_id, 'kiosk_processing_status', 'pending');

        // Clear old ChatGPT data
        delete_post_meta($post_id, 'kiosk_chatgpt_json');

        // Schedule ChatGPT processing
        $this->schedule_chatgpt_processing();

        wp_send_json_success(array(
            'message' => 'Post updated successfully and queued for ChatGPT processing',
            'post_id' => $post_id,
            'source_id' => $source_id
        ));
    }

    /**
     * Add bulk update action to posts list
     */
    public function add_bulk_update_action($bulk_actions)
    {
        $bulk_actions['kiosk_bulk_update'] = '🔄 Update from Source';
        return $bulk_actions;
    }

    /**
     * Handle bulk update action
     */
    public function handle_bulk_update_action($redirect_to, $action, $post_ids)
    {
        if ($action !== 'kiosk_bulk_update') {
            return $redirect_to;
        }

        if (empty($post_ids)) {
            return $redirect_to;
        }

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($post_ids as $post_id) {
            // Get source post ID
            $source_id = get_post_meta($post_id, 'kiosk_source_post_id', true);

            if (empty($source_id)) {
                $skipped++;
                continue;
            }

            // Fetch fresh data from API
            $url = add_query_arg(array(
                '_embed' => 1,
                'acf_format' => 'standard'
            ), $this->get_api_base_url() . '/posts/' . $source_id);

            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'sslverify' => false
            ));

            if (is_wp_error($response)) {
                $errors++;
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $errors++;
                continue;
            }

            $post_data = json_decode(wp_remote_retrieve_body($response), true);

            if (!$post_data || !isset($post_data['id'])) {
                $errors++;
                continue;
            }

            // Prepare new JSON
            $prepared_json = $this->prepare_post_json($post_data);

            // Use ACF post_title if available
            $title_to_use = isset($post_data['acf']['post_title']) && !empty($post_data['acf']['post_title'])
                ? $post_data['acf']['post_title']
                : $post_data['title']['rendered'];

            // Update post
            $update_result = wp_update_post(array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field($title_to_use),
                'post_content' => wp_kses_post($post_data['content']['rendered']),
                'post_excerpt' => isset($post_data['excerpt']['rendered']) ? wp_kses_post($post_data['excerpt']['rendered']) : '',
                'post_status' => 'draft', // Back to draft for re-processing
            ));

            if (is_wp_error($update_result)) {
                $errors++;
                continue;
            }

            // Update featured image if available
            if (isset($post_data['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $image_url = $post_data['_embedded']['wp:featuredmedia'][0]['source_url'];
                $featured_image_id = $this->download_and_attach_image($image_url, $title_to_use);
                if ($featured_image_id > 0) {
                    set_post_thumbnail($post_id, $featured_image_id);
                }
            }

            // Update meta fields
            update_post_meta($post_id, 'kiosk_raw_post_data', $prepared_json);
            update_post_meta($post_id, 'kiosk_processing_status', 'pending');

            // Clear old ChatGPT data
            delete_post_meta($post_id, 'kiosk_chatgpt_json');

            $updated++;
        }

        // Schedule ChatGPT processing if posts were updated
        if ($updated > 0) {
            $this->schedule_chatgpt_processing();
        }

        // Redirect with results
        $redirect_to = add_query_arg(array(
            'kiosk_bulk_updated' => $updated,
            'kiosk_bulk_skipped' => $skipped,
            'kiosk_bulk_errors' => $errors
        ), $redirect_to);

        return $redirect_to;
    }

    /**
     * Display admin notice after bulk update
     */
    public function bulk_update_admin_notice()
    {
        if (!isset($_GET['kiosk_bulk_updated'])) {
            return;
        }

        $updated = intval($_GET['kiosk_bulk_updated']);
        $skipped = isset($_GET['kiosk_bulk_skipped']) ? intval($_GET['kiosk_bulk_skipped']) : 0;
        $errors = isset($_GET['kiosk_bulk_errors']) ? intval($_GET['kiosk_bulk_errors']) : 0;

        printf(
            '<div class="notice notice-success is-dismissible"><p>'
                . '<strong>Bulk Update Complete:</strong> '
                . '%d post(s) updated and queued for ChatGPT processing. '
                . '%d post(s) skipped (no source ID). '
                . '%d error(s).'
                . '</p></div>',
            $updated,
            $skipped,
            $errors
        );
    }

    /**
     * Manual sync handler (AJAX)
     */
    public function manual_sync()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $this->fetch_and_publish_content();

        $last_sync = get_option('kiosk_last_sync', array());
        wp_send_json_success($last_sync);
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
            'post_status' => array('draft', 'publish'), // Include both statuses
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
        $this->process_chatgpt_queue();

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

        // Get the API URL for debugging
        $api_url = $this->get_api_base_url();

        $posts = $this->fetch_posts_from_api(1, 1);

        if ($posts && is_array($posts) && count($posts) > 0) {
            // Use ACF post_title if available
            $sample_title = isset($posts[0]['acf']['post_title']) && !empty($posts[0]['acf']['post_title'])
                ? $posts[0]['acf']['post_title']
                : $posts[0]['title']['rendered'];

            wp_send_json_success(array(
                'message' => 'API connection successful!',
                'sample_post' => $sample_title,
                'api_url' => $api_url
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to connect to API',
                'api_url' => $api_url,
                'response_type' => gettype($posts),
                'is_array' => is_array($posts),
                'count' => is_array($posts) ? count($posts) : 0,
                'debug_info' => 'Check PHP error logs for more details'
            ));
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

        // Build URL with ACF support
        $url = add_query_arg(array(
            '_embed' => 1,
            'acf_format' => 'standard'
        ), $this->get_api_base_url() . '/posts/' . $post_id);

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
            wp_send_json_error('API returned error code: ' . $response_code . '. Response: ' . substr($body, 0, 200));
        }

        $post_data = json_decode($body, true);

        if (!$post_data || !isset($post_data['id'])) {
            wp_send_json_error('Failed to fetch post. Please check the Post ID. JSON Error: ' . json_last_error_msg());
        }

        // Extract available fields
        $available_fields = array();

        // Add standard fields
        $available_fields['Standard Fields'] = array(
            'title.rendered' => 'Post Title',
            'excerpt.rendered' => 'Post Excerpt',
            'content.rendered' => 'Post Content',
        );

        // Add ACF fields if available
        if (isset($post_data['acf']) && is_array($post_data['acf'])) {
            $available_fields['ACF Fields'] = array();
            foreach ($post_data['acf'] as $key => $value) {
                $label = ucwords(str_replace(array('_', '-', ':'), ' ', $key));
                $available_fields['ACF Fields'][$key] = $label . ' (' . $key . ')';
            }
        }

        // Prepare the JSON that will be sent to ChatGPT
        $prepared_json = $this->prepare_post_json($post_data);
        $prepared_data = json_decode($prepared_json, true);

        // Test ChatGPT processing if enabled (for testing only)
        $chatgpt_result = null;
        $chatgpt_full_json = null;
        $settings = get_option('kiosk_automation_settings', array());
        $openai_enabled = isset($settings['openai_enabled']) && $settings['openai_enabled'];

        if ($openai_enabled) {
            // Test the new async processing method
            $chatgpt_full_json = $this->process_post_data_with_chatgpt($prepared_json);
        }

        // Use ACF post_title if available
        $display_title = isset($post_data['acf']['post_title']) && !empty($post_data['acf']['post_title'])
            ? $post_data['acf']['post_title']
            : $post_data['title']['rendered'];

        wp_send_json_success(array(
            'post' => array(
                'id' => $post_data['id'],
                'title' => $display_title,
                'date' => $post_data['date'],
                'link' => $post_data['link']
            ),
            'available_fields' => $available_fields,
            'acf_data' => isset($post_data['acf']) ? $post_data['acf'] : array(),
            'prepared_json' => $prepared_data,
            'chatgpt_enabled' => $openai_enabled,
            'chatgpt_full_json' => $chatgpt_full_json,
            'note' => 'Posts are now created immediately with ACF data. ChatGPT processing happens asynchronously in background.'
        ));
    }

    /**
     * Fix post titles and slugs from ChatGPT data (AJAX)
     * This updates all posts that have ChatGPT data with the correct title and slug
     */
    public function fix_post_slugs_from_chatgpt()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Get all posts that have ChatGPT data
        $args = array(
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'kiosk_chatgpt_json',
                    'compare' => 'EXISTS'
                )
            )
        );

        $query = new WP_Query($args);
        $updated_count = 0;
        $skipped_count = 0;
        $error_count = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get ChatGPT JSON
                $chatgpt_json = get_post_meta($post_id, 'kiosk_chatgpt_json', true);

                if (empty($chatgpt_json)) {
                    $skipped_count++;
                    continue;
                }

                // Decode JSON
                $chatgpt_data = json_decode($chatgpt_json, true);

                if (!is_array($chatgpt_data) || empty($chatgpt_data['post_title'])) {
                    $skipped_count++;
                    continue;
                }

                // Get the new title from ChatGPT data
                $new_title = sanitize_text_field($chatgpt_data['post_title']);
                $new_slug = sanitize_title($new_title);

                // Update post with new title and slug
                $result = wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $new_title,
                    'post_name' => $new_slug
                ), true);

                if (is_wp_error($result)) {
                    $error_count++;
                } else {
                    // Set organization taxonomy from ChatGPT JSON
                    $this->set_post_organization($post_id, $chatgpt_json);

                    $updated_count++;
                }
            }
        }

        wp_reset_postdata();

        wp_send_json_success(array(
            'message' => "Updated {$updated_count} posts with correct titles and slugs",
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'total_checked' => $query->found_posts
        ));
    }

    /**
     * Update post_content from post_content_summary in ChatGPT JSON (AJAX)
     * This updates all posts that have ChatGPT data with post_content_summary
     */
    public function update_post_content_from_json()
    {
        check_ajax_referer('kiosk_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Get all posts that have ChatGPT data
        $args = array(
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'kiosk_chatgpt_json',
                    'compare' => 'EXISTS'
                )
            )
        );

        $query = new WP_Query($args);
        $updated_count = 0;
        $skipped_count = 0;
        $error_count = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get ChatGPT JSON
                $chatgpt_json = get_post_meta($post_id, 'kiosk_chatgpt_json', true);

                if (empty($chatgpt_json)) {
                    $skipped_count++;
                    continue;
                }

                // Decode JSON
                $chatgpt_data = json_decode($chatgpt_json, true);

                if (!is_array($chatgpt_data) || empty($chatgpt_data['post_content_summary'])) {
                    $skipped_count++;
                    continue;
                }

                // Get the post_content_summary from ChatGPT data
                $new_content = wp_kses_post($chatgpt_data['post_content_summary']);

                // Update post content
                $result = wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $new_content
                ), true);

                if (is_wp_error($result)) {
                    $error_count++;
                } else {
                    $updated_count++;
                }
            }
        }

        wp_reset_postdata();

        wp_send_json_success(array(
            'message' => "Updated {$updated_count} posts with content from ChatGPT post_content_summary",
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'total_checked' => $query->found_posts
        ));
    }
}

// Initialize the class
new Kiosk_Content_Automation();
