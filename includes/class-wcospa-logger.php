<?php
// This file handles logging of sync events and order details

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Logger {

    const SYNC_LOG_OPTION = '_wcospa_sync_logs';

    public static function log_sync_event($order, $pronto_order_number) {
        $logs = get_option(self::SYNC_LOG_OPTION, []);

        $logs[] = [
            'order_id' => $order->get_id(),
            'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'name' => $order->get_formatted_billing_full_name(),
            'email' => $order->get_billing_email(),
            'delivery_address' => $order->get_formatted_shipping_address(),
            'cost' => $order->get_total(),
            'sync_date' => current_time('mysql'),
            'pronto_order_number' => $pronto_order_number
        ];

        update_option(self::SYNC_LOG_OPTION, $logs);
    }

    public static function get_sync_logs() {
        return get_option(self::SYNC_LOG_OPTION, []);
    }

    public static function clear_sync_logs() {
        delete_option(self::SYNC_LOG_OPTION);
    }
}