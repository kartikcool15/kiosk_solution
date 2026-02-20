jQuery(document).ready(function ($) {
    'use strict';

    // Cache DOM elements
    const $imageForm = $('#image-convert-form');
    const $imageFile = $('#image-file');
    const $outputFormat = $('#output-format');
    const $qualitySlider = $('#conversion-quality');
    const $qualityValue = $('.quality-value');
    const $qualityContainer = $('.quality-container');
    const $convertBtn = $('#convert-btn');
    const $convertLoading = $('#convert-loading');
    const $imageResult = $('#image-result');
    const $errorContainer = $('#error-container');
    const $errorText = $('#error-text');

    // Update quality display
    $qualitySlider.on('input', function () {
        $qualityValue.text($(this).val());
    });

    // Handle output format change
    $outputFormat.on('change', function () {
        const format = $(this).val().toLowerCase();
        
        // BMP doesn't support quality settings
        if (format === 'bmp') {
            $qualityContainer.hide();
        } else {
            $qualityContainer.show();
        }
    });

    // Validate file on selection
    $imageFile.on('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
        if (!validTypes.includes(file.type)) {
            showError('Please select a valid image file (JPEG, PNG, GIF, WebP, BMP, or TIFF).');
            this.value = '';
            return;
        }

        // Check file size
        if (file.size > imageConverter.maxFileSize) {
            showError('File size must be less than 10MB.');
            this.value = '';
            return;
        }

        hideError();
    });

    // Handle image conversion form submit
    $imageForm.on('submit', function (e) {
        e.preventDefault();

        const file = $imageFile[0].files[0];
        if (!file) {
            showError('Please select an image file.');
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'convert_image');
        formData.append('nonce', imageConverter.nonce);
        formData.append('image_file', file);
        formData.append('output_format', $outputFormat.val());
        formData.append('quality', $qualitySlider.val());

        // Show loading state
        $convertBtn.prop('disabled', true);
        $convertLoading.addClass('active');
        $imageResult.hide();
        hideError();

        $.ajax({
            url: imageConverter.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $convertBtn.prop('disabled', false);
                $convertLoading.removeClass('active');

                if (response.success && response.data) {
                    displayResult(response.data);
                } else {
                    showError(response.data || 'Failed to convert image.');
                }
            },
            error: function (xhr) {
                $convertBtn.prop('disabled', false);
                $convertLoading.removeClass('active');
                showError('Error converting image. Please try again.');
            }
        });
    });

    /**
     * Display conversion results
     */
    function displayResult(data) {
        $('#original-size').text(data.original_size);
        $('#converted-size').text(data.converted_size);
        $('#dimensions').text(data.dimensions);
        $('#output-format-display').text(data.format);
        
        const $sizeChange = $('#size-change');
        $sizeChange.text(data.size_change);
        
        // Color code the size change
        if (data.size_change.startsWith('-')) {
            $sizeChange.removeClass('increase').addClass('decrease');
        } else if (data.size_change.startsWith('+')) {
            $sizeChange.removeClass('decrease').addClass('increase');
        } else {
            $sizeChange.removeClass('decrease increase');
        }
        
        const $downloadBtn = $('#download-image');
        $downloadBtn.attr('href', data.download_url);
        $downloadBtn.attr('download', data.filename);

        $imageResult.fadeIn(300);
    }

    /**
     * Show error message
     */
    function showError(message) {
        $errorText.text(message);
        $errorContainer.fadeIn(300);

        setTimeout(function () {
            hideError();
        }, 5000);
    }

    /**
     * Hide error message
     */
    function hideError() {
        $errorContainer.fadeOut(300);
    }
});
