// File: assets/js/settings-tabs.js

jQuery(document).ready(function($) {
    const SettingsTabs = {
        init() {
            this.bindEvents();
            this.initRangeSliders();
            this.runBackgroundChecks();
        },

        bindEvents() {
            // Tab switching - pastikan element ada sebelum bind
            if ($('.nav-tab').length > 0) {
                $('.nav-tab').on('click', this.handleTabSwitch.bind(this));
            }

            // System checks
            if ($('.mio-run-check').length > 0) {
                $('.mio-run-check').on('click', this.handleRunCheck.bind(this));
            }

            // API testing
            if ($('.mio-test-api').length > 0) {
                $('.mio-test-api').on('click', this.handleTestApi.bind(this));
            }

            // Reset settings
            if ($('.mio-reset-settings').length > 0) {
                $('.mio-reset-settings').on('click', this.handleResetSettings.bind(this));
            }

            // Toggle password visibility
            if ($('.mio-toggle-password').length > 0) {
                $('.mio-toggle-password').on('click', this.handleTogglePassword.bind(this));
            }

            // API service change
            if ($('#mio_api_service').length > 0) {
                $('#mio_api_service').on('change', this.handleApiServiceChange.bind(this));
            }
        },

        initRangeSliders() {
            const $compressionLevel = $('#mio_compression_level');
            if ($compressionLevel.length > 0) {
                $compressionLevel.on('input', function() {
                    const value = $(this).val();
                    $('.mio-range-value').text(value);

                    let label = '';
                    if (value >= 90) label = '(Best Quality)';
                    else if (value >= 75) label = '(Recommended)';
                    else if (value >= 60) label = '(Good Balance)';
                    else label = '(Smaller Files)';

                    $('.mio-range-label').text(label);
                });
            }
        },

        handleTabSwitch(e) {
            e.preventDefault();
            const url = $(e.target).attr('href');
            if (url) {
                window.location.href = url;
            }
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

            $btn.prop('disabled', true).text(this.getLocalizedString('testing'));
            $('#mio-api-test-result').empty();

            try {
                const response = await this.makeAjaxRequest('mio_test_api_connection', {
                    service: service
                });

                if (response.success) {
                    this.showApiResult(this.getLocalizedString('test_success'), 'success');
                } else {
                    this.showApiResult(`${this.getLocalizedString('test_failed')} ${response.data.message}`, 'error');
                }
            } catch (error) {
                this.showApiResult(`${this.getLocalizedString('test_failed')} ${error.message}`, 'error');
            } finally {
                $btn.prop('disabled', false).text(originalText);
            }
        },

        async handleResetSettings(e) {
            e.preventDefault();

            if (!confirm(this.getLocalizedString('reset_confirm'))) {
                return;
            }

            const $btn = $(e.target);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Resetting...');

            try {
                const response = await this.makeAjaxRequest('mio_reset_settings');

                if (response.success) {
                    this.showNotice('Settings reset successfully!', 'success');
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

        runBackgroundChecks() {
            // Auto-run system checks when on system tab
            if (window.location.href.includes('tab=system')) {
                setTimeout(() => {
                    this.runServerCheck();
                    this.runOptimizationTest();
                }, 1000);
            }
        },

        async runServerCheck() {
            const $checkItem = $('.mio-check-item[data-check="server"]');
            if ($checkItem.length === 0) return;

            const $icon = $checkItem.find('.mio-check-icon');
            const $result = $checkItem.find('.mio-check-result');

            $icon.removeClass().addClass('mio-check-icon mio-checking').text('⏳');

            try {
                const response = await this.makeAjaxRequest('mio_check_server_compatibility');

                if (response.success) {
                    $icon.removeClass().addClass('mio-check-icon mio-success').text('✅');

                    let html = '<div class="mio-check-results">';
                    Object.values(response.data.checks).forEach(check => {
                        const statusClass = check.status === 'pass' ? 'success' : (check.status === 'fail' ? 'error' : 'warning');
                        html += `<div class="mio-check-detail mio-${statusClass}">
                            <strong>${check.name}:</strong> ${check.message}
                        </div>`;
                    });
                    html += '</div>';

                    $result.html(html);
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                $icon.removeClass().addClass('mio-check-icon mio-error').text('❌');
                $result.html(`<div class="mio-check-error">Error: ${error.message}</div>`);
            }
        },

        async runOptimizationTest() {
            const $checkItem = $('.mio-check-item[data-check="optimization"]');
            if ($checkItem.length === 0) return;

            const $icon = $checkItem.find('.mio-check-icon');
            const $result = $checkItem.find('.mio-check-result');

            $icon.removeClass().addClass('mio-check-icon mio-checking').text('⏳');

            try {
                const response = await this.makeAjaxRequest('mio_run_test_optimization');

                if (response.success) {
                    $icon.removeClass().addClass('mio-check-icon mio-success').text('✅');

                    const testResult = response.data.test_result;
                    let html = `<div class="mio-test-result mio-${testResult.status}">
                        <p><strong>${testResult.message}</strong></p>
                        <ul>`;

                    testResult.details.forEach(detail => {
                        html += `<li>${detail}</li>`;
                    });

                    html += '</ul></div>';
                    $result.html(html);
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                $icon.removeClass().addClass('mio-check-icon mio-error').text('❌');
                $result.html(`<div class="mio-test-error">Error: ${error.message}</div>`);
            }
        },

        async handleRunCheck(e) {
            e.preventDefault();

            const checkType = $(e.target).data('check');

            if (checkType === 'server') {
                await this.runServerCheck();
            } else if (checkType === 'optimization') {
                await this.runOptimizationTest();
            }
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

        getLocalizedString(key) {
            // Check if mio_ajax is defined and has strings
            if (typeof mio_ajax !== 'undefined' && mio_ajax.strings && mio_ajax.strings[key]) {
                return mio_ajax.strings[key];
            }

            // Fallback strings
            const fallbacks = {
                'testing': 'Testing...',
                'test_success': 'Test successful!',
                'test_failed': 'Test failed:',
                'reset_confirm': 'Are you sure you want to reset all settings to defaults?'
            };

            return fallbacks[key] || key;
        },

        makeAjaxRequest(action, data = {}) {
            return new Promise((resolve, reject) => {
                // Check if mio_ajax is defined
                if (typeof mio_ajax === 'undefined') {
                    reject(new Error('AJAX configuration not found'));
                    return;
                }

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

    // Initialize only if we're on the right page
    if ($('body').hasClass('settings_page_morden_optimizer')) {
        SettingsTabs.init();
    }
});
