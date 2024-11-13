<?php

// This file handles WooCommerce order processing and syncing with the API
if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Order_Handler
{
    public static function init()
    {
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_sync'], 10, 1);
    }

    public static function handle_order_sync($order_id)
    {
        $response = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($response)) {
            error_log('Order sync failed: ' . $response->get_error_message());
        } else {
            $order = wc_get_order($order_id);
            $order->update_status('wc-pronto-received', 'Order marked as Pronto Received after successful API sync.');
            error_log('Order ' . $order_id . ' updated to Pronto Received by API sync.');
        }
    }
}

class WCOSPA_Order_Sync_Button
{
    public static function init()
    {
        // Add buttons to the single order page under General section
        add_action('admin_footer', [__CLASS__, 'enqueue_sync_button_script']);
        add_action('wp_ajax_wcospa_sync_order', [__CLASS__, 'handle_ajax_sync']);
        add_action('wp_ajax_wcospa_fetch_pronto_order', [__CLASS__, 'handle_ajax_fetch']);
    }

    public static function enqueue_sync_button_script()
    {
        wp_enqueue_script('wcospa-admin', WCOSPA_URL . 'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL . 'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
    }

    public static function handle_ajax_sync()
    {
        check_ajax_referer('wcospa_sync_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);

        if ($already_synced) {
            wp_send_json_error('This order has already been synced.');
        }

        $uuid = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($uuid)) {
            wp_send_json_error($uuid->get_error_message());
        }

        update_post_meta($order_id, '_wcospa_transaction_uuid', $uuid);
        wp_send_json_success(['uuid' => $uuid, 'sync_time' => time()]);
    }

    public static function handle_ajax_fetch()
    {
        check_ajax_referer('wcospa_fetch_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        if (is_wp_error($pronto_order_number)) {
            wp_send_json_error($pronto_order_number->get_error_message());
        }

        update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);

        wp_send_json_success(['pronto_order_number' => $pronto_order_number]);
    }
}

WCOSPA_Order_Sync_Button::init();

class WCOSPA_Admin_Orders_Column
{
    public static function init()
    {
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_pronto_order_column']);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'display_pronto_order_column'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_styles_and_scripts']);
    }

    public static function add_pronto_order_column($columns)
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['pronto_order_number'] = __('Pronto Order', 'wcospa');
            }
        }

        return $new_columns;
    }

    public static function display_pronto_order_column($column, $post_id)
    {
        if ($column === 'pronto_order_number') {
            $pronto_order_number = get_post_meta($post_id, '_wcospa_pronto_order_number', true);
            $transaction_uuid = get_post_meta($post_id, '_wcospa_transaction_uuid', true);
            $fetch_button_text = 'Fetch';
            $fetch_disabled = true;

            if ($pronto_order_number) {
                $fetch_button_text = 'Fetched';
                $fetch_disabled = true;
            } else {
                $fetch_button_text = 'Fetch';
                $fetch_disabled = false;
            }

            echo '<div class="wcospa-order-column">';
            echo '<div class="wcospa-sync-fetch-buttons" style="justify-content: flex-end; width: 100%;">';
            echo '<button class="button wc-action-button wc-action-button-fetch fetch-order-button"
                      data-order-id="' . esc_attr($post_id) . '"
                      data-nonce="' . esc_attr(wp_create_nonce('wcospa_fetch_order_nonce')) . '"
                      ' . disabled($fetch_disabled, true, false) . '>' . esc_html($fetch_button_text) . '</button>';
            echo '</div>';
            if ($pronto_order_number) {
                echo '<div class="pronto-order-number">' . esc_html($pronto_order_number) . '</div>';
            } else {
                echo '<div class="pronto-order-number"></div>';
            }
            echo '</div>';
        }
    }

    public static function enqueue_admin_styles_and_scripts()
    {
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL . 'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
        wp_enqueue_script('wcospa-admin', WCOSPA_URL . 'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
    }
}

WCOSPA_Admin_Orders_Column::init();

