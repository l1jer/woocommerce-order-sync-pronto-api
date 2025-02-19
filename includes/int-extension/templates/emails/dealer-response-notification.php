<?php
/**
 * Dealer Response Notification email
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Get dealer information
$shipping_country = $order->get_shipping_country();
$dealer_config = isset($dealer_config) ? $dealer_config : [];
$dealer_name = isset($dealer_config['name']) ? $dealer_config['name'] : $shipping_country;
$dealer_email = $order->get_meta('_wcospa_int_dealer_email');
?>

<p>
    <?php
    printf(
        esc_html__('Order #%s has been %s by %s (%s) at:', 'wcospa'),
        esc_html($order->get_order_number()),
        esc_html($action),
        esc_html($dealer_name),
        esc_html($dealer_email)
    );
    ?>
</p>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;">
    <tr>
        <th style="text-align: left; padding: 10px;"><?php esc_html_e('Sydney Time:', 'wcospa'); ?></th>
        <td style="text-align: left; padding: 10px;"><?php echo esc_html($sydney_time); ?></td>
    </tr>
    <tr>
        <th style="text-align: left; padding: 10px;"><?php esc_html_e('Dealer Local Time:', 'wcospa'); ?></th>
        <td style="text-align: left; padding: 10px;"><?php echo esc_html($dealer_time); ?> (<?php echo esc_html($dealer_timezone); ?>)</td>
    </tr>
</table>

<h2><?php esc_html_e('Order Details', 'wcospa'); ?></h2>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 