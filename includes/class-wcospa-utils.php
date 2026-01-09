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
     * Sanitise text before sending it to external APIs.
     *
     * Some downstream systems reject 4-byte UTF-8 characters (commonly emoji). WordPress can store emoji,
     * but external systems may not accept them. This method:
     * - Ensures valid UTF-8
     * - Removes non-BMP characters (U+10000 and above), which covers most emoji
     * - Removes control characters (keeps \t, \n, \r)
     *
     * @param string $text Raw text.
     * @return string Sanitised text.
     */
    public static function sanitise_text_for_api(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        // Ensure the string is valid UTF-8 (returns an empty string if invalid).
        if (function_exists('wp_check_invalid_utf8')) {
            $text = (string) wp_check_invalid_utf8($text, true);
        }

        if ($text === '') {
            return $text;
        }

        // Remove non-BMP characters (e.g., most emoji) to avoid upstream API validation failures.
        $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        // Remove control characters but keep tab/newline/carriage return.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return $text;
    }

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
     * Start queue processing loop
     */
    public static function start_queue_processing() {
        // Process order number queue
        WCOSPA_Queue_Handler::process_order_number_queue();
    }


} 