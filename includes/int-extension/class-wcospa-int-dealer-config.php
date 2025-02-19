<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_INT_Dealer_Config
 * 
 * Handles dealer configuration and JSON file management for INT extension
 */
class WCOSPA_INT_Dealer_Config {
    /**
     * @var string Path to the dealer configuration JSON file
     */
    private string $config_file;

    /**
     * @var array Cached dealer configuration data
     */
    private array $dealer_config;

    /**
     * @var string Default dealer email address
     */
    private const DEFAULT_DEALER_EMAIL = 'jerry@tasco.com.au';

    /**
     * @var array Timezone mapping for each country
     */
    private const COUNTRY_TIMEZONES = [
        'AR' => 'America/Argentina/Buenos_Aires', // Argentina
        'AT' => 'Europe/Vienna',                  // Austria
        'BY' => 'Europe/Minsk',                   // Belarus
        'BE' => 'Europe/Brussels',                // Belgium
        'BA' => 'Europe/Sarajevo',                // Bosnia and Herzegovina
        'DK' => 'Europe/Copenhagen',              // Denmark
        'FI' => 'Europe/Helsinki',                // Finland
        'FR' => 'Europe/Paris',                   // France
        'DE' => 'Europe/Berlin',                  // Germany
        'GR' => 'Europe/Athens',                  // Greece
        'HU' => 'Europe/Budapest',                // Hungary
        'IE' => 'Europe/Dublin',                  // Ireland
        'IT' => 'Europe/Rome',                    // Italy
        'JP' => 'Asia/Tokyo',                     // Japan
        'KP' => 'Asia/Pyongyang',                // Korea, Democratic People's Republic of
        'KR' => 'Asia/Seoul',                    // Korea, Republic of
        'LV' => 'Europe/Riga',                    // Latvia
        'LT' => 'Europe/Vilnius',                 // Lithuania
        'LU' => 'Europe/Luxembourg',              // Luxembourg
        'MC' => 'Europe/Monaco',                  // Monaco
        'NL' => 'Europe/Amsterdam',               // Netherlands
        'NO' => 'Europe/Oslo',                    // Norway
        'PL' => 'Europe/Warsaw',                  // Poland
        'PT' => 'Europe/Lisbon',                  // Portugal
        'RO' => 'Europe/Bucharest',               // Romania
        'ES' => 'Europe/Madrid',                  // Spain
        'SE' => 'Europe/Stockholm',               // Sweden
        'CH' => 'Europe/Zurich',                  // Switzerland
        'UA' => 'Europe/Kiev',                    // Ukraine
        'GB' => 'Europe/London'                   // United Kingdom
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->config_file = plugin_dir_path(__FILE__) . 'data/dealer-config.json';
        $this->init();
    }

    /**
     * Initialize the dealer configuration
     */
    private function init(): void {
        // Create data directory if it doesn't exist
        $data_dir = dirname($this->config_file);
        if (!file_exists($data_dir)) {
            wp_mkdir_p($data_dir);
        }

        // Create default configuration if file doesn't exist
        if (!file_exists($this->config_file)) {
            $this->create_default_config();
        }

        $this->load_config();
    }

