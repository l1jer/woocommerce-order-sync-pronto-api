<?php

declare(strict_types=1);

/**
 * Handles shipment tracking and processing
 */
class WCOSPA_Shipment_Handler
{
    const RETRY_INTERVAL = 3600; // 1 hour in seconds

    /**
     * Initialise the shipment handler
     */
    public static function init()
    {
        // Remove existing schedule if any
        $timestamp = wp_next_scheduled('wcospa_process_shipment_tracking');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wcospa_process_shipment_tracking');
        }

        // Schedule twice daily checks (11:55 AM and 4:55 PM Sydney time)
        if (!wp_next_scheduled('wcospa_process_shipment_tracking_scheduled')) {
            // Get current Sydney time
            $sydney_timezone = new DateTimeZone('Australia/Sydney');
            $sydney_time = new DateTime('now', $sydney_timezone);
            $current_time = $sydney_time->format('H:i');
            
            // Set up morning schedule (11:55 AM)
            $morning = new DateTime('today 11:55', $sydney_timezone);
            if ($current_time > '11:55') {
                $morning->modify('+1 day');
            }
            
            // Set up afternoon schedule (4:55 PM)
            $afternoon = new DateTime('today 16:55', $sydney_timezone);
            if ($current_time > '16:55') {
                $afternoon->modify('+1 day');
            }
            
            // Only schedule on weekdays (Monday to Friday)
            if ($morning->format('N') <= 5) {
                wp_schedule_single_event($morning->getTimestamp(), 'wcospa_process_shipment_tracking_scheduled');
            }
            if ($afternoon->format('N') <= 5) {
                wp_schedule_single_event($afternoon->getTimestamp(), 'wcospa_process_shipment_tracking_scheduled');
            }
        }

        // Add action hooks
        add_action('wcospa_process_shipment_tracking_scheduled', [__CLASS__, 'schedule_next_check']);
        add_action('wcospa_process_shipment_tracking_scheduled', [__CLASS__, 'process_pending_shipments']);
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
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT pm1.post_id, pm1.meta_value as pronto_order_number, pm2.meta_value as tracking_start 
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
            LIMIT %d
        ", 5);

        $results = $wpdb->get_results($query);

        if (empty($results)) {
            return;
        }

        foreach ($results as $index => $order_data) {
            // Add delay between requests
            if ($index > 0) {
                sleep(3);
            }

            $order_id = (int) $order_data->post_id;
            
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
            self::fetch_shipment_number($order_id);
        }
    }

    /**
     * Core shipment number validation and processing
     * 
     * @param int $order_id The WooCommerce order ID
     * @param string $context The context of the call (e.g., 'cron', 'ajax', 'auto')
     * @return array Returns array with validation and processing results
     */
    public static function fetch_shipment_number($order_id, $context = 'auto')
    {
        $order_details = WCOSPA_API_Client::get_pronto_order_details($order_id);
        
        if (!is_wp_error($order_details)) {
            // Check status_code first
            if (isset($order_details['status_code'])) {
                $status_code = (string) $order_details['status_code'];
                
                // Only proceed if status_code is 80 or 90
                if ($status_code === '80' || $status_code === '90') {
                    if (isset($order_details['consignment_note']) && !empty($order_details['consignment_note'])) {
                        $shipment_number = $order_details['consignment_note'];
                        
                        // Add tracking to WooCommerce order
                        if (self::add_tracking_to_order($order_id, $shipment_number)) {
                            // Store shipment number in order meta
                            update_post_meta($order_id, '_wcospa_shipment_number', $shipment_number);
                            
                            // Clean up tracking attempt data if this was from scheduled processing
                            if ($context === 'cron') {
                                delete_post_meta($order_id, '_wcospa_shipment_tracking_start');
                                delete_post_meta($order_id, '_wcospa_shipment_tracking_attempts');
                                delete_post_meta($order_id, '_wcospa_last_tracking_attempt');
                            }

                            // Log successful tracking addition with context
                            wc_get_logger()->info(
                                sprintf('Successfully added tracking number %s to order %d with status code %s via %s', 
                                    $shipment_number, 
                                    $order_id,
                                    $status_code,
                                    strtoupper($context)
                                ),
                                ['source' => 'wcospa']
                            );

                            return [
                                'success' => true,
                                'shipment_number' => $shipment_number,
                                'status_code' => $status_code,
                                'context' => $context
                            ];
                        }
                        
                        return [
                            'success' => false,
                            'message' => 'Failed to add tracking information',
                            'context' => $context
                        ];
                    }
                }
                
                // Log that we're waiting for correct status code
                wc_get_logger()->debug(
                    sprintf('Order %d has status code %s, waiting for 80 or 90 (via %s)', 
                        $order_id,
                        $status_code,
                        strtoupper($context)
                    ),
                    ['source' => 'wcospa']
                );
                
                return [
                    'success' => false,
                    'message' => sprintf('Order %d has status code %s, waiting for 80 or 90', $order_id, $status_code),
                    'context' => $context,
                    'status_code' => $status_code
                ];
            }
            
            // Log missing status code
            wc_get_logger()->error(
                sprintf('Order %d response missing status_code: %s (via %s)', 
                    $order_id,
                    print_r($order_details, true),
                    strtoupper($context)
                ),
                ['source' => 'wcospa']
            );
            
            return [
                'success' => false,
                'message' => sprintf('Order %d response missing status_code', $order_id),
                'context' => $context
            ];
        }
        
        return [
            'success' => false,
            'message' => $order_details->get_error_message(),
            'context' => $context
        ];
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

        // Check if tracking number is valid
        if (empty($tracking_number) || !is_string($tracking_number)) {
            error_log("Invalid or empty tracking number for order {$order_id}");
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
            
            error_log("Successfully added tracking number {$tracking_number} to order {$order_id}");
            return true;
        }

        error_log('Advanced Shipment Tracking plugin is not active');
        return false;
    }

    public static function schedule_next_check()
    {
        // Schedule next check
        $sydney_timezone = new DateTimeZone('Australia/Sydney');
        $sydney_time = new DateTime('now', $sydney_timezone);
        $current_time = $sydney_time->format('H:i');
        
        // Determine next check time
        if ($current_time < '11:55') {
            $next_check = new DateTime('today 11:55', $sydney_timezone);
        } elseif ($current_time < '16:55') {
            $next_check = new DateTime('today 16:55', $sydney_timezone);
        } else {
            $next_check = new DateTime('tomorrow 11:55', $sydney_timezone);
        }
        
        // Only schedule on weekdays
        while ($next_check->format('N') > 5) {
            $next_check->modify('+1 day');
        }
        
        wp_schedule_single_event($next_check->getTimestamp(), 'wcospa_process_shipment_tracking_scheduled');
    }
} 
