jQuery(document).ready(function ($) {
    'use strict';

    // Cache DOM elements
    const $pdfForm = $('#pdf-compress-form');
    const $pdfFile = $('#pdf-file');
    const $qualitySlider = $('#compression-quality');
    const $qualityValue = $('.quality-value');
    const $compressBtn = $('#compress-btn');
    const $compressLoading = $('#compress-loading');
    const $pdfResult = $('#pdf-result');
    const $errorContainer = $('#error-container');
    const $errorText = $('#error-text');

    // Update quality display
    $qualitySlider.on('input', function () {
        $qualityValue.text($(this).val());
    });

    // Validate file on selection
    $pdfFile.on('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Check file type
        if (file.type !== 'application/pdf') {
            showError('Please select a PDF file.');
            this.value = '';
            return;
        }

        // Check file size
        if (file.size > imageTools.maxFileSize) {
            showError('File size must be less than 10MB.');
            this.value = '';
            return;
        }

        hideError();
    });

    // Handle PDF compression form submit
    $pdfForm.on('submit', function (e) {
        e.preventDefault();

        const file = $pdfFile[0].files[0];
        if (!file) {
            showError('Please select a PDF file.');
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'compress_pdf');
        formData.append('nonce', imageTools.nonce);
        formData.append('pdf_file', file);
        formData.append('quality', $qualitySlider.val());
        formData.append('resolution', $('#resolution').val());

        // Show loading state
        $compressBtn.prop('disabled', true);
        $compressLoading.addClass('active');
        $pdfResult.hide();
        hideError();

        $.ajax({
            url: imageTools.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $compressBtn.prop('disabled', false);
                $compressLoading.removeClass('active');

                if (response.success && response.data) {
                    displayResult(response.data);
                } else {
                    showError(response.data || 'Failed to compress PDF.');
                }
            },
            error: function (xhr) {
                $compressBtn.prop('disabled', false);
                $compressLoading.removeClass('active');
                showError('Error compressing PDF. Please try again.');
            }
        });
    });

    /**
     * Display compression results
     */
    function displayResult(data) {
        $('#original-size').text(data.original_size);
        $('#compressed-size').text(data.compressed_size);
        $('#reduction-percent').text(data.reduction + '% smaller');
        
        const $downloadBtn = $('#download-pdf');
        $downloadBtn.attr('href', data.download_url);
        $downloadBtn.attr('download', data.filename);

        $pdfResult.fadeIn(300);
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
