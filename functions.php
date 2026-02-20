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

// Include content automation functionality
require_once get_template_directory() . '/module/content-automation/content-automation.php';
require_once get_template_directory() . '/module/admin/admin-settings.php';
require_once get_template_directory() . '/module/csv-importer/csv-importer.php';
require_once get_template_directory() . '/module/search-pincode/pincode-search.php';
require_once get_template_directory() . '/module/search-ifsc/ifsc-search.php';
require_once get_template_directory() . '/module/image-tools/image-tools.php';
require_once get_template_directory() . '/module/image-tools/image-converter.php';
?>
