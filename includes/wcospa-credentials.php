<?php

// This file manages API credentials

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Credentials
{
    public static function get_api_credentials()
    {
        return [
            'post_order' => 'https://tasco-750-test.prontoavenue.biz/api/json/order/v6.json/',
            'get_transaction' => 'https://tasco-750-test.prontoavenue.biz/api/json/transaction/v5.json/',
            'get_order' => 'https://tasco-750-test.prontoavenue.biz/api/json/order/v4.json/',
            'username' => 'jerry@tasco.com.au',
            'password' => 'x$ArLvH*JgFsrHoQyDwwzQ)n',
        ];
    }
}