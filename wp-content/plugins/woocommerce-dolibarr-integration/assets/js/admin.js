/**
 * WooCommerce Dolibarr Integration - Admin JavaScript
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Test Connection
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('#connection-result');
        
        // Show loading state
        $button.prop('disabled', true).text('Testing...');
        $result.removeClass('success error').empty();
        
        // Get form data
        var apiUrl = $('#wc_dolibarr_api_url').val();
        var apiKey = $('#wc_dolibarr_api_key').val();
        
        if (!apiUrl || !apiKey) {
            $result.addClass('error').html('<p>Please enter both API URL and API Key before testing.</p>');
            $button.prop('disabled', false).text('Test Connection');
            return;
        }
        
        // Make AJAX request
        $.ajax({
            url: wc_dolibarr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_dolibarr_test_connection',
                nonce: wc_dolibarr_ajax.nonce,
                api_url: apiUrl,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').html('<p>' + response.data.message + '</p>');
                    if (response.data.version) {
                        $result.append('<p><small>Dolibarr Version: ' + response.data.version + '</small></p>');
                    }
                } else {
                    $result.addClass('error').html('<p>' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $result.addClass('error').html('<p>Connection test failed: ' + error + '</p>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    });

    // Bulk Sync Operations
    function performBulkSync(syncType, buttonId, resultId) {
        var $button = $(buttonId);
        var $result = $(resultId);
        
        $button.prop('disabled', true).text('Syncing...');
        $result.removeClass('success error info').empty();
        
        $.ajax({
            url: wc_dolibarr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_dolibarr_sync_' + syncType,
                nonce: wc_dolibarr_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').html('<p>' + response.data + '</p>');
                } else {
                    $result.addClass('error').html('<p>' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $result.addClass('error').html('<p>Sync failed: ' + error + '</p>');
            },
            complete: function() {
                var originalText = $button.data('original-text') || $button.text().replace('Syncing...', '');
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Sync Customers
    $('#sync-customers').on('click', function(e) {
        e.preventDefault();
        $(this).data('original-text', 'Sync Customers');
        performBulkSync('customers', '#sync-customers', '#sync-result');
    });

    // Sync Orders
    $('#sync-orders').on('click', function(e) {
        e.preventDefault();
        $(this).data('original-text', 'Sync Orders');
        performBulkSync('orders', '#sync-orders', '#sync-result');
    });

    // Sync Products
    $('#sync-products').on('click', function(e) {
        e.preventDefault();
        $(this).data('original-text', 'Sync Products');
        performBulkSync('products', '#sync-products', '#sync-result');
    });

    // Sync Inventory
    $('#sync-inventory').on('click', function(e) {
        e.preventDefault();
        $(this).data('original-text', 'Sync Inventory');
        performBulkSync('inventory', '#sync-inventory', '#sync-result');
    });

    // Clear Logs
    $('#clear-logs').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear all sync logs? This action cannot be undone.')) {
            return;
        }
        
        var $button = $(this);
        var $result = $('#sync-result');
        
        $button.prop('disabled', true).text('Clearing...');
        $result.removeClass('success error info').empty();
        
        $.ajax({
            url: wc_dolibarr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_dolibarr_clear_logs',
                nonce: wc_dolibarr_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').html('<p>All logs cleared successfully.</p>');
                    // Reload the logs table if it exists
                    if ($('.wc-dolibarr-logs-table').length) {
                        location.reload();
                    }
                } else {
                    $result.addClass('error').html('<p>' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $result.addClass('error').html('<p>Failed to clear logs: ' + error + '</p>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Clear All Logs');
            }
        });
    });

    // Form Validation
    $('form').on('submit', function(e) {
        var $form = $(this);
        var isValid = true;
        
        // Validate API URL
        var $apiUrl = $form.find('#wc_dolibarr_api_url');
        if ($apiUrl.length && $apiUrl.val()) {
            var urlPattern = /^https?:\/\/.+/i;
            if (!urlPattern.test($apiUrl.val())) {
                $apiUrl.after('<div class="wc-dolibarr-notice error">Please enter a valid URL starting with http:// or https://</div>');
                isValid = false;
            }
        }
        
        // Remove existing validation messages
        $form.find('.wc-dolibarr-notice').remove();
        
        if (!isValid) {
            e.preventDefault();
        }
    });

    // Auto-hide notices after 5 seconds
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);

    // Initialize Select2 for dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('select[name*="wc_dolibarr"]').select2({
            width: '100%',
            placeholder: 'Select an option'
        });
    }

    // Progress Bar Animation
    function animateProgressBar(selector, targetWidth, duration) {
        $(selector).animate({
            width: targetWidth + '%'
        }, duration || 1000);
    }

    // Initialize progress bars if they exist
    $('.wc-dolibarr-progress-bar').each(function() {
        var $bar = $(this);
        var targetWidth = $bar.data('width') || 0;
        setTimeout(function() {
            animateProgressBar($bar, targetWidth);
        }, 500);
    });

    // Real-time sync status updates (if needed)
    function checkSyncStatus() {
        if ($('.wc-dolibarr-sync-status').length) {
            $.ajax({
                url: wc_dolibarr_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_dolibarr_get_sync_status',
                    nonce: wc_dolibarr_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        updateSyncStatusDisplay(response.data);
                    }
                }
            });
        }
    }

    function updateSyncStatusDisplay(statusData) {
        // Update sync status indicators
        if (statusData.customers) {
            $('.wc-dolibarr-customer-status').text(statusData.customers.status);
        }
        if (statusData.orders) {
            $('.wc-dolibarr-order-status').text(statusData.orders.status);
        }
        if (statusData.products) {
            $('.wc-dolibarr-product-status').text(statusData.products.status);
        }
    }

    // Check sync status every 30 seconds if on settings page
    if ($('.wc-dolibarr-settings-page').length) {
        setInterval(checkSyncStatus, 30000);
    }

    // Tooltips for help text
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-tooltip]').tooltip({
            position: { my: "left+15 center", at: "right center" }
        });
    }

    // Confirmation dialogs for destructive actions
    $('[data-confirm]').on('click', function(e) {
        var confirmMessage = $(this).data('confirm');
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });

    // Copy to clipboard functionality
    $('.wc-dolibarr-copy-btn').on('click', function(e) {
        e.preventDefault();
        var targetSelector = $(this).data('target');
        var $target = $(targetSelector);
        
        if ($target.length) {
            $target.select();
            document.execCommand('copy');
            
            var $btn = $(this);
            var originalText = $btn.text();
            $btn.text('Copied!').addClass('success');
            
            setTimeout(function() {
                $btn.text(originalText).removeClass('success');
            }, 2000);
        }
    });

    // Settings form auto-save (draft)
    var saveTimer;
    $('.wc-dolibarr-settings-page input, .wc-dolibarr-settings-page select, .wc-dolibarr-settings-page textarea').on('change', function() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function() {
            // Auto-save logic could go here
            console.log('Settings changed - could auto-save draft');
        }, 2000);
    });

    // Initialize any additional components
    initializeComponents();

    function initializeComponents() {
        // Initialize date pickers if needed
        if (typeof $.fn.datepicker !== 'undefined') {
            $('.wc-dolibarr-datepicker').datepicker({
                dateFormat: 'yy-mm-dd'
            });
        }

        // Initialize color pickers if needed
        if (typeof $.fn.wpColorPicker !== 'undefined') {
            $('.wc-dolibarr-colorpicker').wpColorPicker();
        }
    }

    // Handle tab switching with URL hash
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        $('.nav-tab[href*="' + hash + '"]').click();
    }

    $('.nav-tab').on('click', function() {
        var href = $(this).attr('href');
        if (href.indexOf('#') !== -1) {
            window.location.hash = href.split('#')[1];
        }
    });
});
