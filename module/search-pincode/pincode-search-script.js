jQuery(document).ready(function ($) {
    'use strict';

    // Cache DOM elements
    const $stateSelect = $('#state-select');
    const $districtSelect = $('#district-select');
    const $officeSelect = $('#office-select');
    const $searchBtn = $('#search-btn');
    const $resetBtn = $('#reset-btn');
    const $form = $('#pincode-search-form');
    const $resultContainer = $('#result-container');
    const $errorContainer = $('#error-container');
    const $errorText = $('#error-text');

    // Loading spinners
    const $stateLoading = $('#state-loading');
    const $districtLoading = $('#district-loading');
    const $officeLoading = $('#office-loading');

    // SlimSelect instances
    let stateSlim, districtSlim, officeSlim;

    // Initialize SlimSelect
    initializeSlimSelect();

    // Initialize: Load states on page load
    loadStates();

    /**
     * Initialize SlimSelect for searchable dropdowns
     */
    function initializeSlimSelect() {
        stateSlim = new SlimSelect({
            select: '#state-select',
            settings: {
                searchPlaceholder: 'Search states...',
                searchText: 'No states found',
                searchingText: 'Searching...',
                closeOnSelect: true
            }
        });

        districtSlim = new SlimSelect({
            select: '#district-select',
            settings: {
                searchPlaceholder: 'Search districts...',
                searchText: 'No districts found',
                searchingText: 'Searching...',
                closeOnSelect: true
            }
        });
        districtSlim.disable();

        officeSlim = new SlimSelect({
            select: '#office-select',
            settings: {
                searchPlaceholder: 'Search offices...',
                searchText: 'No offices found',
                searchingText: 'Searching...',
                closeOnSelect: true
            }
        });
        officeSlim.disable();
    }

    /**
     * Load all states
     */
    function loadStates() {
        showLoading($stateLoading);

        $.ajax({
            url: pincodeSearch.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_states',
                nonce: pincodeSearch.nonce
            },
            success: function (response) {
                hideLoading($stateLoading);

                if (response.success && response.data) {
                    populateSelect($stateSelect, response.data, 'statename', '-- Choose State --');
                } else {
                    showError('Failed to load states');
                }
            },
            error: function () {
                hideLoading($stateLoading);
                showError('Error loading states. Please refresh the page.');
            }
        });
    }

    /**
     * Load districts based on selected state
     */
    function loadDistricts(state) {
        // Reset district and office
        resetSelect($districtSelect, '-- Loading Districts... --', true);
        resetSelect($officeSelect, '-- First Select District --', true);
        $searchBtn.prop('disabled', true);

        showLoading($districtLoading);

        $.ajax({
            url: pincodeSearch.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_districts',
                nonce: pincodeSearch.nonce,
                state: state
            },
            success: function (response) {
                hideLoading($districtLoading);

                if (response.success && response.data) {
                    populateSelect($districtSelect, response.data, 'district', '-- Choose District --');
                    $districtSelect.prop('disabled', false);
                    if (districtSlim) {
                        districtSlim.enable();
                    }
                } else {
                    resetSelect($districtSelect, '-- No Districts Available --', true);
                    showError('No districts found for the selected state');
                }
            },
            error: function () {
                hideLoading($districtLoading);
                resetSelect($districtSelect, '-- Error Loading Districts --', true);
                showError('Error loading districts. Please try again.');
            }
        });
    }

    /**
     * Load offices based on selected state and district
     */
    function loadOffices(state, district) {
        // Reset office
        resetSelect($officeSelect, '-- Loading Offices... --', true);
        $searchBtn.prop('disabled', true);

        showLoading($officeLoading);

        $.ajax({
            url: pincodeSearch.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_offices',
                nonce: pincodeSearch.nonce,
                state: state,
                district: district
            },
            success: function (response) {
                hideLoading($officeLoading);

                if (response.success && response.data) {
                    populateSelect($officeSelect, response.data, 'officename', '-- Choose Office --');
                    $officeSelect.prop('disabled', false);
                    if (officeSlim) {
                        officeSlim.enable();
                    }
                } else {
                    resetSelect($officeSelect, '-- No Offices Available --', true);
                    showError('No offices found for the selected district');
                }
            },
            error: function () {
                hideLoading($officeLoading);
                resetSelect($officeSelect, '-- Error Loading Offices --', true);
                showError('Error loading offices. Please try again.');
            }
        });
    }

    /**
     * Search for pincode
     */
    function searchPincode(state, district, office) {
        hideError();
        $searchBtn.prop('disabled', true).html('<span class="btn-spinner"></span> Searching...');

        $.ajax({
            url: pincodeSearch.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_pincode',
                nonce: pincodeSearch.nonce,
                state: state,
                district: district,
                office: office
            },
            success: function (response) {
                $searchBtn.prop('disabled', false).html('Search Pincode');

                if (response.success && response.data) {
                    displayResult(response.data);
                } else {
                    showError(response.data || 'Pincode not found');
                }
            },
            error: function () {
                $searchBtn.prop('disabled', false).html('Search Pincode');
                showError('Error searching for pincode. Please try again.');
            }
        });
    }

    /**
     * Display search result
     */
    function displayResult(data) {
        $('#result-state').text(data.statename);
        $('#result-district').text(data.district);
        $('#result-office').text(data.officename);
        $('#result-pincode').text(data.pincode);

        $resultContainer.fadeIn(300);

        // Scroll to result
        $('html, body').animate({
            scrollTop: $resultContainer.offset().top - 20
        }, 500);
    }

    /**
     * Reset form and show search again
     */
    function resetForm() {
        $resultContainer.fadeOut(300);

        // Reset all selects
        if (stateSlim) {
            stateSlim.setSelected('');
        }
        resetSelect($districtSelect, '-- First Select State --', true);
        resetSelect($officeSelect, '-- First Select District --', true);
        $searchBtn.prop('disabled', true);
        hideError();

        // Scroll to form
        $('html, body').animate({
            scrollTop: $form.offset().top - 100
        }, 500);
    }

    /**
     * Populate select dropdown
     */
    function populateSelect($select, data, key, placeholder) {
        const selectId = $select.attr('id');
        const options = [{ text: placeholder, value: '' }];

        $.each(data, function (index, item) {
            options.push({
                text: item[key],
                value: item[key]
            });
        });

        // Update SlimSelect based on which select it is
        if (selectId === 'state-select' && stateSlim) {
            stateSlim.setData(options);
        } else if (selectId === 'district-select' && districtSlim) {
            districtSlim.setData(options);
        } else if (selectId === 'office-select' && officeSlim) {
            officeSlim.setData(options);
        }
    }

    /**
     * Reset select dropdown
     */
    function resetSelect($select, placeholder, disabled) {
        const selectId = $select.attr('id');
        const options = [{ text: placeholder, value: '' }];

        $select.prop('disabled', disabled);

        // Update SlimSelect based on which select it is
        if (selectId === 'state-select' && stateSlim) {
            stateSlim.setData(options);
            if (disabled) {
                stateSlim.disable();
            } else {
                stateSlim.enable();
            }
        } else if (selectId === 'district-select' && districtSlim) {
            districtSlim.setData(options);
            if (disabled) {
                districtSlim.disable();
            } else {
                districtSlim.enable();
            }
        } else if (selectId === 'office-select' && officeSlim) {
            officeSlim.setData(options);
            if (disabled) {
                officeSlim.disable();
            } else {
                officeSlim.enable();
            }
        }
    }

    /**
     * Show/hide loading spinner
     */
    function showLoading($spinner) {
        $spinner.addClass('active');
    }

    function hideLoading($spinner) {
        $spinner.removeClass('active');
    }

    /**
     * Show/hide error message
     */
    function showError(message) {
        $errorText.text(message);
        $errorContainer.fadeIn(300);

        // Auto hide after 5 seconds
        setTimeout(function () {
            hideError();
        }, 5000);
    }

    function hideError() {
        $errorContainer.fadeOut(300);
    }

    /**
     * Event Handlers
     */

    // State selection change
    $stateSelect.on('change', function () {
        const state = $(this).val();
        hideError();

        if (state) {
            loadDistricts(state);
        } else {
            resetSelect($districtSelect, '-- First Select State --', true);
            resetSelect($officeSelect, '-- First Select District --', true);
            $searchBtn.prop('disabled', true);
        }
    });

    // District selection change
    $districtSelect.on('change', function () {
        const district = $(this).val();
        const state = $stateSelect.val();
        hideError();

        if (district && state) {
            loadOffices(state, district);
        } else {
            resetSelect($officeSelect, '-- First Select District --', true);
            $searchBtn.prop('disabled', true);
        }
    });

    // Office selection change
    $officeSelect.on('change', function () {
        const office = $(this).val();
        const state = $stateSelect.val();
        const district = $districtSelect.val();

        if (office && state && district) {
            // Automatically search when office is selected
            searchPincode(state, district, office);
        } else {
            $searchBtn.prop('disabled', true);
        }
    });

    // Form submission
    $form.on('submit', function (e) {
        e.preventDefault();
        // Form submission is handled automatically on office change
    });

    // Reset button click
    $resetBtn.on('click', function () {
        resetForm();
    });
});
