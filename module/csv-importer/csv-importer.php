<?php
/**
 * CSV Importer for WordPress Admin
 * Allows uploading and importing CSV files into database tables
 */

// Add admin menu for CSV Importer
function csv_importer_admin_menu() {
    add_menu_page(
        'CSV Importer',           // Page title
        'CSV Importer',           // Menu title
        'manage_options',         // Capability
        'csv-importer',           // Menu slug
        'csv_importer_page',      // Callback function
        'dashicons-upload',       // Icon
        30                        // Position
    );
}
add_action('admin_menu', 'csv_importer_admin_menu');

// Process CSV import
function csv_importer_process_upload() {
    if (!isset($_POST['csv_importer_nonce']) || !wp_verify_nonce($_POST['csv_importer_nonce'], 'csv_importer_upload')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        add_settings_error('csv_importer', 'file_error', 'Error uploading file. Please try again.', 'error');
        return;
    }

    $file = $_FILES['csv_file'];
    $table_name = sanitize_text_field($_POST['table_name']);
    $file_size = $file['size'];
    $max_size = 50 * 1024 * 1024; // 50MB

    // Check file size
    if ($file_size > $max_size) {
        add_settings_error('csv_importer', 'file_size', 'File size exceeds 50MB limit.', 'error');
        return;
    }

    // Check file type
    $file_type = wp_check_filetype($file['name']);
    if (!in_array($file_type['ext'], ['csv', 'txt'])) {
        add_settings_error('csv_importer', 'file_type', 'Only CSV files are allowed.', 'error');
        return;
    }

    // Validate table name
    if (empty($table_name)) {
        add_settings_error('csv_importer', 'table_name', 'Please enter a table name.', 'error');
        return;
    }

    global $wpdb;
    
    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . $table_name));
    
    $create_table = isset($_POST['create_table']) && $_POST['create_table'] === '1';
    
    // Read CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        add_settings_error('csv_importer', 'file_read', 'Could not read CSV file.', 'error');
        return;
    }

    // Get headers from first row
    $headers = fgetcsv($handle);
    if (!$headers) {
        add_settings_error('csv_importer', 'no_headers', 'CSV file appears to be empty.', 'error');
        fclose($handle);
        return;
    }

    // Sanitize column names
    $columns = array_map(function($header) {
        return sanitize_key(str_replace(' ', '_', strtolower(trim($header))));
    }, $headers);

    // Create table if needed
    if (!$table_exists && $create_table) {
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}{$table_name}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,";
        
        foreach ($columns as $column) {
            $sql .= "`{$column}` TEXT,";
        }
        
        $sql .= "`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) {$wpdb->get_charset_collate()};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    } elseif (!$table_exists) {
        add_settings_error('csv_importer', 'table_not_found', 'Table does not exist. Check "Create table if not exists" to create it automatically.', 'error');
        fclose($handle);
        return;
    }

    // Import data
    $row_count = 0;
    $error_count = 0;
    
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) !== count($columns)) {
            $error_count++;
            continue;
        }

        $insert_data = array_combine($columns, $data);
        
        $result = $wpdb->insert(
            $wpdb->prefix . $table_name,
            $insert_data
        );

        if ($result) {
            $row_count++;
        } else {
            $error_count++;
        }
    }

    fclose($handle);

    add_settings_error(
        'csv_importer',
        'import_success',
        sprintf('Successfully imported %d rows. Errors: %d', $row_count, $error_count),
        'success'
    );
}

// Render the admin page
function csv_importer_page() {
    // Process form submission
    if (isset($_POST['csv_importer_submit'])) {
        csv_importer_process_upload();
    }

    // Get all available tables
    global $wpdb;
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    $prefix_length = strlen($wpdb->prefix);
    
    ?>
    <div class="wrap csv-importer-wrap">
        <h1><span class="dashicons dashicons-upload"></span> CSV Importer</h1>
        <p class="description">Upload CSV files (max 50MB) and import them into your database tables.</p>

        <?php settings_errors('csv_importer'); ?>

        <div class="csv-importer-container">
            <div class="csv-importer-form">
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field('csv_importer_upload', 'csv_importer_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv_file">CSV File</label>
                            </th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" required />
                                <p class="description">Select a CSV file to upload (max 50MB)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="table_name">Table Name</label>
                            </th>
                            <td>
                                <input type="text" name="table_name" id="table_name" class="regular-text" 
                                       placeholder="e.g., import_data" list="existing_tables" required />
                                <datalist id="existing_tables">
                                    <?php foreach ($tables as $table): ?>
                                        <?php 
                                        $table_name = $table[0];
                                        // Show tables with and without prefix
                                        if (strpos($table_name, $wpdb->prefix) === 0) {
                                            $short_name = substr($table_name, $prefix_length);
                                            echo '<option value="' . esc_attr($short_name) . '">' . esc_html($table_name) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($table_name) . '">';
                                        }
                                        ?>
                                    <?php endforeach; ?>
                                </datalist>
                                <p class="description">
                                    Enter table name without the prefix (<?php echo esc_html($wpdb->prefix); ?>). 
                                    It will be added automatically.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Options</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="create_table" value="1" />
                                    Create table if it doesn't exist
                                </label>
                                <p class="description">
                                    If checked, a new table will be created using the CSV headers as column names.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="csv_importer_submit" id="submit" class="button button-primary" 
                               value="Upload and Import CSV" />
                    </p>
                </form>
            </div>

            <div class="csv-importer-info">
                <div class="info-box">
                    <h3><span class="dashicons dashicons-info"></span> How to Use</h3>
                    <ol>
                        <li><strong>Prepare your CSV file:</strong> The first row should contain column headers.</li>
                        <li><strong>Select the CSV file:</strong> Choose a file up to 50MB in size.</li>
                        <li><strong>Enter table name:</strong> Type the name of the database table (without the wp_ prefix).</li>
                        <li><strong>Create table option:</strong> Check this if the table doesn't exist yet.</li>
                        <li><strong>Click Import:</strong> The data will be imported into the specified table.</li>
                    </ol>
                </div>

                <div class="info-box">
                    <h3><span class="dashicons dashicons-warning"></span> Important Notes</h3>
                    <ul>
                        <li>Maximum file size: <strong>50MB</strong></li>
                        <li>Only CSV files (.csv) are accepted</li>
                        <li>First row must contain column headers</li>
                        <li>Column names will be sanitized (lowercase, underscores)</li>
                        <li>If creating a new table, all columns will be TEXT type</li>
                        <li>Backup your database before importing into existing tables</li>
                    </ul>
                </div>

                <div class="info-box existing-tables">
                    <h3><span class="dashicons dashicons-database"></span> Existing Tables</h3>
                    <div class="tables-list">
                        <?php foreach ($tables as $table): ?>
                            <div class="table-item">
                                <code><?php echo esc_html($table[0]); ?></code>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Enqueue admin styles
function csv_importer_admin_styles($hook) {
    if ($hook !== 'toplevel_page_csv-importer') {
        return;
    }
    
    wp_enqueue_style(
        'csv-importer-admin',
        get_template_directory_uri() . '/module/csv-importer/csv-importer-styles.css',
        array(),
        '1.0.0'
    );
}
add_action('admin_enqueue_scripts', 'csv_importer_admin_styles');

// Increase upload size limit for CSV importer
function csv_importer_upload_size($size) {
    if (isset($_GET['page']) && $_GET['page'] === 'csv-importer') {
        return 50 * 1024 * 1024; // 50MB
    }
    return $size;
}
add_filter('upload_size_limit', 'csv_importer_upload_size');
