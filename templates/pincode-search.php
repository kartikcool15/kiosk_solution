<?php

/**
 * Template Name: Pincode Search
 * Description: Search for pincode by selecting state, district, and office
 */

get_header(); ?>

<header class="page-header">
    <h1 class="page-title">Pincode Search</h1>
    <p class="page-description">Find pincode by selecting state, district, and office name</p>
</header>

<div class="main-content-wrapper">
    <div class="pincode-search-container">
        <div class="search-form-container">
            <form id="pincode-search-form" class="pincode-form">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Select State</h3>
                        <span class="loading-spinner" id="state-loading"></span>
                    </div>
                    <div class="card-content">
                        <select id="state-select" name="state" required>
                            <option value="">-- Choose State --</option>
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
                        <h3 class="card-title">Select Office</h3>
                        <span class="loading-spinner" id="office-loading"></span>
                    </div>
                    <div class="card-content">
                        <select id="office-select" name="office" required disabled>
                            <option value="">-- First Select District --</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <button type="submit" id="search-btn" class="btn-action btn-primary" disabled>
                        Search Pincode
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="result-container" class="result-container card section-dates" style="display: none;">
        <div class="card-header">
            <h2 class="card-title">Pincode Details</h2>
        </div>
        <div class="card-content">
            <div class="items">
                <div class="item">
                    <span class="key">State:</span>
                    <span class="value" id="result-state"></span>
                </div>
                <div class="item">
                    <span class="key">District:</span>
                    <span class="value" id="result-district"></span>
                </div>
                <div class="item">
                    <span class="key">Office:</span>
                    <span class="value" id="result-office"></span>
                </div>
                <div class="item pincode-result">
                    <span class="key">Pincode:</span>
                    <span class="value pincode-value" id="result-pincode"></span>
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