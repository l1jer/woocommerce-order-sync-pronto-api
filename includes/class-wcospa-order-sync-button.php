<?php
// This file adds a sync button to the WooCommerce orders list and handles its AJAX actions

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Order_Sync_Button {

    public static function init() {
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'add_sync_button_to_pronto_column']);
        add_action('admin_footer', [__CLASS__, 'enqueue_sync_button_script']);
        add_action('wp_ajax_wcospa_sync_order', [__CLASS__, 'handle_ajax_sync']);
        add_action('wp_ajax_wcospa_fetch_pronto_order', [__CLASS__, 'handle_ajax_fetch']);
    }

    // Remove buttons from Actions column and add them to Pronto Order column
    public static function add_sync_button_to_pronto_column($order) {
        $order_id = $order->get_id();
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
        $pronto_order_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        $sync_time = get_post_meta($order_id, '_wcospa_sync_time', true);

        $sync_button_text = 'Sync';
        $sync_disabled = false;
        $fetch_button_text = 'Fetch';
        $fetch_disabled = true;

        if ($pronto_order_number) {
            $sync_button_text = 'Already Synced';
            $sync_disabled = true;
            $fetch_button_text = 'Fetched';
            $fetch_disabled = true;
        } elseif ($transaction_uuid) {
            if (time() - $sync_time < 120) {
                $remaining_time = 120 - (time() - $sync_time);
                $fetch_button_text = "Fetch in {$remaining_time}s";
            } else {
                $fetch_button_text = 'Fetch';
                $fetch_disabled = false;
            }
        }

        echo '<div class="wcospa-order-column">';

        // Buttons will be aligned to the left
        echo '<div class="wcospa-sync-fetch-buttons" style="display: flex; justify-content: flex-start; width: 100%;">';

        echo '<button class="button wc-action-button wc-action-button-sync sync-order-button"
                  data-order-id="' . esc_attr($order_id) . '"
                  data-nonce="' . esc_attr(wp_create_nonce('wcospa_sync_order_nonce')) . '"
                  ' . disabled($sync_disabled, true, false) . '>' . esc_html($sync_button_text) . '</button>';

        echo '<button class="button wc-action-button wc-action-button-fetch fetch-order-button"
                  data-order-id="' . esc_attr($order_id) . '"
                  data-nonce="' . esc_attr(wp_create_nonce('wcospa_fetch_order_nonce')) . '"
                  ' . disabled($fetch_disabled, true, false) . '>' . esc_html($fetch_button_text) . '</button>';

        echo '</div>';  // Close the sync-fetch-buttons div

        // Display the Pronto Order number on a new line, left-aligned
        if ($pronto_order_number) {
            echo '<div class="pronto-order-number" style="text-align: left; margin-top: 5px;">' . esc_html($pronto_order_number) . '</div>';
        }

        echo '</div>';  // Close the order-column div
    }

    public static function enqueue_sync_button_script() {
        wp_enqueue_script('wcospa-sync-button', WCOSPA_URL . 'assets/js/wcospa-sync-button.js', ['jquery'], WCOSPA_VERSION, true);
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL . 'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
    }

    public static function handle_ajax_sync() {
        check_ajax_referer('wcospa_sync_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $already_synced = get_post_meta($order_id, '_wcospa_already_synced', true);

        if ($already_synced) {
            wp_send_json_error('This order has already been synced.');
        }

        // Log the order ID being synced
        error_log('Syncing order ID: ' . $order_id);

        // Sync the order and get the transaction UUID
        $uuid = WCOSPA_API_Client::send_order($order_id);

        if (is_wp_error($uuid)) {
            error_log('API request failed: ' . $uuid->get_error_message()); // Log error message
            wp_send_json_error($uuid->get_error_message());
        }

        // Log the successful response from the API
        error_log('API request successful. UUID: ' . $uuid);

        // Store the UUID and sync time with the order
        update_post_meta($order_id, '_wcospa_transaction_uuid', $uuid);
        update_post_meta($order_id, '_wcospa_sync_time', time());

        // Mark the order as already synced and return success
        update_post_meta($order_id, '_wcospa_already_synced', true);

        wp_send_json_success('Order synced successfully. Transaction UUID: ' . $uuid);
    }


    public static function handle_ajax_fetch() {
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
        $pronto_order_number = WCOSPA_API_Client::fetch_pronto_order_number($transaction_uuid);

        if (is_wp_error($pronto_order_number)) {
            wp_send_json_error($pronto_order_number->get_error_message());
        }

        // Store the Pronto Order number with the order
        update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);

        wp_send_json_success('Pronto Order Number fetched successfully: ' . $pronto_order_number);
    }
}

WCOSPA_Order_Sync_Button::init();