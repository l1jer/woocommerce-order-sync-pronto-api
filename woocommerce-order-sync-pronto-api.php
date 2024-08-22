<?php
/*
Plugin Name: WooCommerce Order Sync to Pronto Avenue API
Description: Sends order details to an external API when an order is successfully processed via any payment method. Adds a manual sync button to the WooCommerce order actions.
Version: 1.0
Author: Jerry Li
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into the WooCommerce order status changed action
add_action('woocommerce_order_status_processing', 'send_order_to_api', 10, 1);

// Add button in the WooCommerce orders list within the existing actions column
add_action('woocommerce_admin_order_actions_end', 'add_sync_order_button');

function add_sync_order_button($order) {
    wp_nonce_field('sync_order_action', 'sync_order_nonce');
    echo '<button class="button wc-action-button wc-action-button-sync sync-order-button" data-order-id="' . $order->get_id() . '" title="' . esc_attr__('Sync to API', 'woocommerce-order-sync') . '">Sync</button>';
}

add_action('admin_footer', 'add_sync_order_button_js');

function add_sync_order_button_js() {
    ?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.sync-order-button').click(function() {
        var button = $(this);
        var orderId = button.data('order-id');
        var nonce = $('#sync_order_nonce').val();

        button.html('Syncing...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sync_order_to_api',
                order_id: orderId,
                security: nonce
            },
            success: function(response) {
                if (response.success) {
                    button.html('Synced').prop('disabled', false);
                    console.log('Sync successful: ', response);
                } else {
                    button.html('Failed: ' + response.data).prop('disabled', false);
                    console.log('Sync failed: ', response.data);
                }
            },
            error: function(xhr, status, error) {
                button.html('Retry Sync').prop('disabled', false);
                console.error('Sync error: ', error);
            }
        });
    });
});
</script>
<?php
}

add_action('wp_ajax_sync_order_to_api', 'ajax_sync_order_to_api');

function ajax_sync_order_to_api() {
    check_ajax_referer('sync_order_action', 'security');

    if (!isset($_POST['order_id'])) {
        wp_send_json_error('Missing order ID');
    }

    $order_id = intval($_POST['order_id']);
    $response = send_order_to_api($order_id);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    wp_send_json_success('Order synced successfully.');
}

function send_order_to_api($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("Order not found: {$order_id}");
        return new WP_Error('order_not_found', 'Order not found: ' . $order_id);
    }

    $order_data = $order->get_data();
    $shipping_address = $order_data['shipping'];

    $api_data = [
        'customer_reference' => 'ZTAU' . $order_id,
        'debtor' => '210671',
        'delivery_address' => [
            'address1' => $shipping_address['address_1'],
            'address2' => $shipping_address['address_2'],
            'address3' => '',
            'address4' => $shipping_address['city'],
            'address5' => $shipping_address['state'],
            'address6' => $shipping_address['country'],
            'address7' => '',
            'postcode' => $shipping_address['postcode'],
            'phone' => $order->get_billing_phone()
        ],
        'payment' => [
            'method' => convert_payment_method($order->get_payment_method()),
            'reference' => $order->get_transaction_id(),
            'amount' => $order->get_total(),
            'currency_code' => $order->get_currency(),
            'bank_code' => '',
            'tax_rate_code' => ''
        ],
        'lines' => format_order_items($order->get_items())
    ];

    $response = wp_remote_post('https://tasco-750-test.prontoavenue.biz/api/json/order/v6.json/', [
        'method' => 'POST',
        'body' => json_encode($api_data),
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode('jerry@tasco.com.au:x$ArLvH*JgFsrHoQyDwwzQ)n')
        ]
    ]);

    if (is_wp_error($response)) {
        error_log('API request failed: ' . $response->get_error_message());
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        error_log("API request error: HTTP {$response_code} - {$response_body}");
        return new WP_Error('api_error', "API request error: HTTP {$response_code} - {$response_body}");
    }

    return true;
}

function convert_payment_method($method) {
    $payment_mapping = [
        'paypal' => 'PP',
        'credit_card' => 'CC',
        'gift_voucher' => 'GV',
        'default' => 'CC'
    ];
    return $payment_mapping[$method] ?? $payment_mapping['default'];
}
function format_order_items($items) {
    $formatted_items = [];
    foreach ($items as $item_id => $item) {
        $product = $item->get_product();
        if (!$product || !$product->get_sku()) {
            error_log('Product or SKU not found for item ID: ' . $item_id);
            continue; // Skip items without a valid product or SKU
        }
        $formatted_items[] = [
            'type' => 'SN', // Assuming 'SN' for normal items, adjust as necessary
            'item_code' => $product->get_sku(),
            'ordered_qty' => (string)$item->get_quantity(),
            'backordered_qty' => "0.0", // Assume no items are backordered
            'shipped_qty' => (string)$item->get_quantity(), // Assume all items are shipped
            'uom' => 'EA', // Default unit of measure
            'price_ex_tax' => (string)$item->get_subtotal(),
            'price_inc_tax' => (string)$item->get_total()
        ];
    }
    return $formatted_items;
}