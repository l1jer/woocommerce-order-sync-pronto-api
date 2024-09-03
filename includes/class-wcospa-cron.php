<?php
// This file sets up a custom cron job to check the status of synced orders and update the Pronto Order number when complete

// if (!defined('ABSPATH')) {
//     exit;
// }

// class WCOSPA_Cron {

//     public static function init() {
//         add_action('wcospa_check_pending_orders_event', [__CLASS__, 'check_pending_orders']);
//         add_filter('cron_schedules', [__CLASS__, 'add_custom_cron_schedule']);
//     }

//     public static function add_custom_cron_schedule($schedules) {
//         // Add a custom schedule for every minute
//         $schedules['one_minute'] = [
//             'interval' => 60,
//             'display' => __('Every Minute', 'wcospa')
//         ];
//         return $schedules;
//     }

//     public static function schedule_event() {
//         if (!wp_next_scheduled('wcospa_check_pending_orders_event')) {
//             wp_schedule_event(time(), 'one_minute', 'wcospa_check_pending_orders_event');
//         }
//     }

//     public static function check_pending_orders() {
//         $args = [
//             'post_type' => 'shop_order',
//             'post_status' => 'wc-processing',
//             'meta_query' => [
//                 [
//                     'key' => '_wcospa_transaction_uuid',
//                     'compare' => 'EXISTS',
//                 ],
//                 [
//                     'key' => '_wcospa_pronto_order_number',
//                     'compare' => 'NOT EXISTS',
//                 ],
//                 [
//                     'key' => '_wcospa_check_attempts',
//                     'value' => 10,
//                     'type' => 'NUMERIC',
//                     'compare' => '<',
//                 ],
//             ],
//             'fields' => 'ids',
//         ];

//         $orders = get_posts($args);

//         foreach ($orders as $order_id) {
//             $uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
//             $check_attempts = get_post_meta($order_id, '_wcospa_check_attempts', true) ?: 0;

//             $pronto_order_number = WCOSPA_API_Client::get_pronto_order_number($uuid);

//             if ($pronto_order_number) {
//                 update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
//                 update_post_meta($order_id, '_wcospa_already_synced', true);
//             } else {
//                 $check_attempts++;
//                 update_post_meta($order_id, '_wcospa_check_attempts', $check_attempts);

//                 if ($check_attempts >= 10) {
//                     delete_post_meta($order_id, '_wcospa_transaction_uuid');
//                 }
//             }
//         }
//     }
// }

// // Initialize the cron system
// WCOSPA_Cron::init();