<?php

/**
 * Template Name: Image Tools
 * Description: Quick tools for image and PDF manipulation using Imagick
 */

get_header(); ?>

<header class="page-header">
    <h1 class="page-title">PDF Compressor</h1>
    <p class="page-description">Reduce PDF file size with customizable quality settings</p>
</header>

<div class="main-content-wrapper">
    <form id="pdf-compress-form" class="tool-form" enctype="multipart/form-data">
        <div class="form-row">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" for="pdf-file">Select PDF File</h3>
                </div>
                <div class="card-content">
                    <input type="file" id="pdf-file" name="pdf_file" accept=".pdf" required>
                </div>
                <div class="card-footer">
                    <small class="form-hint">Maximum file size: 10MB</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" for="compression-quality">Compression Quality</h3>
                    <div class="quality-display">
                        <span class="quality-value">60</span>%
                    </div>
                </div>
                <div class="card-content">
                    <input type="range" id="compression-quality" name="quality" min="10" max="100" value="60" step="10">


                </div>
                <div class="card-footer">
                    <small class="form-hint">Lower quality = smaller file size</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" for="resolution">Resolution (DPI)</h3>
                </div>
                <div class="card-content">
                    <select id="resolution" name="resolution">
                        <option value="72">72 DPI (Screen)</option>
                        <option value="100" selected>100 DPI (Balanced)</option>
                        <option value="150">150 DPI (Good)</option>
                        <option value="300">300 DPI (High Quality)</option>
                    </select>
                </div>
                <div class="card-footer"></div>
            </div>
        </div>

        <button type="submit" id="compress-btn" class="btn-action btn-primary">
            <span class="btn-text">Compress PDF</span>
            <span class="loading-spinner" id="compress-loading"></span>
        </button>
    </form>

    <div id="pdf-result" class="tool-result" style="display: none;">
        <div class="result-info">
            <div class="info-item">
                <span class="label">Original Size:</span>
                <span class="value" id="original-size"></span>
            </div>
            <div class="info-item">
                <span class="label">Compressed Size:</span>
                <span class="value" id="compressed-size"></span>
            </div>
            <div class="info-item">
                <span class="label">Reduction:</span>
                <span class="value success" id="reduction-percent"></span>
            </div>
        </div>
        <a href="#" id="download-pdf" class="btn-action btn-success" download>
            Download Compressed PDF
        </a>
    </div>

    <div id="error-container" class="error-container" style="display: none;">
        <div class="error-message">
            <span class="error-icon">⚠️</span>
            <span id="error-text"></span>
        </div>
    </div>
</div>

<?php get_footer(); ?>