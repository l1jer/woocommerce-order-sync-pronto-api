<?php
// This file formats WooCommerce order data for the API request

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Order_Data_Formatter {

    public static function format_order($order, $customer_reference) {
        $order_data = $order->get_data();
        $shipping_address = $order_data['shipping'];
        $billing_email = $order->get_billing_email();
        $shipping_email = $order->get_meta('_shipping_email');
        $order_notes = self::get_order_notes($order->get_id());

        return [
            'customer_reference' => $customer_reference,
            'debtor' => '210942', // Updated debtor code
            'status_code' => self::get_status_code($order->get_status()), // Add status_code
            'delivery_address' => [
                'address1' => strtoupper($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()), // Capitalized name
                'address2' => $shipping_address['address_1'],
                'address3' => $shipping_address['address_2'],
                'address4' => $shipping_address['city'],
                'address5' => $shipping_address['state'],
                'address6' => $shipping_address['country'],
                'address7' => '', // Keep empty as per requirements
                'postcode' => $shipping_address['postcode'],
                'phone' => $order->get_billing_phone()
            ],
            'delivery_instructions' => [
                'del_inst_1' => 'NO INVOICE & PACKING SLIP', // Uppercase instruction
                'del_inst_2' => $billing_email !== $shipping_email && $shipping_email ? $shipping_email : $billing_email, // Customer email from shipping if different
                'del_inst_3' => $order_notes // Order Notes, limited to 30 characters
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
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                error_log('Product or SKU not found for item ID: ' . $item_id);
                continue; // Skip items without a valid product or SKU
            }

            // Calculate the price for API (price divided by 1.1)
            $price_ex_tax = $item->get_total() / 1.1;

            $formatted_items[] = [
                'type' => 'SN', // Assuming 'SN' for normal items, adjust as necessary
                'item_code' => $product->get_sku(),
                'ordered_qty' => (string)$item->get_quantity(),
                'backordered_qty' => "0.0", // Assume no items are backordered
                'shipped_qty' => (string)$item->get_quantity(), // Assume all items are shipped
                'uom' => 'EA', // Default unit of measure
                'price_inc_tax' => (string)round($price_ex_tax, 2) // Send the price after dividing by 1.1, rounded to 2 decimal places
            ];
        }
        return $formatted_items;
    }

    // Method to get the first Order Note, limited to 30 characters
    private static function get_order_notes($order_id) {
        $notes = wc_get_order_notes(['order_id' => $order_id, 'limit' => 1]);

        if (!empty($notes)) {
            $note = reset($notes);
            return substr($note->content, 0, 30); // Limit to 30 characters
        }

        return ''; // Empty if no order notes
    }
}