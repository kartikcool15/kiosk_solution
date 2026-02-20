<?php

/**
 * Template Name: IFSC Search
 * Description: Search for IFSC code by selecting bank, state, district, and branch
 */

get_header();

// Load bank data directly in template
$bank_json_path = get_template_directory() . '/assets/bank-mapping.json';
$bank_data_inline = array();

if (file_exists($bank_json_path)) {
    $bank_json = file_get_contents($bank_json_path);

    // Remove BOM if present
    $bank_json = preg_replace('/\x{FEFF}/u', '', $bank_json);
    $bank_json = trim($bank_json);

    $bank_data_inline = json_decode($bank_json, true); // true = associative array

    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSON decode failed, try to fix common issues
        $bank_json = str_replace(["\r\n", "\r"], "\n", $bank_json);
        $bank_data_inline = json_decode($bank_json, true);
    }

    if (!is_array($bank_data_inline) || empty($bank_data_inline)) {
        $bank_data_inline = array();
    }
}

// Load states data directly in template
$states_json_path = get_template_directory() . '/assets/states.json';
$states_data_inline = array();

if (file_exists($states_json_path)) {
    $states_json = file_get_contents($states_json_path);

    // Remove BOM if present
    $states_json = preg_replace('/\x{FEFF}/u', '', $states_json);
    $states_json = trim($states_json);

    $states_data_inline = json_decode($states_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSON decode failed, try to fix common issues
        $states_json = str_replace(["\r\n", "\r"], "\n", $states_json);
        $states_data_inline = json_decode($states_json, true);
    }

    if (!is_array($states_data_inline) || empty($states_data_inline)) {
        $states_data_inline = array();
    }
}
?>

<header class="page-header">
    <h1 class="page-title">IFSC Code Search</h1>
    <p class="page-description">Find IFSC code by selecting bank, state, district, and branch</p>
</header>

<div class="main-content-wrapper">
    <div class="pincode-search-container">
        <div class="search-form-container">
            <form id="ifsc-search-form" class="pincode-form">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Select Bank</h3>
                        <span class="loading-spinner" id="bank-loading"></span>
                    </div>
                    <div class="card-content">
                        <select id="bank-select" name="bank" required>
                            <option value="">-- Choose Bank --</option>
                            <?php
                            if (!empty($bank_data_inline) && is_array($bank_data_inline)) {
                                // Sort banks alphabetically by name
                                ksort($bank_data_inline);
                                foreach ($bank_data_inline as $bank_name => $bank_code) {
                                    echo '<option value="' . esc_attr($bank_code) . '">' . esc_html($bank_name) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Select State</h3>
                        <span class="loading-spinner" id="state-loading"></span>
                    </div>
                    <div class="card-content">
                        <select id="state-select" name="state" required disabled>
                            <option value="">-- First Select Bank --</option>
                            <?php
                            if (!empty($states_data_inline) && is_array($states_data_inline)) {
                                foreach ($states_data_inline as $state) {
                                    if (isset($state['label'])) {
                                        echo '<option value="' . esc_attr($state['value']) . '">' . esc_html($state['label']) . '</option>';
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Select District</h3>
                        <span class="loading-spinner" id="district-loading"></span>
                    </div>
                    <div class="card-content">
                        <select id="district-select" name="district" required disabled>
                            <option value="">-- First Select State --</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Select Branch</h3>
                        <span class="loading-spinner" id="branch-loading"></span>
                    </div>

                    <div class="card-content">
                        <select id="branch-select" name="branch" required disabled>
                            <option value="">-- First Select District --</option>
                        </select>

                    </div>
                </div>

                <div class="form-row">
                    <button type="submit" id="search-btn" class="btn-action btn-primary" disabled>
                        Get IFSC Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="result-container" class="result-container card" style="display: none;">
        <div class="card-header">
            <h2 class="card-title">IFSC Code Details</h2>
        </div>
        <div class="card-content">
            <div class="items">
                <div class="item">
                    <span class="key">Bank:</span>
                    <span class="value" id="result-bank"></span>
                </div>
                <div class="item">
                    <span class="key">Branch:</span>
                    <span class="value" id="result-branch"></span>
                </div>
                <div class="item">
                    <span class="key">Address:</span>
                    <span class="value" id="result-address"></span>
                </div>
                <div class="item">
                    <span class="key">City:</span>
                    <span class="value" id="result-city"></span>
                </div>
                <div class="item">
                    <span class="key">District:</span>
                    <span class="value" id="result-district"></span>
                </div>
                <div class="item">
                    <span class="key">State:</span>
                    <span class="value" id="result-state"></span>
                </div>
                <div class="item pincode-result">
                    <span class="key">IFSC Code:</span>
                    <span class="value pincode-value" id="result-ifsc"></span>
                </div>
            </div>
        </div>
    </div>

    <div id="error-container" class="error-container" style="display: none;">
        <div class="error-message">
            <span class="error-icon">⚠️</span>
            <span id="error-text"></span>
        </div>
    </div>
</div>

<?php get_footer(); ?>