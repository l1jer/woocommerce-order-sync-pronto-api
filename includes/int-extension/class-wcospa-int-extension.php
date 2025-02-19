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

        $this->log_debug(sprintf('Processing international order #%d', $order_id));

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
     * Set prevention flags for all orders as they are all international
     */
    public function check_international_order(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // All orders are international in this branch
        update_post_meta($order_id, '_wcospa_prevent_pronto_sync', 'yes');
        update_post_meta($order_id, '_wcospa_is_international', 'yes');
    }

    /**
     * Prevent automatic progression from "Processing" to "Pronto Received"
     * Always returns false as all orders require dealer decision
     */
    public function prevent_auto_pronto_sync(bool $should_process, WC_Order $order): bool {
        return false;
    }

    /**
     * Handle dealer response to order
     */
    public function handle_dealer_response(): void {
        if (!isset($_GET['action'], $_GET['order_id'], $_GET['token'], $_GET['ts'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $order_id = (int) $_GET['order_id'];
        $token = sanitize_text_field($_GET['token']);
        $timestamp = (int) $_GET['ts'];

        // Verify token using email handler
        if (!$this->email_handler->verify_action_token($order_id, $action, $token, $timestamp)) {
            $this->log_debug(sprintf('Invalid token for order #%d action: %s', $order_id, $action));
            wp_die(
                __('This link has expired or is invalid. Please contact support if you need assistance.', 'wcospa'),
                __('Access Denied', 'wcospa'),
                ['response' => 403, 'back_link' => false]
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log_debug(sprintf('Order #%d not found', $order_id));
            wp_die(
                __('Order not found. Please contact support if you need assistance.', 'wcospa'),
                __('Order Not Found', 'wcospa'),
                ['response' => 404, 'back_link' => false]
            );
        }

        // Check if order has already been actioned
        $dealer_response = $order->get_meta('_wcospa_int_dealer_response');
        if ($dealer_response) {
            $response_time = $order->get_meta('_wcospa_int_dealer_response_time');
            $formatted_time = $response_time ? wp_date('H:i \o\n d/m/Y', (int) $response_time) : 'unknown time';
            
            $this->log_debug(sprintf(
                'Order #%d already actioned (%s) at %s',
                $order_id,
                $dealer_response,
                $formatted_time
            ));

            wp_die(
                sprintf(
                    __('You have already %s order #%s at %s. If further action is needed, please contact jheads@zerotech.com.au.', 'wcospa'),
                    $dealer_response === 'accepted' ? 'accepted' : 'rejected',
                    $order->get_order_number(),
                    $formatted_time
                ),
                __('Action Already Taken', 'wcospa'),
                ['response' => 403, 'back_link' => false]
            );
        }

        // Clean up used token
        delete_post_meta($order_id, "_wcospa_int_{$action}_token");
        delete_post_meta($order_id, "_wcospa_int_{$action}_timestamp");

        $this->log_debug(sprintf('Processing dealer response for order #%d: %s', $order_id, $action));

        switch ($action) {
            case 'wcospa_int_accept_order':
                $order->update_status('dealer-accept', __('Order accepted by dealer.', 'wcospa'));
                $order->update_meta_data('_wcospa_int_dealer_response', 'accepted');
                $order->update_meta_data('_wcospa_int_dealer_response_time', time());
                $order->save();

                // Start shipping timer
                $this->timer_handler->start_shipping_timer($order_id);
                
                // Send notification
                $this->send_response_notification($order, 'accepted');
                
                // Handle redirect
                $this->handle_response_redirect($order, 'accepted');

            case 'wcospa_int_decline_order':
                // Update order status and metadata
                $order->update_status('processing', __('Order declined by dealer, proceeding with Pronto sync.', 'wcospa'));
                $order->update_meta_data('_wcospa_int_dealer_response', 'declined');
                $order->update_meta_data('_wcospa_int_dealer_response_time', time());
                $order->save();
                
                $this->log_debug(sprintf('Order #%d declined by dealer, proceeding to Pronto sync', $order_id));
                
                // Send notification
                $this->send_response_notification($order, 'declined');
                
                // Remove prevention flags and trigger sync
                delete_post_meta($order_id, '_wcospa_prevent_pronto_sync');
                delete_post_meta($order_id, '_wcospa_is_international');
                
                // Manually handle the sync instead of using action
                WCOSPA_Order_Handler::handle_order_sync($order_id);
                
                // Handle redirect
                $this->handle_response_redirect($order, 'declined');
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
        $columns['dealer_name'] = __('Dealer Name', 'wcospa');
        $columns['dealer_response_time'] = __('Response Time', 'wcospa');
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
        } elseif ($column === 'dealer_name') {
            $order = wc_get_order($post->ID);
            if ($order) {
                $shipping_country = $order->get_shipping_country();
                $dealer_config = $this->dealer_config->get_dealer_config($shipping_country);
                echo esc_html($dealer_config['name'] ?? '');
            }
        } elseif ($column === 'dealer_response_time') {
            $order = wc_get_order($post->ID);
            if ($order) {
                $response_time = (int) $order->get_meta('_wcospa_int_dealer_response_time');
                $response_type = $order->get_meta('_wcospa_int_dealer_response');
                if ($response_time && $response_type) {
                    $sydney_time = wp_date('H:i \o\n d/m/Y (T)', $response_time, new DateTimeZone('Australia/Sydney'));
                    $shipping_country = $order->get_shipping_country();
                    $dealer_timezone = $this->dealer_config->get_dealer_timezone($shipping_country);
                    $dealer_time = wp_date('H:i \o\n d/m/Y (T)', $response_time, new DateTimeZone($dealer_timezone));
                    
                    printf(
                        '<div class="response-type">%s</div><div class="sydney-time">Sydney: %s</div><div class="dealer-time">Local: %s</div>',
                        esc_html(ucfirst($response_type)),
                        esc_html($sydney_time),
                        esc_html($dealer_time)
                    );
                }
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

    /**
     * Send dealer response notification
     */
    private function send_response_notification(WC_Order $order, string $action): void {
        $dealer_email = $order->get_meta('_wcospa_int_dealer_email');
        $response_time = (int) $order->get_meta('_wcospa_int_dealer_response_time');
        $shipping_country = $order->get_shipping_country();
        
        // Get dealer's timezone and configuration
        $dealer_timezone = $this->dealer_config->get_dealer_timezone($shipping_country);
        $dealer_config = $this->dealer_config->get_dealer_config($shipping_country);
        
        // Format times for email using correct timezone
        $dealer_time = wp_date('H:i \o\n d/m/Y (T)', $response_time, new DateTimeZone($dealer_timezone));
        $sydney_time = wp_date('H:i \o\n d/m/Y (T)', $response_time, new DateTimeZone('Australia/Sydney'));
        
        $subject = sprintf(
            __('Order #%s %s by Dealer', 'wcospa'),
            $order->get_order_number(),
            $action === 'accepted' ? 'Accepted' : 'Declined'
        );

        // Build email content using WooCommerce template
        ob_start();
        wc_get_template(
            'emails/dealer-response-notification.php',
            [
                'order' => $order,
                'action' => $action === 'accepted' ? 'accepted' : 'declined',
                'dealer_time' => $dealer_time,
                'sydney_time' => $sydney_time,
                'dealer_timezone' => $dealer_timezone,
                'dealer_config' => $dealer_config,
                'email_heading' => $subject,
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => null
            ],
            '',
            plugin_dir_path(__FILE__) . 'templates/'
        );
        $message = ob_get_clean();
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>',
            'Bcc: jerry@tasco.com.au'
        ];
        
        wp_mail($dealer_email, $subject, $message, $headers);
        
        $this->log_debug(sprintf(
            'Sent %s notification to dealer (%s) for order #%s',
            $action,
            $dealer_email,
            $order->get_order_number()
        ));
    }

    /**
     * Handle redirect after dealer response
     */
    private function handle_response_redirect(WC_Order $order, string $action): void {
        $response_time = (int) $order->get_meta('_wcospa_int_dealer_response_time');
        $shipping_country = $order->get_shipping_country();
        
        // Get dealer's timezone
        $dealer_timezone = $this->dealer_config->get_dealer_timezone($shipping_country);
        
        // Format times using correct timezone
        $dealer_time = wp_date('H:i \o\n d/m/Y (T)', $response_time, new DateTimeZone($dealer_timezone));
        $sydney_time = wp_date('H:i \o\n d/m/Y (T)', $response_time, new DateTimeZone('Australia/Sydney'));
        
        // Store response data in transient for the response page
        $transient_key = 'wcospa_response_' . $order->get_id();
        set_transient($transient_key, [
            'order_number' => $order->get_order_number(),
            'billing_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'action' => $action,
            'dealer_time' => $dealer_time,
            'sydney_time' => $sydney_time,
            'dealer_timezone' => $dealer_timezone,
            'message' => sprintf(
                __('Thank you for your response. Order #%s has been successfully %s.', 'wcospa'),
                $order->get_order_number(),
                $action === 'accepted' ? 'accepted' : 'declined'
            ),
            'details' => sprintf(
                __('Action taken at: %s (Your time - %s) / %s (Sydney time)', 'wcospa'),
                $dealer_time,
                str_replace('_', ' ', $dealer_timezone),
                $sydney_time
            )
        ], 300); // Store for 5 minutes
        
        $this->log_debug(sprintf(
            'Redirecting to response page for order #%s (%s)',
            $order->get_order_number(),
            $action
        ));

        // Create response page content
        ob_start();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html(sprintf(__('Order %s Response', 'wcospa'), $action)); ?></title>
            <?php wp_head(); ?>
            <style>
                .wcospa-response {
                    max-width: 800px;
                    margin: 50px auto;
                    padding: 30px;
                    background: #fff;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    border-radius: 5px;
                    text-align: center;
                }
                .wcospa-response h1 {
                    color: #2c3338;
                    margin-bottom: 20px;
                }
                .wcospa-response .message {
                    font-size: 18px;
                    color: #2c3338;
                    margin-bottom: 20px;
                }
                .wcospa-response .details {
                    color: #646970;
                    font-size: 14px;
                }
                .wcospa-response .timezone {
                    color: #646970;
                    font-size: 12px;
                    margin-top: 10px;
                    font-style: italic;
                }
                .wcospa-response .icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                }
                .wcospa-response .icon.success {
                    color: #46b450;
                }
                .wcospa-response .icon.declined {
                    color: #dc3232;
                }
            </style>
        </head>
        <body <?php body_class(); ?>>
            <div class="wcospa-response">
                <div class="icon <?php echo esc_attr($action); ?>">
                    <?php echo $action === 'accepted' ? '✓' : '×'; ?>
                </div>
                <h1><?php echo esc_html(sprintf(__('Order #%s Response', 'wcospa'), $order->get_order_number())); ?></h1>
                <div class="message">
                    <?php echo esc_html(sprintf(
                        __('Thank you for your response. Order #%s has been successfully %s.', 'wcospa'),
                        $order->get_order_number(),
                        $action === 'accepted' ? 'accepted' : 'declined'
                    )); ?>
                </div>
                <div class="details">
                    <?php echo esc_html(sprintf(
                        __('Action taken at: %s / %s', 'wcospa'),
                        $dealer_time,
                        $sydney_time
                    )); ?>
                </div>
                <div class="timezone">
                    <?php echo esc_html(sprintf(
                        __('Your timezone: %s', 'wcospa'),
                        str_replace('_', ' ', $dealer_timezone)
                    )); ?>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        $content = ob_get_clean();
        
        // Output the response page
        echo $content;
        exit;
    }
} 