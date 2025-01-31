<?php

declare(strict_types=1);

class WCOSPA_Shipment_Handler
{
    const RETRY_INTERVAL = 3600; // 1 hour in seconds
    const MAX_TIMEOUT = 48; // 48 hours
    const WORKING_HOURS_START = 6; // 6 AM
    const WORKING_HOURS_END = 19; // 7 PM

    public static function init()
    {
        // Schedule the cron event if not already scheduled
        if (!wp_next_scheduled('wcospa_process_shipment_tracking')) {
            wp_schedule_event(time(), 'every_three_minutes', 'wcospa_process_shipment_tracking');
        }

        // Add action hooks
        add_action('wcospa_process_shipment_tracking', [__CLASS__, 'process_pending_shipments']);
        add_action('wcospa_pronto_order_number_received', [__CLASS__, 'schedule_shipment_tracking'], 10, 2);
    }

    /**
     * Schedule shipment tracking after receiving Pronto order number
     */
    public static function schedule_shipment_tracking($order_id, $pronto_order_number)
    {
        // Store initial tracking attempt time
        update_post_meta($order_id, '_wcospa_shipment_tracking_start', time());
        update_post_meta($order_id, '_wcospa_shipment_tracking_attempts', 0);
        
        // Schedule immediate tracking attempt
        wp_schedule_single_event(time(), 'wcospa_process_shipment_tracking');
    }

    /**
     * Process pending shipments that need tracking information
     */
    public static function process_pending_shipments()
    {
        if (!self::is_processing_time()) {
            return;
        }

        global $wpdb;

        // Get orders that have Pronto order number but no shipment tracking
        $pending_orders = $wpdb->get_results(
            "SELECT post_id, pm1.meta_value as pronto_order_number, pm2.meta_value as tracking_start 
            FROM {$wpdb->postmeta} pm1
            JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
            WHERE pm1.meta_key = '_wcospa_pronto_order_number'
            AND pm2.meta_key = '_wcospa_shipment_tracking_start'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm3
                WHERE pm3.post_id = pm1.post_id
                AND pm3.meta_key = '_wcospa_shipment_number'
            )
            ORDER BY pm2.meta_value ASC
            LIMIT 5"
        );

        if (empty($pending_orders)) {
            return;
        }

        foreach ($pending_orders as $index => $order_data) {
            // Add delay between requests
            if ($index > 0) {
                sleep(3);
            }

            $order_id = $order_id = (int) $order_data->post_id;
            $tracking_start = (int) $order_data->tracking_start;
            $working_hours = self::calculate_working_hours($tracking_start);

            // Check if we've exceeded the 48-hour limit
            if ($working_hours >= self::MAX_TIMEOUT) {
                self::send_timeout_alert($order_id);
                continue;
            }

            // Get current attempt count
            $attempts = (int) get_post_meta($order_id, '_wcospa_shipment_tracking_attempts', true);
            $last_attempt = (int) get_post_meta($order_id, '_wcospa_last_tracking_attempt', true);

            // Check if we need to wait before next attempt
            if ($last_attempt && (time() - $last_attempt) < self::RETRY_INTERVAL) {
                continue;
            }

            // Update attempt count and time
            update_post_meta($order_id, '_wcospa_shipment_tracking_attempts', $attempts + 1);
            update_post_meta($order_id, '_wcospa_last_tracking_attempt', time());

            // Try to get shipment number
            $order_details = WCOSPA_API_Client::get_pronto_order_details($order_id);
            
            if (!is_wp_error($order_details) && isset($order_details['consignment_note'])) {
                $shipment_number = $order_details['consignment_note'];
                
                // Add tracking to WooCommerce order
                self::add_tracking_to_order($order_id, $shipment_number);
                
                // Store shipment number in order meta
                update_post_meta($order_id, '_wcospa_shipment_number', $shipment_number);
                
                // Clean up tracking attempt data
                delete_post_meta($order_id, '_wcospa_shipment_tracking_start');
                delete_post_meta($order_id, '_wcospa_shipment_tracking_attempts');
                delete_post_meta($order_id, '_wcospa_last_tracking_attempt');
            }
        }
    }

    /**
     * Add tracking information to WooCommerce order using Advanced Shipment Tracking
     */
    public static function add_tracking_to_order($order_id, $tracking_number)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        if (class_exists('WC_Advanced_Shipment_Tracking_Actions')) {
            $ast = WC_Advanced_Shipment_Tracking_Actions::get_instance();
            
            // Prepare the tracking data
            $tracking_item = array(
                'tracking_provider'        => 'Australia Post',
                'tracking_number'          => $tracking_number,
                'date_shipped'            => current_time('Y-m-d'),
                'status_shipped'          => 1
            );

            // Add the tracking info
            $ast->add_tracking_item($order_id, $tracking_item);
            
            // Update order status to completed
            $order->update_status('completed', 'Order completed and tracking information added.');
            
            return true;
        }

        error_log('Advanced Shipment Tracking plugin is not active');
        return false;
    }

    /**
     * Send timeout alert email
     */
    private static function send_timeout_alert($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $pronto_order_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        
        $subject = sprintf('Order #%s exceeded 48-hour shipping timeout', $order->get_order_number());
        
        $message = sprintf(
            "Order #%s has exceeded the 48-hour shipping timeout.\n\n" .
            "Order Details:\n" .
            "Original Order ID: %s\n" .
            "Pronto Order Number: %s\n" .
            "Order Status: %s\n" .
            "Order Total: %s\n" .
            "Customer: %s %s\n" .
            "Email: %s\n\n" .
            "This order has not received a shipment number after 48 working hours.",
            $order->get_order_number(),
            $order_id,
            $pronto_order_number,
            $order->get_status(),
            $order->get_formatted_order_total(),
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            $order->get_billing_email()
        );

        wp_mail('jerry@tasco.com.au', $subject, $message);
        
        // Mark as alerted to prevent duplicate emails
        update_post_meta($order_id, '_wcospa_timeout_alerted', '1');
    }

    /**
     * Check if current time is within processing window
     */
    private static function is_processing_time()
    {
        $current_time = current_time('timestamp');
        
        // Get day of week (0 = Sunday, 6 = Saturday)
        $day = (int) date('w', $current_time);
        
        // Check if it's weekend
        if ($day === 0 || $day === 6) {
            return false;
        }
        
        // Get current hour
        $hour = (int) date('G', $current_time);
        
        // Check if within working hours
        return $hour >= self::WORKING_HOURS_START && $hour < self::WORKING_HOURS_END;
    }

    /**
     * Calculate working hours elapsed since start time
     */
    private static function calculate_working_hours($start_time)
    {
        $current_time = current_time('timestamp');
        $elapsed_hours = 0;
        $current_time_check = $start_time;

        while ($current_time_check < $current_time) {
            if (self::is_processing_time()) {
                $elapsed_hours++;
            }
            $current_time_check += 3600; // Add one hour
        }

        return $elapsed_hours;
    }
} 
