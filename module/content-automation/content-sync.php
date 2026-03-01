<?php

/**
 * Content Sync Controller
 * Main sync orchestrator with different sync modes
 */

if (!defined('ABSPATH')) exit;

class Kiosk_Content_Sync
{
    private $api_fetcher;
    private $post_processor;
    private $chatgpt_processor;

    public function __construct()
    {
        // Initialize dependencies
        $this->api_fetcher = new Kiosk_API_Fetcher();
        $this->post_processor = new Kiosk_Post_Processor();
        $this->chatgpt_processor = new Kiosk_ChatGPT_Processor();
    }

    /**
     * SYNC MODE 1: Fetch Recently Created Posts
     * Fetches only NEW posts created after last sync timestamp
     * 
     * @param int $per_page Number of posts to fetch
     * @param array $categories Category filter
     * @return array Sync results
     */
    public function fetch_recently_created($per_page = 10, $categories = array())
    {
        // Get last sync timestamp for created posts
        $last_sync = get_option('kiosk_last_created_sync', array());
        $created_after = isset($last_sync['timestamp_iso']) ? $last_sync['timestamp_iso'] : '';

        // Fetch posts created after timestamp (new posts only)
        $posts = $this->api_fetcher->fetch_posts(1, $per_page, $categories, '', $created_after);

        $imported_count = 0;
        $skipped_count = 0;
        $queued_count = 0;

        if (!$posts || !is_array($posts)) {
            // No posts found, but still update timestamp to show sync ran
            update_option('kiosk_last_created_sync', array(
                'time' => current_time('mysql'),
                'timestamp' => current_time('timestamp'),
                'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
                'imported' => 0,
                'skipped' => 0
            ));

            update_option('kiosk_last_sync', array(
                'time' => current_time('mysql'),
                'timestamp' => current_time('timestamp'),
                'timestamp_iso' => gmdate('Y-m-d\\TH:i:s'),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0
            ));

            // Log to Sentry
            \Sentry\logger()->info('Cron: Fetch Recently Created - No Posts Found', [
                'timestamp' => current_time('mysql'),
                'posts_returned' => 0,
                'message' => 'No new posts to sync'
            ]);
            \Sentry\logger()->flush();

            return array(
                'success' => true,
                'message' => 'No new posts to sync'
            );
        }

        foreach ($posts as $post_data) {
            $source_post_id = $post_data['id'];

            // Check if post already exists (skip if exists)
            $existing_post_id = $this->post_processor->post_exists_by_source_id($source_post_id);

            if ($existing_post_id) {
                $skipped_count++;
                continue; // Skip existing posts in "created" mode
            }

            // Prepare JSON for ChatGPT processing
            $prepared_json = $this->post_processor->prepare_post_json($post_data);

            // Create new post
            $new_post_id = $this->post_processor->create_post($post_data, $prepared_json);

            if ($new_post_id) {
                $imported_count++;
                $queued_count++;
            }
        }

        // Update last sync timestamp
        update_option('kiosk_last_created_sync', array(
            'time' => current_time('mysql'),
            'timestamp' => current_time('timestamp'),
            'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
            'imported' => $imported_count,
            'skipped' => $skipped_count
        ));

        // Also update main sync tracker for admin display
        update_option('kiosk_last_sync', array(
            'time' => current_time('mysql'),
            'timestamp' => current_time('timestamp'),
            'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
            'imported' => $imported_count,
            'updated' => 0,
            'skipped' => $skipped_count
        ));

        // Schedule ChatGPT processing
        if ($queued_count > 0) {
            $this->chatgpt_processor->schedule_processing();
        }

        // Log to Sentry
        \Sentry\logger()->info('Cron: Fetch Recently Created', [
            'timestamp' => current_time('mysql'),
            'posts_returned' => is_array($posts) ? count($posts) : 0,
            'posts_imported' => $imported_count,
            'posts_skipped' => $skipped_count,
            'queued_for_chatgpt' => $queued_count
        ]);
        \Sentry\logger()->flush();

        return array(
            'success' => true,
            'message' => 'Sync completed',
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'queued_for_processing' => $queued_count
        );
    }

