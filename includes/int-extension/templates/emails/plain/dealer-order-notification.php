<?php
/**
 * Dealer Order Notification email (plain text)
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Order number */
echo sprintf(esc_html__('Order #%s Details', 'wcospa'), $order->get_order_number()) . "\n\n";

// Get order dates
$order_date = $order->get_date_created();
$shipping_country = $order->get_shipping_country();

// Get dealer configuration and timezone
$dealers = isset($dealer_config) ? $dealer_config : [];
$dealer_name = isset($dealers[$shipping_country]['name']) ? $dealers[$shipping_country]['name'] : $shipping_country;
$dealer_timezone = isset($dealers[$shipping_country]['timezone']) 
    ? $dealers[$shipping_country]['timezone'] 
    : WCOSPA_INT_Dealer_Config::COUNTRY_TIMEZONES[$shipping_country] ?? 'Europe/London';

$sydney_timezone = new DateTimeZone('Australia/Sydney');

// Convert to dealer's local time
$dealer_time = clone $order_date;
$dealer_time->setTimezone(new DateTimeZone($dealer_timezone));
$order_date_dealer = $dealer_time->format('H:i \o\n d/m/Y (T)');

// Convert to Sydney time
$sydney_time = clone $order_date;
$sydney_time->setTimezone($sydney_timezone);
$order_date_sydney = $sydney_time->format('H:i \o\n d/m/Y (T)');

echo esc_html__('Order Date (Dealer Time):', 'wcospa') . ' ' . $order_date_dealer . "\n";
echo esc_html__('Order Date (Sydney):', 'wcospa') . ' ' . $order_date_sydney . "\n\n";

// Order details
echo "----------------------------------------\n\n";
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

// Customer details
echo "\n----------------------------------------\n\n";
echo esc_html__('Customer Details', 'wcospa') . "\n";
echo esc_html__('Name:', 'wcospa') . ' ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . "\n";
if ($order->get_billing_company()) {
    echo esc_html__('Company:', 'wcospa') . ' ' . $order->get_billing_company() . "\n";
}
echo esc_html__('Email:', 'wcospa') . ' ' . $order->get_billing_email() . "\n";
echo esc_html__('Phone:', 'wcospa') . ' ' . $order->get_billing_phone() . "\n\n";

// Shipping address if different from billing
$shipping_address = $order->get_formatted_shipping_address();
$billing_address = $order->get_formatted_billing_address();
if ($shipping_address !== $billing_address) {
    echo esc_html__('Shipping Address:', 'wcospa') . "\n";
    echo wp_strip_all_tags($shipping_address) . "\n\n";
}

// Action buttons
echo "----------------------------------------\n\n";
echo esc_html__('Please click one of the following links to take action:', 'wcospa') . "\n\n";
echo esc_html__('Accept & Fulfil:', 'wcospa') . ' ' . esc_url($accept_url) . "\n";
echo esc_html__('Decline Order:', 'wcospa') . ' ' . esc_url($decline_url) . "\n\n";

// Additional content
echo "----------------------------------------\n\n";
echo wp_strip_all_tags(wptexturize($additional_content));
echo "\n\n----------------------------------------\n\n";

echo wp_strip_all_tags(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 