<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_INT_Extension
 * 
 * Main loader class for the INT extension functionality
 */
class WCOSPA_INT_Extension {
    /**
     * @var WCOSPA_INT_Dealer_Config
     */
    private WCOSPA_INT_Dealer_Config $dealer_config;

    /**
     * @var WCOSPA_INT_Email_Handler
     */
    private WCOSPA_INT_Email_Handler $email_handler;

    /**
     * @var WCOSPA_INT_Timer_Handler
     */
    private WCOSPA_INT_Timer_Handler $timer_handler;

    /**
     * @var bool Whether the extension is initialized
     */
    private static bool $initialized = false;

    /**
     * Constructor
     */
    public function __construct() {
        // Add initialization hook with high priority to ensure WooCommerce is loaded
        add_action('plugins_loaded', [$this, 'init'], 99);
    }

    /**
     * Initialize the INT extension
     */
    public function init(): void {
        if (self::$initialized) {
            return;
        }

        if (!class_exists('WooCommerce')) {
            return;
        }

        // Load required files
        $this->load_dependencies();

        // Initialize components
        $this->dealer_config = new WCOSPA_INT_Dealer_Config();
        $this->email_handler = new WCOSPA_INT_Email_Handler();
        $this->timer_handler = new WCOSPA_INT_Timer_Handler();

        // Add hooks
        $this->add_hooks();

        self::$initialized = true;
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        require_once plugin_dir_path(__FILE__) . 'class-wcospa-int-dealer-config.php';
        require_once plugin_dir_path(__FILE__) . 'class-wcospa-int-email-handler.php';
        require_once plugin_dir_path(__FILE__) . 'class-wcospa-int-timer-handler.php';
    }

    /**
     * Add WordPress hooks
     */
    private function add_hooks(): void {
        // Register custom order statuses early
        add_action('init', [$this, 'register_order_status']);
        add_filter('wc_order_statuses', [$this, 'add_order_status']);

        // Add early filter to prevent Pronto sync for international orders
        add_filter('wcospa_should_process_order', [$this, 'prevent_auto_pronto_sync'], 5, 2);
        
        // Handle order status transitions
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);

        // Handle dealer response actions
        add_action('init', [$this, 'handle_dealer_response']);

        // Add debug column to orders list
        add_filter('manage_edit-shop_order_columns', [$this, 'add_order_list_columns']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_order_list_columns']);

        // Handle order shipping status
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_shipped']);

        // Add early action to prevent Pronto sync
        add_action('woocommerce_checkout_order_processed', [$this, 'check_international_order'], 5, 1);
    }

    /**
     * Handle order status changes
     */
    public function handle_order_status_change(int $order_id, string $old_status, string $new_status, WC_Order $order): void {
        $this->log_debug(sprintf(
            'Order #%d status change: %s -> %s',
            $order_id,
            $old_status,
            $new_status
        ));

        // Only handle processing status
        if ($new_status !== 'processing') {
            return;
        }

        // Check if this is an international order
        $shipping_country = $order->get_shipping_country();
        if ($shipping_country === 'AU') {
            $this->log_debug(sprintf('Order #%d is domestic (AU), skipping INT handling', $order_id));
            return;
        }

        $this->log_debug(sprintf('Processing international order #%d for country: %s', $order_id, $shipping_country));

        // Prevent order from being processed by Pronto sync
        update_post_meta($order_id, '_wcospa_prevent_pronto_sync', 'yes');

        // Send dealer notification
        $sent = $this->email_handler->send_dealer_notification($order_id);

        if ($sent) {
            $this->log_debug(sprintf('Successfully sent dealer notification for order #%d', $order_id));
            // Update order status to "Await Dealer Decision"
            $order->update_status('await-dealer', __('Awaiting dealer decision.', 'wcospa'));
        } else {
            $this->log_debug(sprintf('Failed to send dealer notification for order #%d', $order_id));
            // Handle email failure
            $this->handle_email_failure($order);
        }
    }

    /**
     * Register custom order status
     */
    public function register_order_status(): void {
        register_post_status('wc-await-dealer', [
            'label' => __('Await Dealer Decision', 'wcospa'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Await Dealer Decision <span class="count">(%s)</span>',
                                   'Await Dealer Decision <span class="count">(%s)</span>')
        ]);

        register_post_status('wc-dealer-accept', [
            'label' => __('Dealer Accept', 'wcospa'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Dealer Accept <span class="count">(%s)</span>',
                                   'Dealer Accept <span class="count">(%s)</span>')
        ]);
    }

    /**
     * Add custom order status to WooCommerce statuses
     *
     * @param array $order_statuses Existing order statuses
     * @return array Modified order statuses
     */
    public function add_order_status(array $order_statuses): array {
        $new_statuses = [
            'wc-await-dealer' => __('Await Dealer Decision', 'wcospa'),
            'wc-dealer-accept' => __('Dealer Accept', 'wcospa')
        ];

        return array_merge($order_statuses, $new_statuses);
    }

