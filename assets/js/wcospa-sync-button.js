jQuery(document).ready(function ($) {
    $('.sync-order-button').click(function () {
        var button = $(this);
        var orderId = button.data('order-id');
        var nonce = $('#wcospa_sync_nonce').val();

        button.html('Syncing...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wcospa_sync_order',
                order_id: orderId,
                security: nonce
            },
            success: function (response) {
                if (response.success) {
                    button.html('Synced').prop('disabled', false);
                    console.log('Sync successful: ', response);
                } else {
                    button.html('Failed: ' + response.data).prop('disabled', false);
                    console.log('Sync failed: ', response.data);
                }
            },
            error: function (xhr, status, error) {
                button.html('Retry Sync').prop('disabled', false);
                console.error('Sync error: ', error);
            }
        });
    });
});
