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
        add_action('wcospa_fetch_pronto_order_number', [__CLASS__, 'scheduled_fetch_pronto_order'], 10, 1);
        add_action('wcospa_process_pending_orders', [__CLASS__, 'process_pending_orders'], 10);
        
        // Schedule recurring event for processing pending orders
        if (!wp_next_scheduled('wcospa_process_pending_orders')) {
            wp_schedule_event(time(), 'every_three_seconds', 'wcospa_process_pending_orders');
        }
    }

    public static function handle_order_sync($order_id)
    {
        $response = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($response)) {
            error_log('Order sync failed: ' . $response->get_error_message());
        } else {
            $order = wc_get_order($order_id);
            $order->update_status('wc-pronto-received', 'Order marked as Pronto Received after successful API sync.');
            
            // Store transaction UUID and sync time
            update_post_meta($order_id, '_wcospa_transaction_uuid', $response);
            update_post_meta($order_id, '_wcospa_sync_time', time());
            
            error_log('Order ' . $order_id . ' updated to Pronto Received by API sync.');
        }
    }

    /**
     * Scheduled task to fetch Pronto order number
     *
     * @param int $order_id The WooCommerce order ID
     */
    public static function scheduled_fetch_pronto_order($order_id)
    {
        // Check if Pronto Order Number exists
        $existing_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        if (!empty($existing_number)) {
            return;
        }

        // Execute Fetch operation
        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        if (!is_wp_error($pronto_order_number)) {
            update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
            error_log('Successfully fetched Pronto Order Number: ' . $pronto_order_number . ' for order: ' . $order_id);
        } else {
            error_log('Failed to fetch Pronto Order Number for order ' . $order_id . ': ' . $pronto_order_number->get_error_message());
        }
    }

    /**
     * Process pending orders that need Pronto order number fetch
     */
    public static function process_pending_orders()
    {
        global $wpdb;

        // Get orders that were synced more than 120 seconds ago but don't have a Pronto order number
        $pending_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} pm1
                JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                WHERE pm1.meta_key = '_wcospa_sync_time'
                AND pm1.meta_value < %d
                AND pm2.meta_key = '_wcospa_transaction_uuid'
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm3
                    WHERE pm3.post_id = pm1.post_id
                    AND pm3.meta_key = '_wcospa_pronto_order_number'
                )
                LIMIT 1",
                time() - 120
            )
        );

        if (empty($pending_orders)) {
            return;
        }

        // Process one order at a time
        $order_id = $pending_orders[0]->post_id;
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);

        if (empty($transaction_uuid)) {
            return;
        }

        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        if (!is_wp_error($pronto_order_number) && !empty($pronto_order_number)) {
            update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
            error_log('Successfully fetched Pronto Order Number: ' . $pronto_order_number . ' for order: ' . $order_id);
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

        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
        if (!empty($transaction_uuid)) {
            wp_send_json_error('This order has already been synchronised with Pronto.');
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

            echo '<div class="wcospa-order-column">';
            if ($pronto_order_number) {
                // If Pronto Order Number exists, display directly
                echo '<div class="pronto-order-number">' . esc_html($pronto_order_number) . '</div>';
            } elseif ($transaction_uuid) {
                // If UUID exists but no Order Number, display waiting status
                echo '<div class="pronto-order-number">Awaiting Pronto Order Number...</div>';
            } else {
                // If neither UUID nor Order Number exists
                echo '<div class="pronto-order-number">Not synced</div>';
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

        // Apply the conditions to modify the delivery address
        if (empty($business_name) && empty($shipping_address['address_2'])) {
            // 1. No business name and no 2nd address line
            $delivery_address['address2'] = $shipping_address['address_1'];
            $delivery_address['address3'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        } elseif (empty($business_name) && !empty($shipping_address['address_2'])) {
            // 2. No business name, but 2nd address line exists
            $delivery_address['address2'] = $shipping_address['address_1'];
            $delivery_address['address3'] = $shipping_address['address_2'];
            $delivery_address['address4'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        } elseif (!empty($business_name) && empty($shipping_address['address_2'])) {
            // 3. Business name exists, but no 2nd address line
            $delivery_address['address2'] = $business_name;
            $delivery_address['address3'] = $shipping_address['address_1'];
            $delivery_address['address4'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        } elseif (!empty($business_name) && !empty($shipping_address['address_2'])) {
            // 4. Both business name and 2nd address line exist
            $delivery_address['address2'] = $business_name;
            $delivery_address['address3'] = $shipping_address['address_1'];
            $delivery_address['address4'] = $shipping_address['address_2'];
            $delivery_address['address5'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        }

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
                'reference' => ' ' . strtoupper($payment_method) . ' ' . $customer_reference,
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

// Register custom cron interval
function register_three_second_interval($schedules)
{
    $schedules['every_three_seconds'] = array(
        'interval' => 3,
        'display' => __('Every Three Seconds')
    );
    return $schedules;
}
add_filter('cron_schedules', [__CLASS__, 'register_three_second_interval']);