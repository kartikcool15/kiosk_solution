<?php

/**
 * Image Converter Functionality
 * Handles image format conversion and compression using Imagick
 */

// Enqueue scripts and styles for image converter page
function image_converter_enqueue_scripts()
{
    if (is_page_template('templates/image-converter.php')) {
        // Enqueue styles (reuse image tools styles)
        wp_enqueue_style(
            'image-tools-style',
            get_template_directory_uri() . '/module/image-tools/image-tools-style.css',
            array(),
            '1.0.0'
        );

        // Enqueue converter script
        wp_enqueue_script(
            'image-converter-script',
            get_template_directory_uri() . '/module/image-tools/image-converter-script.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script with AJAX URL
        wp_localize_script('image-converter-script', 'imageConverter', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('image_converter_nonce'),
            'maxFileSize' => 10 * 1024 * 1024 // 10MB in bytes
        ));
    }
}
add_action('wp_enqueue_scripts', 'image_converter_enqueue_scripts');

// AJAX Handler: Convert Images
function ajax_convert_image()
{
    check_ajax_referer('image_converter_nonce', 'nonce');

    // Check if Imagick is available
    if (!extension_loaded('imagick')) {
        wp_send_json_error('Imagick extension is not installed on this server.');
    }

    // Validate file upload
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Please upload a valid image file.');
    }

    $file = $_FILES['image_file'];
    $output_format = isset($_POST['output_format']) ? sanitize_text_field($_POST['output_format']) : 'jpeg';
    $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;

    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff');
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        wp_send_json_error('Only image files (JPEG, PNG, GIF, WebP, BMP, TIFF) are allowed.');
    }

    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('File size must be less than 10MB.');
    }

    // Validate output format
    $valid_formats = array('jpeg', 'jpg', 'png', 'webp', 'gif', 'bmp', 'tiff');
    if (!in_array(strtolower($output_format), $valid_formats)) {
        wp_send_json_error('Invalid output format selected.');
    }

    try {
        // Generate unique filename
        $upload_dir = wp_upload_dir();
        $file_info = pathinfo($file['name']);
        $base_name = sanitize_file_name($file_info['filename']);
        
        // Determine file extension
        $extension = strtolower($output_format);
        if ($extension === 'jpg') {
            $extension = 'jpeg';
        }
        
        $filename = 'converted_' . wp_generate_uuid4() . '_' . $base_name . '.' . $extension;
        $output_path = $upload_dir['path'] . '/' . $filename;

        // Create Imagick object
        $imagick = new Imagick($file['tmp_name']);
        
        // Get original dimensions
        $original_width = $imagick->getImageWidth();
        $original_height = $imagick->getImageHeight();

        // Normalize quality range.
        $quality = max(1, min(100, $quality));

        // Set image format
        $imagick->setImageFormat($extension);
        
        // Handle specific format settings
        switch ($extension) {
            case 'jpeg':
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality($quality);
                $imagick->setOption('jpeg:optimize-coding', 'true');
                $imagick->setOption('jpeg:dct-method', 'float');
                // Remove alpha channel for JPEG
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                break;
                
            case 'png':
                $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
                // PNG uses compression level (0-9): lower quality => stronger compression.
                $png_compression_level = (int) round((100 - $quality) * 9 / 100);
                $imagick->setOption('png:compression-level', (string) $png_compression_level);
                $imagick->setOption('png:compression-filter', '5');
                $imagick->setOption('png:compression-strategy', '1');
                break;
                
            case 'webp':
                $imagick->setImageCompression(Imagick::COMPRESSION_WEBP);
                $imagick->setImageCompressionQuality($quality);
                $imagick->setOption('webp:quality', (string) $quality);
                $imagick->setOption('webp:method', '6'); // Best quality/size ratio
                break;
                
            case 'gif':
                $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
                break;
                
            case 'bmp':
                // BMP doesn't support compression quality
                break;
                
            case 'tiff':
                $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
                $imagick->setImageCompressionQuality($quality);
                break;
        }
        
        // Strip metadata to reduce file size (optional, preserves privacy)
        $imagick->stripImage();
        
        // Write converted image
        $imagick->writeImage($output_path);
        
        // Get file sizes
        $original_size = $file['size'];
        $converted_size = filesize($output_path);
        
        // Calculate size difference
        if ($converted_size < $original_size) {
            $reduction = round((($original_size - $converted_size) / $original_size) * 100, 2);
            $size_change = '-' . $reduction . '%';
        } elseif ($converted_size > $original_size) {
            $increase = round((($converted_size - $original_size) / $original_size) * 100, 2);
            $size_change = '+' . $increase . '%';
        } else {
            $size_change = '0%';
        }
        
        // Clean up
        $imagick->clear();
        $imagick->destroy();

        // Return download URL and stats
        wp_send_json_success(array(
            'download_url' => $upload_dir['url'] . '/' . $filename,
            'original_size' => size_format($original_size, 2),
            'converted_size' => size_format($converted_size, 2),
            'size_change' => $size_change,
            'filename' => $filename,
            'dimensions' => $original_width . 'x' . $original_height,
            'format' => strtoupper($extension)
        ));
    } catch (Exception $e) {
        wp_send_json_error('Error converting image: ' . $e->getMessage());
    }
}
add_action('wp_ajax_convert_image', 'ajax_convert_image');
add_action('wp_ajax_nopriv_convert_image', 'ajax_convert_image');

// Clean up old converted files (optional - run via cron or manually)
function cleanup_converted_files()
{
    $upload_dir = wp_upload_dir();
    $patterns = array(
        $upload_dir['path'] . '/converted_*.jpeg',
        $upload_dir['path'] . '/converted_*.jpg',
        $upload_dir['path'] . '/converted_*.png',
        $upload_dir['path'] . '/converted_*.webp',
        $upload_dir['path'] . '/converted_*.gif',
        $upload_dir['path'] . '/converted_*.bmp',
        $upload_dir['path'] . '/converted_*.tiff'
    );
    
    $now = time();

    foreach ($patterns as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            // Delete files older than 1 hour
            if (is_file($file) && ($now - filemtime($file)) >= 3600) {
                unlink($file);
            }
        }
    }
}
