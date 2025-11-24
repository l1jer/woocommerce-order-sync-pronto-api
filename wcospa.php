<?php
/**
 * Plugin Name: WooCommerce Order Sync Pronto API
 * Description: A comprehensive WooCommerce integration with Pronto API that handles order synchronization, shipment tracking, and status management. Features include automatic order syncing upon processing, manual sync capability, Pronto order number retrieval, shipment tracking integration with Advanced Shipment Tracking, custom order statuses, detailed sync logging, and admin interface enhancements. The plugin ensures reliable data synchronization with features like retry mechanisms, weekend processing control, and timeout alerts. Includes a dedicated sync status page, order column enhancements, and robust error handling.
 * Version: 1.6.9b
 * Author: Jerry Li
 * Text Domain: wcospa
 * Requires at least: 5.0
 * Tested up to: 6.7.2
 * Requires PHP: 8.2
 * License: GPLv2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants after WordPress loads
add_action('plugins_loaded', function() {
    // Define plugin constants
    if (!defined('WCOSPA_PATH')) {
        define('WCOSPA_PATH', plugin_dir_path(__FILE__));
    }
    if (!defined('WCOSPA_URL')) {
        define('WCOSPA_URL', plugin_dir_url(__FILE__));
    }
    if (!defined('WCOSPA_VERSION')) {
        define('WCOSPA_VERSION', '1.6.9b');
    }
});

/**
 * Initialise plugin functionality
 */
function wcospa_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('WCOSPA requires WooCommerce to be installed and activated.', 'wcospa'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Include required files
    require_once WCOSPA_PATH . 'includes/class-wcospa-logger.php';
    require_once WCOSPA_PATH . 'includes/class-wcospa-utils.php';
    require_once WCOSPA_PATH . 'includes/class-wcospa-queue-handler.php';
    require_once WCOSPA_PATH . 'includes/class-wcospa-order-handler.php';
    require_once WCOSPA_PATH . 'includes/class-wcospa-api-client.php';
    require_once WCOSPA_PATH . 'includes/class-wcospa-admin-sync-status.php';
    require_once WCOSPA_PATH . 'includes/class-wcospa-shipment-handler.php';
    require_once WCOSPA_PATH . 'includes/wcospa-credentials.php';

    // Initialize plugin components
    WCOSPA_Logger::init();
    WCOSPA_Order_Handler::init();
    WCOSPA_Order_Sync_Button::init();
    WCOSPA_Admin_Sync_Status::init();
    WCOSPA_Admin_Orders_Column::init();
    WCOSPA_Shipment_Handler::init();

    // Add queue processing to admin-ajax.php
    add_action('admin_init', function() {
        if (wp_doing_ajax()) {
            WCOSPA_Utils::start_queue_processing();
        }
    });
}

// Hook into WordPress init
add_action('plugins_loaded', 'wcospa_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'wcospa_activate');
function wcospa_activate() {
    // Check WooCommerce dependency
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('This plugin requires WooCommerce to be installed and activated.', 'wcospa'),
            'Plugin dependency check',
            ['back_link' => true]
        );
    }
    
    // Initialize components that need activation
    require_once plugin_dir_path(__FILE__) . 'includes/class-wcospa-logger.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wcospa-order-handler.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wcospa-shipment-handler.php';
    
    WCOSPA_Logger::init();
    WCOSPA_Order_Handler::activate();
    WCOSPA_Shipment_Handler::activate();
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'wcospa_deactivate');
function wcospa_deactivate() {
    // Include required files for deactivation
    require_once plugin_dir_path(__FILE__) . 'includes/class-wcospa-logger.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wcospa-order-handler.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wcospa-shipment-handler.php';
    
    WCOSPA_Order_Handler::deactivate();
    WCOSPA_Shipment_Handler::deactivate();
}
