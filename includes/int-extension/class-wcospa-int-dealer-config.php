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
                'AU' => ['email' => self::DEFAULT_DEALER_EMAIL],
                'NZ' => ['email' => self::DEFAULT_DEALER_EMAIL],
                // Add more countries as needed
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
} 