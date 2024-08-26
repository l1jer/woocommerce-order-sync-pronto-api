<?php
// This file manages API credentials

if (!defined('ABSPATH')) {
    exit;
}

class WCOSPA_Credentials {

    public static function get_api_credentials() {
        return [
            'api_url' => 'https://tasco-750-test.prontoavenue.biz/api/json/order/v6.json/',
            'username' => 'jerry@tasco.com.au',
            'password' => 'x$ArLvH*JgFsrHoQyDwwzQ)n'
        ];
    }
}