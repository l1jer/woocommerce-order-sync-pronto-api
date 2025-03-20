<?php
/**
 * WCOSPA Utilities
 *
 * Provides shared utility functions for the WCOSPA plugin.
 *
 * @package WCOSPA
 * @version 1.4.13
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Utils
{
    const SYDNEY_TIMEZONE = 'Australia/Sydney';

    /**
     * Get current Sydney time
     */
    public static function get_sydney_time($format = 'U'): string
    {
        $sydney_timezone = new DateTimeZone(self::SYDNEY_TIMEZONE);
        $sydney_time = new DateTime('now', $sydney_timezone);
        return $sydney_time->format($format);
    }

    /**
     * Convert UTC timestamp to Sydney time
     */
    public static function convert_to_sydney_time($utc_timestamp): int
    {
        $sydney_timezone = new DateTimeZone(self::SYDNEY_TIMEZONE);
        $utc_timezone = new DateTimeZone('UTC');
        
        $utc_time = new DateTime('@' . $utc_timestamp, $utc_timezone);
        $utc_time->setTimezone($sydney_timezone);
        
        return (int) $utc_time->format('U');
    }

    /**
     * Check if current time matches shipment check schedule
     */
    public static function is_shipment_check_time(): bool
    {
        $sydney_time = self::get_sydney_time();
        $hour = (int) date('G', (int) $sydney_time);
        $minute = (int) date('i', (int) $sydney_time);

        return ($hour === 11 && $minute === 25) || 
               ($hour === 16 && $minute === 55);
    }

    /**
     * Start queue processing loop
     */
    public static function start_queue_processing() {
        // Process order number queue
        WCOSPA_Queue_Handler::process_order_number_queue();

        // Check if it's time for shipment processing
        if (self::is_shipment_check_time()) {
            WCOSPA_Queue_Handler::process_shipment_queue();
        }
    }

    /**
     * Check if current time is weekend in Sydney
     */
    public static function is_weekend(): bool
    {
        $sydney_time = self::get_sydney_time();
        $day = (int) date('w', (int) $sydney_time);
        return $day === 0 || $day === 6; // 0 = Sunday, 6 = Saturday
    }

    /**
     * Check if current time is Monday morning in Sydney
     */
    public static function is_monday_morning(): bool
    {
        $sydney_time = self::get_sydney_time();
        $day = (int) date('w', (int) $sydney_time);
        $hour = (int) date('G', (int) $sydney_time);
        return $day === 1 && $hour < 12; // Monday before noon
    }

    /**
     * Get order meta data in an HPOS-compatible way
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @param bool $single Whether to return a single value (default: true)
     * @return mixed The meta value
     */
    public static function get_order_meta($order_id, $key, $single = true)
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return $single ? '' : [];
        }
        
        // Check if we need to use HPOS or the legacy method
        if (self::is_hpos_enabled()) {
            return $order->get_meta($key, $single);
        } else {
            return get_post_meta($order_id, $key, $single);
        }
    }

    /**
     * Update order meta data in an HPOS-compatible way
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return mixed Result of the update
     */
    public static function update_order_meta($order_id, $key, $value)
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Check if we need to use HPOS or the legacy method
        if (self::is_hpos_enabled()) {
            $order->update_meta_data($key, $value);
            return $order->save();
        } else {
            return update_post_meta($order_id, $key, $value);
        }
    }

    /**
     * Delete order meta data in an HPOS-compatible way
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @return mixed Result of the deletion
     */
    public static function delete_order_meta($order_id, $key)
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Check if we need to use HPOS or the legacy method
        if (self::is_hpos_enabled()) {
            $order->delete_meta_data($key);
            return $order->save();
        } else {
            return delete_post_meta($order_id, $key);
        }
    }

    /**
     * Check if HPOS (Custom Order Tables) is enabled
     *
     * @return bool Whether HPOS is enabled
     */
    public static function is_hpos_enabled()
    {
        if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
} 