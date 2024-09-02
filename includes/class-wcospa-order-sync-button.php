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
        $order_id = $order->get_id();
        $order_status = $order->get_status();
        $already_synced = get_post_meta($order_id, '_wcospa_already_synced', true);
        $pronto_order_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);

        // Determine button state and tooltip
        $disabled = false;
        $tooltip = '';
        $button_text = 'Sync';

        if ($already_synced || $pronto_order_number) {
            $disabled = true;
            $tooltip = __('Already Synced', 'wcospa');
            $button_text = __('Already Synced', 'wcospa');
        } elseif (!in_array($order_status, ['processing', 'completed'])) {
            $disabled = true;
            $tooltip = __('Unable to sync Cancelled and On-hold orders', 'wcospa');
            $button_text = __('Unable to Sync', 'wcospa');
        }

        $disabled_class = $disabled ? 'disabled' : '';
        $disabled_attr = $disabled ? 'disabled="disabled"' : '';

        echo '<button class="button wc-action-button wc-action-button-sync sync-order-button ' . esc_attr($disabled_class) . '"
                  data-order-id="' . esc_attr($order_id) . '"
                  data-nonce="' . esc_attr(wp_create_nonce('wcospa_sync_order_nonce')) . '"
                  title="' . esc_attr($tooltip) . '"
                  ' . esc_attr($disabled_attr) . '>' . esc_html($button_text) . '</button>';
    }

    public static function enqueue_sync_button_script() {
        wp_enqueue_script('wcospa-sync-button', WCOSPA_URL . 'assets/js/wcospa-sync-button.js', ['jquery'], WCOSPA_VERSION, true);
    }

    public static function handle_ajax_sync() {
        check_ajax_referer('wcospa_sync_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $already_synced = get_post_meta($order_id, '_wcospa_already_synced', true);

        if ($already_synced) {
            wp_send_json_error('This order has already been synced.');
        }

        // Sync the order and get the transaction UUID
        $uuid = WCOSPA_API_Client::send_order($order_id);

        if (is_wp_error($uuid)) {
            wp_send_json_error($uuid->get_error_message());
        }

        // Store the UUID with the order
        update_post_meta($order_id, '_wcospa_transaction_uuid', $uuid);

        // Schedule the custom cron event to start checking order status
        WCOSPA_Cron::schedule_event();

        // Mark the order as already synced and return success
        update_post_meta($order_id, '_wcospa_already_synced', true);

        wp_send_json_success('Order synced successfully. Transaction UUID: ' . $uuid);
    }
}