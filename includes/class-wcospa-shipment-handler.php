<?php

declare(strict_types=1);

/**
 * Handles shipment tracking and processing
 */
class WCOSPA_Shipment_Handler
{
    /**
     * Initialise the shipment handler
     */
    public static function init()
    {
        // Remove existing schedule if any (silent operation)
        $timestamp = wp_next_scheduled('wcospa_process_shipment_tracking');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wcospa_process_shipment_tracking');
        }

        // Schedule multiple daily checks as per task 1.6.6
        // Monday-Thursday: 9:00 AM, 12:00 PM, 2:00 PM, 5:30 PM Sydney time
        // Friday: 9:00 AM, 12:00 PM Sydney time
        if (!wp_next_scheduled('wcospa_process_shipment_tracking_scheduled')) {
            WCOSPA_Logger::info('Setting up shipment tracking scheduled events...');
            // Get current Sydney time
            $sydney_timezone = new DateTimeZone('Australia/Sydney');
            $sydney_time = new DateTime('now', $sydney_timezone);
            $current_time = $sydney_time->format('H:i');
            
            // Define check times for Monday-Thursday (in 24-hour format) as per task 1.6.6
            $weekday_times = ['09:00', '12:00', '14:00', '17:30'];
            
            // Define check times for Friday (in 24-hour format) as per task 1.6.6
            $friday_times = ['09:00', '12:00'];
            
            foreach ($weekday_times as $time) {
                $check_time = new DateTime('today ' . $time, $sydney_timezone);
                
                // If current time is past this check time, schedule for tomorrow
                if ($current_time > $time) {
                    $check_time->modify('+1 day');
                }
                
                // Only schedule on weekdays (Monday to Friday)
                while ($check_time->format('N') > 5) {
                    $check_time->modify('+1 day');
                }
                
                // For Friday, skip times not in friday_times
                if ($check_time->format('N') == 5 && !in_array($time, $friday_times)) {
                    continue;
                }
                
                wp_schedule_single_event($check_time->getTimestamp(), 'wcospa_process_shipment_tracking_scheduled');
                
                // Log each scheduled event
                WCOSPA_Logger::info(sprintf(
                    'Scheduled shipment tracking event for %s %s (timestamp: %d)',
                    $check_time->format('l, Y-m-d'),
                    $time,
                    $check_time->getTimestamp()
                ));
            }
            
            WCOSPA_Logger::info('Completed setting up shipment tracking scheduled events');
        }
        // Skip debug logging when events already scheduled to reduce log noise

