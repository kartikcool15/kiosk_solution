<?php
function kiosk_theme_setup() {
    // Add theme support for various features
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');

    // Register navigation menu
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'kiosk'),
    ));

    // Register sidebar
    register_sidebar(array(
        'name' => __('Main Sidebar', 'kiosk'),
        'id' => 'main-sidebar',
        'description' => __('Widgets for the main sidebar', 'kiosk'),
        'before_widget' => '<div class="widget">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));
}
add_action('after_setup_theme', 'kiosk_theme_setup');

function kiosk_enqueue_styles() {
    wp_enqueue_style('kiosk-style', get_stylesheet_uri(), array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'kiosk_enqueue_styles');

function kiosk_enqueue_scripts() {
    // Enqueue SlimSelect library
    wp_enqueue_style(
        'slimselect-css',
        'https://cdn.jsdelivr.net/npm/slim-select@latest/dist/slimselect.css',
        array(),
        null
    );

    wp_enqueue_script(
        'slimselect-js',
        'https://cdn.jsdelivr.net/npm/slim-select@latest/dist/slimselect.min.js',
        array(),
        null,
        true
    );

    wp_enqueue_script('kiosk-main', get_template_directory_uri() . '/assets/main.js', array('jquery', 'slimselect-js'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'kiosk_enqueue_scripts');

// Filter posts by education taxonomy on category archives
function kiosk_filter_by_education($query) {
    // Only on frontend category archives for latest-job
    if (!is_admin() && $query->is_main_query() && is_category('latest-job')) {
        // Check if education filter is set
        if (isset($_GET['education']) && !empty($_GET['education'])) {
            $education_term = $_GET['education'];
            
            // Add tax query
            $tax_query = array(
                array(
                    'taxonomy' => 'education',
                    'field'    => 'slug',
                    'terms'    => $education_term,
                ),
            );
            
            $query->set('tax_query', $tax_query);
        }
    }
}
add_action('pre_get_posts', 'kiosk_filter_by_education');

/**
 * Bulk process existing posts to assign education taxonomy
 * Run this once via URL: yoursite.com/?process_education_taxonomy=1
 */
function kiosk_process_existing_education_taxonomy() {
    if (!isset($_GET['process_education_taxonomy']) || !current_user_can('manage_options')) {
        return;
    }
    
    // Get all posts in latest-job category
    $args = array(
        'category_name' => 'latest-job',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    );
    
    $post_ids = get_posts($args);
    
    $processed = 0;
    $updated = 0;
    $skipped = 0;
    
    foreach ($post_ids as $post_id) {
        $processed++;
        
        // Get ChatGPT JSON
        $chatgpt_json = get_post_meta($post_id, 'kiosk_chatgpt_json', true);
        
        if (empty($chatgpt_json)) {
            $skipped++;
            continue;
        }
        
        $chatgpt_data = json_decode($chatgpt_json, true);
        
        if (!is_array($chatgpt_data) || empty($chatgpt_data['education'])) {
            $skipped++;
            continue;
        }
        
        $education_values = $chatgpt_data['education'];
        
        // Ensure it's an array
        if (!is_array($education_values)) {
            $education_values = array($education_values);
        }
        
        // Get or create terms for each education value
        $term_ids = array();
        foreach ($education_values as $education_name) {
            $education_name = trim($education_name);
            $education_name = sanitize_text_field($education_name);
            
            if (empty($education_name)) {
                continue;
            }
            
            // Check if term already exists
            $term = get_term_by('name', $education_name, 'education');
            
            if ($term && !is_wp_error($term)) {
                $term_ids[] = $term->term_id;
            } else {
                // Create new term
                $result = wp_insert_term($education_name, 'education');
                
                if (!is_wp_error($result)) {
                    $term_ids[] = $result['term_id'];
                } elseif (isset($result->error_data['term_exists'])) {
                    $term_ids[] = $result->error_data['term_exists'];
                }
            }
        }
        
        if (!empty($term_ids)) {
            $result = wp_set_object_terms($post_id, $term_ids, 'education', false);
            if (!is_wp_error($result)) {
                $updated++;
            }
        }
    }
    
    // Display results
    wp_die(
        sprintf(
            '<h1>Education Taxonomy Processing Complete</h1>
            <p><strong>Total Posts Processed:</strong> %d</p>
            <p><strong>Posts Updated:</strong> %d</p>
            <p><strong>Posts Skipped:</strong> %d</p>
            <p><a href="%s">Go to Latest Jobs</a></p>',
            $processed,
            $updated,
            $skipped,
            get_category_link(get_category_by_slug('latest-job'))
        )
    );
}
add_action('init', 'kiosk_process_existing_education_taxonomy');

// Include content automation functionality
require_once get_template_directory() . '/module/content-automation/content-automation.php';
require_once get_template_directory() . '/module/admin/admin-settings.php';
require_once get_template_directory() . '/module/csv-importer/csv-importer.php';
require_once get_template_directory() . '/module/search-pincode/pincode-search.php';
require_once get_template_directory() . '/module/search-ifsc/ifsc-search.php';
require_once get_template_directory() . '/module/image-tools/image-tools.php';
require_once get_template_directory() . '/module/image-tools/image-converter.php';
?>
