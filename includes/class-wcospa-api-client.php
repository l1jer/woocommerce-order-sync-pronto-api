<?php
// This file manages the API requests to the Pronto API

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_API_Client {

    public static function send_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: ' . $order_id);
        }

        $order_data = WCOSPA_Order_Data_Formatter::format_order($order);
        $credentials = WCOSPA_Credentials::get_api_credentials();

        $response = wp_remote_post($credentials['api_url'], [
            'method'  => 'POST',
            'body'    => json_encode($order_data),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', "API request error: HTTP {$response_code} - {$response_body}");
        }

        return true;
    }
}