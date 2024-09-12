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
        $response = WCOSPA_API_Client::sync_order($order_id);  // Corrected method call

        if (is_wp_error($response)) {
            error_log('Order sync failed: '.$response->get_error_message());
        } else {
            error_log('Order sync successful for Order ID: '.$order_id);
        }
    }
}

class WCOSPA_Order_Sync_Button
{
    public static function init()
    {
        // Add buttons to the single order page under General section
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'add_sync_button_to_order_page']);
        add_action('admin_footer', [__CLASS__, 'enqueue_sync_button_script']);
        add_action('wp_ajax_wcospa_sync_order', [__CLASS__, 'handle_ajax_sync']);
        add_action('wp_ajax_wcospa_fetch_pronto_order', [__CLASS__, 'handle_ajax_fetch']);
    }

    // Add Sync and Fetch buttons under the General section of each order page
    public static function add_sync_button_to_order_page($order)
    {
        $order_id = $order->get_id();
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
        $pronto_order_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        $sync_time = get_post_meta($order_id, '_wcospa_sync_time', true); // Timestamp of sync

        // Format the sync timestamp if available
        $sync_time_formatted = $sync_time ? date('Y-m-d H:i:s', $sync_time) : null;

        // Sync and Fetch button text and disabled states
        $sync_button_text = 'Sync';
        $sync_disabled = false;
        $fetch_button_text = 'Fetch';
        $fetch_disabled = true;
        $sync_tooltip = ''; // Tooltip for Sync button
        $fetch_tooltip = 'Only available after a successful sync to Pronto'; // Tooltip for Fetch button

        if ($pronto_order_number) {
            $sync_button_text = 'Synced';
            $sync_disabled = true;
            $fetch_button_text = 'Fetched';
            $fetch_disabled = true;
        } elseif ($transaction_uuid) {
            // If sync was completed, disable the button and show the timestamp
            if ($sync_time_formatted) {
                $sync_button_text = 'Synced';
                $sync_disabled = true;
                $sync_tooltip = 'Synced on '.$sync_time_formatted;
            }

            // Handle Fetch button countdown based on time
            if (time() - $sync_time < 120) {
                $remaining_time = 120 - (time() - $sync_time);
                $fetch_button_text = "{$remaining_time}s";
                $fetch_disabled = true;
            } else {
                $fetch_disabled = false;
                $fetch_tooltip = ''; // Remove tooltip when fetch is enabled
            }
        }

        // Display Sync and Fetch buttons under General section
        echo '<div class="wcospa-sync-fetch-buttons" style="padding: 20px 1px 0 0;flex-direction: column;">';
        echo '<div>';
        echo '<button class="button wc-action-button wc-action-button-sync sync-order-button"
                  data-order-id="'.esc_attr($order_id).'"
                  data-nonce="'.esc_attr(wp_create_nonce('wcospa_sync_order_nonce')).'"
                  '.disabled($sync_disabled, true, false).' title="'.esc_attr($sync_tooltip).'">'.esc_html($sync_button_text).'</button>';

        echo '<button class="button wc-action-button wc-action-button-fetch fetch-order-button"
                  data-order-id="'.esc_attr($order_id).'"
                  data-nonce="'.esc_attr(wp_create_nonce('wcospa_fetch_order_nonce')).'"
                  '.disabled($fetch_disabled, true, false).' title="'.esc_attr($fetch_tooltip).'">'.esc_html($fetch_button_text).'</button>';

        echo '</div>'; // End button div

        // Display Pronto Order number or "-"
        if ($pronto_order_number) {
            echo '<div class="pronto-order-number" style="text-align: left; margin-top: 10px;">Pronto Order Number:'.esc_html($pronto_order_number).'</div>';
        } else {
            echo '<div class="pronto-order-number" style="text-align: left; margin-top: 10px;">-</div>';
        }

        echo '</div>'; // End button container div
    }

    public static function enqueue_sync_button_script()
    {
        wp_enqueue_script('wcospa-admin', WCOSPA_URL.'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL.'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
    }

    public static function handle_ajax_sync()
    {
        check_ajax_referer('wcospa_sync_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $already_synced = get_post_meta($order_id, '_wcospa_already_synced', true);

        if ($already_synced) {
            wp_send_json_error('This order has already been synced.');
        }

        // Sync the order and get the transaction UUID
        $uuid = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($uuid)) {
            wp_send_json_error($uuid->get_error_message());
        }

        // Store the UUID and sync time with the order
        update_post_meta($order_id, '_wcospa_transaction_uuid', $uuid);
        update_post_meta($order_id, '_wcospa_sync_time', time()); // Store the timestamp

        // Mark the order as Synced
        update_post_meta($order_id, '_wcospa_already_synced', true);

        wp_send_json_success('Order synced successfully. Transaction UUID: '.$uuid);
    }

    public static function handle_ajax_fetch()
    {
        check_ajax_referer('wcospa_fetch_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);

        if (!$transaction_uuid) {
            wp_send_json_error('No transaction UUID found for this order.');
        }

        // Fetch the Pronto Order number using the UUID
        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id); // Correct method name

        if (is_wp_error($pronto_order_number)) {
            wp_send_json_error($pronto_order_number->get_error_message());
        }

        // Store the Pronto Order number with the order
        update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);

        wp_send_json_success('Pronto Order Number fetched successfully: '.$pronto_order_number);
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
            $sync_time = get_post_meta($post_id, '_wcospa_sync_time', true);

            $sync_button_text = 'Sync';
            $sync_disabled = false;
            $fetch_button_text = 'Fetch';
            $fetch_disabled = true;

            if ($pronto_order_number) {
                $sync_button_text = 'Synced';
                $sync_disabled = true;
                $fetch_button_text = 'Fetched';
                $fetch_disabled = true;
            } elseif ($transaction_uuid) {
                if (time() - $sync_time < 120) {
                    $remaining_time = 120 - (time() - $sync_time);
                    $fetch_button_text = "{$remaining_time}s";
                } else {
                    $fetch_button_text = 'Fetch';
                    $fetch_disabled = false;
                }
            }

            echo '<div class="wcospa-order-column">';
            echo '<div class="wcospa-sync-fetch-buttons" style="display: flex; justify-content: flex-end; width: 100%;">';

            echo '<button class="button wc-action-button wc-action-button-sync sync-order-button"
                      data-order-id="'.esc_attr($post_id).'"
                      data-nonce="'.esc_attr(wp_create_nonce('wcospa_sync_order_nonce')).'"
                      '.disabled($sync_disabled, true, false).'>'.esc_html($sync_button_text).'</button>';

            echo '<button class="button wc-action-button wc-action-button-fetch fetch-order-button"
                      data-order-id="'.esc_attr($post_id).'"
                      data-nonce="'.esc_attr(wp_create_nonce('wcospa_fetch_order_nonce')).'"
                      '.disabled($fetch_disabled, true, false).'>'.esc_html($fetch_button_text).'</button>';
            echo '</div>';  // Close the sync-fetch-buttons div
            if ($pronto_order_number) {
                echo '<div class="pronto-order-number">'.esc_html($pronto_order_number).'</div>';
            } else {
                echo '<div class="pronto-order-number"></div>';
            }
            echo '</div>';  // Close the order-column div
        }
    }

    public static function enqueue_admin_styles_and_scripts()
    {
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL.'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
        wp_enqueue_script('wcospa-admin', WCOSPA_URL.'assets/js/wcospa-admin.js', [], WCOSPA_VERSION, true);
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
        $customer_provided_note = $order->get_customer_note(); // Get the customer-provided note

        // Combine delivery instructions into a single string
        $delivery_instructions = implode(', ', [
            'NO INVOICE & PACKING SLIP', // del_inst_1: Uppercase instruction
            $billing_email !== $shipping_email && $shipping_email ? $shipping_email : $billing_email, // del_inst_2: Customer email from shipping if different
            !empty($customer_provided_note) ? substr($customer_provided_note, 0, 30) : '', // del_inst_3: Customer-provided note, limited to 30 characters
        ]);

        return [
            'customer_reference' => $customer_reference,
            'debtor' => '210942', // Updated debtor code
            'delivery_address' => [
                'address1' => strtoupper($order->get_shipping_first_name().' '.$order->get_shipping_last_name()), // Capitalized name
                'address2' => $shipping_address['address_1'],
                'address3' => $shipping_address['address_2'],
                'address4' => $shipping_address['city'],
                'address5' => $shipping_address['state'],
                'address6' => $shipping_address['country'],
                'address7' => '', // Keep empty as per requirements
                'postcode' => $shipping_address['postcode'],
                'phone' => $order->get_billing_phone(),
            ],
            'delivery_instructions' => $delivery_instructions, // Single string of delivery instructions
            'payment' => [
                'method' => self::convert_payment_method($order->get_payment_method()),
                'reference' => $order->get_transaction_id(),
                'amount' => $order->get_total(),
                'currency_code' => $order->get_currency(),
            ],
            'lines' => self::format_order_items($order->get_items()),
        ];
    }

    private static function convert_payment_method($method)
    {
        $payment_mapping = [
            'paypal' => 'PP',
            'credit_card' => 'CC',
            'gift_voucher' => 'GV',
            'default' => 'CC',
        ];

        return $payment_mapping[$method] ?? $payment_mapping['default'];
    }

    private static function format_order_items($items)
    {
        $formatted_items = [];
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                error_log('Product or SKU not found for item ID: '.$item_id);
                continue; // Skip items without a valid product or SKU
            }

            // Calculate the price for API (price divided by 1.1)
            $price_ex_tax = $item->get_total() / 1.1;

            $formatted_items[] = [
                'type' => 'SN', // Assuming 'SN' for normal items, adjust as necessary
                'item_code' => $product->get_sku(),
                'quantity' => (string) $item->get_quantity(),
                'uom' => 'EA', // Default unit of measure
                'price_inc_tax' => (string) round($price_ex_tax, 2), // Send the price after dividing by 1.1, rounded to 2 decimal places
            ];
        }

        return $formatted_items;
    }

    // New function to format the amount and ensure it's a string without trailing ".00"
    private static function format_amount($amount)
    {
        // Convert amount to string and remove trailing ".00" if present
        return rtrim(rtrim((string) $amount, '0'), '.');
    }
}