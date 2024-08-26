<?php
// This file adds a sync button to the WooCommerce orders list and handles its AJAX actions

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Order_Sync_Button {

    public static function init() {
        add_action('woocommerce_admin_order_actions_end', [__CLASS__, 'add_sync_button']);
        add_action('admin_footer', [__CLASS__, 'enqueue_sync_button_script']);
        add_action('wp_ajax_wcospa_sync_order', [__CLASS__, 'handle_ajax_sync']);
    }

    public static function add_sync_button($order) {
        wp_nonce_field('wcospa_sync_order', 'wcospa_sync_nonce');
        echo '<button class="button wc-action-button wc-action-button-sync sync-order-button" data-order-id="' . $order->get_id() . '" title="' . esc_attr__('Sync to API', 'wcospa') . '">Sync</button>';
    }

    public static function enqueue_sync_button_script() {
        wp_enqueue_script('wcospa-sync-button', WCOSPA_URL . 'assets/js/wcospa-sync-button.js', ['jquery'], WCOSPA_VERSION, true);
    }

    public static function handle_ajax_sync() {
        check_ajax_referer('wcospa_sync_order', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $response = WCOSPA_API_Client::send_order($order_id);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        wp_send_json_success('Order synced successfully.');
    }
}