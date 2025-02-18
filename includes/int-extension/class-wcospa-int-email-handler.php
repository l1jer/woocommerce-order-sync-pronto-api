<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_INT_Email_Handler
 * 
 * Handles email notifications for the INT extension using WordPress mail system
 */
class WCOSPA_INT_Email_Handler {
    /**
     * @var WCOSPA_INT_Dealer_Config
     */
    private WCOSPA_INT_Dealer_Config $dealer_config;

    /**
     * @var string Accept order URL parameter
     */
    private const ACCEPT_ACTION = 'wcospa_int_accept_order';

    /**
     * @var string Decline order URL parameter
     */
    private const DECLINE_ACTION = 'wcospa_int_decline_order';

    /**
     * @var int Maximum number of retry attempts
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * @var int Retry delay in seconds
     */
    private const RETRY_DELAY = 30;

    /**
     * Constructor
     */
    public function __construct() {
        $this->dealer_config = new WCOSPA_INT_Dealer_Config();
        add_action('init', [$this, 'handle_email_retry']);
        
        // Set email content type to HTML
        add_filter('wp_mail_content_type', function() {
            return 'text/html';
        });
    }

    /**
     * Handle email retry attempts
     */
    public function handle_email_retry(): void {
        global $wpdb;

        // Get failed emails that need retry
        $retry_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_wcospa_int_email_retry_count' 
                AND CAST(meta_value AS UNSIGNED) < %d",
                self::MAX_RETRY_ATTEMPTS
            )
        );

        foreach ($retry_orders as $retry_order) {
            $order_id = $retry_order->post_id;
            $retry_count = (int) $retry_order->meta_value;
            $last_attempt = get_post_meta($order_id, '_wcospa_int_last_retry_time', true);

            // Check if enough time has passed since last attempt
            if ($last_attempt && (time() - (int) $last_attempt) < self::RETRY_DELAY) {
                continue;
            }

            $this->log_debug(sprintf(
                'Attempting retry #%d for order #%d',
                $retry_count + 1,
                $order_id
            ));

            // Try sending the email again
            $sent = $this->send_dealer_notification($order_id);

            if ($sent) {
                $this->log_debug(sprintf('Retry successful for order #%d', $order_id));
                delete_post_meta($order_id, '_wcospa_int_email_retry_count');
                delete_post_meta($order_id, '_wcospa_int_last_retry_time');

                // Update order status
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_status('await-dealer', __('Dealer notification sent successfully after retry.', 'wcospa'));
                }
            } else {
                $retry_count++;
                update_post_meta($order_id, '_wcospa_int_email_retry_count', $retry_count);
                update_post_meta($order_id, '_wcospa_int_last_retry_time', time());

                if ($retry_count >= self::MAX_RETRY_ATTEMPTS) {
                    $this->log_debug(sprintf('All retry attempts exhausted for order #%d', $order_id));
                    $this->handle_final_failure($order_id);
                }
            }
        }
    }

    /**
     * Handle final failure after all retries are exhausted
     */
    private function handle_final_failure(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Update order status and notify admins
        $order->update_status('failed', __('Failed to send dealer notification email after all retry attempts.', 'wcospa'));
        
        $admin_emails = ['jli@zerotech.com.au'];
        $subject = sprintf(__('Final failure: Dealer notification for order #%s', 'wcospa'), $order->get_order_number());
        $message = sprintf(
            __('All retry attempts failed for order #%s. The order has been marked as failed. Please check the order and handle it manually.', 'wcospa'),
            $order->get_order_number()
        );

        foreach ($admin_emails as $admin_email) {
            wp_mail($admin_email, $subject, $message);
        }
    }

    /**
     * Send order notification email to dealer
     *
     * @param int $order_id Order ID
     * @return bool Whether the email was sent successfully
     */
    public function send_dealer_notification(int $order_id): bool {
        $this->log_debug(sprintf('Attempting to send dealer notification for order #%d', $order_id));

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log_debug(sprintf('Order #%d not found', $order_id));
            return false;
        }

        // Get shipping country and corresponding dealer email
        $shipping_country = $order->get_shipping_country();
        $dealer_email = $this->dealer_config->get_dealer_email($shipping_country);

        $this->log_debug(sprintf(
            'Processing order #%d for country: %s, dealer email: %s',
            $order_id,
            $shipping_country,
            $dealer_email
        ));

        // Store dealer email in order meta
        $order->update_meta_data('_wcospa_int_dealer_email', $dealer_email);
        $order->save();

        // Generate accept/decline URLs
        $accept_url = $this->generate_action_url($order_id, self::ACCEPT_ACTION);
        $decline_url = $this->generate_action_url($order_id, self::DECLINE_ACTION);

        // Prepare email content
        $subject = sprintf(__('New Order #%s Requires Your Attention', 'wcospa'), $order->get_order_number());
        
        // Get order details
        $order_date_utc = $order->get_date_created()->format('Y-m-d H:i:s T');
        $order_date_sydney = (new DateTime($order_date_utc))
            ->setTimezone(new DateTimeZone('Australia/Sydney'))
            ->format('Y-m-d H:i:s T');

        // Build HTML email content
        $message = $this->get_email_template($order, [
            'order_number' => $order->get_order_number(),
            'order_date_utc' => $order_date_utc,
            'order_date_sydney' => $order_date_sydney,
            'accept_url' => $accept_url,
            'decline_url' => $decline_url
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        ];

        $this->log_debug(sprintf('Sending email for order #%d to %s', $order_id, $dealer_email));

        // Send the email
        $sent = wp_mail($dealer_email, $subject, $message, $headers);

        if ($sent) {
            $this->log_debug(sprintf('Successfully sent email for order #%d', $order_id));
            
            // Clear retry meta if successful
            delete_post_meta($order_id, '_wcospa_int_email_retry_count');
            delete_post_meta($order_id, '_wcospa_int_last_retry_time');
            
            // Start decision timer
            update_post_meta($order_id, '_wcospa_int_decision_timer', time());
        } else {
            $this->log_debug(sprintf('Failed to send email for order #%d', $order_id));
            
            // Initialize retry mechanism
            $retry_count = (int) get_post_meta($order_id, '_wcospa_int_email_retry_count', true);
            if ($retry_count === 0) {
                update_post_meta($order_id, '_wcospa_int_email_retry_count', 1);
                update_post_meta($order_id, '_wcospa_int_last_retry_time', time());
            }
        }

        return $sent;
    }

    /**
     * Get email template with order details
     *
     * @param WC_Order $order Order object
     * @param array $data Template data
     * @return string HTML email content
     */
    private function get_email_template(WC_Order $order, array $data): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <title><?php echo sprintf(__('Order #%s', 'wcospa'), $data['order_number']); ?></title>
        </head>
        <body style="background-color: #f7f7f7; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h1 style="color: #333333; margin-bottom: 30px;">
                    <?php echo sprintf(__('New Order #%s', 'wcospa'), $data['order_number']); ?>
                </h1>

                <table style="width: 100%; margin-bottom: 30px;">
                    <tr>
                        <th style="text-align: left; padding: 10px;"><?php _e('Order Date (UTC):', 'wcospa'); ?></th>
                        <td style="text-align: left; padding: 10px;"><?php echo esc_html($data['order_date_utc']); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px;"><?php _e('Order Date (Sydney):', 'wcospa'); ?></th>
                        <td style="text-align: left; padding: 10px;"><?php echo esc_html($data['order_date_sydney']); ?></td>
                    </tr>
                </table>

                <h2 style="color: #333333; margin-bottom: 20px;"><?php _e('Order Details', 'wcospa'); ?></h2>
                <?php
                $items_table = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">';
                $items_table .= '<tr style="background-color: #f8f8f8;">';
                $items_table .= '<th style="text-align: left; padding: 10px; border: 1px solid #dddddd;">' . __('Product', 'wcospa') . '</th>';
                $items_table .= '<th style="text-align: center; padding: 10px; border: 1px solid #dddddd;">' . __('Quantity', 'wcospa') . '</th>';
                $items_table .= '<th style="text-align: right; padding: 10px; border: 1px solid #dddddd;">' . __('Price', 'wcospa') . '</th>';
                $items_table .= '</tr>';

                foreach ($order->get_items() as $item) {
                    $items_table .= '<tr>';
                    $items_table .= '<td style="text-align: left; padding: 10px; border: 1px solid #dddddd;">' . $item->get_name() . '</td>';
                    $items_table .= '<td style="text-align: center; padding: 10px; border: 1px solid #dddddd;">' . $item->get_quantity() . '</td>';
                    $items_table .= '<td style="text-align: right; padding: 10px; border: 1px solid #dddddd;">' . wc_price($item->get_total()) . '</td>';
                    $items_table .= '</tr>';
                }

                $items_table .= '</table>';
                echo $items_table;
                ?>

                <div style="text-align: center; margin: 40px 0;">
                    <a href="<?php echo esc_url($data['accept_url']); ?>" style="display: inline-block; padding: 15px 25px; background-color: #22c55e; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 0 10px;">
                        <?php _e('Accept & Fulfil', 'wcospa'); ?>
                    </a>
                    <a href="<?php echo esc_url($data['decline_url']); ?>" style="display: inline-block; padding: 15px 25px; background-color: #ef4444; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 0 10px;">
                        <?php _e('Decline Order', 'wcospa'); ?>
                    </a>
                </div>

                <p style="color: #666666; font-size: 14px; text-align: center; margin-top: 40px;">
                    <?php _e('Please respond within 48 hours. If no response is received, the order will be automatically processed.', 'wcospa'); ?>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate secure URL for dealer actions
     *
     * @param int $order_id Order ID
     * @param string $action Action type (accept/decline)
     * @return string Secure URL with nonce
     */
    private function generate_action_url(int $order_id, string $action): string {
        $nonce = wp_create_nonce("wcospa_int_{$action}_{$order_id}");
        $url = add_query_arg([
            'action' => $action,
            'order_id' => $order_id,
            'nonce' => $nonce
        ], home_url('/'));

        $this->log_debug(sprintf('Generated action URL for order #%d: %s', $order_id, $url));

        return $url;
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     */
    private function log_debug(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[WCOSPA INT Email] %s', $message));
        }
    }
} 