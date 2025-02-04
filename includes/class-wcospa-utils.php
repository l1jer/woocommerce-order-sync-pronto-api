<?php
/**
 * WCOSPA Utilities
 *
 * Provides shared utility functions for the WCOSPA plugin.
 *
 * @package WCOSPA
 * @version 1.4.10
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
} 