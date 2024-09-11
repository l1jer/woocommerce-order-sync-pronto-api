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