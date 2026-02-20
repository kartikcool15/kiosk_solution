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

        // Enable custom fields meta box
        add_action('init', array($this, 'enable_custom_fields_metabox'));

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

        // Admin column for ChatGPT status
        add_filter('manage_posts_columns', array($this, 'add_chatgpt_status_column'));
        add_action('manage_posts_custom_column', array($this, 'display_chatgpt_status_column'), 10, 2);
        add_action('admin_head', array($this, 'add_chatgpt_status_column_styles'));
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
    }

    /**
     * Enable custom fields meta box visibility
     */
    public function enable_custom_fields_metabox()
    {
        add_post_type_support('post', 'custom-fields');

        // Prevent ACF from hiding the default custom fields meta box
        add_filter('acf/settings/remove_wp_meta_box', '__return_false');
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
    private function fetch_posts_from_api($page = 1, $per_page = 10, $categories = array())
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
     * Extract custom field data from post content
     * Returns array with 'fields' (empty) and 'chatgpt_json' for JSON data
     */
    private function extract_custom_fields($post_data)
    {
        // Check if ChatGPT processing is enabled
        $settings = get_option('kiosk_automation_settings', array());
        $openai_enabled = isset($settings['openai_enabled']) && $settings['openai_enabled'];

        if ($openai_enabled) {
            // Try ChatGPT processing
            $chatgpt_result = $this->process_with_chatgpt($post_data);
            if ($chatgpt_result !== false && is_array($chatgpt_result)) {
                return array(
                    'fields' => array(), // Empty - not used anymore
                    'chatgpt_json' => $chatgpt_result['full_json']
                );
            }
            // If ChatGPT fails, fall through to prepared JSON
        }

        // Fallback: Use prepared JSON without ChatGPT
        $prepared_json = $this->prepare_post_json($post_data);

        return array(
            'fields' => array(), // Empty - not used anymore
            'chatgpt_json' => $prepared_json
        );
    }

    /**
     * Process post data through ChatGPT API
     * Returns array with 'fields' for mapped data and 'full_json' for complete ChatGPT response
     */
    private function process_with_chatgpt($post_data)
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

        // Prepare post JSON
        $post_json = $this->prepare_post_json($post_data);
        if (!$post_json) {
            error_log('Kiosk Automation: Failed to prepare post JSON');
            return false;
        }

        // Replace placeholder in user prompt
        $user_prompt = str_replace('[PASTE CLEANED JSON HERE]', $post_json, $user_prompt_template);

        // Call OpenAI API
        $response = $this->call_openai_api($api_key, $model, $system_prompt, $user_prompt);

        if (!$response) {
            error_log('Kiosk Automation: OpenAI API call failed');
            return false;
        }

        // Return the full JSON response
        return array(
            'fields' => array(), // Empty fields array (not used anymore)
            'full_json' => $response // Store the complete ChatGPT JSON response
        );
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
     * Clean HTML and convert to plain text, split by newlines if needed
     */
    private function clean_and_parse_field($value)
    {
        if (empty($value)) {
            return '';
        }

        // Strip HTML tags
        $text = wp_strip_all_tags($value);

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
     * Extract "as on" date from content
     */
    private function extract_as_on_date($content)
    {
        $text = wp_strip_all_tags($content);

        // Look for patterns like "as on DD/MM/YYYY" or "as on DD-MM-YYYY"
        if (preg_match('/as\s+on[:\s]+([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract links and FAQs from HTML containing multiple tables
     * Returns array with 'links' and 'faqs' keys
     */
    private function extract_links_from_html($html)
    {
        if (empty($html)) {
            return array('links' => '', 'faqs' => '');
        }

        $result = array('links' => array(), 'faqs' => array());

        // Split by table tags to separate multiple tables
        preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $tables, PREG_SET_ORDER);

        if (!empty($tables)) {
            // Process first table for links
            if (isset($tables[0][1])) {
                $first_table = $tables[0][1];
                preg_match_all('/<tr[^>]*>.*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>.*?<a[^>]*href=["\']([^"\']*)["\'][^>]*>.*?<\/a>.*?<\/td>.*?<\/tr>/is', $first_table, $link_matches, PREG_SET_ORDER);

                foreach ($link_matches as $match) {
                    $title = wp_strip_all_tags($match[1]);
                    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $title = trim($title);
                    $url = trim($match[2]);

                    if (!empty($url) && !empty($title)) {
                        $result['links'][] = array(
                            'title' => $title,
                            'url' => $url
                        );
                    }
                }
            }

            // Process second table for Q&A if exists
            if (isset($tables[1][1])) {
                $second_table = $tables[1][1];

                // Extract Q&A pairs
                preg_match_all('/<li[^>]*>.*?<strong[^>]*>Question:<\/strong>(.*?)<\/li>.*?<li[^>]*>.*?<strong[^>]*>Answer:?<\/strong>(.*?)<\/li>/is', $second_table, $qa_matches, PREG_SET_ORDER);

                foreach ($qa_matches as $match) {
                    $question = wp_strip_all_tags($match[1]);
                    $question = html_entity_decode($question, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $question = trim($question);

                    $answer = wp_strip_all_tags($match[2]);
                    $answer = html_entity_decode($answer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $answer = trim($answer);

                    if (!empty($question) && !empty($answer)) {
                        $result['faqs'][] = array(
                            'question' => $question,
                            'answer' => $answer
                        );
                    }
                }
            }
        } else {
            // Fallback: Match all anchor tags directly
            preg_match_all('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = trim($match[1]);
                $title = wp_strip_all_tags($match[2]);
                $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = trim($title);

                if (!empty($url) && !empty($title)) {
                    $result['links'][] = array(
                        'title' => $title,
                        'url' => $url
                    );
                }
            }
        }

        // Return empty strings if no data found
        if (empty($result['links'])) {
            $result['links'] = '';
        }
        if (empty($result['faqs'])) {
            $result['faqs'] = '';
        }

        return $result;
    }

    /**
     * Extract dates from HTML list structure
     * Returns array of objects with event and date
     */
    private function extract_dates_from_html($html)
    {
        if (empty($html)) {
            return '';
        }

        $dates = array();

        // Match all list items
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $item) {
                // Strip all HTML tags but preserve the text
                $text = wp_strip_all_tags($item);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = trim($text);

                // Skip empty items
                if (empty($text)) {
                    continue;
                }

                // Split by colon to separate event and date
                if (strpos($text, ':') !== false) {
                    $parts = explode(':', $text, 2);
                    $event = trim($parts[0]);
                    $date = isset($parts[1]) ? trim($parts[1]) : '';

                    if (!empty($event) && !empty($date)) {
                        $dates[] = array(
                            'event' => $event,
                            'date' => $date
                        );
                    }
                } else {
                    // If no colon, store as single event
                    $dates[] = array(
                        'event' => $text,
                        'date' => ''
                    );
                }
            }
        }

        // Return empty string if no dates found
        if (empty($dates)) {
            return '';
        }

        return $dates;
    }

    /**
     * Extract vacancy details from HTML containing multiple tables
     * Returns structured data with posts, eligibility, instructions, and selection mode
     */
    private function extract_vacancy_details_from_html($html)
    {
        if (empty($html)) {
            return '';
        }

        $result = array(
            'posts' => array(),
            'eligibility' => array(),
            'download_instructions' => array(),
            'selection_mode' => array()
        );

        // Split by table tags to separate multiple tables
        preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $tables, PREG_SET_ORDER);

        if (!empty($tables)) {
            // Process first table for vacancy posts and counts
            if (isset($tables[0][1])) {
                $first_table = $tables[0][1];

                // Extract all rows
                preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $first_table, $rows, PREG_SET_ORDER);

                $is_header = true;
                foreach ($rows as $row) {
                    // Extract all cells from this row
                    preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $row[1], $cells);

                    if (empty($cells[1]) || count($cells[1]) < 2) {
                        continue;
                    }

                    // Skip header row
                    if ($is_header) {
                        $is_header = false;
                        continue;
                    }

                    $post_name = wp_strip_all_tags($cells[1][0]);
                    $post_name = html_entity_decode($post_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $post_name = trim($post_name);

                    $post_count = wp_strip_all_tags($cells[1][1]);
                    $post_count = html_entity_decode($post_count, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $post_count = trim($post_count);

                    // Extract link from third column if exists
                    $details_link = '';
                    if (isset($cells[1][2]) && preg_match('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $cells[1][2], $link_match)) {
                        $details_link = trim($link_match[1]);
                    }

                    if (!empty($post_name)) {
                        $result['posts'][] = array(
                            'name' => $post_name,
                            'count' => $post_count,
                            'details_link' => $details_link
                        );
                    }
                }
            }

            // Process second table for eligibility criteria
            if (isset($tables[1][1])) {
                $second_table = $tables[1][1];

                // Extract all rows
                preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $second_table, $rows, PREG_SET_ORDER);

                $is_header = true;
                foreach ($rows as $row) {
                    // Extract all cells from this row
                    preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $row[1], $cells);

                    if (empty($cells[1]) || count($cells[1]) < 2) {
                        continue;
                    }

                    // Skip header row
                    if ($is_header) {
                        $is_header = false;
                        continue;
                    }

                    $post_name = wp_strip_all_tags($cells[1][0]);
                    $post_name = html_entity_decode($post_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $post_name = trim($post_name);

                    // Extract criteria from list items in second column
                    $criteria = array();
                    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $cells[1][1], $li_matches);
                    foreach ($li_matches[1] as $li_content) {
                        $criterion = wp_strip_all_tags($li_content);
                        $criterion = html_entity_decode($criterion, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $criterion = trim($criterion);
                        if (!empty($criterion)) {
                            $criteria[] = $criterion;
                        }
                    }

                    // If no list items found, treat entire cell content as single criterion
                    if (empty($criteria)) {
                        $criterion = wp_strip_all_tags($cells[1][1]);
                        $criterion = html_entity_decode($criterion, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $criterion = trim($criterion);
                        if (!empty($criterion)) {
                            $criteria[] = $criterion;
                        }
                    }

                    if (!empty($post_name) && !empty($criteria)) {
                        $result['eligibility'][] = array(
                            'post' => $post_name,
                            'criteria' => $criteria
                        );
                    }
                }
            }

            // Process third table for download instructions
            if (isset($tables[2][1])) {
                $third_table = $tables[2][1];
                preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $third_table, $instruction_matches);
                foreach ($instruction_matches[1] as $instruction) {
                    $text = wp_strip_all_tags($instruction);
                    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $text = trim($text);
                    if (!empty($text)) {
                        $result['download_instructions'][] = $text;
                    }
                }
            }

            // Process fourth table for selection mode
            if (isset($tables[3][1])) {
                $fourth_table = $tables[3][1];
                preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $fourth_table, $mode_matches);
                foreach ($mode_matches[1] as $mode) {
                    $text = wp_strip_all_tags($mode);
                    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $text = trim($text);
                    if (!empty($text)) {
                        $result['selection_mode'][] = $text;
                    }
                }
            }
        }

        // Return empty strings for empty arrays
        if (empty($result['posts'])) {
            $result['posts'] = '';
        }
        if (empty($result['eligibility'])) {
            $result['eligibility'] = '';
        }
        if (empty($result['download_instructions'])) {
            $result['download_instructions'] = '';
        }
        if (empty($result['selection_mode'])) {
            $result['selection_mode'] = '';
        }

        return $result;
    }

    /**
     * Clean post title by removing common suffixes
     */
    private function clean_title($title)
    {
        // Remove common suffixes that are added to source titles
        $patterns_to_remove = array(
            '/\s*[-–—]\s*Start\s*$/i',
            '/\s*[-–—]\s*Out\s*$/i',
            '/\s*[-–—]\s*Last Date Today\s*$/i',
            '/\s*[-–—]\s*Last Date\s*$/i',
            '/\s*[-–—]\s*Extended\s*$/i',
            '/\s*[-–—]\s*Live\s*$/i',
            '/\s*[-–—]\s*Apply Now\s*$/i',
            '/\s*[-–—]\s*Apply Online\s*$/i',
            '/\s*[-–—]\s*Online Form\s*$/i',
        );

        $cleaned_title = $title;
        foreach ($patterns_to_remove as $pattern) {
            $cleaned_title = preg_replace($pattern, '', $cleaned_title);
        }

        return trim($cleaned_title);
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
        $clean_data['title'] = $this->clean_title($raw_title);

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
     * Check if post already exists
     */
    private function post_exists_by_source_id($source_id)
    {
        $args = array(
            'post_type' => 'post',
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

        $posts = $this->fetch_posts_from_api(1, $per_page, $categories);

        if (!$posts || !is_array($posts)) {
            error_log('Kiosk: No posts fetched from API');
            return;
        }

        $imported_count = 0;
        $skipped_count = 0;
        $queued_for_processing = 0;

        foreach ($posts as $post_data) {
            $source_post_id = $post_data['id'];

            // Check if post already exists
            if ($this->post_exists_by_source_id($source_post_id)) {
                $skipped_count++;
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

            // Clean and prepare title
            $clean_title = $this->clean_title($title_to_use);

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
                'post_title' => sanitize_text_field($clean_title),
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

        // Log the results
        update_option('kiosk_last_sync', array(
            'time' => current_time('mysql'),
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'queued_for_chatgpt' => $queued_for_processing
        ));
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
        </style>';
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
}

// Initialize the class
new Kiosk_Content_Automation();