    /**
     * SYNC MODE 2: Fetch Recently Modified Posts
     * Fetches posts modified after last sync timestamp (for updates)
     * Saves timestamp for next run
     * 
     * @param int $per_page Number of posts to fetch
     * @param array $categories Category filter
     * @return array Sync results
     */
    public function fetch_recently_modified($per_page = 10, $categories = array())
    {
        // Get last sync timestamp for modified posts
        $last_sync = get_option('kiosk_last_modified_sync', array());
        $modified_after = isset($last_sync['timestamp_iso']) ? $last_sync['timestamp_iso'] : '';

        // Fetch posts modified after timestamp
        $posts = $this->api_fetcher->fetch_posts(1, $per_page, $categories, $modified_after, '');

        $updated_count = 0;
        $imported_count = 0;
        $skipped_count = 0;
        $queued_count = 0;

        if (!$posts || !is_array($posts)) {
            // No posts found, but still update timestamp to show sync ran
            update_option('kiosk_last_modified_sync', array(
                'time' => current_time('mysql'),
                'timestamp' => current_time('timestamp'),
                'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
                'updated' => 0,
                'imported' => 0,
                'skipped' => 0
            ));

            update_option('kiosk_last_sync', array(
                'time' => current_time('mysql'),
                'timestamp' => current_time('timestamp'),
                'timestamp_iso' => gmdate('Y-m-d\\TH:i:s'),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0
            ));

            // Log to Sentry
            \Sentry\logger()->info('Cron: Fetch Recently Modified - No Posts Found', [
                'timestamp' => current_time('mysql'),
                'posts_returned' => 0,
                'message' => 'No modified posts to sync'
            ]);
            \Sentry\logger()->flush();

            return array(
                'success' => true,
                'message' => 'No modified posts to sync'
            );
        }

        foreach ($posts as $post_data) {
            $source_post_id = $post_data['id'];

            // Prepare JSON for ChatGPT processing
            $prepared_json = $this->post_processor->prepare_post_json($post_data);

            // Check if post already exists
            $existing_post_id = $this->post_processor->post_exists_by_source_id($source_post_id);

            if ($existing_post_id) {
                // Update existing post
                $this->post_processor->update_post($existing_post_id, $post_data, $prepared_json);
                $updated_count++;
                $queued_count++;
            } else {
                // Create new post
                $new_post_id = $this->post_processor->create_post($post_data, $prepared_json);
                if ($new_post_id) {
                    $imported_count++;
                    $queued_count++;
                }
            }
        }

        // Update last sync timestamp for modified posts
        update_option('kiosk_last_modified_sync', array(
            'time' => current_time('mysql'),
            'timestamp' => current_time('timestamp'),
            'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
            'updated' => $updated_count,
            'imported' => $imported_count,
            'skipped' => $skipped_count
        ));

        // Also update main sync tracker for admin display
        update_option('kiosk_last_sync', array(
            'time' => current_time('mysql'),
            'timestamp' => current_time('timestamp'),
            'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
            'imported' => $imported_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count
        ));

        // Schedule ChatGPT processing
        if ($queued_count > 0) {
            $this->chatgpt_processor->schedule_processing();
        }

        // Log to Sentry
        \Sentry\logger()->info('Cron: Fetch Recently Modified', [
            'timestamp' => current_time('mysql'),
            'posts_returned' => is_array($posts) ? count($posts) : 0,
            'posts_imported' => $imported_count,
            'posts_updated' => $updated_count,
            'posts_skipped' => $skipped_count,
            'queued_for_chatgpt' => $queued_count
        ]);
        \Sentry\logger()->flush();

        return array(
            'success' => true,
            'message' => 'Sync completed',
            'updated' => $updated_count,
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'queued_for_processing' => $queued_count
        );
    }

