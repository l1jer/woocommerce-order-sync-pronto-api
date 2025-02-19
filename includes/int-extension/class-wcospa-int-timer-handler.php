<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_INT_Timer_Handler
 * 
 * Handles decision and shipping timers for international orders
 */
class WCOSPA_INT_Timer_Handler {
    /**
     * @var int Decision timer duration in seconds (48 hours)
     */
    private const DECISION_TIMER_DURATION = 48 * 60 * 60;

    /**
     * @var int Shipping timer duration in seconds (48 hours)
     */
    private const SHIPPING_TIMER_DURATION = 48 * 60 * 60;

    /**
     * Constructor
     */
    public function __construct() {
        // Add timer check to WordPress cron
        add_action('init', [$this, 'schedule_timer_check']);
        add_action('wcospa_int_check_timers', [$this, 'check_timers']);
        
        // Clean up shipping responder data
        $this->cleanup_shipping_responder_data();
    }

    /**
     * Schedule timer check if not already scheduled
     */
    public function schedule_timer_check(): void {
        if (!wp_next_scheduled('wcospa_int_check_timers')) {
            wp_schedule_event(time(), 'hourly', 'wcospa_int_check_timers');
            $this->log_debug('Timer check scheduled for hourly execution');
        }
    }

    /**
     * Check decision and shipping timers
     */
    public function check_timers(): void {
        $this->log_debug('Starting timer check process');
        $this->check_decision_timers();
        $this->check_shipping_timers();
        $this->log_debug('Timer check process completed');
    }

    /**
     * Check decision timers for orders awaiting dealer decision
     */
    private function check_decision_timers(): void {
        global $wpdb;

        $this->log_debug('Checking decision timers');

        // Get orders with expired decision timers
        $expired_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_wcospa_int_decision_timer' 
                AND CAST(meta_value AS UNSIGNED) < %d",
                time() - self::DECISION_TIMER_DURATION
            )
        );

        $this->log_debug(sprintf('Found %d orders with expired decision timers', count($expired_orders)));

        foreach ($expired_orders as $expired_order) {
            $order_id = $expired_order->post_id;
            $order = wc_get_order($order_id);

            if (!$order || $order->get_status() !== 'await-dealer') {
                continue;
            }

            $timer_start = (int) $expired_order->meta_value;
            $time_elapsed = time() - $timer_start;
            
            $this->log_debug(sprintf(
                'Decision timer expired for order #%d. Timer started: %s, Elapsed time: %s hours',
                $order_id,
                date('Y-m-d H:i:s', $timer_start),
                round($time_elapsed / 3600, 2)
            ));

            // No response received, trigger Pronto sync
            $order->add_order_note(__('No dealer response received within 48 hours. Proceeding with Pronto sync.', 'wcospa'));
            $order->update_meta_data('_wcospa_int_dealer_response', 'timeout');
            $order->save();

            // Remove decision timer
            delete_post_meta($order_id, '_wcospa_int_decision_timer');

            // Trigger Pronto sync
            do_action('wcospa_sync_order', $order_id);
            
            $this->log_debug(sprintf('Order #%d processed: Timer cleared and Pronto sync triggered', $order_id));
        }
    }

    /**
     * Check shipping timers for accepted orders
     */
    private function check_shipping_timers(): void {
        global $wpdb;

        $this->log_debug('Checking shipping timers');

        // Get orders with expired shipping timers
        $expired_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_wcospa_int_shipping_timer' 
                AND CAST(meta_value AS UNSIGNED) < %d",
                time() - self::SHIPPING_TIMER_DURATION
            )
        );

        $this->log_debug(sprintf('Found %d orders with expired shipping timers', count($expired_orders)));

        foreach ($expired_orders as $expired_order) {
            $order_id = $expired_order->post_id;
            $order = wc_get_order($order_id);

            if (!$order || $order->get_status() !== 'dealer-accept') {
                continue;
            }

            // Check if order is marked as shipped
            $is_shipped = $order->get_meta('_wcospa_int_shipped');
            if ($is_shipped) {
                delete_post_meta($order_id, '_wcospa_int_shipping_timer');
                $this->log_debug(sprintf('Order #%d already shipped, clearing timer', $order_id));
                continue;
            }

            $timer_start = (int) $expired_order->meta_value;
            $time_elapsed = time() - $timer_start;
            
            $this->log_debug(sprintf(
                'Shipping timer expired for order #%d. Timer started: %s, Elapsed time: %s hours',
                $order_id,
                date('Y-m-d H:i:s', $timer_start),
                round($time_elapsed / 3600, 2)
            ));

            // Send notification to admins
            $admin_emails = ['jli@zerotech.com.au'];
            $subject = sprintf(__('Shipping Delay: Order #%s not shipped within 48 hours', 'wcospa'), $order->get_order_number());
            $message = sprintf(
                __('Order #%s was accepted by the dealer but has not been marked as shipped within 48 hours. Please investigate the delay.', 'wcospa'),
                $order->get_order_number()
            );

            foreach ($admin_emails as $admin_email) {
                wp_mail($admin_email, $subject, $message);
            }

            $order->add_order_note(__('Shipping delay notification sent to administrators.', 'wcospa'));
            delete_post_meta($order_id, '_wcospa_int_shipping_timer');
            
            $this->log_debug(sprintf('Order #%d processed: Timer cleared and delay notification sent', $order_id));
        }
    }

    /**
     * Mark order as shipped
     */
    public function mark_as_shipped(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order->update_meta_data('_wcospa_int_shipped', true);
        $order->save();

        // Remove shipping timer
        delete_post_meta($order_id, '_wcospa_int_shipping_timer');
        
        $this->log_debug(sprintf('Order #%d marked as shipped by dealer, timer cleared', $order_id));
    }

    /**
     * Clean up shipping responder data from the database
     */
    private function cleanup_shipping_responder_data(): void {
        global $wpdb;

        // Get all orders with shipping responder meta
        $orders = $wpdb->get_results(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wcospa_int_shipping_responder'"
        );

        if (!empty($orders)) {
            foreach ($orders as $order) {
                delete_post_meta($order->post_id, '_wcospa_int_shipping_responder');
                $this->log_debug(sprintf('Cleaned up shipping responder data for order #%d', $order->post_id));
            }
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     */
    private function log_debug(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[WCOSPA INT Timer] %s', $message));
        }
    }
} 