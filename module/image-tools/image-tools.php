<?php

/**
 * Image Tools Functionality
 * Handles PDF compression and image manipulation using Imagick
 */

// Enqueue scripts and styles for image tools page
function image_tools_enqueue_scripts()
{
    if (is_page_template('templates/image-tools.php')) {
        // Enqueue styles
        wp_enqueue_style(
            'image-tools-style',
            get_template_directory_uri() . '/module/image-tools/image-tools-style.css',
            array(),
            '1.0.0'
        );

        // Enqueue script
        wp_enqueue_script(
            'image-tools-script',
            get_template_directory_uri() . '/module/image-tools/image-tools-script.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script with AJAX URL
        wp_localize_script('image-tools-script', 'imageTools', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('image_tools_nonce'),
            'maxFileSize' => 10 * 1024 * 1024 // 10MB in bytes
        ));
    }
}
add_action('wp_enqueue_scripts', 'image_tools_enqueue_scripts');

// AJAX Handler: Compress PDF
function ajax_compress_pdf()
{
    check_ajax_referer('image_tools_nonce', 'nonce');

    // Check if Imagick is available
    if (!extension_loaded('imagick')) {
        wp_send_json_error('Imagick extension is not installed on this server.');
    }

    // Validate file upload
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Please upload a valid PDF file.');
    }

    $file = $_FILES['pdf_file'];
    $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 60;
    $resolution = isset($_POST['resolution']) ? intval($_POST['resolution']) : 100;

    // Validate file type
    $file_type = mime_content_type($file['tmp_name']);
    if ($file_type !== 'application/pdf') {
        wp_send_json_error('Only PDF files are allowed.');
    }

    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('File size must be less than 10MB.');
    }

    try {
        // Generate unique filename
        $upload_dir = wp_upload_dir();
        $filename = 'compressed_' . time() . '_' . sanitize_file_name($file['name']);
        $output_path = $upload_dir['path'] . '/' . $filename;

        // Use Ghostscript for better PDF compression
        $gs_quality_map = array(
            10 => 'screen',   // 72 DPI
            20 => 'screen',
            30 => 'screen',
            40 => 'ebook',    // 150 DPI
            50 => 'ebook',
            60 => 'ebook',
            70 => 'printer',  // 300 DPI
            80 => 'printer',
            90 => 'prepress', // 300 DPI, color preserving
            100 => 'prepress'
        );

        $gs_setting = 'ebook'; // default
        foreach ($gs_quality_map as $q => $setting) {
            if ($quality <= $q) {
                $gs_setting = $setting;
                break;
            }
        }

        // Try Ghostscript first (best for PDF compression)
        $gs_command = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
            $gs_setting,
            escapeshellarg($output_path),
            escapeshellarg($file['tmp_name'])
        );

        exec($gs_command . ' 2>&1', $output, $return_code);

        // If Ghostscript failed or not available, fallback to Imagick
        if ($return_code !== 0 || !file_exists($output_path)) {
            $imagick = new Imagick();
            
            // Don't rasterize - try to preserve PDF structure
            $imagick->setResolution($resolution, $resolution);
            $imagick->readImage($file['tmp_name']);
            
            // Iterate through all pages
            foreach ($imagick as $page) {
                $page->setImageCompressionQuality($quality);
                $page->setImageCompression(Imagick::COMPRESSION_JPEG);
                $page->setImageFormat('pdf');
                
                // Strip metadata to reduce size
                $page->stripImage();
            }

            // Write compressed PDF
            $imagick->writeImages($output_path, true);
            $imagick->clear();
            $imagick->destroy();
        }

        // Verify compression was successful
        if (!file_exists($output_path)) {
            wp_send_json_error('Failed to create compressed PDF.');
        }

        // Get file sizes
        $original_size = $file['size'];
        $compressed_size = filesize($output_path);
        $reduction = round((($original_size - $compressed_size) / $original_size) * 100, 2);

        // If compressed file is larger, use original
        if ($compressed_size >= $original_size) {
            unlink($output_path);
            copy($file['tmp_name'], $output_path);
            $compressed_size = $original_size;
            $reduction = 0;
        }

        // Return download URL and stats
        wp_send_json_success(array(
            'download_url' => $upload_dir['url'] . '/' . $filename,
            'original_size' => size_format($original_size, 2),
            'compressed_size' => size_format($compressed_size, 2),
            'reduction' => $reduction,
            'filename' => $filename
        ));
    } catch (Exception $e) {
        wp_send_json_error('Error compressing PDF: ' . $e->getMessage());
    }
}
add_action('wp_ajax_compress_pdf', 'ajax_compress_pdf');
add_action('wp_ajax_nopriv_compress_pdf', 'ajax_compress_pdf');

// Clean up old compressed files (optional - run via cron or manually)
function cleanup_compressed_files()
{
    $upload_dir = wp_upload_dir();
    $files = glob($upload_dir['path'] . '/compressed_*.pdf');
    $now = time();

    foreach ($files as $file) {
        // Delete files older than 1 hour
        if (is_file($file) && ($now - filemtime($file)) >= 3600) {
            unlink($file);
        }
    }
}
