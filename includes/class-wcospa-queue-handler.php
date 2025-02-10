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
    const ORDER_NUMBER_WAIT_TIME = 120; // 2 minutes wait before first attempt
    private static $last_process_time = 0;

    /**
     * Process queue for Pronto order number fetching
     * 
     * @param string $context Either 'ajax' or 'cron'
     */
    public static function process_order_number_queue($context = 'cron') {
        // For AJAX requests, use shorter interval
        $interval = ($context === 'ajax') ? 10 : self::QUEUE_PROCESS_INTERVAL;
        
        // Check if enough time has passed since last process
        if (time() - self::$last_process_time < $interval) {
            return;
        }

        global $wpdb;

        // Get next order to process, ensuring 2 minutes have passed since order creation
        $next_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT pm1.post_id, pm1.meta_value as sync_time, p.post_date 
                FROM {$wpdb->postmeta} pm1
                JOIN {$wpdb->posts} p ON p.ID = pm1.post_id
                JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                WHERE pm1.meta_key = '_wcospa_sync_time'
                AND pm2.meta_key = '_wcospa_transaction_uuid'
                AND p.post_date <= %s
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
                date('Y-m-d H:i:s', time() - self::ORDER_NUMBER_WAIT_TIME),
                WCOSPA_Order_Handler::MAX_RETRY_COUNT
            )
        );

        if ($next_order) {
            wc_get_logger()->debug(
                sprintf('[%s] Processing order %s for Pronto order number. Order age: %s seconds', 
                    strtoupper($context),
                    $next_order->post_id,
                    time() - strtotime($next_order->post_date)
                ),
                array('source' => 'WCOSPA')
            );
            
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

    public function process_queue() {
        // Get orders that need processing
        $orders = $this->get_orders_to_process();
        
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $transaction_uuid = get_post_meta($order_id, '_pronto_transaction_uuid', true);
            
            // If no UUID, skip this order
            if (empty($transaction_uuid)) {
                continue;
            }
            
            // Check when order was created
            $order_created = strtotime($order->get_date_created()->date('Y-m-d H:i:s'));
            $current_time = time();
            
            // For new orders, wait 2 minutes before first check
            if (($current_time - $order_created) < 120) {
                continue;
            }
            
            // Check if we've recently tried to get the order number
            $last_check = get_post_meta($order_id, '_pronto_last_check', true);
            
            // If we checked less than 2 minutes ago, skip
            if (!empty($last_check) && ($current_time - $last_check) < 120) {
                continue;
            }
            
            // Update last check time
            update_post_meta($order_id, '_pronto_last_check', $current_time);
            
            // Log the attempt
            wc_get_logger()->debug(
                sprintf('Attempting to get Pronto order number for order %s', $order_id),
                array('source' => 'WCOSPA')
            );
            
            // Try to get Pronto order number
            $this->get_pronto_order_number($order_id, $transaction_uuid);
        }
    }
} 