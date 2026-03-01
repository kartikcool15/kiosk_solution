<?php

/**
 * Post Processor Module
 * Handles post creation, updates, and data preparation
 */

if (!defined('ABSPATH')) exit;

class Kiosk_Post_Processor
{
    /**
     * Check if post already exists by source ID (checks all post statuses)
     * 
     * @param int $source_id Source post ID from API
     * @return int|false Post ID if exists, false otherwise
     */
    public function post_exists_by_source_id($source_id)
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
     * Create a new post from API data
     * 
     * @param array $post_data Post data from API
     * @param string $prepared_json Prepared JSON for ChatGPT processing
     * @return int|false New post ID or false on failure
     */
    public function create_post($post_data, $prepared_json)
    {
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

        if (is_wp_error($new_post_id) || !$new_post_id) {
            return false;
        }

        // Set featured image
        if ($featured_image_id > 0) {
            set_post_thumbnail($new_post_id, $featured_image_id);
        }

        // Save source post ID
        update_post_meta($new_post_id, 'kiosk_source_post_id', $post_data['id']);
        
        // Store source modified timestamp for future sync comparisons
        if (isset($post_data['modified_gmt']) && !empty($post_data['modified_gmt'])) {
            update_post_meta($new_post_id, 'kiosk_source_modified_gmt', $post_data['modified_gmt']);
        }

        // Store complete cleaned ACF JSON for later ChatGPT processing
        update_post_meta($new_post_id, 'kiosk_raw_post_data', $prepared_json);
        update_post_meta($new_post_id, 'kiosk_processing_status', 'pending');

        return $new_post_id;
    }

    /**
     * Update existing post with new data from API
     * 
     * @param int $post_id Existing post ID
     * @param array $post_data Post data from API
     * @param string $prepared_json Prepared JSON for ChatGPT processing
     * @return bool Success status
     */
    public function update_post($post_id, $post_data, $prepared_json)
    {
        // Check if source post has actually been modified since last sync
        $source_modified = isset($post_data['modified_gmt']) ? $post_data['modified_gmt'] : '';
        $last_synced_modified = get_post_meta($post_id, 'kiosk_source_modified_gmt', true);
        
        error_log("Kiosk Sync Debug: Post {$post_id} (Source: {$post_data['id']}) - Source Modified: {$source_modified}, Last Synced: {$last_synced_modified}");
        
        // If this is a legacy post without timestamp, just store it and skip update
        if (empty($last_synced_modified) && !empty($source_modified)) {
            update_post_meta($post_id, 'kiosk_source_modified_gmt', $source_modified);
            error_log("Kiosk Sync: Legacy post {$post_id} - Stored timestamp without updating: {$source_modified}");
            return false; // Skip update, just stored timestamp
        }
        
        // Skip update if source hasn't been modified since last sync
        if (!empty($source_modified) && !empty($last_synced_modified)) {
            $source_time = strtotime($source_modified);
            $synced_time = strtotime($last_synced_modified);
            
            if ($source_time <= $synced_time) {
                error_log("Kiosk Sync: Skipping update - Source post {$post_data['id']} not modified (Source: {$source_modified} [{$source_time}], Last: {$last_synced_modified} [{$synced_time}])");
                return false; // No update needed
            }
            
            error_log("Kiosk Sync: Source post {$post_data['id']} WAS modified - Will update (Source: {$source_modified} [{$source_time}], Last: {$last_synced_modified} [{$synced_time}])");
        }
        
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
        
        // Store the source post's modified timestamp to prevent re-updating
        if (!empty($source_modified)) {
            update_post_meta($post_id, 'kiosk_source_modified_gmt', $source_modified);
        }

        // Clear old ChatGPT data to force re-processing
        delete_post_meta($post_id, 'kiosk_chatgpt_json');
        
        error_log("Kiosk Sync: Successfully updated post {$post_id} - Source modified: {$source_modified}");

        return true;
    }

    /**
     * Map categories from source to local
     * Only uses the first category from source
     * 
     * @param array $source_categories Source category IDs
     * @param bool $return_details Return full category details
     * @return array Array of local category IDs or details
     */
    public function map_categories($source_categories, $return_details = false)
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
     * Prepare post data as clean JSON for ChatGPT with custom field mappings
     * 
     * @param array $post_data Post data from API
     * @return string JSON encoded data
     */
    public function prepare_post_json($post_data)
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
     * Clean HTML and convert to plain text, preserving links
     * 
     * @param string $value HTML content
     * @return string|array Cleaned text or array of lines
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
     * Extract links from HTML and preserve them with their URLs
     * 
     * @param string $html HTML content
     * @return string Processed content
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
     * Check if URL should be excluded/filtered
     * 
     * @param string $url URL to check
     * @return bool True if should be excluded
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
     * Download and attach image
     * 
     * @param string $image_url Image URL
     * @param string $post_title Post title for image alt text
     * @return int Attachment ID or 0 on failure
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
}
