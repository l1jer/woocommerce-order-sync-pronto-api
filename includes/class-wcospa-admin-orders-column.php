<?php
// This file adds a custom column to display the Pronto Order number in the WooCommerce Orders admin page

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Admin_Orders_Column {

    public static function init() {
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_pronto_order_column']);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'display_pronto_order_column'], 10, 2);
    }

    public static function add_pronto_order_column($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['pronto_order_number'] = __('Pronto Order No.', 'wcospa');
            }
        }

        return $new_columns;
    }

    public static function display_pronto_order_column($column, $post_id) {
        if ($column === 'pronto_order_number') {
            $pronto_order_number = get_post_meta($post_id, '_wcospa_pronto_order_number', true);
            $transaction_uuid = get_post_meta($post_id, '_wcospa_transaction_uuid', true);

            if ($pronto_order_number) {
                echo esc_html($pronto_order_number);
            } elseif ($transaction_uuid) {
                echo __('Pending', 'wcospa');
            } else {
                echo '-';
            }
        }
    }
}

WCOSPA_Admin_Orders_Column::init();