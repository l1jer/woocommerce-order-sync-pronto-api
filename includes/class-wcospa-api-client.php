<?php

declare(strict_types=1);

/**
 * Class WCOSPA_API_Client
 * 
 * Handles API communication with Pronto system.
 * 
 * @since 1.0.0
 */
class WCOSPA_API_Client
{
    /**
     * Submit order information and retrieve the transaction UUID
     *
     * @param int $order_id WooCommerce order ID
     * @return string|WP_Error Transaction UUID on success, WP_Error on failure
     */
    public static function sync_order($order_id)
    {
        if (!is_int($order_id)) {
            $order_id = (int) $order_id;
        }

        $order = wc_get_order($order_id);
        if (!($order instanceof WC_Order)) {
            return new WP_Error('order_not_found', sprintf('Order not found: %d', $order_id));
        }

        try {
            // Get API credentials
            $credentials = WCOSPA_Credentials::get_api_credentials();
            if (!isset($credentials['post_order'])) {
                throw new Exception('API URL not found in credentials');
            }
            $api_url = $credentials['post_order'];

            // Prepare the second argument for the format_order method
            $customer_reference = sprintf('%d / %s', $order->get_id(), strtoupper($order->get_shipping_last_name()));

            // Prepare order data with the second argument (customer_reference)
            $order_data = WCOSPA_Order_Data_Formatter::format_order($order, $customer_reference);

            // Log the sync URL and order data for debugging
            self::log(sprintf('Sync URL: %s', $api_url));
            self::log(sprintf('Order Data: %s', print_r($order_data, true)));

            // Make the POST request to the API to sync the order
            $response = wp_remote_post($api_url, [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', 
                        base64_encode($credentials['username'] . ':' . $credentials['password'])
                    ),
                    'Content-Type' => 'application/json',
                ],
                'body' => function_exists('wp_json_encode') ? wp_json_encode($order_data) : json_encode($order_data),
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                self::log(sprintf('Sync API request failed: %s', $response->get_error_message()));
                return $response;
            }

            // Check if the response body is empty
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                self::log('Sync response body is empty.');
                return new WP_Error('empty_response', 'The API returned an empty response.');
            }

