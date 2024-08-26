<?php
// This file formats WooCommerce order data for the API request

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Order_Data_Formatter {

    public static function format_order($order) {
        $order_data = $order->get_data();
        $shipping_address = $order_data['shipping'];

        return [
            'customer_reference' => 'ZTAU' . $order->get_id(),
            'debtor' => '210671',
            'delivery_address' => [
                'address1' => $shipping_address['address_1'],
                'address2' => $shipping_address['address_2'],
                'address4' => $shipping_address['city'],
                'address5' => $shipping_address['state'],
                'address6' => $shipping_address['country'],
                'postcode' => $shipping_address['postcode'],
                'phone' => $order->get_billing_phone()
            ],
            'payment' => [
                'method' => self::convert_payment_method($order->get_payment_method()),
                'reference' => $order->get_transaction_id(),
                'amount' => $order->get_total(),
                'currency_code' => $order->get_currency()
            ],
            'lines' => self::format_order_items($order->get_items())
        ];
    }

    private static function convert_payment_method($method) {
        $payment_mapping = [
            'paypal' => 'PP',
            'credit_card' => 'CC',
            'gift_voucher' => 'GV',
            'default' => 'CC'
        ];
        return $payment_mapping[$method] ?? $payment_mapping['default'];
    }

    private static function format_order_items($items) {
        $formatted_items = [];
        foreach ($items as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                continue;
            }
            $formatted_items[] = [
                'type' => 'SN',
                'item_code' => $product->get_sku(),
                'ordered_qty' => (string)$item->get_quantity(),
                'shipped_qty' => (string)$item->get_quantity(),
                'uom' => 'EA',
                'price_ex_tax' => (string)$item->get_subtotal(),
                'price_inc_tax' => (string)$item->get_total()
            ];
        }
        return $formatted_items;
    }
}