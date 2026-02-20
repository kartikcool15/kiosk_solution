<?php

/**
 * IFSC Search Functionality
 * Handles AJAX requests for cascading dropdowns and IFSC code search
 * Uses Razorpay IFSC API: https://github.com/razorpay/ifsc-api
 */

// Enqueue scripts and styles for IFSC search page
function ifsc_search_enqueue_scripts()
{
    if (is_page_template('templates/ifsc-search.php')) {
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

        // Reuse pincode search styles (same UI)
        wp_enqueue_style(
            'ifsc-search-style',
            get_template_directory_uri() . '/module/search-pincode/pincode-search-style.css',
            array('slimselect-css'),
            '1.0.0'
        );

        // Enqueue IFSC search script
        wp_enqueue_script(
            'ifsc-search-script',
            get_template_directory_uri() . '/module/search-ifsc/ifsc-search-script.js',
            array('jquery', 'slimselect-js'),
            '1.0.0',
            true
        );

        // Localize script with AJAX URL
        wp_localize_script('ifsc-search-script', 'ifscSearch', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ifsc_search_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'ifsc_search_enqueue_scripts');

/**
 * AJAX handler to get districts for a bank and state
 */
function ifsc_get_districts()
{
    check_ajax_referer('ifsc_search_nonce', 'nonce');

    $bank_code = sanitize_text_field($_GET['bankcode']);
    $state = sanitize_text_field($_GET['state']);

    if (empty($bank_code) || empty($state)) {
        wp_send_json_error('Missing parameters');
        return;
    }

    $url = 'https://ifsc.razorpay.com/places?bankcode=' . $bank_code . '&state=' . $state;
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error('API request failed');
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    wp_send_json_success($data);
}
add_action('wp_ajax_ifsc_get_districts', 'ifsc_get_districts');
add_action('wp_ajax_nopriv_ifsc_get_districts', 'ifsc_get_districts');

/**
 * AJAX handler to get branches for a bank, state, and district
 */
function ifsc_get_branches()
{
    check_ajax_referer('ifsc_search_nonce', 'nonce');

    $bank_code = sanitize_text_field($_GET['bankcode']);
    $state = sanitize_text_field($_GET['state']);
    $district = sanitize_text_field($_GET['district']);

    if (empty($bank_code) || empty($state) || empty($district)) {
        wp_send_json_error('Missing parameters');
        return;
    }

    $url = 'https://ifsc.razorpay.com/places?bankcode=' . $bank_code . '&state=' . $state . '&district=' . urlencode($district);
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error('API request failed');
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    wp_send_json_success($data);
}
add_action('wp_ajax_ifsc_get_branches', 'ifsc_get_branches');
add_action('wp_ajax_nopriv_ifsc_get_branches', 'ifsc_get_branches');

/**
 * AJAX handler to get IFSC details
 */
function ifsc_get_details()
{
    check_ajax_referer('ifsc_search_nonce', 'nonce');

    $bank_code = sanitize_text_field($_GET['bankcode']);
    $branch = sanitize_text_field($_GET['branch']);

    if (empty($bank_code) || empty($branch)) {
        wp_send_json_error('Missing parameters');
        return;
    }

    // API endpoint: /search?limit=1&offset=0&bankcode=BANK&branch=BRANCH
    $url = 'https://ifsc.razorpay.com/search?limit=1&offset=0&bankcode=' . $bank_code . '&branch=' . urlencode($branch);
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error('API request failed');
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // The search API returns an array of results, get the first one
    if (is_array($data) && !empty($data)) {
        wp_send_json_success($data['data'][0]);
    } else {
        wp_send_json_error('No IFSC details found');
    }
}
add_action('wp_ajax_ifsc_get_details', 'ifsc_get_details');
add_action('wp_ajax_nopriv_ifsc_get_details', 'ifsc_get_details');
