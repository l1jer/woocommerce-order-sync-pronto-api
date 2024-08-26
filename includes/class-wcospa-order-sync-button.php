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
        $status = $order->get_status();
        $disabled = ($status === 'on-hold' || $status === 'cancelled') ? 'disabled' : '';
        $tooltip = ($status === 'on-hold' || $status === 'cancelled') ? 'title="' . esc_attr__('Unable to sync Cancelled and On-hold orders', 'wcospa') . '"' : '';
        $button_class = ($disabled) ? 'wcospa-disabled-button' : 'sync-order-button';

        wp_nonce_field('wcospa_sync_order', 'wcospa_sync_nonce');
        echo '<button class="button wc-action-button wc-action-button-sync ' . esc_attr($button_class) . '" data-order-id="' . esc_attr($order->get_id()) . '" ' . esc_attr($disabled) . ' ' . esc_attr($tooltip) . '>' . esc_html__('Sync', 'wcospa') . '</button>';
    }

    public static function enqueue_sync_button_script() {
        wp_enqueue_script('wcospa-sync-button', WCOSPA_URL . 'assets/js/wcospa-sync-button.js', ['jquery'], WCOSPA_VERSION, true);
        wp_add_inline_style('wcospa-sync-button', self::get_button_css());
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

    private static function get_button_css() {
        return "
            .wcospa-disabled-button {
                background-color: #ccc !important;
                color: #999 !important;
                cursor: not-allowed !important;
                pointer-events: none;
            }
            .wcospa-disabled-button:hover {
                cursor: not-allowed;
            }
        ";
    }
}