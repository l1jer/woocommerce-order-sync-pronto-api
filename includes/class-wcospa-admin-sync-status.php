<?php
// This file creates the Sync Status admin page and manages its functionality

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Admin_Sync_Status {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_sync_status_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wcospa_clear_sync_logs', [__CLASS__, 'clear_sync_logs']);
        add_action('wp_ajax_wcospa_clear_all_sync_data', [__CLASS__, 'clear_all_sync_data']); // New action
    }

    public static function add_sync_status_menu() {
        add_submenu_page(
            'woocommerce',
            __('Order Sync Status', 'wcospa'),
            __('Sync Status', 'wcospa'),
            'manage_woocommerce',
            'wcospa-sync-status',
            [__CLASS__, 'render_sync_status_page']
        );
    }

    public static function render_sync_status_page() {
        $logs = WCOSPA_Logger::get_sync_logs();
        ?>
<div class="wrap">
    <h1><?php _e('WooCommerce Order Sync Pronto API - Order Status', 'wcospa'); ?></h1>
    <button id="wcospa-clear-logs" class="button button-large"><?php _e('Clear Sync Records', 'wcospa'); ?></button>
    <button id="wcospa-clear-all-sync-data" class="button button-large"><?php _e('Clear All Sync Data', 'wcospa'); ?></button>

    <p><?php _e('Click "Clear Sync Records" to delete all sync logs, or "Clear All Sync Data" to reset sync statuses for all orders.', 'wcospa'); ?></p>
    <textarea readonly rows="20" style="width: 100%;"><?php echo esc_textarea(self::format_logs($logs)); ?></textarea>
</div>
<?php
    }

    public static function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_wcospa-sync-status') {
            return;
        }
        wp_enqueue_script('jquery'); // Ensure jQuery is enqueued
        wp_enqueue_script('wcospa-sync-status', WCOSPA_URL . 'assets/js/wcospa-admin-sync-status.js', ['jquery'], WCOSPA_VERSION, true);

        // Ensure ajaxurl is defined for non-admin pages
        // wp_localize_script('wcospa-sync-status', 'ajaxurl', admin_url('admin-ajax.php'));
    }


    public static function clear_sync_logs() {
        WCOSPA_Logger::clear_sync_logs();
        wp_send_json_success();
    }

    // New method to clear all sync data
    public static function clear_all_sync_data() {
        // Get all WooCommerce orders
        $orders = get_posts([
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        // Clear sync-related data for each order
        foreach ($orders as $order_id) {
            delete_post_meta($order_id, '_wcospa_transaction_uuid');
            delete_post_meta($order_id, '_wcospa_pronto_order_number');
            delete_post_meta($order_id, '_wcospa_already_synced');
            delete_post_meta($order_id, '_wcospa_check_attempts');
        }

        // Return a success response
        wp_send_json_success(__('All sync data has been cleared.', 'wcospa'));
    }


    private static function format_logs($logs) {
        if (empty($logs)) {
            return __('No sync records found.', 'wcospa');
        }

        $output = '';
        foreach ($logs as $log) {
            $output .= sprintf(
                __('Order #%d | %s | %s | %s | %s | $%s | Synced on %s | Pronto Order: %s', 'wcospa'),
                $log['order_id'],
                $log['order_date'],
                $log['name'],
                $log['email'],
                $log['delivery_address'],
                $log['cost'],
                $log['sync_date'],
                $log['pronto_order_number']
            );
            $output .= "\n";
        }

        return $output;
    }
}

WCOSPA_Admin_Sync_Status::init();