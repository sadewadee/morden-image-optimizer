// File: assets/js/settings-page.js

jQuery(document).ready(function($) {
    // Check if mio_ajax is defined
    if (typeof mio_ajax === 'undefined') {
        console.error('MIO: mio_ajax is not defined. Please check script localization.');
        return;
    }

    const SettingsPage = {
        init() {
            this.bindEvents();
            this.initRangeSliders();
        },

        bindEvents() {
            // API service change handler
            $('#mio_api_service').on('change', this.handleApiServiceChange.bind(this));

            // Test API connection
            $('.mio-test-api').on('click', this.handleTestApi.bind(this));

            // Reset settings
            $('.mio-reset-settings').on('click', this.handleResetSettings.bind(this));

            // Toggle password visibility
            $('.mio-toggle-password').on('click', this.handleTogglePassword.bind(this));

            // Dismiss welcome notice
            $('.mio-dismiss-welcome').on('click', this.handleDismissWelcome.bind(this));
        },

        initRangeSliders() {
            $('#mio_compression_level').on('input', function() {
                const value = $(this).val();
                $('.mio-range-value').text(value);

                let label = '';
                if (value >= 90) label = '(Best Quality)';
                else if (value >= 75) label = '(Recommended)';
                else if (value >= 60) label = '(Good Balance)';
                else label = '(Smaller Files)';

                $('.mio-range-label').text(label);
            });
        },

        handleApiServiceChange(e) {
            const selectedService = $(e.target).val();
            $('.mio-api-config').hide();
            $(`.mio-api-config-${selectedService}`).show();

            // Update test button
            $('.mio-test-api').data('service', selectedService);
        },

        async handleTestApi(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const service = $btn.data('service') || $('#mio_api_service').val();
            const originalText = $btn.text();

            $btn.prop('disabled', true).text(mio_ajax.strings.testing);
            $('#mio-api-test-result').empty();

            try {
                const response = await this.makeAjaxRequest('mio_test_api_connection', {
                    service: service
                });

                if (response.success) {
                    this.showApiResult(mio_ajax.strings.test_success, 'success');
                } else {
                    this.showApiResult(`${mio_ajax.strings.test_failed} ${response.data.message}`, 'error');
                }
            } catch (error) {
                this.showApiResult(`${mio_ajax.strings.test_failed} ${error.message}`, 'error');
            } finally {
                $btn.prop('disabled', false).text(originalText);
            }
        },

        async handleResetSettings(e) {
            e.preventDefault();

            if (!confirm(mio_ajax.strings.reset_confirm)) {
                return;
            }

            const $btn = $(e.target);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Resetting...');

            try {
                const response = await this.makeAjaxRequest('mio_reset_settings');

                if (response.success) {
                    this.showNotice(mio_ajax.strings.reset_success, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotice(`Reset failed: ${response.data.message}`, 'error');
                }
            } catch (error) {
                this.showNotice(`Reset failed: ${error.message}`, 'error');
            } finally {
                $btn.prop('disabled', false).text(originalText);
            }
        },

        handleTogglePassword(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const $input = $btn.siblings('input[type="password"], input[type="text"]');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text('Hide');
            } else {
                $input.attr('type', 'password');
                $btn.text('Show');
            }
        },

        handleDismissWelcome(e) {
            e.preventDefault();

            const $notice = $(e.target).closest('.mio-welcome-notice');
            $notice.fadeOut();

            // Optional: Send AJAX to permanently dismiss
            this.makeAjaxRequest('mio_dismiss_notice', {
                notice_type: 'welcome'
            }).catch(error => console.error('Failed to dismiss notice:', error));
        },

        showApiResult(message, type) {
            const className = type === 'success' ? 'notice-success' : 'notice-error';
            $('#mio-api-test-result').html(`<div class="notice ${className} inline"><p>${message}</p></div>`);
        },

        showNotice(message, type = 'info') {
            const className = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $(`<div class="notice ${className} is-dismissible"><p>${message}</p></div>`);

            $('.wrap h1').first().after($notice);

            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 4000);
        },

        makeAjaxRequest(action, data = {}) {
            return new Promise((resolve, reject) => {
                $.post(mio_ajax.ajax_url, {
                    action,
                    nonce: mio_ajax.nonce,
                    ...data
                })
                .done(resolve)
                .fail((xhr, status, error) => {
                    reject(new Error(error || 'Network error'));
                });
            });
        }
    };

    SettingsPage.init();
});
