<?php
/**
 * Dealer Order Notification email (HTML)
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

/*
 * @hooked WC_Emails::email_order_details() Shows the order details table.
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

// Get order details for display
$order_id = $order->get_id();
$order_number = $order->get_order_number();
$order_date_utc = $order->get_date_created()->format('Y-m-d H:i:s T');
$order_date_sydney = (new DateTime($order_date_utc))->setTimezone(new DateTimeZone('Australia/Sydney'))->format('Y-m-d H:i:s T');

// Get customer details
$billing_first_name = $order->get_billing_first_name();
$billing_last_name = $order->get_billing_last_name();
$billing_company = $order->get_billing_company();
$billing_email = $order->get_billing_email();
$billing_phone = $order->get_billing_phone();

// Get shipping details if different from billing
$shipping_address = $order->get_formatted_shipping_address();
$billing_address = $order->get_formatted_billing_address();
$show_shipping = $shipping_address !== $billing_address;
?>

<div style="margin-bottom: 40px;">
    <h2><?php printf(esc_html__('Order #%s Details', 'wcospa'), esc_html($order_number)); ?></h2>
    
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;">
        <tr>
            <th scope="row" style="text-align:left;"><?php esc_html_e('Order Date (UTC):', 'wcospa'); ?></th>
            <td style="text-align:left;"><?php echo esc_html($order_date_utc); ?></td>
        </tr>
        <tr>
            <th scope="row" style="text-align:left;"><?php esc_html_e('Order Date (Sydney):', 'wcospa'); ?></th>
            <td style="text-align:left;"><?php echo esc_html($order_date_sydney); ?></td>
        </tr>
    </table>

    <h3><?php esc_html_e('Customer Details', 'wcospa'); ?></h3>
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;">
        <tr>
            <th scope="row" style="text-align:left;"><?php esc_html_e('Name:', 'wcospa'); ?></th>
            <td style="text-align:left;"><?php echo esc_html($billing_first_name . ' ' . $billing_last_name); ?></td>
        </tr>
        <?php if (!empty($billing_company)) : ?>
        <tr>
            <th scope="row" style="text-align:left;"><?php esc_html_e('Company:', 'wcospa'); ?></th>
            <td style="text-align:left;"><?php echo esc_html($billing_company); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th scope="row" style="text-align:left;"><?php esc_html_e('Email:', 'wcospa'); ?></th>
            <td style="text-align:left;"><?php echo esc_html($billing_email); ?></td>
        </tr>
        <tr>
            <th scope="row" style="text-align:left;"><?php esc_html_e('Phone:', 'wcospa'); ?></th>
            <td style="text-align:left;"><?php echo esc_html($billing_phone); ?></td>
        </tr>
    </table>

    <?php if ($show_shipping) : ?>
        <h3><?php esc_html_e('Shipping Address', 'wcospa'); ?></h3>
        <p><?php echo wp_kses_post($shipping_address); ?></p>
    <?php endif; ?>

    <div style="margin: 40px 0; text-align: center;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <table border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td class="button-td" style="border-radius: 3px; background: #22c55e;">
                                <a class="button-a" href="<?php echo esc_url($accept_url); ?>" style="background: #22c55e; border: 1px solid #22c55e; font-family: sans-serif; font-size: 15px; line-height: 15px; text-decoration: none; padding: 13px 17px; color: #ffffff; display: block; border-radius: 3px;">
                                    <?php esc_html_e('Accept & Fulfil', 'wcospa'); ?>
                                </a>
                            </td>
                            <td style="width: 20px;"></td>
                            <td class="button-td" style="border-radius: 3px; background: #ef4444;">
                                <a class="button-a" href="<?php echo esc_url($decline_url); ?>" style="background: #ef4444; border: 1px solid #ef4444; font-family: sans-serif; font-size: 15px; line-height: 15px; text-decoration: none; padding: 13px 17px; color: #ffffff; display: block; border-radius: 3px;">
                                    <?php esc_html_e('Decline Order', 'wcospa'); ?>
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 