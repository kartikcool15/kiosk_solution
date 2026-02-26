<?php

/**
 * Admin Settings Page for Content Automation
 */

if (!defined('ABSPATH')) exit;

class Kiosk_Admin_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Content Automation', 'kiosk'),
            __('Content Sync', 'kiosk'),
            'manage_options',
            'kiosk-automation',
            array($this, 'render_settings_page'),
            'dashicons-update',
            26
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('kiosk_automation_settings', 'kiosk_automation_settings');
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'kiosk-automation') === false) {
            return;
        }

        wp_enqueue_style('kiosk-admin', get_template_directory_uri() . '/module/admin/admin-styles.css', array(), '1.0.1');
        wp_enqueue_script('kiosk-admin', get_template_directory_uri() . '/module/admin/admin-scripts.js', array('jquery'), '1.0.1', true);

        wp_localize_script('kiosk-admin', 'kioskAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kiosk_admin_nonce')
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        $settings = get_option('kiosk_automation_settings', array(
            'enabled' => false,
            'api_base_url' => 'https://sarkariresult.com.cm/wp-json/wp/v2',
            'cron_schedule' => 'every_5_minutes',
            'posts_per_sync' => 10,
            'filter_by_categories' => false,
            'source_categories' => array(),
            'openai_enabled' => false,
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o'
        ));

        $last_sync = get_option('kiosk_last_sync', array());

