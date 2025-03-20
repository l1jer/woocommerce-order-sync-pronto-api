<?php

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Credentials {
    
    // Define constants for easier maintenance
    const DEFAULT_DEBTOR_CODE = '210942';
    
    /**
     * Get the API credentials for connecting to Pronto
     * Enable test mode by defining WCOSPA_TEST_MODE as true in wp-config.php
     * or by setting it per order
     * 
     * @param int $order_id Optional order ID to check for per-order environment setting
     * @return array The API credentials
     */
    public static function get_api_credentials($order_id = null) {
        // First check for per-order environment setting
        $use_test_env = false;
        
        if ($order_id) {
            $order_env = get_post_meta($order_id, '_wcospa_test_environment', true);
            if (!empty($order_env)) {
                $use_test_env = $order_env === 'test';
            }
        }
        
        // If no per-order setting, check for global setting
        if (!$order_id || empty($order_env)) {
            $use_test_env = defined('WCOSPA_TEST_MODE') && WCOSPA_TEST_MODE === true;
        }
        
        if ($use_test_env) {
            // Test environment
            return [
                'post_order' => 'https://tasco-750-test.prontoavenue.biz/api/json/order/v6.json/',
                'get_transaction' => 'https://tasco-750-test.prontoavenue.biz/api/json/transaction/v5.json/',
                'get_order' => 'https://tasco-750-test.prontoavenue.biz/api/json/order/v4.json/',
                'username' => 'jerry@tasco.com.au',
                'password' => 'x$ArLvH*JgFsrHoQyDwwzQ)n',
            ];
        } else {
            // Production environment
            return [
                'post_order' => 'https://sales.tasco.net.au/api/json/order/v6.json/',
                'get_transaction' => 'https://sales.tasco.net.au/api/json/transaction/v5.json/',
                'get_order' => 'https://sales.tasco.net.au/api/json/order/v4.json/',
                'username' => 'jerry@tasco.com.au',
                'password' => 'x$ArLvH*JgFsrHoQyDwwzQ)n',
            ];
        }
    }
    
    /**
     * Get the debtor code based on the current website domain
     * 
     * @return string The appropriate debtor code for the current site
     */
    public static function get_debtor_code() {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        // Website-specific debtor codes
        $debtor_codes = [
            // Production sites
            'nitecoreaustralia.com.au' => '211023',
            'zerotech.com.au' => self::DEFAULT_DEBTOR_CODE,
            'shop.zerotechoptics.com' => self::DEFAULT_DEBTOR_CODE,
            'skywatcheraustralia.com.au' => '210943', // Replace SKYWATCH_PLACEHOLDER with actual code
            
            // Development/staging environments - specific matches only
            'localhost' => self::DEFAULT_DEBTOR_CODE,
        ];
        
        // Option 1: Check for specific domain match
        if (isset($debtor_codes[$domain])) {
            return $debtor_codes[$domain];
        }
        
        // Option 2: Check for staging environments with patterns like stagingX.domain.com
        $staging_patterns = [
            '/^staging\d*\.nitecoreaustralia\.com\.au$/' => '211023',
            '/^staging\d*\.zerotech\.com\.au$/' => self::DEFAULT_DEBTOR_CODE,
            '/^staging\d*\.zerotechoptics\.com$/' => self::DEFAULT_DEBTOR_CODE,
            '/^staging\d*\.skywatcheraustralia\.com\.au$/' => '210943', // Same as production
        ];
        
        foreach ($staging_patterns as $pattern => $debtor_code) {
            if (preg_match($pattern, $domain)) {
                return $debtor_code;
            }
        }
        
        // Option 3: Check for generic development/test environments
        $dev_environments = [
            'localhost', 
            '127.0.0.1', 
            '.test', 
            '.local', 
            '.dev', 
            'dev.'
        ];
        
        foreach ($dev_environments as $dev_env) {
            if (strpos($domain, $dev_env) !== false) {
                // This is a development environment
                return defined('WCOSPA_DEV_DEBTOR_CODE') ? WCOSPA_DEV_DEBTOR_CODE : self::DEFAULT_DEBTOR_CODE;
            }
        }
        
        // Option 4: Fall back to default production code
        return self::DEFAULT_DEBTOR_CODE;
    }
}