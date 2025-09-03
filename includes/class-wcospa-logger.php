<?php

declare(strict_types=1);

/**
 * WCOSPA Lightweight Logger Class
 * 
 * Provides efficient logging functionality with minimal CPU and memory usage.
 * Features:
 * - Dedicated log files in plugin directory
 * - Automatic log rotation (7-day retention)
 * - Performance optimizations (buffering, minimal file operations)
 * - Security features (file protection, input sanitization)
 * 
 * @package WCOSPA
 * @version 1.6.5
 */
class WCOSPA_Logger
{
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Maximum log file size in bytes (10MB)
     */
    const MAX_LOG_SIZE = 10485760;

    /**
     * Log retention period in days
     */
    const RETENTION_DAYS = 7;

    /**
     * Buffer size for batch writing (number of log entries)
     */
    const BUFFER_SIZE = 10;

    /**
     * Log buffer for batch writing
     */
    private static $log_buffer = [];

    /**
     * Last cleanup check time
     */
    private static $last_cleanup_check = null;

    /**
     * Initialize the logger
     */
    public static function init()
    {
        // Create logs directory if it doesn't exist
        self::ensure_logs_directory();
        
        // Register shutdown function to flush any remaining buffered logs
        register_shutdown_function([__CLASS__, 'flush_buffer']);
        
        // Schedule daily cleanup
        if (!wp_next_scheduled('wcospa_daily_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wcospa_daily_log_cleanup');
        }
        
        add_action('wcospa_daily_log_cleanup', [__CLASS__, 'cleanup_old_logs']);
    }

    /**
     * Log a debug message
     */
    public static function debug(string $message, array $context = [])
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info(string $message, array $context = [])
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning(string $message, array $context = [])
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     */
    public static function error(string $message, array $context = [])
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a critical message
     */
    public static function critical(string $message, array $context = [])
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Core logging method with performance optimizations
     */
    private static function log(string $level, string $message, array $context = [])
    {
        // Skip logging if debug mode is disabled and this is a debug message
        if ($level === self::LEVEL_DEBUG && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            return;
        }

        // Sanitize message to prevent injection attacks
        $message = wp_strip_all_tags($message);
        $message = substr($message, 0, 1000); // Limit message length

        // Format log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = empty($context) ? '' : ' ' . wp_json_encode($context);
        $log_entry = sprintf(
            "[%s] [WCOSPA] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $context_str
        );

        // Add to buffer for batch writing
        self::$log_buffer[] = $log_entry;

        // Flush buffer if it's full or if this is a critical/error message
        if (count(self::$log_buffer) >= self::BUFFER_SIZE || 
            in_array($level, [self::LEVEL_CRITICAL, self::LEVEL_ERROR])) {
            self::flush_buffer();
        }

        // Perform cleanup check periodically (not on every log call)
        self::maybe_perform_cleanup();
    }

    /**
     * Flush the log buffer to file
     */
    public static function flush_buffer()
    {
        if (empty(self::$log_buffer)) {
            return;
        }

        $log_file = self::get_log_file_path();
        $logs_dir = dirname($log_file);

        // Ensure logs directory exists
        if (!is_dir($logs_dir)) {
            self::ensure_logs_directory();
        }

        // Check if log file is too large before writing
        if (file_exists($log_file) && filesize($log_file) > self::MAX_LOG_SIZE) {
            self::rotate_log_file($log_file);
        }

        // Write buffer contents to file
        $buffer_content = implode('', self::$log_buffer);
        
        // Use file_put_contents with LOCK_EX for atomic writes
        $result = file_put_contents($log_file, $buffer_content, FILE_APPEND | LOCK_EX);
        
        if ($result !== false) {
            // Clear buffer only if write was successful
            self::$log_buffer = [];
        }
    }

    /**
     * Get the current log file path
     */
    private static function get_log_file_path(): string
    {
        $logs_dir = self::get_logs_directory();
        $date = current_time('Y-m-d');
        return $logs_dir . "/wcospa-{$date}.log";
    }

    /**
     * Get the logs directory path
     */
    private static function get_logs_directory(): string
    {
        return WCOSPA_PATH . 'logs';
    }

    /**
     * Ensure logs directory exists with proper permissions and security
     */
    private static function ensure_logs_directory()
    {
        $logs_dir = self::get_logs_directory();

        if (!is_dir($logs_dir)) {
            // Create directory with proper permissions
            if (!wp_mkdir_p($logs_dir)) {
                return false;
            }
        }

        // Create .htaccess to protect log files
        $htaccess_file = $logs_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php to prevent directory listing
        $index_file = $logs_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        return true;
    }

    /**
     * Rotate log file when it gets too large
     */
    private static function rotate_log_file(string $log_file)
    {
        $rotated_file = $log_file . '.' . time();
        if (rename($log_file, $rotated_file)) {
            // Compress the rotated file to save space (if gzip is available)
            if (function_exists('gzencode')) {
                $content = file_get_contents($rotated_file);
                if ($content !== false) {
                    $compressed = gzencode($content, 6); // Medium compression
                    if ($compressed !== false) {
                        file_put_contents($rotated_file . '.gz', $compressed);
                        unlink($rotated_file); // Remove uncompressed file
                    }
                }
            }
        }
    }

    /**
     * Cleanup old log files (called periodically, not on every log)
     */
    private static function maybe_perform_cleanup()
    {
        // Only check for cleanup once per hour to minimize performance impact
        $current_time = time();
        if (self::$last_cleanup_check && ($current_time - self::$last_cleanup_check) < 3600) {
            return;
        }

        self::$last_cleanup_check = $current_time;

        // Schedule cleanup to run asynchronously if possible
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + 30, 'wcospa_async_log_cleanup');
            add_action('wcospa_async_log_cleanup', [__CLASS__, 'cleanup_old_logs']);
        } else {
            self::cleanup_old_logs();
        }
    }

    /**
     * Clean up old log files
     */
    public static function cleanup_old_logs()
    {
        $logs_dir = self::get_logs_directory();
        
        if (!is_dir($logs_dir)) {
            return;
        }

        $cutoff_time = time() - (self::RETENTION_DAYS * 24 * 60 * 60);
        $files = glob($logs_dir . '/wcospa-*.log*');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }

    /**
     * Get log file statistics for admin display
     */
    public static function get_log_stats(): array
    {
        $logs_dir = self::get_logs_directory();
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'oldest_log' => null,
            'newest_log' => null
        ];

        if (!is_dir($logs_dir)) {
            return $stats;
        }

        $files = glob($logs_dir . '/wcospa-*.log*');
        $stats['total_files'] = count($files);

        foreach ($files as $file) {
            $stats['total_size'] += filesize($file);
            $mtime = filemtime($file);
            
            if ($stats['oldest_log'] === null || $mtime < $stats['oldest_log']) {
                $stats['oldest_log'] = $mtime;
            }
            
            if ($stats['newest_log'] === null || $mtime > $stats['newest_log']) {
                $stats['newest_log'] = $mtime;
            }
        }

        return $stats;
    }

    /**
     * Get recent log entries for admin display
     */
    public static function get_recent_logs(int $limit = 50): array
    {
        $log_file = self::get_log_file_path();
        
        if (!file_exists($log_file)) {
            return [];
        }

        // Read file in reverse to get most recent entries first
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        // Return last N lines (most recent)
        return array_slice(array_reverse($lines), 0, $limit);
    }
}
