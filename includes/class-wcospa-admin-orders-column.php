<?php
// This file manages the WooCommerce Orders column for displaying the Pronto Order number and buttons

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Admin_Orders_Column {

    public static function init() {
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_pronto_order_column']);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'display_pronto_order_column'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_styles_and_scripts']);
    }

    public static function add_pronto_order_column($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['pronto_order_number'] = __('Pronto Order', 'wcospa');
            }
        }

        return $new_columns;
    }

    public static function display_pronto_order_column($column, $post_id) {
        if ($column === 'pronto_order_number') {
            $pronto_order_number = get_post_meta($post_id, '_wcospa_pronto_order_number', true);
            $transaction_uuid = get_post_meta($post_id, '_wcospa_transaction_uuid', true);
            $sync_time = get_post_meta($post_id, '_wcospa_sync_time', true);

            $sync_button_text = 'Sync';
            $sync_disabled = false;
            $fetch_button_text = 'Fetch';
            $fetch_disabled = true;

            if ($pronto_order_number) {
                $sync_button_text = 'Already Synced';
                $sync_disabled = true;
                $fetch_button_text = 'Fetched';
                $fetch_disabled = true;
            } elseif ($transaction_uuid) {
                if (time() - $sync_time < 120) {
                    $remaining_time = 120 - (time() - $sync_time);
                    $fetch_button_text = "Fetch in {$remaining_time}s";
                } else {
                    $fetch_button_text = 'Fetch';
                    $fetch_disabled = false;
                }
            }

            echo '<div class="wcospa-order-column">';
            echo '<div class="wcospa-sync-fetch-buttons" style="display: flex; justify-content: flex-end; width: 100%;">';

            echo '<button class="button wc-action-button wc-action-button-sync sync-order-button"
                      data-order-id="' . esc_attr($post_id) . '"
                      data-nonce="' . esc_attr(wp_create_nonce('wcospa_sync_order_nonce')) . '"
                      ' . disabled($sync_disabled, true, false) . '>' . esc_html($sync_button_text) . '</button>';

            echo '<button class="button wc-action-button wc-action-button-fetch fetch-order-button"
                      data-order-id="' . esc_attr($post_id) . '"
                      data-nonce="' . esc_attr(wp_create_nonce('wcospa_fetch_order_nonce')) . '"
                      ' . disabled($fetch_disabled, true, false) . '>' . esc_html($fetch_button_text) . '</button>';

            if ($pronto_order_number) {
                echo '<div class="pronto-order-number">' . esc_html($pronto_order_number) . '</div>';
            } else {
                echo '<div class="pronto-order-number">-</div>';
            }

            echo '</div>';  // Close the sync-fetch-buttons div
            echo '</div>';  // Close the order-column div
        }
    }

    public static function enqueue_admin_styles_and_scripts() {
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL . 'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
        wp_enqueue_script('wcospa-sync-button', WCOSPA_URL . 'assets/js/wcospa-sync-button.js', [], WCOSPA_VERSION, true);
    }
}

WCOSPA_Admin_Orders_Column::init();