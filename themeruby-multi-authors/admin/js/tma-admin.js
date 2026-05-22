/**
 * ThemeRuby Multi Authors - Admin JavaScript
 *
 * Handles tab switching, AJAX saving, and UI interactions.
 *
 * @package ThemeRuby_Multi_Authors
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    const EMAAdmin = {
        /**
         * localStorage key for last active tab
         */
        tabStorageKey: 'tmauthors_last_active_tab',

        /**
         * Initialize admin functionality
         */
        init: function () {
            this.bindEvents();
            this.restoreLastTab();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Tab switching
            $('.tma-tab-btn').on('click', this.handleTabClick);

            // Settings changes
            $('.tma-toggle-switch input[type="checkbox"]').on('change', this.handleToggleChange);
            $('.tma-post-type-checkbox').on('change', this.handlePostTypesChange);

            // Clear cache button
            $('#tmauthors_clear_cache').on('click', this.handleClearCache);
        },

        /**
         * Restore last active tab from localStorage
         */
        restoreLastTab: function () {
            // Check if localStorage is available
            if (typeof (Storage) === 'undefined') {
                return;
            }

            // Get last active tab from localStorage
            const lastTab = localStorage.getItem(this.tabStorageKey);

            // If found, activate it
            if (lastTab) {
                const $tabBtn = $('.tma-tab-btn[data-tab="' + lastTab + '"]');
                if ($tabBtn.length > 0) {
                    // Remove active from all tabs
                    $('.tma-tab-btn').removeClass('active');
                    $('.tma-tab-content').removeClass('active');

                    // Activate the saved tab
                    $tabBtn.addClass('active');
                    $('#tma-tab-' + lastTab).addClass('active');
                }
            }
        },

        /**
         * Save active tab to localStorage
         */
        saveLastTab: function (tabName) {
            // Check if localStorage is available
            if (typeof (Storage) !== 'undefined') {
                localStorage.setItem(this.tabStorageKey, tabName);
            }
        },

        /**
         * Handle tab click
         */
        handleTabClick: function (e) {
            e.preventDefault();

            const $btn = $(this);
            const tabName = $btn.data('tab');

            // Update active state
            $('.tma-tab-btn').removeClass('active');
            $btn.addClass('active');

            // Show corresponding tab content
            $('.tma-tab-content').removeClass('active');
            $('#tma-tab-' + tabName).addClass('active');

            // Save to localStorage
            EMAAdmin.saveLastTab(tabName);
        },

        /**
         * Handle toggle switch change
         */
        handleToggleChange: function (e) {
            const $checkbox = $(this);
            const settingName = $checkbox.attr('name');
            const settingValue = $checkbox.is(':checked') ? 1 : 0;
            const $row = $checkbox.closest('.tma-setting-row');

            EMAAdmin.saveSetting(settingName, settingValue, $row);
        },

        /**
         * Handle post types checkbox change
         */
        handlePostTypesChange: function () {
            const $checkbox = $(this);
            const $label = $checkbox.closest('.tma-post-type-tag');

            // Toggle active class
            if ($checkbox.is(':checked')) {
                $label.addClass('active');
            } else {
                $label.removeClass('active');
            }

            // Collect all selected types
            const $container = $('.tma-post-types-container');
            const selectedTypes = [];

            $container.find('input[type="checkbox"]:checked').each(function () {
                selectedTypes.push($(this).val());
            });

            // Ensure at least 'post' is selected
            const finalTypes = selectedTypes.length > 0 ? selectedTypes : ['post'];
            const $row = $container.closest('.tma-setting-row');

            EMAAdmin.savePostTypes(finalTypes, $row);
        },

        /**
         * Save setting via AJAX
         */
        saveSetting: function (name, value, $row) {
            // Show saving indicator
            this.showToast('Saving...', 'saving');

            $.ajax({
                url: tmAuthorsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tmauthors_save_setting',
                    nonce: tmAuthorsAdmin.nonce,
                    setting_name: name,
                    setting_value: value
                },
                success: function (response) {
                    if (response.success) {
                        EMAAdmin.showToast(response.data.message || 'Setting saved successfully.', 'success');
                    } else {
                        EMAAdmin.showToast(response.data.message || 'Failed to save setting.', 'error');
                    }
                },
                error: function () {
                    EMAAdmin.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Save post types via AJAX
         */
        savePostTypes: function (types, $row) {
            // Show saving indicator
            this.showToast('Saving post types...', 'saving');

            $.ajax({
                url: tmAuthorsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tmauthors_save_post_types',
                    nonce: tmAuthorsAdmin.nonce,
                    post_types: types
                },
                success: function (response) {
                    if (response.success) {
                        EMAAdmin.showToast(response.data.message || 'Post types saved successfully.', 'success');
                    } else {
                        EMAAdmin.showToast(response.data.message || 'Failed to save post types.', 'error');
                    }
                },
                error: function () {
                    EMAAdmin.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Handle clear cache button click
         */
        handleClearCache: function (e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();

            // Disable button and show loading state
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update spin"></span> Clearing cache...');

            // Show clearing indicator
            EMAAdmin.showToast('Clearing all author cache...', 'saving');

            $.ajax({
                url: tmAuthorsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tmauthors_clear_cache',
                    nonce: tmAuthorsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        EMAAdmin.showToast(response.data.message || 'Cache cleared successfully.', 'success');
                    } else {
                        EMAAdmin.showToast(response.data.message || 'Failed to clear cache.', 'error');
                    }
                },
                error: function () {
                    EMAAdmin.showToast('Network error. Please try again.', 'error');
                },
                complete: function () {
                    // Re-enable button and restore original text
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function (message, type) {
            // Remove previous toasts
            $('.tma-toast').remove();

            // Create toast container if it doesn't exist
            if ($('.tma-toast-container').length === 0) {
                $('body').append('<div class="tma-toast-container"></div>');
            }

            // Create toast element
            const iconClass = type === 'success' ? 'dashicons-yes' : (type === 'error' ? 'dashicons-warning' : 'dashicons-update');
            const spinClass = type === 'saving' ? ' spin' : '';

            const $toast = $('<div class="tma-toast tma-toast-' + type + '">' +
                '<span class="dashicons ' + iconClass + spinClass + '"></span>' +
                '<span class="tma-toast-message">' + message + '</span>' +
                '</div>');

            // Append and show toast
            $('.tma-toast-container').append($toast);

            // Trigger animation
            setTimeout(function () {
                $toast.addClass('tma-toast-show');
            }, 10);

            // Auto-hide after delay (except for saving state)
            if (type !== 'saving') {
                setTimeout(function () {
                    $toast.removeClass('tma-toast-show');
                    setTimeout(function () {
                        $toast.remove();
                    }, 300);
                }, 3000);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        EMAAdmin.init();
    });

})(jQuery);