    /**
     * SYNC MODE 3: Resync Post Content
     * Remaps existing ChatGPT JSON to posts without re-fetching from ChatGPT
     * Use this when mapping logic changes (organization, slugs, etc.)
     * 
     * @param array $post_ids Specific post IDs to resync (empty for all)
     * @return array Sync results
     */
    public function resync_post_content($post_ids = array())
    {
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

        // If specific post IDs provided
        if (!empty($post_ids)) {
            $args['post__in'] = $post_ids;
            $args['posts_per_page'] = count($post_ids);
        }

        $query = new WP_Query($args);
        $updated_count = 0;
        $skipped_count = 0;
        $error_count = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get existing ChatGPT JSON
                $chatgpt_json = get_post_meta($post_id, 'kiosk_chatgpt_json', true);

                if (empty($chatgpt_json)) {
                    $skipped_count++;
                    continue;
                }

                // Re-apply ChatGPT data to post (this will remap taxonomies, update slugs, etc.)
                $result = $this->chatgpt_processor->apply_chatgpt_data_to_post($post_id, $chatgpt_json);

                if ($result) {
                    $updated_count++;
                } else {
                    $error_count++;
                }
            }
        }

        wp_reset_postdata();

        return array(
            'success' => true,
            'message' => "Resynced {$updated_count} posts",
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'total_checked' => $query->found_posts
        );
    }

    /**
     * SYNC MODE 4: Update All Posts
     * Re-processes all posts with ChatGPT using their raw JSON data
     * This fetches content from ChatGPT again
     * 
     * @param array $post_ids Specific post IDs to update (empty for all)
     * @return array Sync results
     */
    public function update_all_posts($post_ids = array())
    {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'kiosk_raw_post_data',
                    'compare' => 'EXISTS'
                )
            )
        );

        // If specific post IDs provided
        if (!empty($post_ids)) {
            $args['post__in'] = $post_ids;
            $args['posts_per_page'] = count($post_ids);
        }

        $query = new WP_Query($args);
        $queued_count = 0;
        $skipped_count = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get raw post data
                $raw_post_data = get_post_meta($post_id, 'kiosk_raw_post_data', true);

                if (empty($raw_post_data)) {
                    $skipped_count++;
                    continue;
                }

                // Mark for re-processing with ChatGPT
                update_post_meta($post_id, 'kiosk_processing_status', 'pending');

                // Clear old ChatGPT data to force fresh processing
                delete_post_meta($post_id, 'kiosk_chatgpt_json');

                $queued_count++;
            }
        }

        wp_reset_postdata();

        // Schedule ChatGPT processing
        if ($queued_count > 0) {
            $this->chatgpt_processor->schedule_processing();
        }

        return array(
            'success' => true,
            'message' => "Queued {$queued_count} posts for ChatGPT processing",
            'queued' => $queued_count,
            'skipped' => $skipped_count,
            'total_checked' => $query->found_posts
        );
    }

    /**
     * Legacy method: Fetch and publish content (supports both new and updated posts)
     * This is the original sync method kept for backwards compatibility
     * 
     * @param bool $force_full Force full sync (ignore timestamps)
     * @return array Sync results
     */
    public function fetch_and_publish_content($force_full = false)
    {
        $settings = get_option('kiosk_automation_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : false;

        if (!$enabled && !$force_full) {
            return array('success' => false, 'message' => 'Automation is disabled');
        }

        $per_page = isset($settings['posts_per_sync']) ? intval($settings['posts_per_sync']) : 10;

        // Get category filter if enabled
        $categories = array();
        if (isset($settings['filter_by_categories']) && $settings['filter_by_categories']) {
            $categories = isset($settings['source_categories']) ? $settings['source_categories'] : array();
        }

        // Get last sync timestamp
        $last_sync_data = get_option('kiosk_last_sync', array());
        $modified_after = '';

        // Use last successful sync timestamp in ISO 8601 format
        if (!$force_full && isset($last_sync_data['timestamp_iso'])) {
            $modified_after = $last_sync_data['timestamp_iso'];
        }

        // Only use modified_after (not created_after) to catch both new and updated posts
        // The modified_after parameter will include newly created posts since they're "modified" when created
        $posts = $this->api_fetcher->fetch_posts(1, $per_page, $categories, $modified_after, '');

        $imported_count = 0;
        $updated_count = 0;
        $skipped_count = 0;
        $queued_count = 0;

        if (!$posts || !is_array($posts)) {
            // No posts found, but still update timestamp to show cron ran
            update_option('kiosk_last_sync', array(
                'time' => current_time('mysql'),
                'timestamp' => current_time('timestamp'),
                'timestamp_iso' => gmdate('Y-m-d\\TH:i:s'),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0
            ));

            // Log to Sentry
            \Sentry\logger()->info('Cron: Fetch and Publish - No Posts Found', [
                'timestamp' => current_time('mysql'),
                'posts_returned' => 0,
                'message' => 'No new posts to sync'
            ]);
            \Sentry\logger()->flush();

            return array('success' => true, 'message' => 'No new posts to sync');
        }

        foreach ($posts as $post_data) {
            $source_post_id = $post_data['id'];
            $prepared_json = $this->post_processor->prepare_post_json($post_data);

            // Check if post already exists
            $existing_post_id = $this->post_processor->post_exists_by_source_id($source_post_id);

            if ($existing_post_id) {
                // Update existing post
                $this->post_processor->update_post($existing_post_id, $post_data, $prepared_json);
                $updated_count++;
                $queued_count++;
                
                // Log update to Sentry for debugging
                error_log("Kiosk Sync: Updated existing post - Source ID: {$source_post_id}, Local ID: {$existing_post_id}");
            } else {
                // Create new post
                $new_post_id = $this->post_processor->create_post($post_data, $prepared_json);
                if ($new_post_id) {
                    $imported_count++;
                    $queued_count++;
                    
                    // Log import to Sentry for debugging
                    error_log("Kiosk Sync: Created new post - Source ID: {$source_post_id}, Local ID: {$new_post_id}");
                }
            }
        }

        // Update last sync timestamp
        update_option('kiosk_last_sync', array(
            'time' => current_time('mysql'),
            'timestamp' => current_time('timestamp'),
            'timestamp_iso' => gmdate('Y-m-d\TH:i:s'),
            'imported' => $imported_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count
        ));

        // Schedule ChatGPT processing
        if ($queued_count > 0) {
            $this->chatgpt_processor->schedule_processing();
        }

        // Collect source post IDs for logging
        $source_post_ids = array();
        foreach ($posts as $post_data) {
            $source_post_ids[] = $post_data['id'];
        }

        // Log to Sentry
        \Sentry\logger()->info('Cron: Fetch and Publish Content', [
            'timestamp' => current_time('mysql'),
            'last_sync_timestamp' => $modified_after,
            'posts_returned' => is_array($posts) ? count($posts) : 0,
            'source_post_ids' => implode(',', $source_post_ids),
            'posts_imported' => $imported_count,
            'posts_updated' => $updated_count,
            'posts_skipped' => $skipped_count,
            'queued_for_chatgpt' => $queued_count,
            'force_full' => $force_full
        ]);
        \Sentry\logger()->flush();

        return array(
            'success' => true,
            'message' => 'Sync completed',
            'imported' => $imported_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'queued_for_processing' => $queued_count
        );
    }
}
