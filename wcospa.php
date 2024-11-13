<?php
/*
Plugin Name: WooCommerce Order Sync Pronto API
Description: Automatically syncs WooCommerce orders with the Pronto API upon successful processing. Includes a manual sync button in the WooCommerce admin order actions.
Version: 1.4.1
Author: Jerry Li
Text Domain: wcospa
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WCOSPA_PATH', plugin_dir_path(__FILE__));
define('WCOSPA_URL', plugin_dir_url(__FILE__));
define('WCOSPA_VERSION', '1.4.0');

// Include required files
require_once WCOSPA_PATH.'includes/class-wcospa-order-handler.php';
require_once WCOSPA_PATH.'includes/class-wcospa-api-client.php';
require_once WCOSPA_PATH.'includes/class-wcospa-admin-sync-status.php';
require_once WCOSPA_PATH.'includes/wcospa-credentials.php';

// Initialize the plugin
function wcospa_init()
{
    WCOSPA_Order_Handler::init();
    WCOSPA_Order_Sync_Button::init();
    WCOSPA_Admin_Sync_Status::init();
    WCOSPA_Admin_Orders_Column::init();
}
add_action('plugins_loaded', 'wcospa_init');
