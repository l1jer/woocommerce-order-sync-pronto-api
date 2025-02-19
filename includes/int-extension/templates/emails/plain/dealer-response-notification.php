<?php
/**
 * Dealer Response Notification email (plain text)
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
    esc_html__('Order #%s has been %s at:', 'wcospa'),
    $order->get_order_number(),
    $action
);
echo "\n\n";

echo esc_html__('Dealer Local Time:', 'wcospa') . ' ' . $dealer_time . "\n";
echo esc_html__('Sydney Time:', 'wcospa') . ' ' . $sydney_time . "\n\n";

echo "----------------------------------------\n\n";

echo esc_html__('Order Details', 'wcospa') . "\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

echo wp_strip_all_tags(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 