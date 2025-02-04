<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_INT
 * 
 * Main loader class for INT functionality
 */
class WCOSPA_INT {
    /**
     * @var WCOSPA_INT_Email_Handler
     */
    private $email_handler;

    /**
     * Constructor
     */
    public function __construct() {
        error_log('WCOSPA INT: Initializing INT functionality');
        $this->init();
    }

    /**
     * Initialize the INT functionality
     */
    private function init(): void {
        try {
            // Load dependencies
            $handler_file = plugin_dir_path(__FILE__) . 'class-wcospa-int-email-handler.php';
            if (!file_exists($handler_file)) {
                error_log('WCOSPA INT: Email handler file not found at: ' . $handler_file);
                return;
            }
            
            require_once $handler_file;
            error_log('WCOSPA INT: Email handler file loaded successfully');

            // Initialize components
            $this->email_handler = new WCOSPA_INT_Email_Handler();
            error_log('WCOSPA INT: Email handler initialized successfully');
        } catch (Exception $e) {
            error_log('WCOSPA INT: Error initializing INT functionality: ' . $e->getMessage());
        }
    }
} 