class WCOSPA_Order_Data_Formatter
{
    public static function format_order($order, $customer_reference)
    {
        $order_data = $order->get_data();
        $shipping_address = $order_data['shipping'];
        $billing_email = $order->get_billing_email();
        $shipping_email = $order->get_meta('_shipping_email');
        $customer_provided_note = $order->get_customer_note();

        // Combine delivery instructions
        $delivery_instructions = '*NO INVOICE AND PACKING SLIP* ' .
            ($billing_email !== $shipping_email && $shipping_email ? $shipping_email . "\n" : $billing_email . "\n") .
            (!empty($customer_provided_note) ? ' ' . $customer_provided_note : '');

        // Get business name using the recommended WooCommerce getter method
        $business_name = $order->get_shipping_company();

        // Determine delivery address based on the conditions
        $delivery_address = [
            'address1' => strtoupper($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
            'address2' => '',
            'address3' => '',
            'address4' => '',
            'address5' => '',
            'postcode' => $shipping_address['postcode'],
            'phone' => $order->get_billing_phone(),
        ];

        // Get payment method from the order
        $payment_method = $order->get_payment_method();

        // Calculate total price_inc_tax for all items
        $total_price_inc_tax = array_reduce($order->get_items(), function ($total, $item) {
            return $total + $item->get_total();
        }, 0);

        // Return the formatted order data
        return [
            'customer_reference' => $customer_reference,
            'debtor' => '210942',
            'delivery_address' => $delivery_address,
            'delivery_instructions' => $delivery_instructions,
            'payment' => [
                'method' => self::convert_payment_method($payment_method),
                'reference' => $order->get_transaction_id(),
                'amount' => round($total_price_inc_tax, 2),
                'currency_code' => $order->get_currency(),
                'bank_code' => self::get_bank_code($payment_method),
            ],
            'lines' => self::format_order_items($order->get_items(), $payment_method, $order),
        ];
    }

    private static function convert_payment_method($method)
    {
        WCOSPA_API_Client::log('Order payment method: ' . $method);

        // Correct payment method mapping
        $payment_mapping = [
            'ppcp' => 'PP',          // PayPal
            'afterpay' => 'CC', // AfterPay
            'stripe_cc' => 'CC', // Stripe Credit Card
        ];

        // Check if the payment method exists in the mapping
        if (!isset($payment_mapping[$method])) {
            WCOSPA_API_Client::log('Payment method not found in mapping: ' . $method);
        }

        return $payment_mapping[$method] ?? 'CC'; // Return the mapped method or 'CC'
    }

    // return BANK CODE regarding to payment methods due to Pronto transaction requirements where indicates payment method on each transaction
    private static function get_bank_code($payment_method)
    {
        switch ($payment_method) {
            case 'ppcp':
                return 'PAYPAL';
            case 'stripe_cc':
                return 'STRIPE';
            case 'afterpay':
                return 'AFTER';
            default:
                return '';
        }
    }

    private static function format_order_items($items, $payment_method, $order)
    {
        $formatted_items = [];
        $total_price_inc_tax = 0;

        // Process regular items in the order
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                error_log('Product or SKU not found for item ID: ' . $item_id);
                continue;
            }

            // Calculate price_inc_tax per item (single unit price including tax)
            $price_inc_tax_per_item = $item->get_total() / $item->get_quantity();
            $price_ex_tax_per_item = $price_inc_tax_per_item / 1.1; // Calculate price excluding tax per unit

            // Add the formatted item to the list
            $formatted_items[] = [
                'type' => 'SN',
                'item_code' => $product->get_sku(),
                'quantity' => (string) $item->get_quantity(),
                'uom' => 'EA',
                'price_inc_tax' => (string) round($price_inc_tax_per_item, 2),
                'price_ex_tax' => (string) round($price_ex_tax_per_item, 2),
            ];

            // Accumulate total including tax for all items
            $total_price_inc_tax += $item->get_total();
        }

        // Add the discount note after all product items
        $discount_note = self::get_discount_note($order);
        if (!empty($discount_note)) {
            $formatted_items[] = [
                'type' => 'DN',
                'item_code' => 'Note',
                'description' => $discount_note,
                'quantity' => '0',
                'uom' => '',
                'price_inc_tax' => '0',
            ];
        }

        // Add an extra item based on the payment method
        $description = '';
        switch ($payment_method) {
            case 'ppcp':
                $description = 'PayPal';
                break;
            case 'stripe_cc':
                $description = 'Stripe - Credit Card';
                break;
            case 'afterpay':
                $description = 'AfterPay';
                break;
        }

        if (!empty($description)) {
            $formatted_items[] = [
                'type' => 'DN',
                'item_code' => 'Note',
                'description' => $description,
                'quantity' => '0',
                'uom' => '',
                'price_inc_tax' => '0',
            ];
        }

        return $formatted_items;
    }

    // Helper method to generate the discount note based on order discounts
    private static function get_discount_note($order)
    {
        $note_parts = [];

        // Check for coupons applied to the order
        if ($coupons = $order->get_coupon_codes()) {
            foreach ($coupons as $coupon_code) {
                $coupon = new WC_Coupon($coupon_code);
                $note_parts[] = 'Discount: Coupon(' . $coupon_code . ') - ' . $coupon->get_description();
            }
        }

        // Check for on sale products
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->is_on_sale()) {
                $note_parts[] = 'Product SKU: ' . $product->get_sku() . ' is on sale';
            }
        }

        // Placeholder for auto-gift logic
        // Uncomment and complete the following line if auto-gift logic is needed
        // if ($auto_gift) { $note_parts[] = 'Auto gift included in order'; }

        // Combine all note parts into a single description string
        return implode('; ', $note_parts);
    }
}

function register_pronto_received_order_status()
{
    register_post_status('wc-pronto-received', [
        'label' => 'Pronto Received',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Pronto Received <span class="count">(%s)</span>', 'Pronto Received <span class="count">(%s)</span>'),
    ]);
}
add_action('init', 'register_pronto_received_order_status');

function add_pronto_received_to_order_statuses($order_statuses)
{
    $new_order_statuses = [];

    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-pronto-received'] = 'Pronto Received';
        }
    }

    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'add_pronto_received_to_order_statuses');

function wc_custom_order_status_styles()
{
    echo '<style>
        .status-pronto-received {
            background-color: orange !important;
            color: white !important;
        }
    </style>';
}
add_action('admin_head', 'wc_custom_order_status_styles');