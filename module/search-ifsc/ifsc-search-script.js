jQuery(document).ready(function ($) {
    'use strict';

    // Cache DOM elements
    const $bankSelect = $('#bank-select');
    const $stateSelect = $('#state-select');
    const $districtSelect = $('#district-select');
    const $branchSelect = $('#branch-select');
    const $searchBtn = $('#search-btn');
    const $resetBtn = $('#reset-btn');
    const $form = $('#ifsc-search-form');
    const $resultContainer = $('#result-container');
    const $errorContainer = $('#error-container');
    const $errorText = $('#error-text');

    // Loading spinners
    const $stateLoading = $('#state-loading');
    const $districtLoading = $('#district-loading');
    const $branchLoading = $('#branch-loading');

    // SlimSelect instances
    let bankSlim, stateSlim, districtSlim, branchSlim;

    // Store selected values
    let selectedBankCode = '';
    let selectedBankName = '';
    let selectedState = '';
    let selectedDistrict = '';
    let selectedBranch = '';

    // Initialize SlimSelect
    initializeSlimSelect();

    /**
     * Initialize SlimSelect for searchable dropdowns
     */
    function initializeSlimSelect() {
        bankSlim = new SlimSelect({
            select: '#bank-select',
            settings: {
                searchPlaceholder: 'Search banks...',
                searchText: 'No banks found',
                searchingText: 'Searching...',
                closeOnSelect: true
            }
        });

        stateSlim = new SlimSelect({
            select: '#state-select',
            settings: {
                searchPlaceholder: 'Search states...',
                searchText: 'No states found',
                searchingText: 'Searching...',
                closeOnSelect: true
            }
        });
        stateSlim.disable();

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

        branchSlim = new SlimSelect({
            select: '#branch-select',
            settings: {
                searchPlaceholder: 'Search branches...',
                searchText: 'No branches found',
                searchingText: 'Searching...',
                closeOnSelect: true
            }
        });
        branchSlim.disable();
    }

    /**
     * Load districts for selected bank and state
     */
    function loadDistricts(bankCode, state) {
        // Reset dependent selects
        resetSelect($districtSelect, '-- Loading Districts... --', true);
        resetSelect($branchSelect, '-- First Select District --', true);
        $searchBtn.prop('disabled', true);

        showLoading($districtLoading);

        // Call WordPress AJAX endpoint
        $.ajax({
            url: ifscSearch.ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'ifsc_get_districts',
                nonce: ifscSearch.nonce,
                bankcode: bankCode,
                state: state
            },
            success: function (response) {
                hideLoading($districtLoading);
                console.log('Response:', response);
                console.log('Response.data:', response.data);

                // Access districts field from response.data
                let districts = [];
                if (response.success && response.data) {
                    // The API returns data as object, check for districts property
                    if (response.data.districts && Array.isArray(response.data.districts)) {
                        districts = response.data.districts;
                    } else if (Array.isArray(response.data)) {
                        districts = response.data;
                    }
                }

                if (districts.length > 0) {
                    // Convert district array to object format
                    const districtOptions = districts.sort().map(function (district) {
                        return { district: district };
                    });

                    populateSelect($districtSelect, districtOptions, 'district', '-- Choose District --');
                    $districtSelect.prop('disabled', false);
                    if (districtSlim) {
                        districtSlim.enable();
                    }
                } else {
                    resetSelect($districtSelect, '-- No Districts Available --', true);
                    showError('No districts found for the selected state');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error loading districts:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    statusCode: xhr.status
                });
                hideLoading($districtLoading);
                resetSelect($districtSelect, '-- Error Loading Districts --', true);
                showError('Error loading districts. Please try again.');
            }
        });
    }

    /**
     * Load branches for selected bank, state, and district
     */
    function loadBranches(bankCode, state, district) {
        // Reset branch select
        resetSelect($branchSelect, '-- Loading Branches... --', true);
        $searchBtn.prop('disabled', true);

        showLoading($branchLoading);

        // Call WordPress AJAX endpoint
        $.ajax({
            url: ifscSearch.ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'ifsc_get_branches',
                nonce: ifscSearch.nonce,
                bankcode: bankCode,
                state: state,
                district: district
            },
            success: function (response) {
                hideLoading($branchLoading);
                console.log('Branches Response:', response);
                console.log('Branches Response.data:', response.data);

                // Access branches from response.data
                let branches = [];
                if (response.success && response.data) {
                    // Check if data has branches property or is array directly
                    if (response.data.branches && Array.isArray(response.data.branches)) {
                        branches = response.data.branches;
                    } else if (Array.isArray(response.data)) {
                        branches = response.data;
                    }
                }

                if (branches.length > 0) {
                    // Map branches to select options (branches are just strings)
                    const branchOptions = branches
                        .map(function (branch) {
                            return {
                                text: branch,
                                value: branch
                            };
                        })
                        .sort(function (a, b) {
                            return a.text.localeCompare(b.text);
                        });

                    branchSlim.setData([
                        { text: '-- Choose Branch --', value: '', placeholder: true },
                        ...branchOptions
                    ]);

                    $branchSelect.prop('disabled', false);
                    if (branchSlim) {
                        branchSlim.enable();
                    }
                } else {
                    resetSelect($branchSelect, '-- No Branches Available --', true);
                    showError('No branches found for the selected district');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error loading branches:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    statusCode: xhr.status
                });
                hideLoading($branchLoading);
                resetSelect($branchSelect, '-- Error Loading Branches --', true);
                showError('Error loading branches. Please try again.');
            }
        });
    }

    /**
     * Populate select dropdown with data
     */
    function populateSelect($select, data, key, placeholder) {
        const options = data.map(item => ({
            text: item[key],
            value: item[key]
        }));

        const selectInstance = getSlimSelectInstance($select);
        if (selectInstance) {
            selectInstance.setData([
                { text: placeholder, value: '', placeholder: true },
                ...options
            ]);
        }
    }

    /**
     * Reset select dropdown
     */
    function resetSelect($select, placeholder, disable) {
        const selectInstance = getSlimSelectInstance($select);
        if (selectInstance) {
            selectInstance.setData([
                { text: placeholder, value: '', placeholder: true }
            ]);
            selectInstance.setSelected('');
            if (disable) {
                selectInstance.disable();
            }
        }
        $select.prop('disabled', disable);
    }

    /**
     * Get SlimSelect instance for a select element
     */
    function getSlimSelectInstance($select) {
        const id = $select.attr('id');
        switch (id) {
            case 'bank-select': return bankSlim;
            case 'state-select': return stateSlim;
            case 'district-select': return districtSlim;
            case 'branch-select': return branchSlim;
            default: return null;
        }
    }

    /**
     * Show loading spinner
     */
    function showLoading($spinner) {
        $spinner.addClass('active');
    }

    /**
     * Hide loading spinner
     */
    function hideLoading($spinner) {
        $spinner.removeClass('active');
    }

    /**
     * Show error message
     */
    function showError(message) {
        $errorText.text(message);
        $errorContainer.fadeIn();
        setTimeout(function () {
            $errorContainer.fadeOut();
        }, 5000);
    }

    /**
     * Hide error message
     */
    function hideError() {
        $errorContainer.fadeOut();
    }

    // Event: Bank selection changed
    $bankSelect.on('change', function () {
        const bankCode = $(this).val();
        const bankName = $(this).find('option:selected').text();

        hideError();
        $resultContainer.hide();

        if (bankCode) {
            selectedBankCode = bankCode;
            selectedBankName = bankName;

            // Enable state select (states are pre-loaded)
            $stateSelect.prop('disabled', false);
            if (stateSlim) {
                stateSlim.enable();
            }

            // Reset state selection
            stateSlim.setSelected('');
            resetSelect($districtSelect, '-- First Select State --', true);
            resetSelect($branchSelect, '-- First Select District --', true);
            $searchBtn.prop('disabled', true);
        } else {
            selectedBankCode = '';
            selectedBankName = '';
            resetSelect($stateSelect, '-- First Select Bank --', true);
            resetSelect($districtSelect, '-- First Select State --', true);
            resetSelect($branchSelect, '-- First Select District --', true);
            $searchBtn.prop('disabled', true);
        }
    });

    // Event: State selection changed
    $stateSelect.on('change', function () {
        const state = $(this).val();

        hideError();
        $resultContainer.hide();

        if (state) {
            selectedState = state;
            loadDistricts(selectedBankCode, state);
        } else {
            selectedState = '';
            resetSelect($districtSelect, '-- First Select State --', true);
            resetSelect($branchSelect, '-- First Select District --', true);
            $searchBtn.prop('disabled', true);
        }
    });

    // Event: District selection changed
    $districtSelect.on('change', function () {
        const district = $(this).val();

        hideError();
        $resultContainer.hide();

        if (district) {
            selectedDistrict = district;
            loadBranches(selectedBankCode, selectedState, district);
        } else {
            selectedDistrict = '';
            resetSelect($branchSelect, '-- First Select District --', true);
            $searchBtn.prop('disabled', true);
        }
    });

    // Event: Branch selection changed
    $branchSelect.on('change', function () {
        const branch = $(this).val();

        hideError();
        $resultContainer.hide();

        if (branch) {
            selectedBranch = branch;

            // Show loading state
            $searchBtn.prop('disabled', true).html('<span class="btn-spinner"></span> Searching...');

            // Automatically fetch IFSC details when branch is selected
            $.ajax({
                url: ifscSearch.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'ifsc_get_details',
                    nonce: ifscSearch.nonce,
                    bankcode: selectedBankCode,
                    branch: selectedBranch
                },
                success: function (response) {
                    console.log('IFSC Details Response:', response);
                    $searchBtn.prop('disabled', false).html('Search IFSC');

                    if (response.success && response.data && response.data.IFSC) {
                        displayResult(response.data);
                    } else {
                        const errorMsg = response.data || 'Failed to fetch IFSC details';
                        showError(errorMsg);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching IFSC details:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        statusCode: xhr.status
                    });
                    $searchBtn.prop('disabled', false).html('Search IFSC');
                    showError('Error fetching IFSC details. Please try again.');
                }
            });
        } else {
            selectedBranch = '';
            $searchBtn.prop('disabled', true).html('Search IFSC');
        }
    });

    // Event: Form submission
    $form.on('submit', function (e) {
        e.preventDefault();
        // Form submission is handled automatically on branch change
    });

    /**
     * Display IFSC result
     */
    function displayResult(data) {
        $('#result-bank').text(data.BANK || '-');
        $('#result-branch').text(data.BRANCH || '-');
        $('#result-address').text(data.ADDRESS || '-');
        $('#result-city').text(data.CITY || '-');
        $('#result-district').text(data.DISTRICT || '-');
        $('#result-state').text(data.STATE || '-');
        $('#result-ifsc').text(data.IFSC || '-');

        $resultContainer.fadeIn();

        // Scroll to result
        $('html, body').animate({
            scrollTop: $resultContainer.offset().top - 100
        }, 500);
    }

    // Event: Reset button
    $resetBtn.on('click', function () {
        $resultContainer.fadeOut();
        hideError();
    });
});
