<?php

/**
 * Admin Columns and Actions for Content Automation
 * Handles posts list custom columns, row actions, and bulk actions
 */

if (!defined('ABSPATH')) exit;

class Kiosk_Admin_Columns
{
    private $api_fetcher;
    private $post_processor;
    private $chatgpt_processor;
    private $content_sync;

    public function __construct()
    {
        // Initialize dependencies
        $this->api_fetcher = new Kiosk_API_Fetcher();
        $this->post_processor = new Kiosk_Post_Processor();
        $this->chatgpt_processor = new Kiosk_ChatGPT_Processor();
        $this->content_sync = new Kiosk_Content_Sync();

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

        // Get API base URL
        $settings = get_option('kiosk_automation_settings', array());
        $api_base_url = isset($settings['api_base_url']) ? rtrim($settings['api_base_url'], '/') : 'https://sarkariresult.com.cm/wp-json/wp/v2';

        // Fetch fresh data from API
        $url = add_query_arg(array(
            '_embed' => 1,
            'acf_format' => 'standard'
        ), $api_base_url . '/posts/' . $source_id);

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
        $prepared_json = $this->post_processor->prepare_post_json($post_data);

        // Update post using post processor
        $this->post_processor->update_post($post_id, $post_data, $prepared_json);

        // Schedule ChatGPT processing
        $this->chatgpt_processor->schedule_processing();

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

        // Get API base URL
        $settings = get_option('kiosk_automation_settings', array());
        $api_base_url = isset($settings['api_base_url']) ? rtrim($settings['api_base_url'], '/') : 'https://sarkariresult.com.cm/wp-json/wp/v2';

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
            ), $api_base_url . '/posts/' . $source_id);

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
            $prepared_json = $this->post_processor->prepare_post_json($post_data);

            // Update post using post processor
            $result = $this->post_processor->update_post($post_id, $post_data, $prepared_json);

            if ($result) {
                $updated++;
            } else {
                $errors++;
            }
        }

        // Schedule ChatGPT processing if posts were updated
        if ($updated > 0) {
            $this->chatgpt_processor->schedule_processing();
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
}
