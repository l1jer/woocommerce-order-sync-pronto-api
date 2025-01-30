<?php
/**
 * Admin Interface Class
 *
 * Handles all admin-related functionality including order list modifications,
 * AJAX handlers, and admin UI elements for the Pronto sync feature.
 *
 * @package    WooCommerce Order Sync Pronto API
 * @subpackage Admin
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCOSPA_Admin Class
 *
 * Manages the WordPress admin interface modifications for Pronto sync functionality.
 *
 * @since 1.0.0
 */
class WCOSPA_Admin
{
    /**
     * Initialise the admin functionality.
     *
     * Sets up all necessary admin hooks and filters.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init()
    {
        // Initialize both admin features
        self::init_sync_button();
        self::init_orders_column();
        
        // Add admin styles
        add_action('admin_head', [__CLASS__, 'add_admin_styles']);
    }

    /**
     * Initialise sync button functionality.
     *
     * Sets up the manual sync button in the order admin interface.
     *
     * @since 1.0.0
     * @return void
     */
    private static function init_sync_button()
    {
        add_action('admin_footer', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_ajax_wcospa_sync_order', [__CLASS__, 'handle_ajax_sync']);
        add_action('wp_ajax_wcospa_fetch_pronto_order', [__CLASS__, 'handle_ajax_fetch']);
    }

    /**
     * Initialise orders column functionality.
     *
     * Adds and manages the Pronto order number column in the orders list.
     *
     * @since 1.0.0
     * @return void
     */
    private static function init_orders_column()
    {
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_order_column']);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'display_order_column'], 10, 2);
    }

    public static function enqueue_admin_assets()
    {
        wp_enqueue_script('wcospa-admin', WCOSPA_URL . 'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL . 'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
    }

    public static function handle_ajax_sync()
    {
        check_ajax_referer('wcospa_sync_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
        
        if (!empty($transaction_uuid)) {
            wp_send_json_error('This order has already been synchronised with Pronto.');
        }

        $uuid = WCOSPA_API_Client::sync_order($order_id);

        if (is_wp_error($uuid)) {
            wp_send_json_error($uuid->get_error_message());
        }

        update_post_meta($order_id, '_wcospa_transaction_uuid', $uuid);
        wp_send_json_success(['uuid' => $uuid, 'sync_time' => time()]);
    }

    public static function handle_ajax_fetch()
    {
        check_ajax_referer('wcospa_fetch_order_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
        }

        $order_id = intval($_POST['order_id']);
        $pronto_order_number = WCOSPA_API_Client::fetch_order_status($order_id);

        if (is_wp_error($pronto_order_number)) {
            wp_send_json_error($pronto_order_number->get_error_message());
        }

        update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);
        wp_send_json_success(['pronto_order_number' => $pronto_order_number]);
    }

    public static function add_order_column($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['pronto_order_number'] = __('Pronto Order', 'wcospa');
            }
        }
        return $new_columns;
    }

    public static function display_order_column($column, $post_id)
    {
        if ($column === 'pronto_order_number') {
            $pronto_order_number = get_post_meta($post_id, '_wcospa_pronto_order_number', true);
            $transaction_uuid = get_post_meta($post_id, '_wcospa_transaction_uuid', true);

            echo '<div class="wcospa-order-column">';
            if ($pronto_order_number) {
                echo '<div class="pronto-order-number">' . esc_html($pronto_order_number) . '</div>';
            } elseif ($transaction_uuid) {
                echo '<div class="pronto-order-number">Awaiting Pronto Order Number...</div>';
            } else {
                echo '<div class="pronto-order-number">Not synced</div>';
            }
            echo '</div>';
        }
    }

    public static function add_admin_styles()
    {
        echo '<style>
            .status-pronto-received {
                background-color: orange !important;
                color: white !important;
            }
        </style>';
    }
} 