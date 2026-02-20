<?php

/**
 * Template Name: Image Converter
 * Description: Convert images between formats with quality control
 */

get_header(); ?>

<header class="page-header">
    <h1 class="page-title">Image Format Converter</h1>
    <p class="page-description">Convert images between formats with customizable quality settings</p>
</header>

<div class="main-content-wrapper">
    <form id="image-convert-form" class="tool-form" enctype="multipart/form-data">
        <div class="form-row">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" for="image-file">Select Image File</h3>
                </div>
                <div class="card-content">
                    <input type="file" id="image-file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff" required>
                </div>
                <div class="card-footer">
                    <small class="form-hint">Supported: JPEG, PNG, GIF, WebP, BMP, TIFF (Max: 10MB)</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" for="output-format">Output Format</h3>
                </div>
                <div class="card-content">
                    <select id="output-format" name="output_format" required>
                        <option value="jpeg">JPEG (.jpg)</option>
                        <option value="png">PNG (.png)</option>
                        <option value="webp">WebP (.webp)</option>
                        <option value="gif">GIF (.gif)</option>
                        <option value="bmp">BMP (.bmp)</option>
                        <option value="tiff">TIFF (.tiff)</option>
                    </select>
                </div>
                <div class="card-footer">
                    <small class="form-hint">Choose the desired output format</small>
                </div>
            </div>

            <div class="card quality-container">
                <div class="card-header">
                    <h3 class="card-title" for="conversion-quality">Quality / Compression</h3>
                    <div class="quality-display">
                        <span class="quality-value">80</span>%
                    </div>
                </div>
                <div class="card-content">
                    <input type="range" id="conversion-quality" name="quality" min="10" max="100" value="80" step="10">
                </div>
                <div class="card-footer">
                    <small class="form-hint">Higher quality = larger file size</small>
                </div>
            </div>
        </div>

        <button type="submit" id="convert-btn" class="btn-action btn-primary">
            <span class="btn-text">Convert Image</span>
            <span class="loading-spinner" id="convert-loading"></span>
        </button>
    </form>

    <div id="image-result" class="tool-result" style="display: none;">
        <div class="result-info">
            <div class="info-item">
                <span class="label">Original Size:</span>
                <span class="value" id="original-size"></span>
            </div>
            <div class="info-item">
                <span class="label">Converted Size:</span>
                <span class="value" id="converted-size"></span>
            </div>
            <div class="info-item">
                <span class="label">Size Change:</span>
                <span class="value" id="size-change"></span>
            </div>
            <div class="info-item">
                <span class="label">Dimensions:</span>
                <span class="value" id="dimensions"></span>
            </div>
            <div class="info-item">
                <span class="label">Output Format:</span>
                <span class="value" id="output-format-display"></span>
            </div>
        </div>
        <a href="#" id="download-image" class="btn-action btn-success" download>
            Download Converted Image
        </a>
    </div>

    <div id="error-container" class="error-container" style="display: none;">
        <div class="error-message">
            <span class="error-icon">⚠️</span>
            <span id="error-text"></span>
        </div>
    </div>
</div>

<style>
    /* Additional styles for size change colors */
    #size-change.decrease {
        color: #059669;
    }
    
    #size-change.increase {
        color: #dc2626;
    }
</style>

<?php get_footer(); ?>
