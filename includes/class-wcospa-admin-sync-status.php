<?php
/**
 * Admin Sync Status functionality
 *
 * @package WCOSPA
 * @version 1.4.9
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_Admin_Sync_Status
 * Handles the sync status admin page functionality
 */
class WCOSPA_Admin_Sync_Status
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_sync_status_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wcospa_clear_all_sync_data', [__CLASS__, 'clear_all_sync_data']);
    }

    public static function add_sync_status_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Order Sync Status', 'wcospa'),
            __('Sync Status', 'wcospa'),
            'manage_woocommerce',
            'wcospa-sync-status',
            [__CLASS__, 'render_sync_status_page']
        );
    }

    public static function render_sync_status_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Order Sync Pronto API - Order Status', 'wcospa'); ?></h1>
            <button id="wcospa-clear-all-sync-data" class="button button-large"><?php _e('Clear All Sync Data', 'wcospa'); ?></button>
            <p><?php _e('Click "Clear All Sync Data" to reset sync statuses for all orders.', 'wcospa'); ?></p>

        </div>
        <?php
    }

    public static function enqueue_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_wcospa-sync-status') {
            return;
        }
        
        // Add nonce for AJAX security
        wp_enqueue_script('wcospa-admin', WCOSPA_URL.'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
        wp_localize_script('wcospa-admin', 'wcospaAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcospa_admin_nonce')
        ]);
    }

    public static function clear_all_sync_data()
    {
        // Verify nonce for security
        check_ajax_referer('wcospa_admin_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wcospa'));
        }

        // Use WC_Order_Query instead of get_posts for better compatibility
        try {
            $query = new WC_Order_Query([
                'limit' => -1,
                'return' => 'ids',
                'type' => 'shop_order', // Explicitly set the order type
            ]);
            
            $orders = $query->get_orders();

            if (empty($orders)) {
                wp_send_json_success(__('No orders found to clear.', 'wcospa'));
                return;
            }

            foreach ($orders as $order_id) {
                delete_post_meta($order_id, '_wcospa_transaction_uuid');
                delete_post_meta($order_id, '_wcospa_pronto_order_number');
            }

            wp_send_json_success(sprintf(
                /* translators: %d: number of orders processed */
                __('Sync data cleared for %d orders.', 'wcospa'),
                count($orders)
            ));

        } catch (Exception $e) {
            wc_get_logger()->error(
                'Error clearing sync data: ' . $e->getMessage(),
                ['source' => 'wcospa']
            );
            wp_send_json_error(__('An error occurred while clearing sync data.', 'wcospa'));
        }
    }
}

// Initialize the class if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    WCOSPA_Admin_Sync_Status::init();
}
