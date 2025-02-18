<?php

/**
 * Handles WooCommerce order processing and synchronisation with the API
 */
class WCOSPA_Order_Handler
{
    const MAX_RETRY_COUNT = 5;
    const RETRY_INTERVAL = 30; // seconds between retries
    const REQUEST_DELAY = 3;   // seconds between requests
    const INITIAL_WAIT = 120;  // seconds to wait before first fetch

    // Define excluded order statuses
    private static $excluded_statuses = [
        'wc-shipped',
        'wc-delivered',
        'wc-cancelled',
        'wc-on-hold',
        'wc-completed',
        'wc-refunded',
        'wc-failed'
    ];

    /**
     * Initialise the handler
     */
    public static function init()
    {
        // Remove automatic sync on processing status
        // add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_sync'], 10, 1);
        
        // Keep other necessary hooks
        add_action('wcospa_fetch_pronto_order_number', [__CLASS__, 'scheduled_fetch_pronto_order'], 10, 2);
        add_action('wcospa_process_pending_orders', [__CLASS__, 'process_pending_orders'], 10);
        
        // Add filter to control sync behavior
        add_filter('wcospa_allow_auto_sync', '__return_false');
        
        // Schedule recurring event for processing pending orders
        if (!wp_next_scheduled('wcospa_process_pending_orders')) {
            wp_schedule_event(time(), 'every_three_seconds', 'wcospa_process_pending_orders');
        }
    }

    /**
     * Check if order status is excluded from processing
     *
     * @param WC_Order|int $order Order object or ID
     * @return bool True if order should be excluded
     */
    public static function is_excluded_order($order)
    {
        if (!($order instanceof WC_Order)) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return true;
        }

