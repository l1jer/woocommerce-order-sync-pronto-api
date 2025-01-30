<?php
/**
 * Order Handler Class
 *
 * Handles the core functionality of order synchronisation with Pronto API.
 * This includes order status management, API sync scheduling, and metadata handling.
 *
 * @package    WooCommerce Order Sync Pronto API
 * @subpackage Handlers
 * @since      1.0.0
 */

// This file handles WooCommerce order processing and syncing with the API
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCOSPA_Order_Handler Class
 *
 * Manages WooCommerce order processing and synchronisation with Pronto API.
 *
 * @since 1.0.0
 */
class WCOSPA_Order_Handler
{
    /**
     * Initialise the order handler.
     *
     * Sets up all the necessary hooks for order processing.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init()
    {
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_sync'], 10, 1);
        add_action('wcospa_process_pending_orders', [__CLASS__, 'process_pending_orders'], 10);
    }

    /**
     * Handle order synchronisation with Pronto API.
     *
     * Processes a single order and updates its status based on the API response.
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    public static function handle_order_sync($order_id)
    {
        $response = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($response)) {
            error_log('Order sync failed: ' . $response->get_error_message());
        } else {
            $order = wc_get_order($order_id);
            $order->update_status('wc-pronto-received', 'Order marked as Pronto Received after successful API sync.');
            
            update_post_meta($order_id, '_wcospa_transaction_uuid', $response);
            update_post_meta($order_id, '_wcospa_sync_time', time());
            
            error_log('Order ' . $order_id . ' updated to Pronto Received by API sync.');
        }
    }

    /**
     * Process pending orders that need Pronto order number fetch.
     *
     * Queries and processes orders that have been synced but don't have a Pronto order number yet.
     *
     * @since 1.0.0
     * @return void
     */
    public static function process_pending_orders()
    {
        global $wpdb;

        $pending_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} pm1
                JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                WHERE pm1.meta_key = '_wcospa_sync_time'
                AND pm1.meta_value < %d
                AND pm2.meta_key = '_wcospa_transaction_uuid'
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm3
                    WHERE pm3.post_id = pm1.post_id
                    AND pm3.meta_key = '_wcospa_pronto_order_number'
                )
                LIMIT 1",
                time() - 120
            )
        );

        if (empty($pending_orders)) {
            return;
        }

        $order_id = $pending_orders[0]->post_id;
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);

        if (empty($transaction_uuid)) {
            return;
        }

        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        if (!is_wp_error($pronto_order_number) && !empty($pronto_order_number)) {
            update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
            error_log('Successfully fetched Pronto Order Number: ' . $pronto_order_number . ' for order: ' . $order_id);
        }
    }
}

// Register custom order status
function register_pronto_received_order_status()
{
    register_post_status('wc-pronto-received', [
        'label' => 'Pronto Received',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Pronto Received <span class="count">(%s)</span>', 'Pronto Received <span class="count">(%s)</span>'),
    ]);
}
add_action('init', 'register_pronto_received_order_status');

// Add status to order statuses list
function add_pronto_received_to_order_statuses($order_statuses)
{
    $new_order_statuses = [];
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-pronto-received'] = 'Pronto Received';
        }
    }
    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'add_pronto_received_to_order_statuses');

function wc_custom_order_status_styles()
{
    echo '<style>
        .status-pronto-received {
            background-color: orange !important;
            color: white !important;
        }
    </style>';
}
add_action('admin_head', 'wc_custom_order_status_styles');

// Register custom cron interval
function register_three_second_interval($schedules)
{
    $schedules['every_three_seconds'] = array(
        'interval' => 3,
        'display' => __('Every Three Seconds')
    );
    return $schedules;
}
add_filter('cron_schedules', [__CLASS__, 'register_three_second_interval']);