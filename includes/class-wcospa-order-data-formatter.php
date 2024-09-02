<?php
// This file formats WooCommerce order data for the API request

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Order_Data_Formatter {

    public static function format_order($order, $customer_reference) {
        $order_data = $order->get_data();
        $shipping_address = $order_data['shipping'];

        return [
            'customer_reference' => $customer_reference,
            'debtor' => '210942', // Updated debtor code
            'status_code' => self::get_status_code($order->get_status()), // Add status_code
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

    private static function get_status_code($wc_status) {
        $status_mapping = [
            'processing' => 30,
            'completed' => 80,
            // Add more WooCommerce status mappings if needed
        ];

        return isset($status_mapping[$wc_status]) ? $status_mapping[$wc_status] : 30; // Default to 30 (processing) if status is unrecognized
    }

    // Method to convert payment method codes
    private static function convert_payment_method($method) {
        $payment_mapping = [
            'paypal' => 'PP',
            'credit_card' => 'CC',
            'gift_voucher' => 'GV',
            'default' => 'CC'
        ];
        return $payment_mapping[$method] ?? $payment_mapping['default'];
    }

    // Method to format order items
    private static function format_order_items($items) {
        $formatted_items = [];
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                error_log('Product or SKU not found for item ID: ' . $item_id);
                continue; // Skip items without a valid product or SKU
            }
            $formatted_items[] = [
                'type' => 'SN', // Assuming 'SN' for normal items, adjust as necessary
                'item_code' => $product->get_sku(),
                'ordered_qty' => (string)$item->get_quantity(),
                'backordered_qty' => "0.0", // Assume no items are backordered
                'shipped_qty' => (string)$item->get_quantity(), // Assume all items are shipped
                'uom' => 'EA', // Default unit of measure
                'price_ex_tax' => (string)$item->get_subtotal(),
                'price_inc_tax' => (string)$item->get_total()
            ];
        }
        return $formatted_items;
    }
}