jQuery(document).ready(function ($) {
    $('.sync-order-button').click(function () {
        var button = $(this);
        var orderId = button.data('order-id');
        var nonce = button.data('nonce');

        if (button.hasClass('disabled')) {
            return; // Prevent action if button is disabled
        }

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
                    button.html('Already Synced').addClass('disabled').attr('title', 'Already Synced');
                    console.log('Sync successful: ', response);
                } else {
                    button.html('Retry Sync').prop('disabled', false);
                    console.log('Sync failed: ', response.data);
                }
            },
            error: function (xhr, status, error) {
                button.html('Retry Sync').prop('disabled', false);
                console.error('Sync error: ', error);
            }
        });
    });

    // Initialize tooltips
    $('.sync-order-button').hover(function () {
        var tooltip = $(this).attr('title');
        if (tooltip) {
            $(this).attr('data-tooltip', tooltip).removeAttr('title');
        }
    }, function () {
        var tooltip = $(this).attr('data-tooltip');
        if (tooltip) {
            $(this).attr('title', tooltip).removeAttr('data-tooltip');
        }
    });
});
