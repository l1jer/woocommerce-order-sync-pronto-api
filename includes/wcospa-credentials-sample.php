<?php
// This is a sample credentials file. Copy this to wcospa-credentials.php and update with your actual credentials.

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Credentials {

    public static function get_api_credentials() {
        return [
            'api_url' => 'https://your-api-url.com/api/json/order/v6.json/',
            'username' => 'your-username',
            'password' => 'your-password'
        ];
    }
}