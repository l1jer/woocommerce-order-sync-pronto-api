<?php
/**
 * WooCommerce Order Sync with Pronto API
 *
 * This plugin enables automatic synchronisation of WooCommerce orders with the Pronto API.
 * It handles order status updates, data formatting, and provides admin interface for manual syncing.
 *
 * @package     WooCommerce Order Sync Pronto API
 * @author      Jerry Li
 * @copyright   2024 Jerry Li
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: WooCommerce Order Sync Pronto API
 * Description: Automatically syncs WooCommerce orders with the Pronto API upon successful processing. Includes a manual sync button in the WooCommerce admin order actions.
 * Version:     1.4.4
 * Author:      Jerry Li
 * Text Domain: wcospa
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
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
