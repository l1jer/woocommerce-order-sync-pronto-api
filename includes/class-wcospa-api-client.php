<?php

class WCOSPA_API_Client
{
    // Step 1: Submit order information and retrieve the transaction UUID
    public static function sync_order($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: '.$order_id);
        }

        // Get API credentials
        $credentials = WCOSPA_Credentials::get_api_credentials();
        $api_url = $credentials['post_order'];

        // Prepare the second argument for the format_order method
        $customer_reference = $order->get_id().' / '.strtoupper($order->get_shipping_last_name());

        // Prepare order data with the second argument (customer_reference)
        $order_data = WCOSPA_Order_Data_Formatter::format_order($order, $customer_reference);

        // Log the sync URL and order data for debugging
        WCOSPA_API_Client::log('Sync URL: '.$api_url);
        WCOSPA_API_Client::log('Order Data: '.print_r($order_data, true));

        // Make the POST request to the API to sync the order
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($credentials['username'].':'.$credentials['password']),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($order_data),
            'timeout' => 20, // Set a timeout of 20 seconds
        ]);

        // $order->update_status('wc-pronto-received', 'Order marked as Pronto Received after successful API sync.');
        // error_log('Order '.$order_id.' updated to Pronto Received by API sync.');

        if (is_wp_error($response)) {
            WCOSPA_API_Client::log('Sync API request failed: '.$response->get_error_message());

            return $response;
        }

        // Check if the response body is empty
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            WCOSPA_API_Client::log('Sync response body is empty.');

            return new WP_Error('empty_response', 'The API returned an empty response.');
        }

        // Decode the response body and log for debugging
        $body = json_decode($body, true);
        WCOSPA_API_Client::log('Sync response body: '.print_r($body, true));

        // Extract the Transaction UUID from the apitransactions array
        if (isset($body['apitransactions'][0]['uuid'])) {
            $transaction_uuid = $body['apitransactions'][0]['uuid'];
            update_post_meta($order_id, '_wcospa_transaction_uuid', $transaction_uuid);
            WCOSPA_API_Client::log('Stored Transaction UUID: '.$transaction_uuid);

            return $transaction_uuid;
        } else {
            WCOSPA_API_Client::log('Transaction UUID not found in sync response.');

            return new WP_Error('uuid_not_found', 'Transaction UUID not found.');
        }
    }

    // Step 2: Retrieve transaction status and get Pronto Order Number
    public static function fetch_order_status($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: '.$order_id);
        }

        // Retrieve the stored Transaction UUID
        $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
        if (!$transaction_uuid) {
            return new WP_Error('uuid_not_found', 'Transaction UUID not found in order.');
        }

        // Get API credentials
        $credentials = WCOSPA_Credentials::get_api_credentials();
        $transaction_url = $credentials['get_transaction'].'?uuid='.$transaction_uuid;

        // Log the transaction URL for debugging
        WCOSPA_API_Client::log('Transaction URL: '.$transaction_url);

        // Make the GET request to the API
        $response = wp_remote_get($transaction_url, [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($credentials['username'].':'.$credentials['password']),
            ],
            'timeout' => 20, // Set a timeout of 20 seconds
        ]);

        if (is_wp_error($response)) {
            WCOSPA_API_Client::log('Fetch API request failed: '.$response->get_error_message());

            return $response;
        }

        // Get the full response body as raw text (HTML)
        $raw_body = wp_remote_retrieve_body($response);

        // Log the raw HTML body for debugging (this will show the source text in the log)
        WCOSPA_API_Client::log('Raw Response Body: '.$raw_body);

        // Check if the response is an HTML redirect
        if (stripos($raw_body, '<html>') !== false) {
            WCOSPA_API_Client::log('HTML response detected: '.$raw_body);

            return new WP_Error('html_response_detected', 'The API returned an HTML response.');
        }

        // Decode the JSON response
        $body = json_decode($raw_body, true);
        WCOSPA_API_Client::log('Fetch response body (decoded): '.print_r($body, true));

        if (!isset($body['apitransactions'][0]['result_url'])) {
            WCOSPA_API_Client::log('Pronto Order number not found in fetch response.');

            return new WP_Error('order_number_not_found', 'Pronto Order number not found.');
        }

        // Extract the Pronto Order number from the result URL
        $result_url = $body['apitransactions'][0]['result_url'];
        preg_match('/number=(\d+)/', $result_url, $matches);
        if (!isset($matches[1])) {
            return new WP_Error('invalid_result_url', 'Invalid result URL format.');
        }
        $pronto_order_number = $matches[1];

        // Store the Pronto Order number in the order meta
        update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number);

        WCOSPA_API_Client::log('Stored Pronto Order number: '.$pronto_order_number);

        return $pronto_order_number;
    }

    // Step 3: Get Pronto Order details using the Pronto Order Number
    public static function get_pronto_order_details($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: '.$order_id);
        }

        // Retrieve the Pronto Order Number
        $pronto_order_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        if (!$pronto_order_number) {
            return new WP_Error('pronto_order_number_not_found', 'Pronto Order number not found in order.');
        }

        // Get API credentials
        $credentials = WCOSPA_Credentials::get_api_credentials();
        $order_url = $credentials['get_order'].'?number='.$pronto_order_number;

        // Log the order URL for debugging
        WCOSPA_API_Client::log('Order URL: '.$order_url);

        // Make the GET request to the API
        $response = wp_remote_get($order_url, [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($credentials['username'].':'.$credentials['password']),
            ],
            'timeout' => 20, // Set a timeout of 20 seconds
        ]);

        if (is_wp_error($response)) {
            WCOSPA_API_Client::log('Pronto Order API request failed: '.$response->get_error_message());

            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        WCOSPA_API_Client::log('Pronto Order response body: '.print_r($body, true));

        if (empty($body)) {
            return new WP_Error('empty_response', 'The API returned an empty response.');
        }

        return $body; // Return the Pronto order details
    }

    public static function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Write the log message to the WordPress debug log
            error_log('[WCOSPA] '.$message);
        }
    }
}
