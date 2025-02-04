<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WC_Email_New_Order;
use WC_Order;

/**
 * Class WCOSPA_INT_Email_Handler
 * 
 * Handles email notifications for dealers based on shipping country
 */
class WCOSPA_INT_Email_Handler {
    /**
     * @var array
     */
    private $countries_config;

    /**
     * Constructor
     */
    public function __construct() {
        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            error_log('WCOSPA INT: WooCommerce is not active');
            return;
        }

        // Load WordPress functions
        require_once(ABSPATH . 'wp-includes/pluggable.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        add_action('woocommerce_new_order', array($this, 'send_dealer_notification'), 10, 1);
        add_action('wp_ajax_accept_order', array($this, 'handle_accept_order'));
        add_action('wp_ajax_decline_order', array($this, 'handle_decline_order'));
        
        $this->load_countries_config();
    }

    /**
     * Load countries configuration from JSON file
     */
    private function load_countries_config(): void {
        $config_file = plugin_dir_path(__FILE__) . 'config/countries.json';
        if (file_exists($config_file)) {
            $json_content = file_get_contents($config_file);
            if ($json_content === false) {
                error_log('WCOSPA INT: Failed to read countries configuration file');
                return;
            }
            
            $decoded = json_decode($json_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('WCOSPA INT: Failed to parse countries configuration: ' . json_last_error_msg());
                return;
            }
            
            $this->countries_config = $decoded['countries'] ?? array();
            error_log('WCOSPA INT: Loaded ' . count($this->countries_config) . ' country configurations');
        } else {
            error_log('WCOSPA INT: Countries configuration file not found at: ' . $config_file);
        }
    }

    /**
     * Send notification email to dealer
     * 
     * @param int $order_id
     */
    public function send_dealer_notification($order_id): void {
        error_log('WCOSPA INT: Processing new order notification for order ID: ' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            error_log('WCOSPA INT: Invalid order object for order ID: ' . $order_id);
            return;
        }

        $shipping_country = $order->get_shipping_country();
        error_log('WCOSPA INT: Order shipping country: ' . $shipping_country);
        
        $dealer_email = $this->get_dealer_email($shipping_country);
        if (!$dealer_email) {
            error_log('WCOSPA INT: No dealer found for country: ' . $shipping_country);
            return;
        }

        error_log('WCOSPA INT: Sending notification to dealer: ' . $dealer_email);

        // Get the email template
        $mailer = WC()->mailer();
        $email = new WC_Email_New_Order();
        
        // Customise email template
        add_action('woocommerce_email_order_details', array($this, 'add_action_buttons'), 15, 4);
        
        // Send the email
        $email->recipient = $dealer_email;
        $email->trigger($order_id);
        
        // Remove our custom action to prevent affecting other emails
        remove_action('woocommerce_email_order_details', array($this, 'add_action_buttons'), 15);
        
        error_log('WCOSPA INT: Notification sent successfully for order ID: ' . $order_id);
    }

    /**
     * Get dealer email for a country
     * 
     * @param string $country_code
     * @return string|null
     */
    private function get_dealer_email(string $country_code): ?string {
        if (empty($this->countries_config)) {
            error_log('WCOSPA INT: Countries configuration is empty');
            return null;
        }

        foreach ($this->countries_config as $country_data) {
            if ($country_data['country_code'] === $country_code) {
                return $country_data['dealer_email'];
            }
        }
        
        error_log('WCOSPA INT: No dealer email found for country code: ' . $country_code);
        return null;
    }

    /**
     * Add action buttons to email template
     * 
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param WC_Email_New_Order $email
     */
    public function add_action_buttons($order, $sent_to_admin, $plain_text, $email): void {
        if ($plain_text || !($order instanceof WC_Order)) {
            error_log('WCOSPA INT: Skipping action buttons for plain text email or invalid order');
            return;
        }

        try {
            $accept_url = add_query_arg(array(
                'action' => 'accept_order',
                'order_id' => $order->get_id(),
                'nonce' => wp_create_nonce('dealer_action_' . $order->get_id())
            ), admin_url('admin-ajax.php'));

            $decline_url = add_query_arg(array(
                'action' => 'decline_order',
                'order_id' => $order->get_id(),
                'nonce' => wp_create_nonce('dealer_action_' . $order->get_id())
            ), admin_url('admin-ajax.php'));

            ?>
            <div style="margin-top: 20px; text-align: center;">
                <a href="<?php echo esc_url($accept_url); ?>" style="background-color: #7ab80e; color: #ffffff; padding: 12px 25px; text-decoration: none; margin-right: 10px; border-radius: 3px;">Accept & Fulfil</a>
                <a href="<?php echo esc_url($decline_url); ?>" style="background-color: #dc3232; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 3px;">Decline Order</a>
            </div>
            <?php
        } catch (Exception $e) {
            error_log('WCOSPA INT: Error adding action buttons: ' . $e->getMessage());
        }
    }

    /**
     * Handle order acceptance
     */
    public function handle_accept_order(): void {
        error_log('WCOSPA INT: Processing order acceptance');
        $this->handle_dealer_action('accepted');
    }

    /**
     * Handle order decline
     */
    public function handle_decline_order(): void {
        error_log('WCOSPA INT: Processing order decline');
        $this->handle_dealer_action('declined');
    }

    /**
     * Handle dealer actions
     * 
     * @param string $action
     */
    private function handle_dealer_action(string $action): void {
        if (!isset($_GET['order_id'], $_GET['nonce'])) {
            error_log('WCOSPA INT: Missing required parameters for dealer action');
            wp_die('Invalid request');
        }

        $order_id = intval($_GET['order_id']);
        error_log('WCOSPA INT: Processing dealer action "' . $action . '" for order ID: ' . $order_id);

        if (!wp_verify_nonce($_GET['nonce'], 'dealer_action_' . $order_id)) {
            error_log('WCOSPA INT: Invalid nonce for order ID: ' . $order_id);
            wp_die('Invalid nonce');
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            error_log('WCOSPA INT: Order not found for ID: ' . $order_id);
            wp_die('Order not found');
        }

        try {
            if ($action === 'accepted') {
                $order->update_status('processing', __('Order accepted by dealer', 'woocommerce'));
                $order->add_order_note(__('Order accepted by dealer', 'woocommerce'));
                error_log('WCOSPA INT: Order ' . $order_id . ' accepted by dealer');
            } else {
                $order->update_status('cancelled', __('Order declined by dealer', 'woocommerce'));
                $order->add_order_note(__('Order declined by dealer', 'woocommerce'));
                error_log('WCOSPA INT: Order ' . $order_id . ' declined by dealer');
            }

            wp_safe_redirect($order->get_edit_order_url());
            exit;
        } catch (Exception $e) {
            error_log('WCOSPA INT: Error processing dealer action: ' . $e->getMessage());
            wp_die('Error processing dealer action: ' . esc_html($e->getMessage()));
        }
    }
} 