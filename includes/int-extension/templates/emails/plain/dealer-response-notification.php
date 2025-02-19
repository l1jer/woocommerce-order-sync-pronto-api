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

// Get dealer information
$shipping_country = $order->get_shipping_country();
$dealer_config = isset($dealer_config) ? $dealer_config : [];
$dealer_name = isset($dealer_config['name']) ? $dealer_config['name'] : $shipping_country;
$dealer_email = $order->get_meta('_wcospa_int_dealer_email');

printf(
    esc_html__('Order #%s has been %s by %s (%s) at:', 'wcospa'),
    $order->get_order_number(),
    $action,
    $dealer_name,
    $dealer_email
);
echo "\n\n";

echo esc_html__('Dealer Local Time:', 'wcospa') . ' ' . $dealer_time . ' (' . $dealer_timezone . ")\n";
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