?>
        <div class="wrap kiosk-admin-wrap">
            <h1><?php _e('Content Automation Settings', 'kiosk'); ?></h1>

            <div class="kiosk-admin-grid">

                <!-- Main Settings Card -->
                <div class="kiosk-card">
                    <h2><?php _e('Automation Settings', 'kiosk'); ?></h2>

                    <form method="post" action="options.php">
                        <?php settings_fields('kiosk_automation_settings'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('API Base URL', 'kiosk'); ?></th>
                                <td>
                                    <input type="url" name="kiosk_automation_settings[api_base_url]"
                                        value="<?php echo esc_url($settings['api_base_url']); ?>"
                                        class="regular-text" placeholder="https://example.com/wp-json/wp/v2">
                                    <p class="description">
                                        <?php _e('Enter the API base URL (e.g., https://sarkariresult.com.cm/wp-json/wp/v2)', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Enable Automation', 'kiosk'); ?></th>
                                <td>
                                    <label class="kiosk-toggle">
                                        <input type="checkbox" name="kiosk_automation_settings[enabled]" value="1"
                                            <?php checked(isset($settings['enabled']) && $settings['enabled'], true); ?>>
                                        <span class="kiosk-toggle-slider"></span>
                                    </label>
                                    <p class="description">
                                        <?php _e('Enable automatic content fetching and publishing', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Sync Schedule', 'kiosk'); ?></th>
                                <td>
                                    <select name="kiosk_automation_settings[cron_schedule]">
                                        <option value="every_5_minutes" <?php selected($settings['cron_schedule'], 'every_5_minutes'); ?>>
                                            <?php _e('Every 5 Minutes', 'kiosk'); ?>
                                        </option>
                                        <option value="every_30_minutes" <?php selected($settings['cron_schedule'], 'every_30_minutes'); ?>>
                                            <?php _e('Every 30 Minutes', 'kiosk'); ?>
                                        </option>
                                        <option value="hourly" <?php selected($settings['cron_schedule'], 'hourly'); ?>>
                                            <?php _e('Every Hour', 'kiosk'); ?>
                                        </option>
                                        <option value="every_6_hours" <?php selected($settings['cron_schedule'], 'every_6_hours'); ?>>
                                            <?php _e('Every 6 Hours', 'kiosk'); ?>
                                        </option>
                                        <option value="every_12_hours" <?php selected($settings['cron_schedule'], 'every_12_hours'); ?>>
                                            <?php _e('Every 12 Hours', 'kiosk'); ?>
                                        </option>
                                        <option value="daily" <?php selected($settings['cron_schedule'], 'daily'); ?>>
                                            <?php _e('Daily', 'kiosk'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('How often to check for new content', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Posts Per Sync', 'kiosk'); ?></th>
                                <td>
                                    <input type="number" name="kiosk_automation_settings[posts_per_sync]"
                                        value="<?php echo esc_attr($settings['posts_per_sync']); ?>"
                                        min="1" max="100" class="small-text">
                                    <p class="description">
                                        <?php _e('Maximum number of posts to fetch per sync (1-100)', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Filter By Categories', 'kiosk'); ?></th>
                                <td>
                                    <label class="kiosk-toggle">
                                        <input type="checkbox" name="kiosk_automation_settings[filter_by_categories]" value="1"
                                            <?php checked(isset($settings['filter_by_categories']) && $settings['filter_by_categories'], true); ?>>
                                        <span class="kiosk-toggle-slider"></span>
                                    </label>
                                    <p class="description">
                                        <?php _e('Enable to fetch only posts from selected categories', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Select Categories', 'kiosk'); ?></th>
                                <td>
                                    <?php
                                    $source_categories = array(
                                        2 => 'Admission',
                                        3 => 'Admit Card',
                                        4 => 'Answer Key',
                                        1 => 'Blog',
                                        170 => 'Documents',
                                        5 => 'Latest Job',
                                        169 => 'New Format',
                                        6 => 'Result',
                                        7 => 'Sarkari Job',
                                        8 => 'Sarkari Yojana'
                                    );
                                    $selected_categories = isset($settings['source_categories']) ? $settings['source_categories'] : array();
                                    ?>
                                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                        <?php foreach ($source_categories as $cat_id => $cat_name) : ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox" name="kiosk_automation_settings[source_categories][]"
                                                    value="<?php echo esc_attr($cat_id); ?>"
                                                    <?php checked(in_array($cat_id, $selected_categories)); ?>>
                                                <?php echo esc_html($cat_name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">
                                        <?php _e('Select source categories to fetch (only works when "Filter By Categories" is enabled)', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <h3 style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;"><?php _e('🤖 ChatGPT Processing', 'kiosk'); ?></h3>
                        <p class="description" style="margin-bottom: 20px;">
                            <?php _e('Enable AI-powered content extraction using ChatGPT to parse and structure post data from custom fields.', 'kiosk'); ?>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Enable ChatGPT Processing', 'kiosk'); ?></th>
                                <td>
                                    <label class="kiosk-toggle">
                                        <input type="checkbox" name="kiosk_automation_settings[openai_enabled]" value="1"
                                            <?php checked(isset($settings['openai_enabled']) && $settings['openai_enabled'], true); ?>>
                                        <span class="kiosk-toggle-slider"></span>
                                    </label>
                                    <p class="description">
                                        <?php _e('Process fetched posts through ChatGPT for structured data extraction', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('OpenAI API Key', 'kiosk'); ?></th>
                                <td>
                                    <input type="password" name="kiosk_automation_settings[openai_api_key]"
                                        value="<?php echo esc_attr($settings['openai_api_key']); ?>"
                                        class="regular-text" placeholder="sk-...">
                                    <p class="description">
                                        <?php _e('Your OpenAI API key. Get it from', 'kiosk'); ?>
                                        <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('OpenAI Model', 'kiosk'); ?></th>
                                <td>
                                    <select name="kiosk_automation_settings[openai_model]">
                                        <option value="gpt-4o" <?php selected($settings['openai_model'], 'gpt-4o'); ?>>
                                            <?php _e('GPT-4o (Recommended)', 'kiosk'); ?>
                                        </option>
                                        <option value="gpt-4o-mini" <?php selected($settings['openai_model'], 'gpt-4o-mini'); ?>>
                                            <?php _e('GPT-4o Mini (Faster, Cheaper)', 'kiosk'); ?>
                                        </option>
                                        <option value="gpt-4-turbo" <?php selected($settings['openai_model'], 'gpt-4-turbo'); ?>>
                                            <?php _e('GPT-4 Turbo', 'kiosk'); ?>
                                        </option>
                                        <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'], 'gpt-3.5-turbo'); ?>>
                                            <?php _e('GPT-3.5 Turbo (Budget)', 'kiosk'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Select the ChatGPT model to use for processing', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Prompt Files', 'kiosk'); ?></th>
                                <td>
                                    <?php
                                    $system_prompt = get_template_directory() . '/prompt/system-prompt.txt';
                                    $user_prompt = get_template_directory() . '/prompt/user-prompt.txt';
                                    ?>
                                    <p>
                                        <strong>System Prompt:</strong> <?php echo file_exists($system_prompt) ? '✓ Found' : '✗ Missing'; ?><br>
                                        <strong>User Prompt:</strong> <?php echo file_exists($user_prompt) ? '✓ Found' : '✗ Missing'; ?>
                                    </p>
                                    <p class="description">
                                        <?php _e('Prompt templates are located in themes/kiosk/prompt/', 'kiosk'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Save Settings', 'kiosk')); ?>
                    </form>
                </div>

                <!-- Status Card -->
                <div class="kiosk-card">
                    <h2><?php _e('Sync Status', 'kiosk'); ?></h2>

                    <div class="kiosk-status-info">
                        <div class="status-item">
                            <span class="label"><?php _e('Automation Status:', 'kiosk'); ?></span>
                            <span class="value">
                                <?php if (isset($settings['enabled']) && $settings['enabled']): ?>
                                    <span class="status-badge status-active"><?php _e('Active', 'kiosk'); ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive"><?php _e('Inactive', 'kiosk'); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if (!empty($last_sync)): ?>
                            <div class="status-item">
                                <span class="label"><?php _e('Last Sync:', 'kiosk'); ?></span>
                                <span class="value"><?php echo isset($last_sync['time']) ? esc_html($last_sync['time']) : '-'; ?></span>
                            </div>

                            <div class="status-item">
                                <span class="label"><?php _e('Posts Imported:', 'kiosk'); ?></span>
                                <span class="value"><?php echo isset($last_sync['imported']) ? intval($last_sync['imported']) : 0; ?></span>
                            </div>

                            <div class="status-item">
                                <span class="label"><?php _e('Posts Updated:', 'kiosk'); ?></span>
                                <span class="value"><?php echo isset($last_sync['updated']) ? intval($last_sync['updated']) : 0; ?></span>
                            </div>

                            <div class="status-item">
                                <span class="label"><?php _e('Posts Skipped:', 'kiosk'); ?></span>
                                <span class="value"><?php echo isset($last_sync['skipped']) ? intval($last_sync['skipped']) : 0; ?></span>
                            </div>
                        <?php else: ?>
                            <p class="no-sync-data"><?php _e('No sync data available yet.', 'kiosk'); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php 
                    // Check cron status
                    $next_cron = wp_next_scheduled('kiosk_fetch_content_cron');
                    $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
                    ?>
                    <div class="kiosk-cron-status" style="margin: 15px 0; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                        <div class="status-item">
                            <span class="label"><strong><?php _e('Cron Status:', 'kiosk'); ?></strong></span>
                            <?php if ($cron_disabled): ?>
                                <span class="value" style="color: #d63638;">
                                    ⚠️ <?php _e('WP Cron is DISABLED. Please setup external cron job.', 'kiosk'); ?>
                                </span>
                            <?php elseif ($next_cron): ?>
                                <span class="value" style="color: #2271b1;">
                                    ✓ <?php echo sprintf(__('Next sync scheduled at: %s (in %s)', 'kiosk'), 
                                        date('Y-m-d H:i:s', $next_cron),
                                        human_time_diff($next_cron, current_time('timestamp'))
                                    ); ?>
                                </span>
                            <?php else: ?>
                                <span class="value" style="color: #d63638;">
                                    ✗ <?php _e('No cron scheduled! Enable automation and save settings.', 'kiosk'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="kiosk-actions">
                        <button type="button" class="button button-primary button-large" id="kiosk-test-connection">
                            <?php _e('Test API Connection', 'kiosk'); ?>
                        </button>

                        <button type="button" class="button button-secondary button-large" id="kiosk-manual-sync">
                            <?php _e('Run Manual Sync Now', 'kiosk'); ?>
                        </button>

                        <button type="button" class="button button-secondary button-large" id="kiosk-force-full-sync">
                            <?php _e('Force Full Sync (Ignore Date Filter)', 'kiosk'); ?>
                        </button>

                        <button type="button" class="button button-secondary button-large" id="kiosk-fix-slugs">
                            <?php _e('Fix Post Slugs from ChatGPT', 'kiosk'); ?>
                        </button>

                        <button type="button" class="button button-secondary button-large" id="kiosk-update-content">
                            <?php _e('Update Post Content from ChatGPT', 'kiosk'); ?>
                        </button>
                    </div>

                    <p class="description" style="margin-top: 10px;">
                        <strong><?php _e('Note:', 'kiosk'); ?></strong> 
                        <?php _e('The "Fix Post Slugs" button updates all posts with correct titles and URL slugs from their ChatGPT processed data. Use this if posts are missing proper URLs or have incorrect titles.', 'kiosk'); ?>
                        <br>
                        <?php _e('The "Update Post Content" button updates all posts with post_content_summary from their ChatGPT processed data. Use this to replace post content with the AI-generated summary.', 'kiosk'); ?>
                    </p>

                    <div id="kiosk-sync-response" class="kiosk-response" style="display:none;"></div>
                </div>

                <!-- Custom Fields Info -->
                <div class="kiosk-card full-width">
                    <h2><?php _e('Custom Fields Available', 'kiosk'); ?></h2>
                    <p><?php _e('The following custom fields are automatically extracted and stored for each imported post:', 'kiosk'); ?></p>

                    <div class="kiosk-fields-grid">
                        <div class="field-info">
                            <strong><?php _e('Overview', 'kiosk'); ?></strong>
                            <code>kiosk_overview</code>
                        </div>
                        <div class="field-info">
                            <strong><?php _e('Important Dates', 'kiosk'); ?></strong>
                            <code>kiosk_important_dates</code>
                        </div>
                        <div class="field-info">
                            <strong><?php _e('Eligibility', 'kiosk'); ?></strong>
                            <code>kiosk_eligibility</code>
                        </div>
                        <div class="field-info">
                            <strong><?php _e('Required Documents', 'kiosk'); ?></strong>
                            <code>kiosk_required_documents</code>
                        </div>
                        <div class="field-info">
                            <strong><?php _e('Direct Apply Link', 'kiosk'); ?></strong>
                            <code>kiosk_apply_link</code>
                        </div>
                        <div class="field-info">
                            <strong><?php _e('Official Notification PDF', 'kiosk'); ?></strong>
                            <code>kiosk_notification_pdf</code>
                        </div>
                        <div class="field-info gold">
                            <strong><?php _e('Form Filling Instructions', 'kiosk'); ?> 🏆</strong>
                            <code>kiosk_form_instructions</code>
                        </div>
                    </div>

                    <p class="description">
                        <?php _e('Use these meta keys in your templates with', 'kiosk'); ?>
                        <code>get_post_meta($post_id, 'meta_key', true)</code>
                    </p>
                </div>

            </div>
        </div>
<?php
    }
}

// Handle cron schedule changes
add_action('update_option_kiosk_automation_settings', 'kiosk_update_cron_schedule', 10, 2);

// Auto-fix cron schedule on admin init
add_action('admin_init', 'kiosk_check_and_fix_cron_schedule');

function kiosk_check_and_fix_cron_schedule()
{
    $settings = get_option('kiosk_automation_settings', array());
    
    // Skip if automation is not enabled
    if (empty($settings['enabled'])) {
        return;
    }
    
    $desired_schedule = isset($settings['cron_schedule']) ? $settings['cron_schedule'] : 'every_5_minutes';
    $next_cron = wp_next_scheduled('kiosk_fetch_content_cron');
    
    // If no cron is scheduled, schedule it now
    if (!$next_cron) {
        wp_schedule_event(time(), $desired_schedule, 'kiosk_fetch_content_cron');
        return;
    }
    
    // Check if the current schedule matches the desired schedule
    $cron_array = _get_cron_array();
    $current_schedule = null;
    
    foreach ($cron_array as $timestamp => $cron_jobs) {
        if (isset($cron_jobs['kiosk_fetch_content_cron'])) {
            foreach ($cron_jobs['kiosk_fetch_content_cron'] as $job) {
                $current_schedule = isset($job['schedule']) ? $job['schedule'] : null;
                break 2;
            }
        }
    }
    
    // If schedules don't match, reschedule
    if ($current_schedule !== $desired_schedule) {
        wp_unschedule_event($next_cron, 'kiosk_fetch_content_cron');
        wp_schedule_event(time(), $desired_schedule, 'kiosk_fetch_content_cron');
    }
}

function kiosk_update_cron_schedule($old_value, $new_value)
{
    // Clear existing cron
    $timestamp = wp_next_scheduled('kiosk_fetch_content_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'kiosk_fetch_content_cron');
    }

    // Schedule new cron if enabled
    if (isset($new_value['enabled']) && $new_value['enabled']) {
        $schedule = isset($new_value['cron_schedule']) ? $new_value['cron_schedule'] : 'every_5_minutes';
        wp_schedule_event(time(), $schedule, 'kiosk_fetch_content_cron');
    }
}

// Clear cron on theme deactivation
register_deactivation_hook(__FILE__, 'kiosk_deactivate_cron');

function kiosk_deactivate_cron()
{
    $timestamp = wp_next_scheduled('kiosk_fetch_content_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'kiosk_fetch_content_cron');
    }
}

// Add Modified Date Column to Posts Admin
add_filter('manage_posts_columns', 'kiosk_add_modified_date_column');
add_action('manage_posts_custom_column', 'kiosk_display_modified_date_column', 10, 2);
add_filter('manage_edit-post_sortable_columns', 'kiosk_modified_date_sortable_column');

/**
 * Add Modified Date column to posts list
 */
function kiosk_add_modified_date_column($columns)
{
    // Insert Modified Date column after the Date column
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'date') {
            $new_columns['modified_date'] = __('Modified Date', 'kiosk');
        }
    }
    return $new_columns;
}

/**
 * Display Modified Date column content
 */
function kiosk_display_modified_date_column($column_name, $post_id)
{
    if ($column_name === 'modified_date') {
        $modified_time = get_post_modified_time('U', false, $post_id);
        $modified_date = get_post_modified_time('Y/m/d', false, $post_id);
        $modified_time_display = get_post_modified_time('g:i a', false, $post_id);
        
        $time_diff = time() - $modified_time;
        
        if ($time_diff < DAY_IN_SECONDS) {
            $display = sprintf(__('%s ago', 'kiosk'), human_time_diff($modified_time, current_time('timestamp')));
        } else {
            $display = $modified_date . '<br>' . $modified_time_display;
        }
        
        echo '<abbr title="' . esc_attr(get_post_modified_time('c', false, $post_id)) . '">' . esc_html($display) . '</abbr>';
    }
}

/**
 * Make Modified Date column sortable
 */
function kiosk_modified_date_sortable_column($columns)
{
    $columns['modified_date'] = 'modified';
    return $columns;
}

// Initialize the class
new Kiosk_Admin_Settings();
