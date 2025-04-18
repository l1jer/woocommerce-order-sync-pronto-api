<?php
/**
 * WCOSPA Utilities
 *
 * Provides shared utility functions for the WCOSPA plugin.
 *
 * @package WCOSPA
 * @version 1.5.2
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
     * 
     * @return bool True if current Sydney time is Saturday or Sunday
     */
    public static function is_weekend(): bool
    {
        // Get current Sydney time
        $sydney_time = self::get_sydney_time('Y-m-d H:i:s');
        $timestamp = strtotime($sydney_time);
        $day = (int) date('w', $timestamp);
        
        $is_weekend = ($day === 0 || $day === 6); // 0 = Sunday, 6 = Saturday
        
        // Add detailed logging for debugging time-related issues
        wc_get_logger()->debug(
            sprintf('Weekend check: Sydney time: %s, Day: %d, Is weekend: %s', 
                $sydney_time,
                $day,
                $is_weekend ? 'Yes' : 'No'
            ),
            ['source' => 'wcospa']
        );
        
        return $is_weekend;
    }

    /**
     * Check if current time is Monday morning in Sydney
     * 
     * @return bool True if current Sydney time is Monday before noon
     */
    public static function is_monday_morning(): bool
    {
        // Get current Sydney time
        $sydney_time = self::get_sydney_time('Y-m-d H:i:s');
        $timestamp = strtotime($sydney_time);
        $day = (int) date('w', $timestamp);
        $hour = (int) date('G', $timestamp);
        
        $is_monday_morning = ($day === 1 && $hour < 12); // Monday before noon
        
        // Add detailed logging for debugging time-related issues
        wc_get_logger()->debug(
            sprintf('Monday morning check: Sydney time: %s, Day: %d, Hour: %d, Is Monday morning: %s', 
                $sydney_time,
                $day,
                $hour,
                $is_monday_morning ? 'Yes' : 'No'
            ),
            ['source' => 'wcospa']
        );
        
        return $is_monday_morning;
    }
} 