    /**
     * Create default dealer configuration
     */
    private function create_default_config(): void {
        $default_config = [
            'dealers' => [
                'AR' => ['email' => self::DEFAULT_DEALER_EMAIL], // Argentina
                'AT' => ['email' => self::DEFAULT_DEALER_EMAIL], // Austria
                'BY' => ['email' => self::DEFAULT_DEALER_EMAIL], // Belarus
                'BE' => ['email' => self::DEFAULT_DEALER_EMAIL], // Belgium
                'BA' => ['email' => self::DEFAULT_DEALER_EMAIL], // Bosnia and Herzegovina
                'DK' => ['email' => self::DEFAULT_DEALER_EMAIL], // Denmark
                'FI' => ['email' => self::DEFAULT_DEALER_EMAIL], // Finland
                'FR' => ['email' => self::DEFAULT_DEALER_EMAIL], // France
                'DE' => ['email' => self::DEFAULT_DEALER_EMAIL], // Germany
                'GR' => ['email' => self::DEFAULT_DEALER_EMAIL], // Greece
                'HU' => ['email' => self::DEFAULT_DEALER_EMAIL], // Hungary
                'IE' => ['email' => self::DEFAULT_DEALER_EMAIL], // Ireland
                'IT' => ['email' => self::DEFAULT_DEALER_EMAIL], // Italy
                'JP' => ['email' => self::DEFAULT_DEALER_EMAIL], // Japan
                'KP' => ['email' => self::DEFAULT_DEALER_EMAIL], // Korea, Democratic People's Republic of
                'KR' => ['email' => self::DEFAULT_DEALER_EMAIL], // Korea, Republic of
                'LV' => ['email' => self::DEFAULT_DEALER_EMAIL], // Latvia
                'LT' => ['email' => self::DEFAULT_DEALER_EMAIL], // Lithuania
                'LU' => ['email' => self::DEFAULT_DEALER_EMAIL], // Luxembourg
                'MC' => ['email' => self::DEFAULT_DEALER_EMAIL], // Monaco
                'NL' => ['email' => self::DEFAULT_DEALER_EMAIL], // Netherlands
                'NO' => ['email' => self::DEFAULT_DEALER_EMAIL], // Norway
                'PL' => ['email' => self::DEFAULT_DEALER_EMAIL], // Poland
                'PT' => ['email' => self::DEFAULT_DEALER_EMAIL], // Portugal
                'RO' => ['email' => self::DEFAULT_DEALER_EMAIL], // Romania
                'ES' => ['email' => self::DEFAULT_DEALER_EMAIL], // Spain
                'SE' => ['email' => self::DEFAULT_DEALER_EMAIL], // Sweden
                'CH' => ['email' => self::DEFAULT_DEALER_EMAIL], // Switzerland
                'UA' => ['email' => self::DEFAULT_DEALER_EMAIL], // Ukraine
                'GB' => ['email' => self::DEFAULT_DEALER_EMAIL]  // United Kingdom
            ],
            'default_email' => self::DEFAULT_DEALER_EMAIL
        ];

        $this->save_config($default_config);
    }

    /**
     * Load dealer configuration from JSON file
     */
    private function load_config(): void {
        $json_content = file_get_contents($this->config_file);
        if ($json_content === false) {
            throw new Exception('Failed to read dealer configuration file');
        }

        $config = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid dealer configuration JSON');
        }

        $this->dealer_config = $config;
    }

    /**
     * Save dealer configuration to JSON file
     *
     * @param array $config Configuration data to save
     */
    private function save_config(array $config): void {
        $json_content = json_encode($config, JSON_PRETTY_PRINT);
        if ($json_content === false) {
            throw new Exception('Failed to encode dealer configuration');
        }

        if (file_put_contents($this->config_file, $json_content) === false) {
            throw new Exception('Failed to save dealer configuration');
        }
    }

    /**
     * Get dealer email for a specific country
     *
     * @param string $country_code Two-letter country code
     * @return string Dealer email address
     */
    public function get_dealer_email(string $country_code): string {
        $country_code = strtoupper($country_code);
        return $this->dealer_config['dealers'][$country_code]['email'] ?? $this->dealer_config['default_email'];
    }

    /**
     * Update dealer email for a specific country
     *
     * @param string $country_code Two-letter country code
     * @param string $email Dealer email address
     */
    public function update_dealer_email(string $country_code, string $email): void {
        $country_code = strtoupper($country_code);
        if (!isset($this->dealer_config['dealers'][$country_code])) {
            $this->dealer_config['dealers'][$country_code] = [];
        }
        
        $this->dealer_config['dealers'][$country_code]['email'] = sanitize_email($email);
        $this->save_config($this->dealer_config);
    }

    /**
     * Get all dealer configurations
     *
     * @return array Dealer configuration array
     */
    public function get_all_dealers(): array {
        return $this->dealer_config['dealers'];
    }

    /**
     * Get timezone for a specific country
     *
     * @param string $country_code Two-letter country code
     * @return string Timezone identifier
     */
    public function get_dealer_timezone(string $country_code): string {
        $country_code = strtoupper($country_code);
        return self::COUNTRY_TIMEZONES[$country_code] ?? 'Europe/London';
    }
} 