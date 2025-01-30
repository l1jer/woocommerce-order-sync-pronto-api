<?php
/**
 * Order Formatter Class
 *
 * Handles the formatting of WooCommerce order data for Pronto API compatibility.
 * Manages address formatting, payment details, and line items according to Pronto specifications.
 *
 * @package    WooCommerce Order Sync Pronto API
 * @subpackage Formatters
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCOSPA_Order_Formatter Class
 *
 * Formats WooCommerce order data for Pronto API compatibility.
 *
 * @since 1.0.0
 */
class WCOSPA_Order_Formatter
{
    /**
     * Format a WooCommerce order for Pronto API.
     *
     * Converts a WooCommerce order object into the format required by Pronto API.
     *
     * @since  1.0.0
     * @param  WC_Order $order             The WooCommerce order object.
     * @param  string   $customer_reference The customer reference number.
     * @return array    The formatted order data.
     */
    public static function format_order($order, $customer_reference)
    {
        $order_data = $order->get_data();
        $shipping_address = $order_data['shipping'];
        $billing_email = $order->get_billing_email();
        $shipping_email = $order->get_meta('_shipping_email');
        
        return [
            'customer_reference' => $customer_reference,
            'debtor' => '210942',
            'delivery_address' => self::format_delivery_address($order, $shipping_address),
            'delivery_instructions' => self::format_delivery_instructions($billing_email, $shipping_email, $order),
            'payment' => self::format_payment_details($order, $customer_reference),
            'lines' => self::format_order_items($order),
        ];
    }

    /**
     * Format delivery address according to Pronto specifications.
     *
     * Handles different address formats based on business name and address line presence.
     *
     * @since  1.0.0
     * @param  WC_Order $order           The WooCommerce order object.
     * @param  array    $shipping_address The shipping address data.
     * @return array    The formatted delivery address.
     */
    private static function format_delivery_address($order, $shipping_address)
    {
        $business_name = $order->get_shipping_company();
        $delivery_address = [
            'address1' => strtoupper($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
            'address2' => '',
            'address3' => '',
            'address4' => '',
            'address5' => '',
            'postcode' => $shipping_address['postcode'],
            'phone' => $order->get_billing_phone(),
        ];

        if (empty($business_name) && empty($shipping_address['address_2'])) {
            $delivery_address['address2'] = $shipping_address['address_1'];
            $delivery_address['address3'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        } elseif (empty($business_name) && !empty($shipping_address['address_2'])) {
            $delivery_address['address2'] = $shipping_address['address_1'];
            $delivery_address['address3'] = $shipping_address['address_2'];
            $delivery_address['address4'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        } elseif (!empty($business_name) && empty($shipping_address['address_2'])) {
            $delivery_address['address2'] = $business_name;
            $delivery_address['address3'] = $shipping_address['address_1'];
            $delivery_address['address4'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        } else {
            $delivery_address['address2'] = $business_name;
            $delivery_address['address3'] = $shipping_address['address_1'];
            $delivery_address['address4'] = $shipping_address['address_2'];
            $delivery_address['address5'] = $shipping_address['city'] . ' ' . $shipping_address['state'];
        }

        return $delivery_address;
    }

    private static function format_delivery_instructions($billing_email, $shipping_email, $order)
    {
        return '*NO INVOICE AND PACKING SLIP* ' .
            ($billing_email !== $shipping_email && $shipping_email ? $shipping_email . "\n" : $billing_email . "\n") .
            (!empty($order->get_customer_note()) ? ' ' . $order->get_customer_note() : '');
    }

    private static function format_payment_details($order, $customer_reference)
    {
        $payment_method = $order->get_payment_method();
        $total_price_inc_tax = array_reduce($order->get_items(), function ($total, $item) {
            return $total + $item->get_total();
        }, 0);

        return [
            'method' => self::convert_payment_method($payment_method),
            'reference' => ' ' . strtoupper($payment_method) . ' ' . $customer_reference,
            'amount' => round($total_price_inc_tax, 2),
            'currency_code' => $order->get_currency(),
            'bank_code' => self::get_bank_code($payment_method),
        ];
    }

    private static function convert_payment_method($method)
    {
        $payment_mapping = [
            'ppcp' => 'PP',
            'afterpay' => 'CC',
            'stripe_cc' => 'CC',
        ];

        return $payment_mapping[$method] ?? 'CC';
    }

    private static function get_bank_code($payment_method)
    {
        $bank_codes = [
            'ppcp' => 'PAYPAL',
            'stripe_cc' => 'STRIPE',
            'afterpay' => 'AFTER'
        ];

        return $bank_codes[$payment_method] ?? '';
    }

    private static function format_order_items($order)
    {
        $formatted_items = [];
        $payment_method = $order->get_payment_method();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                continue;
            }

            $price_inc_tax_per_item = $item->get_total() / $item->get_quantity();
            $formatted_items[] = [
                'type' => 'SN',
                'item_code' => $product->get_sku(),
                'quantity' => (string) $item->get_quantity(),
                'uom' => 'EA',
                'price_inc_tax' => (string) round($price_inc_tax_per_item, 2),
                'price_ex_tax' => (string) round($price_inc_tax_per_item / 1.1, 2),
            ];
        }

        $discount_note = self::get_discount_note($order);
        if (!empty($discount_note)) {
            $formatted_items[] = [
                'type' => 'DN',
                'item_code' => 'Note',
                'description' => $discount_note,
                'quantity' => '0',
                'uom' => '',
                'price_inc_tax' => '0',
            ];
        }

        $payment_descriptions = [
            'ppcp' => 'PayPal',
            'stripe_cc' => 'Stripe - Credit Card',
            'afterpay' => 'AfterPay'
        ];

        if (isset($payment_descriptions[$payment_method])) {
            $formatted_items[] = [
                'type' => 'DN',
                'item_code' => 'Note',
                'description' => $payment_descriptions[$payment_method],
                'quantity' => '0',
                'uom' => '',
                'price_inc_tax' => '0',
            ];
        }

        return $formatted_items;
    }

    private static function get_discount_note($order)
    {
        $note_parts = [];

        if ($coupons = $order->get_coupon_codes()) {
            foreach ($coupons as $coupon_code) {
                $coupon = new WC_Coupon($coupon_code);
                $note_parts[] = 'Discount: Coupon(' . $coupon_code . ') - ' . $coupon->get_description();
            }
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->is_on_sale()) {
                $note_parts[] = 'Product SKU: ' . $product->get_sku() . ' is on sale';
            }
        }

        return implode('; ', $note_parts);
    }
} 