    /**
     * Check if order is international and set prevention flag early
     */
    public function check_international_order(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $shipping_country = $order->get_shipping_country();
        if ($shipping_country !== 'AU') {
            update_post_meta($order_id, '_wcospa_prevent_pronto_sync', 'yes');
            update_post_meta($order_id, '_wcospa_is_international', 'yes');
        }
    }

    /**
     * Prevent automatic progression from "Processing" to "Pronto Received"
     */
    public function prevent_auto_pronto_sync(bool $should_process, WC_Order $order): bool {
        // Early check for international orders
        if (get_post_meta($order->get_id(), '_wcospa_is_international', true) === 'yes') {
            return false;
        }

        // Check shipping country
        $shipping_country = $order->get_shipping_country();
        if ($shipping_country !== 'AU') {
            update_post_meta($order->get_id(), '_wcospa_prevent_pronto_sync', 'yes');
            update_post_meta($order->get_id(), '_wcospa_is_international', 'yes');
            return false;
        }

        // Check if explicitly set to prevent sync
        if (get_post_meta($order->get_id(), '_wcospa_prevent_pronto_sync', true) === 'yes') {
            return false;
        }

        return $should_process;
    }

    /**
     * Handle dealer response to order
     */
    public function handle_dealer_response(): void {
        if (!isset($_GET['action'], $_GET['order_id'], $_GET['nonce'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $order_id = (int) $_GET['order_id'];
        $nonce = sanitize_text_field($_GET['nonce']);

        // Verify nonce
        if (!wp_verify_nonce($nonce, "wcospa_int_{$action}_{$order_id}")) {
            wp_die(__('Invalid request.', 'wcospa'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'wcospa'));
        }

        $this->log_debug(sprintf('Processing dealer response for order #%d: %s', $order_id, $action));

        switch ($action) {
            case 'wcospa_int_accept_order':
                $order->update_status('dealer-accept', __('Order accepted by dealer.', 'wcospa'));
                $order->update_meta_data('_wcospa_int_dealer_response', 'accepted');
                $order->save();

                // Start shipping timer
                $this->timer_handler->start_shipping_timer($order_id);

                $this->log_debug(sprintf('Order #%d accepted by dealer', $order_id));
                wp_safe_redirect(home_url('/order-accepted'));
                exit;

            case 'wcospa_int_decline_order':
                $order->update_meta_data('_wcospa_int_dealer_response', 'declined');
                $order->save();
                $this->log_debug(sprintf('Order #%d declined by dealer, triggering Pronto sync', $order_id));
                
                // Remove prevention flag before triggering sync
                delete_post_meta($order_id, '_wcospa_prevent_pronto_sync');
                delete_post_meta($order_id, '_wcospa_is_international');
                
                // Trigger Pronto sync for declined orders
                do_action('wcospa_sync_order', $order_id);
                wp_safe_redirect(home_url('/order-declined'));
                exit;
        }
    }

    /**
     * Handle email sending failure
     *
     * @param WC_Order $order Order object
     */
    private function handle_email_failure(WC_Order $order): void {
        $order_id = $order->get_id();
        $this->log_debug(sprintf('Handling email failure for order #%d', $order_id));

        // Update order status
        $order->update_status('failed', __('Failed to send dealer notification email.', 'wcospa'));

        // Send failure notification to administrators
        $admin_email = implode(',', ['jli@zerotech.com.au']);
        $subject = sprintf(__('Failed to send dealer notification for order #%s', 'wcospa'), $order->get_order_number());
        $message = sprintf(
            __('The system failed to send the dealer notification email for order #%s. Please check the order and retry manually.', 'wcospa'),
            $order->get_order_number()
        );

        wp_mail($admin_email, $subject, $message);
        $this->log_debug(sprintf('Sent failure notification email to admins for order #%d', $order_id));
    }

    /**
     * Add custom columns to orders list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_order_list_columns(array $columns): array {
        $columns['dealer_email'] = __('Dealer Email', 'wcospa');
        return $columns;
    }

    /**
     * Render custom column content
     *
     * @param string $column Column name
     */
    public function render_order_list_columns(string $column): void {
        global $post;

        if ($column === 'dealer_email') {
            $order = wc_get_order($post->ID);
            if ($order) {
                echo esc_html($order->get_meta('_wcospa_int_dealer_email'));
            }
        }
    }

    /**
     * Handle order shipped status
     */
    public function handle_order_shipped(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Only handle dealer-accepted orders
        if ($order->get_meta('_wcospa_int_dealer_response') !== 'accepted') {
            return;
        }

        $this->timer_handler->mark_as_shipped($order_id);
        $this->log_debug(sprintf('Order #%d marked as shipped by dealer', $order_id));
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     */
    private function log_debug(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[WCOSPA INT] %s', $message));
        }
    }

    /**
     * Get dealer configuration instance
     *
     * @return WCOSPA_INT_Dealer_Config
     */
    public function get_dealer_config(): WCOSPA_INT_Dealer_Config {
        return $this->dealer_config;
    }

    /**
     * Get email handler instance
     *
     * @return WCOSPA_INT_Email_Handler
     */
    public function get_email_handler(): WCOSPA_INT_Email_Handler {
        return $this->email_handler;
    }
} 