/**
 * kiosk Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Test API Connection
         */
        $('#kiosk-test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $response = $('#kiosk-sync-response');
            
            // Disable button and show loading
            $button.prop('disabled', true);
            $button.html('<span class="kiosk-spinner"></span> Testing Connection...');
            
            // Show loading message
            $response.removeClass('success error').addClass('loading').show();
            $response.html('Connecting to API...');
            
            $.ajax({
                url: kioskAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kiosk_test_api_connection',
                    nonce: kioskAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $button.html('Test API Connection');
                    
                    if (response.success) {
                        $response.removeClass('loading error').addClass('success');
                        var successHtml = '<strong>Success!</strong> ' + response.data.message + '<br>Sample post: ' + response.data.sample_post;
                        if (response.data.api_url) {
                            successHtml += '<br><small>API URL: ' + response.data.api_url + '</small>';
                        }
                        $response.html(successHtml);
                    } else {
                        $response.removeClass('loading success').addClass('error');
                        var errorHtml = '<strong>Error:</strong> ';
                        
                        if (typeof response.data === 'object') {
                            errorHtml += response.data.message || 'Connection failed';
                            if (response.data.api_url) {
                                errorHtml += '<br>API URL: ' + response.data.api_url;
                            }
                            if (response.data.response_type) {
                                errorHtml += '<br>Response type: ' + response.data.response_type;
                            }
                            if (response.data.debug_info) {
                                errorHtml += '<br>' + response.data.debug_info;
                            }
                        } else {
                            errorHtml += response.data;
                        }
                        
                        $response.html(errorHtml);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $button.html('Test API Connection');
                    
                    $response.removeClass('loading success').addClass('error');
                    var errorHtml = '<strong>AJAX Error:</strong> ' + error;
                    if (status) {
                        errorHtml += '<br>Status: ' + status;
                    }
                    if (xhr.responseText) {
                        errorHtml += '<br>Response: ' + xhr.responseText.substring(0, 200);
                    }
                    $response.html(errorHtml);
                }
            });
        });
        
        /**
         * Manual Sync
         */
        $('#kiosk-manual-sync').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $response = $('#kiosk-sync-response');
            
            if (!confirm('This will fetch and import new posts. Continue?')) {
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true);
            $button.html('<span class="kiosk-spinner"></span> Syncing...');
            
            // Show loading message
            $response.removeClass('success error').addClass('loading').show();
            $response.html('Fetching content from API and creating posts...');
            
            $.ajax({
                url: kioskAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kiosk_manual_sync',
                    nonce: kioskAdmin.nonce
                },
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    $button.prop('disabled', false);
                    $button.html('Run Manual Sync Now');
                    
                    if (response.success) {
                        $response.removeClass('loading error').addClass('success');
                        
                        var message = '<strong>Sync Complete!</strong><br>';
                        if (response.data && response.data.imported !== undefined) {
                            message += 'Posts imported: ' + response.data.imported + '<br>';
                            message += 'Posts skipped (duplicates): ' + response.data.skipped + '<br>';
                            message += 'Time: ' + response.data.time;
                        } else {
                            message += 'Check the status section for results.';
                        }
                        
                        $response.html(message);
                        
                        // Reload page after 2 seconds to show updated stats
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $response.removeClass('loading success').addClass('error');
                        $response.html('<strong>Error:</strong> ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $button.html('Run Manual Sync Now');
                    
                    $response.removeClass('loading success').addClass('error');
                    
                    if (status === 'timeout') {
                        $response.html('<strong>Timeout:</strong> The sync is taking longer than expected. It may still be running in the background. Please check back in a few minutes.');
                    } else {
                        $response.html('<strong>Error:</strong> ' + error);
                    }
                }
            });
        });
        
        /**
         * Fix Post Slugs from ChatGPT Data
         */
        $('#kiosk-fix-slugs').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $response = $('#kiosk-sync-response');
            
            if (!confirm('This will update all posts with their correct titles and slugs from ChatGPT data. Continue?')) {
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true);
            $button.html('<span class="kiosk-spinner"></span> Fixing Slugs...');
            
            // Show loading message
            $response.removeClass('success error').addClass('loading').show();
            $response.html('Updating post titles and slugs from ChatGPT data...');
            
            $.ajax({
                url: kioskAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kiosk_fix_post_slugs',
                    nonce: kioskAdmin.nonce
                },
                timeout: 120000, // 2 minute timeout for bulk updates
                success: function(response) {
                    $button.prop('disabled', false);
                    $button.html('Fix Post Slugs from ChatGPT');
                    
                    if (response.success) {
                        $response.removeClass('loading error').addClass('success');
                        
                        var message = '<strong>Slugs Fixed!</strong><br>';
                        message += response.data.message + '<br>';
                        message += 'Total posts checked: ' + response.data.total_checked + '<br>';
                        message += 'Posts updated: ' + response.data.updated + '<br>';
                        message += 'Posts skipped: ' + response.data.skipped + '<br>';
                        if (response.data.errors > 0) {
                            message += 'Errors: ' + response.data.errors;
                        }
                        
                        $response.html(message);
                        
                        // Reload page after 3 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        $response.removeClass('loading success').addClass('error');
                        $response.html('<strong>Error:</strong> ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $button.html('Fix Post Slugs from ChatGPT');
                    
                    $response.removeClass('loading success').addClass('error');
                    
                    if (status === 'timeout') {
                        $response.html('<strong>Timeout:</strong> The operation is taking longer than expected. Some posts may have been updated. Please refresh and try again if needed.');
                    } else {
                        $response.html('<strong>Error:</strong> ' + error);
                    }
                }
            });
        });
        
        /**
         * Auto-hide success messages after 5 seconds
         */
        $(document).on('DOMNodeInserted', '.kiosk-response.success', function() {
            var $this = $(this);
            setTimeout(function() {
                $this.fadeOut();
            }, 5000);
        });
        
        /**
         * Fetch Single Post for Field Mapping
         */
        $('#kiosk-fetch-post').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var postId = $('#kiosk-test-post-id').val();
            
            if (!postId) {
                alert('Please enter a post ID');
                return;
            }
            
            $button.prop('disabled', true);
            $button.html('<span class="kiosk-spinner"></span> Fetching Post...');
            
            $('#kiosk-test-post-result').hide();
            $('#kiosk-mapping-section').hide();
            
            $.ajax({
                url: kioskAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kiosk_fetch_single_post',
                    nonce: kioskAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $button.html('Fetch Post');
                    
                    if (response.success) {
                        var post = response.data.post;
                        var fields = response.data.available_fields;
                        var acfData = response.data.acf_data;
                        var preparedJson = response.data.prepared_json;
                        
                        // Display post info
                        var postInfoHtml = '<ul>' +
                            '<li><strong>ID:</strong> ' + post.id + '</li>' +
                            '<li><strong>Title:</strong> ' + post.title + '</li>' +
                            '<li><strong>Date:</strong> ' + post.date + '</li>' +
                            '<li><strong>Link:</strong> <a href=\"' + post.link + '\" target=\"_blank\">' + post.link + '</a></li>' +
                            '</ul>';
                        
                        // Display prepared JSON that will be sent to ChatGPT - MOVED TO TOP
                        if (preparedJson) {
                            postInfoHtml += '<div style="background: #e8f5e9; border: 2px solid #4caf50; padding: 15px; border-radius: 8px; margin: 20px 0;">';
                            postInfoHtml += '<h3 style="margin: 0 0 15px 0; color: #2e7d32; display: flex; align-items: center;">';
                            postInfoHtml += '<span style="font-size: 24px; margin-right: 10px;">📄</span>';
                            postInfoHtml += 'Prepared JSON for ChatGPT</h3>';
                            postInfoHtml += '<p style="margin: 0 0 10px 0; color: #555; font-size: 14px;">This is the cleaned and structured data that will be sent to ChatGPT for processing:</p>';
                            postInfoHtml += '<div style="background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 5px; max-height: 500px; overflow-y: auto; font-family: monospace;">';
                            postInfoHtml += '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 13px; line-height: 1.6;">' + JSON.stringify(preparedJson, null, 2) + '</pre>';
                            postInfoHtml += '</div>';
                            postInfoHtml += '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">💡 <strong>Note:</strong> HTML has been stripped, arrays created from multi-line content, and "as on" dates extracted.</p>';
                            postInfoHtml += '</div>';
                        }
                        
                        // Display ACF fields preview
                        if (Object.keys(acfData).length > 0) {
                            postInfoHtml += '<details style="margin-top: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9;">';
                            postInfoHtml += '<summary style="cursor: pointer; font-weight: bold; font-size: 16px; color: #333;">📋 ACF Fields Found (' + Object.keys(acfData).length + ' fields) - Click to expand</summary>';
                            postInfoHtml += '<ul style="max-height: 400px; overflow-y: auto; margin-top: 15px; padding-left: 20px;">';
                            for (var key in acfData) {
                                var preview = String(acfData[key]);
                                if (preview.length > 150) {
                                    preview = preview.substring(0, 150) + '...';
                                }
                                postInfoHtml += '<li style="margin-bottom: 8px;"><code style="color: #6347ea; font-weight: bold;">' + key + '</code>: ' + preview + '</li>';
                            }
                            postInfoHtml += '</ul>';
                            postInfoHtml += '</details>';
                        } else {
                            postInfoHtml += '<div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; margin-top: 10px;">';
                            postInfoHtml += '<p style="margin: 0; color: #856404;"><strong>⚠️ No ACF fields found</strong><br>Make sure ACF REST API is enabled on the source site.</p>';
                            postInfoHtml += '</div>';
                        }
                        
                        // Display ChatGPT processed results if enabled
                        if (response.data.chatgpt_enabled) {
                            if (response.data.chatgpt_result && response.data.chatgpt_result !== false) {
                                postInfoHtml += '<h4 style="margin-top: 20px; color: #6347ea;">🤖 ChatGPT Extracted Fields:</h4>';
                                postInfoHtml += '<div style="padding: 15px; background: #f0fdf4; border: 2px solid #6347ea; border-radius: 5px;">';
                                postInfoHtml += '<p style="margin: 0 0 10px 0; color: #059669; font-weight: bold;">✓ ChatGPT processing successful</p>';
                                postInfoHtml += '<ul style="margin: 0;">';
                                
                                var chatgptFields = {
                                    'overview': 'Overview',
                                    'important_dates': 'Important Dates',
                                    'eligibility': 'Eligibility',
                                    'required_documents': 'Required Documents',
                                    'apply_link': 'Apply Link',
                                    'notification_pdf': 'Notification PDF',
                                    'form_instructions': 'Form Instructions'
                                };
                                
                                for (var fieldKey in chatgptFields) {
                                    if (response.data.chatgpt_result[fieldKey]) {
                                        var value = String(response.data.chatgpt_result[fieldKey]);
                                        if (value.length > 200) {
                                            value = value.substring(0, 200) + '...';
                                        }
                                        postInfoHtml += '<li style="margin-bottom: 8px;"><strong>' + chatgptFields[fieldKey] + ':</strong> ' + value + '</li>';
                                    }
                                }
                                
                                postInfoHtml += '</ul></div>';
                            } else {
                                postInfoHtml += '<div style="padding: 15px; background: #fef2f2; border: 2px solid #ef4444; border-radius: 5px; margin-top: 20px;">';
                                postInfoHtml += '<p style="margin: 0; color: #dc2626; font-weight: bold;">⚠️ ChatGPT processing failed. Check API key and prompt files.</p>';
                                postInfoHtml += '</div>';
                            }
                        }
                        
                        $('#kiosk-post-info').html(postInfoHtml);
                        $('#kiosk-test-post-result').show();
                        
                        // Populate field mapping dropdowns
                        $('.field-mapping-select').each(function() {
                            var $select = $(this);
                            var currentValue = $select.attr('name').replace('mapping[', '').replace(']', '');
                            var savedMapping = JSON.parse($('#kiosk-mapped-fields').val() || '{}');
                            
                            $select.empty();
                            $select.append('<option value="">-- Select Source Field --</option>');
                            
                            for (var group in fields) {
                                var $optgroup = $('<optgroup label="' + group + '"></optgroup>');
                                for (var fieldKey in fields[group]) {
                                    var $option = $('<option value="' + fieldKey + '">' + fields[group][fieldKey] + '</option>');
                                    if (savedMapping[currentValue] === fieldKey) {
                                        $option.prop('selected', true);
                                    }
                                    $optgroup.append($option);
                                }
                                $select.append($optgroup);
                            }
                        });
                        
                        $('#kiosk-mapping-section').show();
                        
                        $('html, body').animate({
                            scrollTop: $('#kiosk-mapping-section').offset().top - 50
                        }, 500);
                    } else {
                        // Display error in a more user-friendly way
                        var errorHtml = '<div style="padding: 20px; background: #fee; border: 2px solid #f00; border-radius: 5px; margin-top: 20px;">';
                        errorHtml += '<h3 style="margin-top: 0; color: #c00;">❌ Error Fetching Post</h3>';
                        errorHtml += '<p><strong>Message:</strong> ' + response.data + '</p>';
                        errorHtml += '<p><strong>Troubleshooting:</strong></p>';
                        errorHtml += '<ul>';
                        errorHtml += '<li>Check if the Post ID exists on the source site</li>';
                        errorHtml += '<li>Verify the API Base URL is correct in settings</li>';
                        errorHtml += '<li>Ensure the source site allows REST API access</li>';
                        errorHtml += '<li>Check if ACF REST API is enabled on source site</li>';
                        errorHtml += '</ul>';
                        errorHtml += '</div>';
                        
                        $('#kiosk-post-info').html(errorHtml);
                        $('#kiosk-test-post-result').show();
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $button.html('Fetch Post');
                    
                    var errorHtml = '<div style="padding: 20px; background: #fee; border: 2px solid #f00; border-radius: 5px; margin-top: 20px;">';
                    errorHtml += '<h3 style="margin-top: 0; color: #c00;">❌ Network Error</h3>';
                    errorHtml += '<p><strong>Error:</strong> ' + error + '</p>';
                    errorHtml += '<p><strong>Status:</strong> ' + status + '</p>';
                    errorHtml += '<p>Could not connect to the server. Check your internet connection and try again.</p>';
                    errorHtml += '</div>';
                    
                    $('#kiosk-post-info').html(errorHtml);
                    $('#kiosk-test-post-result').show();
                }
            });
        });
        
        /**
         * Save Field Mapping
         */
        $('#kiosk-save-mapping').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $response = $('#kiosk-mapping-response');
            
            // Collect mapping data
            var mapping = {};
            $('.field-mapping-select').each(function() {
                var fieldName = $(this).attr('name').replace('mapping[', '').replace(']', '');
                var sourceField = $(this).val();
                if (sourceField) {
                    mapping[fieldName] = sourceField;
                }
            });
            
            if (Object.keys(mapping).length === 0) {
                alert('Please select at least one field mapping');
                return;
            }
            
            $button.prop('disabled', true);
            $button.html('<span class="kiosk-spinner"></span> Saving...');
            
            $response.removeClass('success error').addClass('loading').show();
            $response.html('Saving field mapping...');
            
            $.ajax({
                url: kioskAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kiosk_save_field_mapping',
                    nonce: kioskAdmin.nonce,
                    mapping: mapping
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $button.html('Save Field Mapping');
                    
                    if (response.success) {
                        $response.removeClass('loading error').addClass('success');
                        $response.html('<strong>Success!</strong> ' + response.data.message);
                        
                        // Update hidden field
                        $('#kiosk-mapped-fields').val(JSON.stringify(mapping));
                        
                        // Reload after 2 seconds to show updated mapping
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $response.removeClass('loading success').addClass('error');
                        $response.html('<strong>Error:</strong> ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $button.html('Save Field Mapping');
                    
                    $response.removeClass('loading success').addClass('error');
                    $response.html('<strong>Error:</strong> ' + error);
                }
            });
        });
        
    });
    
})(jQuery);

