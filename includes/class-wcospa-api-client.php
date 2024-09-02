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
            error_log('API request failed: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', "API request error: HTTP {$response_code}");
        }

        // Attempt to extract the UUID from the response body
        $response_data = json_decode($response_body, true);
        if (isset($response_data['apitransactions'][0]['uuid'])) {
            return $response_data['apitransactions'][0]['uuid']; // Return UUID
        } else {
            error_log('Failed to extract UUID from response body.');
            return new WP_Error('uuid_error', 'Failed to extract UUID from API response.');
        }
    }
}