        return in_array('wc-' . $order->get_status(), self::$excluded_statuses);
    }

    /**
     * Plugin activation handler
     */
    public static function activate()
    {
        // Store first activation time if not already set
        if (!get_option('wcospa_first_activation_time')) {
            update_option('wcospa_first_activation_time', time(), 'no');
        }

        // Store current activation time
        update_option('wcospa_current_activation_time', time(), 'no');

        // Clear any existing scheduled hooks
        wp_clear_scheduled_hook('wcospa_process_pending_orders');
        
        // Schedule the recurring event
        if (!wp_next_scheduled('wcospa_process_pending_orders')) {
            wp_schedule_event(time(), 'every_three_seconds', 'wcospa_process_pending_orders');
        }
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate()
    {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('wcospa_process_pending_orders');
        
        // Remove current activation time
        delete_option('wcospa_current_activation_time');
        
        // Do NOT remove wcospa_first_activation_time to maintain historical reference
    }

    /**
     * Synchronise order with Pronto API
     *
     * @param int $order_id The WooCommerce order ID
     */
    public static function handle_order_sync($order_id)
    {
        // Check if auto sync is allowed
        if (!apply_filters('wcospa_allow_auto_sync', false) && !did_action('wcospa_sync_order')) {
            return;
        }
        
        $response = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($response)) {
            wc_get_logger()->error(
                sprintf('Order sync failed: %s', $response->get_error_message()),
                ['source' => 'wcospa']
            );
        } else {
            $order = wc_get_order($order_id);
            $order->update_status('wc-pronto-received', 'Order marked as Pronto Received after successful API sync.');
            
            // Store transaction UUID and sync time
            update_post_meta($order_id, '_wcospa_transaction_uuid', $response);
            update_post_meta($order_id, '_wcospa_sync_time', time());
            update_post_meta($order_id, '_wcospa_fetch_retry_count', 0);
            
            // Mark as weekend order if applicable
            if (WCOSPA_Utils::is_weekend()) {
                update_post_meta($order_id, '_wcospa_weekend_order', '1');
                wc_get_logger()->info(
                    sprintf('Order %d marked as weekend order', $order_id),
                    ['source' => 'wcospa']
                );
            }
            
            // Schedule the first fetch attempt
            wp_schedule_single_event(time() + self::INITIAL_WAIT, 'wcospa_fetch_pronto_order_number', [$order_id, 1]);
        }
    }

    /**
     * Scheduled task to fetch Pronto order number
     *
     * @param int $order_id The WooCommerce order ID
     * @param int $attempt Current attempt number
     */
    public static function scheduled_fetch_pronto_order($order_id, $attempt = 1)
    {
        // Check if Pronto Order Number already exists
        $existing_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        if (!empty($existing_number)) {
            wc_get_logger()->debug(
                sprintf('Order %d already has Pronto Order Number: %s', $order_id, $existing_number),
                ['source' => 'wcospa']
            );
            return;
        }

        // Get retry count
        $retry_count = (int) get_post_meta($order_id, '_wcospa_fetch_retry_count', true);
        
        // Check if we've exceeded max retries
        if ($retry_count >= self::MAX_RETRY_COUNT) {
            wc_get_logger()->debug(
                sprintf('Order %d exceeded maximum retry attempts (%d)', $order_id, $retry_count),
                ['source' => 'wcospa']
            );
            return;
        }

        // Execute Fetch operation
        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        // Increment retry count
        update_post_meta($order_id, '_wcospa_fetch_retry_count', $retry_count + 1);

        if (!is_wp_error($pronto_order_number) && !empty($pronto_order_number)) {
            // Success! Store the order number
            update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
            delete_post_meta($order_id, '_wcospa_fetch_retry_count'); // Clean up retry count
            
            wc_get_logger()->info(
                sprintf('Successfully fetched Pronto Order Number: %s for order: %d', 
                    $pronto_order_number, 
                    $order_id
                ),
                ['source' => 'wcospa']
            );
            
            // Trigger shipment tracking process
            do_action('wcospa_pronto_order_number_received', $order_id, $pronto_order_number);
            
            // Fetch shipping number
            $order_details = WCOSPA_API_Client::get_pronto_order_details($order_id);
            if (!is_wp_error($order_details)) {
                if (isset($order_details['consignment_note']) && !empty($order_details['consignment_note'])) {
                    $shipment_number = $order_details['consignment_note'];
                    
                    // Add tracking to WooCommerce order using Shipment Handler
                    WCOSPA_Shipment_Handler::add_tracking_to_order($order_id, $shipment_number);
                    
                    // Store shipment number in order meta
                    update_post_meta($order_id, '_wcospa_shipment_number', $shipment_number);
                    
                    error_log("Successfully fetched Shipment Number: {$shipment_number} for order: {$order_id}");
                } else {
                    error_log("No valid consignment note found for order: {$order_id}");
                }
            }
        } else {
            // Failed attempt - increment retry count and schedule next attempt if needed
            $retry_count++;
            update_post_meta($order_id, '_wcospa_fetch_retry_count', $retry_count);
            
            if ($retry_count < self::MAX_RETRY_COUNT) {
                // Calculate delay for next attempt (includes 3-second spacing between orders)
                $next_attempt_delay = self::RETRY_INTERVAL + (self::REQUEST_DELAY * ($order_id % 10));
                wp_schedule_single_event(time() + $next_attempt_delay, 'wcospa_fetch_pronto_order_number', [$order_id, $retry_count + 1]);
                error_log("Scheduled retry #{$retry_count} for order {$order_id} in {$next_attempt_delay} seconds");
            } else {
                error_log("Failed to fetch Pronto Order Number for order {$order_id} after {$retry_count} attempts");
            }
        }
    }

    /**
     * Process pending orders that need Pronto order number fetch
     */
    public static function process_pending_orders()
    {
        global $wpdb;

        // Special handling for Monday morning
        if (WCOSPA_Utils::is_monday_morning()) {
            // Get weekend orders without Pronto order numbers
            $weekend_orders = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT o.ID as order_id
                    FROM {$wpdb->posts} o
                    JOIN {$wpdb->postmeta} pm1 ON o.ID = pm1.post_id AND pm1.meta_key = '_wcospa_weekend_order'
                    JOIN {$wpdb->postmeta} pm2 ON o.ID = pm2.post_id AND pm2.meta_key = '_wcospa_transaction_uuid'
                    WHERE o.post_type = 'shop_order'
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta} pm3
                        WHERE pm3.post_id = o.ID
                        AND pm3.meta_key = '_wcospa_pronto_order_number'
                    )
                    ORDER BY o.ID ASC"
                )
            );

            if (!empty($weekend_orders)) {
                wc_get_logger()->info(
                    sprintf('Processing %d weekend orders on Monday morning', count($weekend_orders)),
                    ['source' => 'wcospa']
                );

                foreach ($weekend_orders as $order) {
                    update_post_meta($order->order_id, '_wcospa_fetch_retry_count', 0);
                    wp_schedule_single_event(
                        time() + (self::REQUEST_DELAY * array_search($order, $weekend_orders)),
                        'wcospa_fetch_pronto_order_number',
                        [$order->order_id, 1]
                    );
                }
            }
        }

        // Process regular (non-weekend) orders
        $regular_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm1.post_id, pm1.meta_value as sync_time 
                FROM {$wpdb->postmeta} pm1
                JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                WHERE pm1.meta_key = '_wcospa_sync_time'
                AND pm2.meta_key = '_wcospa_transaction_uuid'
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm3
                    WHERE pm3.post_id = pm1.post_id
                    AND pm3.meta_key = '_wcospa_pronto_order_number'
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm4
                    WHERE pm4.post_id = pm1.post_id
                    AND pm4.meta_key = '_wcospa_weekend_order'
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm5
                    WHERE pm5.post_id = pm1.post_id
                    AND pm5.meta_key = '_wcospa_fetch_retry_count'
                    AND CAST(pm5.meta_value AS UNSIGNED) >= %d
                )
                ORDER BY pm1.meta_value ASC
                LIMIT 5",
                self::MAX_RETRY_COUNT
            )
        );

        if (!empty($regular_orders)) {
            foreach ($regular_orders as $index => $order) {
                wp_schedule_single_event(
                    time() + (self::REQUEST_DELAY * $index),
                    'wcospa_fetch_pronto_order_number',
                    [$order->post_id, 1]
                );

                wc_get_logger()->debug(
                    sprintf('Scheduled fetch for order %d with %d seconds delay', 
                        $order->post_id, 
                        self::REQUEST_DELAY * $index
                    ),
                    ['source' => 'wcospa']
                );
            }
        }
    }

    /**
     * Fetch Pronto order number
     */
    public static function fetch_pronto_order($order_id)
    {
        // Get retry count
        $retry_count = (int) get_post_meta($order_id, '_wcospa_fetch_retry_count', true);
        $is_weekend_order = get_post_meta($order_id, '_wcospa_weekend_order', true);
        
        // Execute Fetch operation
        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        if (!is_wp_error($pronto_order_number) && !empty($pronto_order_number)) {
            // Success! Store the order number
            update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
            delete_post_meta($order_id, '_wcospa_fetch_retry_count');
            
            // Remove weekend flag if exists
            if ($is_weekend_order) {
                delete_post_meta($order_id, '_wcospa_weekend_order');
            }
            
            wc_get_logger()->info(
                sprintf('Successfully fetched Pronto Order Number: %s for order: %d', 
                    $pronto_order_number, 
                    $order_id
                ),
                ['source' => 'wcospa']
            );
        } else {
            // Failed attempt - increment retry count
            $retry_count++;
            update_post_meta($order_id, '_wcospa_fetch_retry_count', $retry_count);
            
            // Schedule next attempt
            if ($is_weekend_order) {
                // Weekend orders retry every 30 minutes
                wp_schedule_single_event(time() + 1800, 'wcospa_fetch_pronto_order_number', [$order_id, $retry_count + 1]);
            } else if ($retry_count < self::MAX_RETRY_COUNT) {
                // Regular orders use normal retry interval
                $next_attempt_delay = self::RETRY_INTERVAL + (self::REQUEST_DELAY * ($order_id % 10));
                wp_schedule_single_event(time() + $next_attempt_delay, 'wcospa_fetch_pronto_order_number', [$order_id, $retry_count + 1]);
            }
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
        add_action('wp_ajax_wcospa_get_shipping', [__CLASS__, 'handle_ajax_get_shipping']);
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

        // Check if we already have a Pronto order number
        $existing_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        if (!empty($existing_number)) {
            wp_send_json_success(['pronto_order_number' => $existing_number]);
            return;
        }

        // Get a lock to prevent concurrent processing
        $lock_key = '_wcospa_fetch_lock_' . $order_id;
        $lock = get_transient($lock_key);
        if ($lock) {
            wp_send_json_error('Another fetch request is in progress');
            return;
        }

        // Set a lock for 30 seconds
        set_transient($lock_key, true, 30);

        try {
            $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

            if (!is_wp_error($pronto_order_number) && !empty($pronto_order_number)) {
                update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
                wp_send_json_success(['pronto_order_number' => $pronto_order_number]);
            } else {
                $error_message = is_wp_error($pronto_order_number) ? $pronto_order_number->get_error_message() : 'Failed to fetch order number';
                wp_send_json_error($error_message);
            }
        } finally {
            // Always remove the lock
            delete_transient($lock_key);
        }
    }

    public static function handle_ajax_get_shipping()
    {
        check_ajax_referer('wcospa_get_shipping_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);

        // Check if we already have a shipment number
        $existing_number = get_post_meta($order_id, '_wcospa_shipment_number', true);
        if (!empty($existing_number)) {
            wp_send_json_success(['shipment_number' => $existing_number]);
            return;
        }

        // Get a lock to prevent concurrent processing
        $lock_key = '_wcospa_shipping_lock_' . $order_id;
        $lock = get_transient($lock_key);
        if ($lock) {
            wp_send_json_error('Another shipping request is in progress');
            return;
        }

        // Set a lock for 30 seconds
        set_transient($lock_key, true, 30);

        try {
            $order_details = WCOSPA_API_Client::get_pronto_order_details($order_id);

            if (!is_wp_error($order_details) && isset($order_details['consignment_note']) && !empty($order_details['consignment_note'])) {
                $shipment_number = $order_details['consignment_note'];
                
                // Add tracking to WooCommerce order using Shipment Handler
                if (WCOSPA_Shipment_Handler::add_tracking_to_order($order_id, $shipment_number)) {
                    // Store shipment number in order meta
                    update_post_meta($order_id, '_wcospa_shipment_number', $shipment_number);
                    
                    wp_send_json_success(['shipment_number' => $shipment_number]);
                } else {
                    wp_send_json_error('Failed to add tracking information');
                }
            } else {
                $error_message = is_wp_error($order_details) ? 
                    $order_details->get_error_message() : 
                    'No valid shipment number available yet';
                error_log("Failed to get valid shipment number for order {$order_id}: " . $error_message);
                wp_send_json_error($error_message);
            }
        } finally {
            // Always remove the lock
            delete_transient($lock_key);
        }
    }
}

WCOSPA_Order_Sync_Button::init();

class WCOSPA_Admin_Orders_Column
{
    public static function init()
    {
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_order_columns']);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'display_order_columns'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_styles_and_scripts']);
    }

    public static function add_order_columns($columns)
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['pronto_order_number'] = __('Pronto Order', 'wcospa');
                $new_columns['shipment_number'] = __('Shipment', 'wcospa');
                $new_columns['transaction_uuid'] = __('TranUuid', 'wcospa');
            }
        }

        return $new_columns;
    }

    public static function display_order_columns($column, $post_id)
    {
        if ($column === 'pronto_order_number') {
            $order = wc_get_order($post_id);
            $pronto_order_number = get_post_meta($post_id, '_wcospa_pronto_order_number', true);
            $transaction_uuid = get_post_meta($post_id, '_wcospa_transaction_uuid', true);

            echo '<div class="wcospa-order-column">';
            if ($pronto_order_number) {
                // If Pronto Order Number exists, display directly
                echo '<div class="pronto-order-number">' . esc_html($pronto_order_number) . '</div>';
            } elseif ($transaction_uuid) {
                // If UUID exists but no Order Number, display waiting status and fetch button
                echo '<div class="pronto-order-number">Awaiting Pronto Order Number</div>';
                if (!WCOSPA_Order_Handler::is_excluded_order($order)) {
                    echo '<div class="wcospa-fetch-button-wrapper">';
                    echo '<button type="button" class="button fetch-order-button" data-order-id="' . esc_attr($post_id) . '" data-nonce="' . wp_create_nonce('wcospa_fetch_order_nonce') . '">Fetch</button>';
                    echo '</div>';
                }
            } else {
                // Check if order should be excluded
                if (WCOSPA_Order_Handler::is_excluded_order($order)) {
                    echo '<div class="pronto-order-number">Legacy Order</div>';
                } else {
                    echo '<div class="pronto-order-number">Not synced</div>';
                }
            }
            echo '</div>';
        } elseif ($column === 'shipment_number') {
            $order = wc_get_order($post_id);
            $shipment_number = get_post_meta($post_id, '_wcospa_shipment_number', true);
            $pronto_order_number = get_post_meta($post_id, '_wcospa_pronto_order_number', true);

            echo '<div class="wcospa-order-column">';
            if ($shipment_number) {
                echo '<div class="shipment-number">' . esc_html($shipment_number) . '</div>';
            } elseif ($pronto_order_number) {
                echo '<div class="shipment-number">Awaiting Shipment Number</div>';
                if (!WCOSPA_Order_Handler::is_excluded_order($order)) {
                    echo '<div class="wcospa-fetch-button-wrapper">';
                    echo '<button type="button" class="button get-shipping-button" data-order-id="' . esc_attr($post_id) . '" data-nonce="' . wp_create_nonce('wcospa_get_shipping_nonce') . '">Get Shipping</button>';
                    echo '</div>';
                }
            } else {
                echo '<div class="shipment-number">-</div>';
            }
            echo '</div>';
        } elseif ($column === 'transaction_uuid') {
            $transaction_uuid = get_post_meta($post_id, '_wcospa_transaction_uuid', true);
            echo '<div class="wcospa-order-column">';
            if ($transaction_uuid) {
                echo '<div class="transaction-uuid">' . esc_html($transaction_uuid) . '</div>';
            } else {
                echo '<div class="transaction-uuid">-</div>';
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
    $schedules['every_three_minutes'] = array(
        'interval' => 180,
        'display' => __('Every Three Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'register_three_second_interval');