        // Add action hooks
        add_action('wcospa_process_shipment_tracking_scheduled', [__CLASS__, 'schedule_next_check']);
        add_action('wcospa_process_shipment_tracking_scheduled', [__CLASS__, 'process_pending_shipments']);
        add_action('wcospa_pronto_order_number_received', [__CLASS__, 'schedule_shipment_tracking'], 10, 2);
    }

    /**
     * Plugin activation handler for shipment tracking
     */
    public static function activate()
    {
        WCOSPA_Logger::info('WCOSPA_Shipment_Handler::activate() called');
        
        // Clear any existing scheduled events
        self::clear_all_scheduled_events();
        
        // Set up new schedule
        self::setup_initial_schedule();
        
        WCOSPA_Logger::info('WCOSPA_Shipment_Handler activation completed');
    }

    /**
     * Plugin deactivation handler for shipment tracking
     */
    public static function deactivate()
    {
        WCOSPA_Logger::info('WCOSPA_Shipment_Handler::deactivate() called');
        
        // Clear all scheduled events
        self::clear_all_scheduled_events();
        
        WCOSPA_Logger::info('WCOSPA_Shipment_Handler deactivation completed');
    }

    /**
     * Clear all scheduled shipment tracking events
     */
    private static function clear_all_scheduled_events()
    {
        $cleared_count = 0;
        
        // Clear old-style events
        wp_clear_scheduled_hook('wcospa_process_shipment_tracking');
        
        // Clear new-style scheduled events
        $events = wp_get_scheduled_event('wcospa_process_shipment_tracking_scheduled');
        while ($events) {
            wp_unschedule_event($events->timestamp, 'wcospa_process_shipment_tracking_scheduled');
            $cleared_count++;
            $events = wp_get_scheduled_event('wcospa_process_shipment_tracking_scheduled');
        }
        
        WCOSPA_Logger::info(sprintf('Cleared %d scheduled shipment tracking events', $cleared_count));
    }

    /**
     * Set up initial schedule on activation
     */
    private static function setup_initial_schedule()
    {
        WCOSPA_Logger::info('Setting up initial shipment tracking schedule...');
        
        $sydney_timezone = new DateTimeZone('Australia/Sydney');
        $sydney_time = new DateTime('now', $sydney_timezone);
        $current_time = $sydney_time->format('H:i');
        
        // Define check times for Monday-Thursday (in 24-hour format) as per task 1.6.6
        $weekday_times = ['09:00', '12:00', '14:00', '17:30'];
        
        // Define check times for Friday (in 24-hour format) as per task 1.6.6
        $friday_times = ['09:00', '12:00'];
        
        $scheduled_count = 0;
        
        foreach ($weekday_times as $time) {
            $check_time = new DateTime('today ' . $time, $sydney_timezone);
            
            // If current time is past this check time, schedule for tomorrow
            if ($current_time > $time) {
                $check_time->modify('+1 day');
            }
            
            // Only schedule on weekdays (Monday to Friday)
            while ($check_time->format('N') > 5) {
                $check_time->modify('+1 day');
            }
            
            // For Friday, skip times not in friday_times  
            if ($check_time->format('N') == 5 && !in_array($time, $friday_times)) {
                continue;
            }
            
            $result = wp_schedule_single_event($check_time->getTimestamp(), 'wcospa_process_shipment_tracking_scheduled');
            
            if ($result) {
                $scheduled_count++;
                WCOSPA_Logger::info(sprintf(
                    'Scheduled shipment tracking event for %s %s (timestamp: %d)',
                    $check_time->format('l, Y-m-d'),
                    $time,
                    $check_time->getTimestamp()
                ));
            } else {
                WCOSPA_Logger::error(sprintf(
                    'Failed to schedule shipment tracking event for %s %s',
                    $check_time->format('l, Y-m-d'),
                    $time
                ));
            }
        }
        
        WCOSPA_Logger::info(sprintf('Successfully scheduled %d shipment tracking events', $scheduled_count));
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
     * Recover orphaned orders that are missing shipment tracking start meta
     * These orders have Pronto numbers but were never added to the tracking queue
     */
    public static function recover_orphaned_orders()
    {
        global $wpdb;
        
        // Find orders in "Preparing to Ship" status with Pronto numbers but missing tracking start meta
        $orphaned_orders = $wpdb->get_results("
            SELECT DISTINCT o.ID as order_id, pm1.meta_value as pronto_order_number
            FROM {$wpdb->posts} o
            JOIN {$wpdb->postmeta} pm1 ON o.ID = pm1.post_id
            WHERE o.post_type = 'shop_order'
            AND o.post_status = 'wc-preparing-to-ship'
            AND pm1.meta_key = '_wcospa_pronto_order_number'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = o.ID
                AND pm2.meta_key = '_wcospa_shipment_number'
            )
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm3
                WHERE pm3.post_id = o.ID
                AND pm3.meta_key = '_wcospa_shipment_tracking_start'
            )
            ORDER BY o.ID ASC
        ");
        
        if (!empty($orphaned_orders)) {
            WCOSPA_Logger::warning(sprintf('Found %d orphaned orders missing shipment tracking start meta', count($orphaned_orders)));
            
            foreach ($orphaned_orders as $order_data) {
                $order_id = (int) $order_data->order_id;
                
                // Add the missing tracking start meta
                update_post_meta($order_id, '_wcospa_shipment_tracking_start', time());
                update_post_meta($order_id, '_wcospa_shipment_tracking_attempts', 0);
                
                WCOSPA_Logger::info(sprintf('Recovered orphaned order %d (Pronto: %s) - added to tracking queue', 
                    $order_id, 
                    $order_data->pronto_order_number
                ), [], $order_id);
            }
        }
    }

    /**
     * Process pending shipments that need tracking information
     */
    public static function process_pending_shipments()
    {
        WCOSPA_Logger::info('Starting scheduled shipment tracking processing');
        
        global $wpdb;

        // First, fix any orphaned orders (orders missing tracking start meta)
        self::recover_orphaned_orders();

        // Now get orders that need shipment tracking
        $query = $wpdb->prepare("
            SELECT pm1.post_id, pm1.meta_value as pronto_order_number, 
                   COALESCE(pm2.meta_value, '0') as tracking_start 
            FROM {$wpdb->postmeta} pm1
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
                AND pm2.meta_key = '_wcospa_shipment_tracking_start'
            JOIN {$wpdb->posts} o ON pm1.post_id = o.ID
            WHERE pm1.meta_key = '_wcospa_pronto_order_number'
            AND o.post_type = 'shop_order'
            AND o.post_status = 'wc-preparing-to-ship'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm3
                WHERE pm3.post_id = pm1.post_id
                AND pm3.meta_key = '_wcospa_shipment_number'
            )
            ORDER BY CAST(COALESCE(pm2.meta_value, '0') AS UNSIGNED) ASC
            LIMIT %d
        ", 30);

        $results = $wpdb->get_results($query);

        if (empty($results)) {
            WCOSPA_Logger::info('No pending shipments found for processing');
            return;
        }
        
        WCOSPA_Logger::info(sprintf('Found %d pending shipments to process', count($results)));

        foreach ($results as $index => $order_data) {
            // Add delay between requests
            if ($index > 0) {
                sleep(1);
            }

            $order_id = (int) $order_data->post_id;
            
            // Get current attempt count
            $attempts = (int) get_post_meta($order_id, '_wcospa_shipment_tracking_attempts', true);

            // Update attempt count
            update_post_meta($order_id, '_wcospa_shipment_tracking_attempts', $attempts + 1);

            // Try to get shipment number
            WCOSPA_Logger::info(sprintf('Processing order %d for shipment tracking (attempt %d)', $order_id, $attempts + 1), [], $order_id);
            
            $result = self::fetch_shipment_number($order_id, 'cron');
            
            if ($result['success']) {
                WCOSPA_Logger::info(sprintf('Successfully processed order %d: %s', $order_id, $result['shipment_number'] ?? 'tracking added'), [], $order_id);
            } else {
                WCOSPA_Logger::warning(sprintf('Failed to process order %d: %s', $order_id, $result['message']), [], $order_id);
            }
        }
        
        WCOSPA_Logger::info('Completed scheduled shipment tracking processing');
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
        
        // Check for timeout errors first
        if (is_wp_error($order_details)) {
            $error_code = $order_details->get_error_code();
            $error_message = $order_details->get_error_message();
            
            // Handle timeout errors specifically
            if (in_array($error_code, ['api_timeout', 'server_timeout_524'])) {
                WCOSPA_Logger::error(
                    sprintf('Shipment number fetch failed due to timeout for order %d via %s: %s', 
                        $order_id,
                        strtoupper($context),
                        $error_message
                    )
                );
                
                // Mark this order as having a timeout issue for tracking
                update_post_meta($order_id, '_wcospa_shipment_timeout_error', time());
                
                return [
                    'success' => false,
                    'message' => sprintf('Timeout error (524): %s', $error_message),
                    'context' => $context,
                    'error_type' => 'timeout',
                    'error_code' => $error_code
                ];
            }
            
            // Handle other server errors
            if ($error_code === 'server_error') {
                WCOSPA_Logger::error(
                    sprintf('Shipment number fetch failed due to server error for order %d via %s: %s', 
                        $order_id,
                        strtoupper($context),
                        $error_message
                    )
                );
                
                return [
                    'success' => false,
                    'message' => sprintf('Server error: %s', $error_message),
                    'context' => $context,
                    'error_type' => 'server_error',
                    'error_code' => $error_code
                ];
            }
            
            // Handle other errors
            return [
                'success' => false,
                'message' => $error_message,
                'context' => $context,
                'error_type' => 'api_error',
                'error_code' => $error_code
            ];
        }
        
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
        WCOSPA_Logger::info('Scheduling next shipment tracking check');
        
        // Schedule next check
        $sydney_timezone = new DateTimeZone('Australia/Sydney');
        $sydney_time = new DateTime('now', $sydney_timezone);
        $current_time = $sydney_time->format('H:i');
        $current_day = (int)$sydney_time->format('N'); // 1=Monday, 5=Friday
        
        WCOSPA_Logger::debug(sprintf('Current Sydney time: %s (day %d)', $current_time, $current_day));
        
        // Define check times for Monday-Thursday (in 24-hour format) as per task 1.6.6
        $weekday_times = ['09:00', '12:00', '14:00', '17:30'];
        
        // Define check times for Friday (in 24-hour format) as per task 1.6.6
        $friday_times = ['09:00', '12:00'];
        
        // Use appropriate times based on current day
        $check_times = ($current_day == 5) ? $friday_times : $weekday_times;
        
        // Find the next check time
        $next_check = null;
        foreach ($check_times as $time) {
            if ($current_time < $time) {
                $next_check = new DateTime('today ' . $time, $sydney_timezone);
                break;
            }
        }
        
        // If no time found today, schedule for tomorrow
        if (!$next_check) {
            // Determine tomorrow's schedule
            $tomorrow_day = ($current_day == 5) ? 1 : $current_day + 1; // Friday -> Monday
            $tomorrow_times = ($tomorrow_day == 5) ? $friday_times : $weekday_times;
            $next_check = new DateTime('tomorrow ' . $tomorrow_times[0], $sydney_timezone);
        }
        
        // Only schedule on weekdays
        while ($next_check->format('N') > 5) {
            $next_check->modify('+1 day');
            // Update check times for the new day
            $new_day = (int)$next_check->format('N');
            $check_times = ($new_day == 5) ? $friday_times : $weekday_times;
        }
        
        $result = wp_schedule_single_event($next_check->getTimestamp(), 'wcospa_process_shipment_tracking_scheduled');
        
        if ($result) {
            WCOSPA_Logger::info(sprintf(
                'Next shipment tracking check scheduled for %s %s (timestamp: %d)',
                $next_check->format('l, Y-m-d'),
                $next_check->format('H:i'),
                $next_check->getTimestamp()
            ));
        } else {
            WCOSPA_Logger::error(sprintf(
                'Failed to schedule next shipment tracking check for %s %s',
                $next_check->format('l, Y-m-d'),
                $next_check->format('H:i')
            ));
        }
    }
    
    /**
     * Debug function to check current scheduled events
     */
    public static function debug_scheduled_events()
    {
        WCOSPA_Logger::info('=== SCHEDULED EVENTS DEBUG ===');
        
        // Check for scheduled events
        $events = wp_get_scheduled_event('wcospa_process_shipment_tracking_scheduled');
        
        if ($events) {
            $sydney_timezone = new DateTimeZone('Australia/Sydney');
            $event_time = new DateTime('@' . $events->timestamp);
            $event_time->setTimezone($sydney_timezone);
            
            WCOSPA_Logger::info(sprintf(
                'Next scheduled event: %s (timestamp: %d)',
                $event_time->format('l, Y-m-d H:i:s T'),
                $events->timestamp
            ));
        } else {
            WCOSPA_Logger::warning('No scheduled shipment tracking events found');
        }
        
        // Check WordPress cron status
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            WCOSPA_Logger::warning('WordPress cron is disabled (DISABLE_WP_CRON = true)');
        } else {
            WCOSPA_Logger::info('WordPress cron is enabled');
        }
        
        // Check timezone
        $wordpress_timezone = wp_timezone_string();
        $sydney_timezone = new DateTimeZone('Australia/Sydney');
        $sydney_time = new DateTime('now', $sydney_timezone);
        
        WCOSPA_Logger::info(sprintf(
            'WordPress timezone: %s | Sydney time: %s',
            $wordpress_timezone,
            $sydney_time->format('Y-m-d H:i:s T')
        ));
        
        WCOSPA_Logger::info('=== END SCHEDULED EVENTS DEBUG ===');
    }
} 
