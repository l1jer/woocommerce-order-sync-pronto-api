<?php
// This file creates the Sync Status admin page and manages its functionality

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Admin_Sync_Status
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_sync_status_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wcospa_clear_all_sync_data', [__CLASS__, 'clear_all_sync_data']); // Keep "Clear All Sync Data" functionality
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
        wp_enqueue_script('wcospa-admin', WCOSPA_URL.'assets/js/wcospa-admin.js', [], WCOSPA_VERSION, true);
    }

    // Keep the method to clear all sync data
    public static function clear_all_sync_data()
    {
        $orders = get_posts([
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        foreach ($orders as $order_id) {
            delete_post_meta($order_id, '_wcospa_transaction_uuid');
            delete_post_meta($order_id, '_wcospa_pronto_order_number');
        }

        wp_send_json_success(__('All sync data "_wcospa_transaction_uuid" & "_wcospa_pronto_order_number" has been cleared.', 'wcospa'));
    }
}

WCOSPA_Admin_Sync_Status::init();