            // Decode the response body and log for debugging
            $body_data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                self::log(sprintf('JSON decode error: %s', json_last_error_msg()));
                return new WP_Error('json_decode_error', 'Failed to decode API response.');
            }

            self::log(sprintf('Sync response body: %s', print_r($body_data, true)));

            // Extract the Transaction UUID from the apitransactions array
            if (!isset($body_data['apitransactions'][0]['uuid'])) {
                self::log(sprintf('Transaction UUID not found in sync response. Response structure: %s', 
                    print_r($body_data, true)
                ));
                return new WP_Error('uuid_not_found', 'Transaction UUID not found in API response.');
            }

            $transaction_uuid = $body_data['apitransactions'][0]['uuid'];
            
            // Store the UUID in order meta
            $updated = update_post_meta($order_id, '_wcospa_transaction_uuid', $transaction_uuid);
            
            if ($updated) {
                self::log(sprintf('Successfully stored Transaction UUID: %s for order %d', 
                    $transaction_uuid, 
                    $order_id
                ));
            } else {
                self::log(sprintf('Failed to store Transaction UUID: %s for order %d', 
                    $transaction_uuid, 
                    $order_id
                ));
            }

            // Log the full transaction details for debugging
            $transaction_details = [
                'uuid' => $transaction_uuid,
                'status' => isset($body_data['apitransactions'][0]['status']) ? $body_data['apitransactions'][0]['status'] : 'Unknown',
                'errors' => isset($body_data['apitransactions'][0]['errors']) ? $body_data['apitransactions'][0]['errors'] : null,
                'warnings' => isset($body_data['apitransactions'][0]['warnings']) ? $body_data['apitransactions'][0]['warnings'] : null,
                'result_url' => isset($body_data['apitransactions'][0]['result_url']) ? $body_data['apitransactions'][0]['result_url'] : null
            ];
            
            self::log(sprintf('Transaction Details: %s', print_r($transaction_details, true)));

            return $transaction_uuid;

        } catch (Exception $e) {
            self::log(sprintf('Exception occurred: %s', $e->getMessage()));
            return new WP_Error('sync_exception', $e->getMessage());
        }
    }

    /**
     * Retrieve transaction status and get Pronto Order Number
     *
     * @param int|string $order_id WooCommerce order ID
     * @return string|WP_Error Pronto Order Number on success, WP_Error on failure
     */
    public static function fetch_order_status($order_id)
    {
        if (!is_int($order_id)) {
            $order_id = (int) $order_id;
        }

        $order = wc_get_order($order_id);
        if (!($order instanceof WC_Order)) {
            return new WP_Error('order_not_found', sprintf('Order not found: %d', $order_id));
        }

        try {
            // Retrieve the stored Transaction UUID
            $transaction_uuid = get_post_meta($order_id, '_wcospa_transaction_uuid', true);
            if (empty($transaction_uuid)) {
                return new WP_Error('uuid_not_found', 'Transaction UUID not found in order.');
            }

            // Get API credentials
            $credentials = WCOSPA_Credentials::get_api_credentials();
            if (!isset($credentials['get_transaction'])) {
                throw new Exception('Transaction URL not found in credentials');
            }

            $transaction_url = $credentials['get_transaction'] . '?uuid=' . urlencode($transaction_uuid);

            // Log the transaction URL for debugging
            self::log(sprintf('Transaction URL: %s', $transaction_url));

            // Make the GET request to the API
            $response = wp_remote_get($transaction_url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
                ],
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                self::log('Fetch API request failed: ' . $response->get_error_message());
                return $response;
            }

            // Get the response body
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                self::log('Empty response received from API');
                return new WP_Error('empty_response', 'Empty response received from API');
            }

            // Decode the JSON response
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                self::log('Failed to decode JSON response: ' . json_last_error_msg());
                return new WP_Error('json_decode_error', 'Failed to decode API response');
            }

            // Log the raw response for debugging
            self::log('Raw Response Body: ' . $body);

            // Check if we have the required data
            if (!isset($data['apitransactions'][0]['result_url'])) {
                self::log('No result_url found in response');
                return new WP_Error('no_result_url', 'No result URL found in response');
            }

            // Extract order number from result_url
            if (preg_match('/number=(\d+)/', $data['apitransactions'][0]['result_url'], $matches)) {
                $pronto_order_number = $matches[1];
                self::log(sprintf('Successfully extracted Pronto Order Number: %s', $pronto_order_number));
                return $pronto_order_number;
            }

            self::log('Could not extract order number from result_url');
            return new WP_Error('no_order_number', 'Could not extract order number from result URL');

        } catch (Exception $e) {
            self::log('Exception occurred: ' . $e->getMessage());
            return new WP_Error('fetch_exception', $e->getMessage());
        }
    }

    // Step 3: Get Pronto Order details using the Pronto Order Number
    public static function get_pronto_order_details($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: ' . $order_id);
        }

        // Retrieve the Pronto Order Number
        $pronto_order_number = get_post_meta($order_id, '_wcospa_pronto_order_number', true);
        if (!$pronto_order_number) {
            return new WP_Error('pronto_order_number_not_found', 'Pronto Order number not found in order.');
        }

        // Get API credentials
        $credentials = WCOSPA_Credentials::get_api_credentials();
        $order_url = $credentials['get_order'] . '?number=' . $pronto_order_number;

        // Log the order URL for debugging
        self::log('Order URL: ' . $order_url);

        // Make the GET request to the API
        $response = wp_remote_get($order_url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            self::log('Pronto Order API request failed: ' . $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        self::log('Pronto Order response body: ' . print_r($body, true));

        if (empty($body) || !isset($body['orders']) || !is_array($body['orders']) || empty($body['orders'])) {
            return new WP_Error('empty_response', 'The API returned an invalid response.');
        }

        // Get the first order from the orders array
        $order_details = $body['orders'][0];

        // Check if consignment_note exists
        if (!isset($order_details['consignment_note'])) {
            return new WP_Error('no_consignment_note', 'No consignment note found in order details.');
        }

        // Return just the order details instead of the whole response
        return $order_details;
    }

    public static function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Write the log message to the WordPress debug log
            error_log('[WCOSPA] ' . $message);
        }
    }

    private static function handle_order_error($order, $order_id)
    {
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: ' . $order_id);
        }
        return false;
    }

    private static function make_api_request($url, $method = 'GET', $body = null)
    {
        $credentials = WCOSPA_Credentials::get_api_credentials();
        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
        ];

        if ($body) {
            $args['body'] = json_encode($body);
        }

        return $method === 'GET' ? wp_remote_get($url, $args) : wp_remote_post($url, $args);
    }
}
