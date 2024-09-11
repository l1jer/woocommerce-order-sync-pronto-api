<?php

// This file adds the sync and fetch buttons to the WooCommerce order page and manages their states

if (!defined('ABSPATH')) {
    exit;
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
        echo '<div class="wcospa-sync-fetch-buttons" style="margin-top: 20px; border: 1px solid #ddd; padding: 10px;">';
        echo '<h3>'.__('Sync Order with Pronto API', 'wcospa').'</h3>';
        echo '<div style="display: flex; justify-content: flex-start;">';

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
            echo '<div class="pronto-order-number" style="text-align: left; margin-top: 10px;">'.esc_html($pronto_order_number).'</div>';
        } else {
            echo '<div class="pronto-order-number" style="text-align: left; margin-top: 10px;">-</div>';
        }

        echo '</div>'; // End button container div
    }

    public static function enqueue_sync_button_script()
    {
        wp_enqueue_script('wcospa-sync-button', WCOSPA_URL.'assets/js/wcospa-sync-button.js', ['jquery'], WCOSPA_VERSION, true);
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