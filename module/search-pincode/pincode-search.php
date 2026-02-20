<?php

/**
 * Pincode Search Functionality
 * Handles AJAX requests for cascading dropdowns and pincode search
 */

// Enqueue scripts and styles for pincode search page
function pincode_search_enqueue_scripts()
{
    if (is_page_template('templates/pincode-search.php')) {
        // Enqueue SlimSelect library
        wp_enqueue_style(
            'slimselect-css',
            'https://cdn.jsdelivr.net/npm/slim-select@latest/dist/slimselect.css',
            array(),
            '2.0'
        );
        
        wp_enqueue_script(
            'slimselect-js',
            'https://cdn.jsdelivr.net/npm/slim-select@latest/dist/slimselect.min.js',
            array(),
            '2.0',
            true
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'pincode-search-style',
            get_template_directory_uri() . '/module/search-pincode/pincode-search-style.css',
            array('slimselect-css'),
            '1.0.0'
        );

        // Enqueue script
        wp_enqueue_script(
            'pincode-search-script',
            get_template_directory_uri() . '/module/search-pincode/pincode-search-script.js',
            array('jquery', 'slimselect-js'),
            '1.0.0',
            true
        );

        // Localize script with AJAX URL
        wp_localize_script('pincode-search-script', 'pincodeSearch', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pincode_search_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'pincode_search_enqueue_scripts');

// AJAX Handler: Get all states
function ajax_get_states()
{
    check_ajax_referer('pincode_search_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'pincodes';

    $states = $wpdb->get_results(
        "SELECT DISTINCT statename 
         FROM {$table_name} 
         WHERE statename IS NOT NULL 
         AND statename != '' 
         ORDER BY statename ASC",
        ARRAY_A
    );

    if ($states) {
        wp_send_json_success($states);
    } else {
        wp_send_json_error('No states found');
    }
}
add_action('wp_ajax_get_states', 'ajax_get_states');
add_action('wp_ajax_nopriv_get_states', 'ajax_get_states');

// AJAX Handler: Get districts by state
function ajax_get_districts()
{
    check_ajax_referer('pincode_search_nonce', 'nonce');

    $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';

    if (empty($state)) {
        wp_send_json_error('State is required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pincodes';

    $districts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT district 
             FROM {$table_name} 
             WHERE statename = %s 
             AND district IS NOT NULL 
             AND district != '' 
             ORDER BY district ASC",
            $state
        ),
        ARRAY_A
    );

    if ($districts) {
        wp_send_json_success($districts);
    } else {
        wp_send_json_error('No districts found for this state');
    }
}
add_action('wp_ajax_get_districts', 'ajax_get_districts');
add_action('wp_ajax_nopriv_get_districts', 'ajax_get_districts');

// AJAX Handler: Get offices by state and district
function ajax_get_offices()
{
    check_ajax_referer('pincode_search_nonce', 'nonce');

    $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
    $district = isset($_POST['district']) ? sanitize_text_field($_POST['district']) : '';

    if (empty($state) || empty($district)) {
        wp_send_json_error('State and district are required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pincodes';

    $offices = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT officename 
             FROM {$table_name} 
             WHERE statename = %s 
             AND district = %s 
             AND officename IS NOT NULL 
             AND officename != '' 
             ORDER BY officename ASC",
            $state,
            $district
        ),
        ARRAY_A
    );

    if ($offices) {
        wp_send_json_success($offices);
    } else {
        wp_send_json_error('No offices found for this district');
    }
}
add_action('wp_ajax_get_offices', 'ajax_get_offices');
add_action('wp_ajax_nopriv_get_offices', 'ajax_get_offices');

// AJAX Handler: Get pincode by state, district, and office
function ajax_get_pincode()
{
    check_ajax_referer('pincode_search_nonce', 'nonce');

    $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
    $district = isset($_POST['district']) ? sanitize_text_field($_POST['district']) : '';
    $office = isset($_POST['office']) ? sanitize_text_field($_POST['office']) : '';

    if (empty($state) || empty($district) || empty($office)) {
        wp_send_json_error('All fields are required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pincodes';

    $result = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT pincode, statename, district, officename 
             FROM {$table_name} 
             WHERE statename = %s 
             AND district = %s 
             AND officename = %s 
             LIMIT 1",
            $state,
            $district,
            $office
        ),
        ARRAY_A
    );

    if ($result) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error('Pincode not found for the selected office');
    }
}
add_action('wp_ajax_get_pincode', 'ajax_get_pincode');
add_action('wp_ajax_nopriv_get_pincode', 'ajax_get_pincode');
