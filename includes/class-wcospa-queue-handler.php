<?php
/**
 * Queue Handler for WCOSPA
 *
 * Handles queued processing of order numbers and shipment tracking
 *
 * @package WCOSPA
 * @version 1.4.10
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Queue_Handler {
    const QUEUE_PROCESS_INTERVAL = 3; // 3 seconds between each process
    private static $last_process_time = 0;

    /**
     * Process queue for Pronto order number fetching
     */
    public static function process_order_number_queue() {
        // Check if enough time has passed since last process
        if (time() - self::$last_process_time < self::QUEUE_PROCESS_INTERVAL) {
            return;
        }

        global $wpdb;

        // Get next order to process
        $next_order = $wpdb->get_row(
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
                    AND pm4.meta_key = '_wcospa_fetch_retry_count'
                    AND CAST(pm4.meta_value AS UNSIGNED) >= %d
                )
                ORDER BY pm1.meta_value ASC
                LIMIT 1",
                WCOSPA_Order_Handler::MAX_RETRY_COUNT
            )
        );

        if ($next_order) {
            WCOSPA_Order_Handler::fetch_pronto_order($next_order->post_id);
            self::$last_process_time = time();
        }
    }

    /**
     * Process queue for shipment number fetching
     */
    public static function process_shipment_queue() {
        // Check if enough time has passed since last process
        if (time() - self::$last_process_time < self::QUEUE_PROCESS_INTERVAL) {
            return;
        }

        global $wpdb;

        // Get orders from the last 72 hours without shipment numbers
        $next_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT o.ID as order_id
                FROM {$wpdb->posts} o
                JOIN {$wpdb->postmeta} pm1 ON o.ID = pm1.post_id
                WHERE o.post_type = 'shop_order'
                AND o.post_date >= %s
                AND pm1.meta_key = '_wcospa_pronto_order_number'
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm2
                    WHERE pm2.post_id = o.ID
                    AND pm2.meta_key = '_wcospa_shipment_number'
                )
                ORDER BY o.ID ASC
                LIMIT 1",
                date('Y-m-d H:i:s', strtotime('-72 hours', strtotime(WCOSPA_Utils::get_sydney_time('Y-m-d H:i:s'))))
            )
        );

        if ($next_order) {
            WCOSPA_Shipment_Handler::fetch_shipment_number($next_order->order_id);
            self::$last_process_time = time();
        }
    }
} 