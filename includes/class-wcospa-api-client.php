<?php
// This file manages the API requests to the Pronto API

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_API_Client {

    public static function send_order($order_id) {
        // Retrieve the WooCommerce order by order ID
        $order = wc_get_order($order_id);
        if (!$order) {
            // Return error if the order is not found
            return new WP_Error('order_not_found', 'Order not found: ' . $order_id);
        }

        // Format customer_reference as "order number / shipping last name"
        $customer_reference = $order->get_id() . ' / ' . strtoupper($order->get_shipping_last_name());

        // Format order data for API submission
        $order_data = WCOSPA_Order_Data_Formatter::format_order($order, $customer_reference);
        // Retrieve API credentials
        $credentials = WCOSPA_Credentials::get_api_credentials();

        // Add this logging right before the wp_remote_post call
        WCOSPA_Logger::log('Sending order data: ' . json_encode($order_data));

        // Send the order data to the external API using wp_remote_post()
        $response = wp_remote_post($credentials['api_url'], [
            'method'  => 'POST',
            'body'    => json_encode($order_data),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
            ],
        ]);

        // Log and handle errors for debugging purposes
        if (is_wp_error($response)) {
            // Log the error message if the request failed
            WCOSPA_Logger::log('API request failed: ' . $response->get_error_message());
            return $response;
        }

        // Retrieve the response status code
        $status_code = wp_remote_retrieve_response_code($response);
        // If the response code is not 200, log the error
        if ($status_code !== 200) {
            WCOSPA_Logger::log('API returned non-200 status code: ' . $status_code . ' | Response: ' . wp_remote_retrieve_body($response));
            return new WP_Error('api_error', 'API request failed with status code: ' . $status_code);
        }

        // Log successful API request for tracking
        WCOSPA_Logger::log('API request successful for order ID: ' . $order_id);

        // Return the response for further processing if needed
        return $response;
    }
}