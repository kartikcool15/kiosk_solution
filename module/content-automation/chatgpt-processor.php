<?php

/**
 * ChatGPT Processor Module
 * Handles AI processing of posts using OpenAI API
 */

if (!defined('ABSPATH')) exit;

class Kiosk_ChatGPT_Processor
{
    /**
     * Process ChatGPT queue - processes posts marked as 'pending'
     * This runs in background via cron
     */
    public function process_queue()
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

            // Process this single post
            $result = $this->process_single_post($post_id);

            if ($result) {
                $processed_count++;
            } else {
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
            'post_status' => array('draft', 'publish'),
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
     * Process a single post with ChatGPT
     * 
     * @param int $post_id Post ID to process
     * @return bool Success status
     */
    public function process_single_post($post_id)
    {
        // Mark as processing
        update_post_meta($post_id, 'kiosk_processing_status', 'processing');

        // Get stored raw post data
        $raw_post_data = get_post_meta($post_id, 'kiosk_raw_post_data', true);

        if (empty($raw_post_data)) {
            update_post_meta($post_id, 'kiosk_processing_status', 'failed');
            return false;
        }

        // Decode the JSON
        $post_data_decoded = json_decode($raw_post_data, true);

        if (!$post_data_decoded) {
            update_post_meta($post_id, 'kiosk_processing_status', 'failed');
            return false;
        }

        // Process with ChatGPT
        $chatgpt_result = $this->process_with_api($raw_post_data);

        if ($chatgpt_result !== false) {
            // Store ChatGPT result
            update_post_meta($post_id, 'kiosk_chatgpt_json', $chatgpt_result);
            update_post_meta($post_id, 'kiosk_processing_status', 'completed');

            // Apply the ChatGPT data to the post
            $this->apply_chatgpt_data_to_post($post_id, $chatgpt_result);

            // Publish the post now that ChatGPT processing is complete
            wp_publish_post($post_id);

            return true;
        } else {
            update_post_meta($post_id, 'kiosk_processing_status', 'failed');
            return false;
        }
    }

    /**
     * Apply ChatGPT data to post (update title, content, taxonomies, dates)
     * 
     * @param int $post_id Post ID
     * @param string $chatgpt_json ChatGPT JSON data
     * @return bool Success status
     */
    public function apply_chatgpt_data_to_post($post_id, $chatgpt_json)
    {
        // Decode ChatGPT result to extract data
        $chatgpt_data = json_decode($chatgpt_json, true);

        if (!is_array($chatgpt_data)) {
            return false;
        }

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
        $this->set_post_organization($post_id, $chatgpt_json);

        // Set education taxonomy for latest-job category posts
        $this->set_post_education($post_id, $chatgpt_json);

        // Sync dates from ChatGPT JSON to custom fields
        $this->sync_dates_from_json($post_id, $chatgpt_json);

        return true;
    }

    /**
     * Process prepared JSON data with ChatGPT API
     * 
     * @param string $prepared_json Prepared JSON data
     * @return string|false ChatGPT response or false on failure
     */
    private function process_with_api($prepared_json)
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
     * Call OpenAI API
     * 
     * @param string $api_key OpenAI API key
     * @param string $model Model to use
     * @param string $system_prompt System prompt
     * @param string $user_prompt User prompt
     * @return string|false API response or false on failure
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
     * Read prompt file
     * 
     * @param string $filename Prompt filename
     * @return string|false File content or false on failure
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
     * Set organization taxonomy for a post from ChatGPT JSON
     * 
     * @param int $post_id Post ID
     * @param string $chatgpt_json ChatGPT JSON data
     * @return bool Success status
     */
    private function set_post_organization($post_id, $chatgpt_json)
    {
        if (empty($chatgpt_json)) {
            return false;
        }

        $chatgpt_data = json_decode($chatgpt_json, true);

        if (!is_array($chatgpt_data) || empty($chatgpt_data['organization'])) {
            return false;
        }

        $organization_name = $chatgpt_data['organization'];
        $term_id = $this->get_or_create_organization_term($organization_name);

        if (!$term_id) {
            return false;
        }

        $result = wp_set_object_terms($post_id, $term_id, 'organization', false);

        if (is_wp_error($result)) {
            error_log('Kiosk Automation: Failed to set organization term for post ' . $post_id . ': ' . $result->get_error_message());
            return false;
        }

        return true;
    }

    /**
     * Set education taxonomy for a post from ChatGPT JSON
     * 
     * @param int $post_id Post ID
     * @param string $chatgpt_json ChatGPT JSON data
     * @return bool Success status
     */
    private function set_post_education($post_id, $chatgpt_json)
    {
        // Check if post is in 'latest-job' category
        $categories = wp_get_post_categories($post_id, array('fields' => 'slugs'));
        if (!in_array('latest-job', $categories)) {
            return false; // Skip if not in latest-job category
        }

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

        $result = wp_set_object_terms($post_id, $term_ids, 'education', false);

        if (is_wp_error($result)) {
            error_log('Kiosk Automation: Failed to set education terms for post ' . $post_id . ': ' . $result->get_error_message());
            return false;
        }

        return true;
    }

    /**
     * Get or create organization term
     * 
     * @param string $organization_name Organization name
     * @return int|false Term ID or false on failure
     */
    private function get_or_create_organization_term($organization_name)
    {
        if (empty($organization_name)) {
            return false;
        }

        $organization_name = trim(sanitize_text_field($organization_name));

        if (empty($organization_name)) {
            return false;
        }

        $term = get_term_by('name', $organization_name, 'organization');

        if ($term && !is_wp_error($term)) {
            return intval($term->term_id);
        }

        $slug = sanitize_title($organization_name);
        $term = get_term_by('slug', $slug, 'organization');

        if ($term && !is_wp_error($term)) {
            return intval($term->term_id);
        }

        $result = wp_insert_term($organization_name, 'organization', array('slug' => $slug));

        if (is_wp_error($result)) {
            error_log('Kiosk Automation: Failed to create organization term: ' . $result->get_error_message());
            return false;
        }

        return intval($result['term_id']);
    }

    /**
     * Get or create education term
     * 
     * @param string $education_name Education name
     * @return int|false Term ID or false on failure
     */
    private function get_or_create_education_term($education_name)
    {
        if (empty($education_name)) {
            return false;
        }

        $education_name = trim(sanitize_text_field($education_name));

        if (empty($education_name)) {
            return false;
        }

        $term = get_term_by('name', $education_name, 'education');

        if ($term && !is_wp_error($term)) {
            return $term->term_id;
        }

        $result = wp_insert_term($education_name, 'education');

        if (is_wp_error($result)) {
            if (isset($result->error_data['term_exists'])) {
                return $result->error_data['term_exists'];
            }
            error_log('Kiosk Automation: Failed to create education term: ' . $result->get_error_message());
            return false;
        }

        return $result['term_id'];
    }

    /**
     * Sync dates from ChatGPT JSON to custom fields
     * 
     * @param int $post_id Post ID
     * @param string $chatgpt_json ChatGPT JSON data
     * @return bool Success status
     */
    private function sync_dates_from_json($post_id, $chatgpt_json)
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
     * Schedule ChatGPT processing for pending posts
     */
    public function schedule_processing()
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
}
