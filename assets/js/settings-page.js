// File: assets/js/settings-page.js

jQuery(document).ready(function($) {
    // Dynamic settings interactions
    $('#mio-api-service').on('change', function() {
        var selectedService = $(this).val();
        $('.mio-api-config').hide();
        $('.mio-api-config-' + selectedService).show();
    });

    // Test API connection
    $('.mio-test-api').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var service = button.data('service');

        button.prop('disabled', true).text('Testing...');

        $.ajax({
            url: mio_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mio_test_api_connection',
                service: service,
                nonce: mio_ajax.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Test Connection');
                if (response.success) {
                    alert('API connection successful!');
                } else {
                    alert('API connection failed: ' + response.data.message);
                }
            }
        });
    });
});
