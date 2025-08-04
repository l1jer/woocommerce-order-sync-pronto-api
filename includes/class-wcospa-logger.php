<?php

declare(strict_types=1);

/**
 * WCOSPA Logger Class
 * 
 * Handles dedicated logging for the WCOSPA plugin with automatic log rotation
 * and cleanup. All logs are written to the plugin's own directory with proper
 * security measures and WordPress best practices.
 * 
 * @package WCOSPA
 * @since 1.6.6
 */
class WCOSPA_Logger
{
    /**
     * Log directory path
     */
    private static $log_dir = null;
    
    /**
     * Current log file path
     */
    private static $log_file = null;
    
    /**
     * Log retention period in days
     */
    const LOG_RETENTION_DAYS = 7;
    
    /**
     * Maximum log file size in bytes (10MB)
     */
    const MAX_LOG_SIZE = 10485760;
    
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    
    /**
     * Initialize the logger
     */
    public static function init()
    {
        self::setup_log_directory();
        self::setup_log_file();
        self::cleanup_old_logs();
    }
    
    /**
     * Setup the log directory with proper security
     */
    private static function setup_log_directory()
    {
        if (self::$log_dir === null) {
            self::$log_dir = WCOSPA_PATH . 'logs/';
        }
        
        // Create logs directory if it doesn't exist
        if (!file_exists(self::$log_dir)) {
            if (!wp_mkdir_p(self::$log_dir)) {
                // Fallback to WordPress uploads directory
                $upload_dir = wp_upload_dir();
                self::$log_dir = $upload_dir['basedir'] . '/wcospa-logs/';
                wp_mkdir_p(self::$log_dir);
            }
        }
        
        // Create .htaccess file to prevent direct access
        $htaccess_file = self::$log_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create index.php file to prevent directory listing
        $index_file = self::$log_dir . 'index.php';
        if (!file_exists($index_file)) {
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($index_file, $index_content);
        }
    }
    
    /**
     * Setup the current log file
     */
    private static function setup_log_file()
    {
        $date = date('Y-m-d');
        self::$log_file = self::$log_dir . "wcospa-{$date}.log";
        
        // Check if current log file is too large and needs rotation
        if (file_exists(self::$log_file) && filesize(self::$log_file) > self::MAX_LOG_SIZE) {
            $timestamp = date('Y-m-d_H-i-s');
            $rotated_file = self::$log_dir . "wcospa-{$date}_{$timestamp}.log";
            rename(self::$log_file, $rotated_file);
        }
    }
    
    /**
     * Write a log entry
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function write_log($level, $message, $context = [])
    {
        // Ensure logger is initialized
        if (self::$log_file === null) {
            self::init();
        }
        
        // Prepare timestamp in Sydney timezone
        $sydney_timezone = new DateTimeZone('Australia/Sydney');
        $timestamp = new DateTime('now', $sydney_timezone);
        $formatted_timestamp = $timestamp->format('Y-m-d H:i:s T');
        
        // Prepare context string
        $context_string = '';
        if (!empty($context)) {
            $context_string = ' | Context: ' . json_encode($context);
        }
        
        // Format log entry
        $log_entry = sprintf(
            "[%s] [WCOSPA] [%s] %s%s\n",
            $formatted_timestamp,
            $level,
            $message,
            $context_string
        );
        
        // Write to log file
        if (self::$log_file && is_writable(dirname(self::$log_file))) {
            file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
        } else {
            // Fallback to WordPress debug log if our log file is not writable
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($log_entry);
            }
        }
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function debug($message, $context = [])
    {
        self::write_log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function info($message, $context = [])
    {
        self::write_log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function warning($message, $context = [])
    {
        self::write_log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function error($message, $context = [])
    {
        self::write_log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanup_old_logs()
    {
        if (!self::$log_dir || !is_dir(self::$log_dir)) {
            return;
        }
        
        $cutoff_time = time() - (self::LOG_RETENTION_DAYS * 24 * 60 * 60);
        $files = glob(self::$log_dir . 'wcospa-*.log');
        
        if ($files) {
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    unlink($file);
                    self::info("Cleaned up old log file: " . basename($file));
                }
            }
        }
    }
    
    /**
     * Get log directory path
     * 
     * @return string Log directory path
     */
    public static function get_log_directory()
    {
        if (self::$log_dir === null) {
            self::setup_log_directory();
        }
        return self::$log_dir;
    }
    
    /**
     * Get current log file path
     * 
     * @return string Current log file path
     */
    public static function get_current_log_file()
    {
        if (self::$log_file === null) {
            self::setup_log_file();
        }
        return self::$log_file;
    }
    
    /**
     * Get log files for admin display
     * 
     * @return array Array of log file information
     */
    public static function get_log_files()
    {
        if (!self::$log_dir || !is_dir(self::$log_dir)) {
            return [];
        }
        
        $files = glob(self::$log_dir . 'wcospa-*.log');
        $log_files = [];
        
        if ($files) {
            foreach ($files as $file) {
                $log_files[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'readable' => is_readable($file)
                ];
            }
            
            // Sort by modification time, newest first
            usort($log_files, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
        }
        
        return $log_files;
    }
    
    /**
     * Read log file content
     * 
     * @param string $filename Log filename
     * @param int $lines Number of lines to read from end (default: 100)
     * @return string Log content
     */
    public static function read_log_file($filename, $lines = 100)
    {
        $file_path = self::$log_dir . $filename;
        
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return 'Log file not found or not readable.';
        }
        
        // Read last N lines efficiently
        $file = new SplFileObject($file_path);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        $content = '';
        
        $file->seek($start_line);
        while (!$file->eof()) {
            $content .= $file->current();
            $file->next();
        }
        
        return $content;
    }
} 