<?php
/**
 * Admin Sync Status functionality
 *
 * @package WCOSPA
 * @version 1.5.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WCOSPA_Admin_Sync_Status
 * Handles the sync status admin page functionality
 */
class WCOSPA_Admin_Sync_Status
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_sync_status_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wcospa_clear_all_sync_data', [__CLASS__, 'clear_all_sync_data']);
        add_action('wp_ajax_wcospa_toggle_environment', [__CLASS__, 'handle_environment_toggle']);
        add_action('wp_ajax_wcospa_update_debtor_code', [__CLASS__, 'handle_debtor_code_update']);
        add_action('wp_ajax_wcospa_update_afterpay_code', [__CLASS__, 'handle_afterpay_code_update']);
    }

    public static function add_sync_status_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Order Sync Status', 'wcospa'),
            __('Sync Status', 'wcospa'),
            'manage_woocommerce',
            'wcospa-sync-status',
            [__CLASS__, 'render_sync_status_page']
        );
    }

    public static function render_sync_status_page()
    {
        // Get current settings
        $current_env = WCOSPA_Credentials::get_current_environment();
        $is_production = ($current_env === WCOSPA_Credentials::ENV_PRODUCTION);
        $debtor_code = WCOSPA_Order_Data_Formatter::get_debtor_code();
        $afterpay_code = WCOSPA_Order_Data_Formatter::get_afterpay_code();
        
        // Get site URL
        $site_url = site_url();
        
        ?>
        <div class="wrap wcospa-sync-status">
            <h1><?php _e('WooCommerce Order Sync Pronto API - Order Status', 'wcospa'); ?></h1>
            
            <div class="wcospa-settings-section">
                <h2><?php _e('Environment Settings', 'wcospa'); ?></h2>
                <div class="wcospa-toggle-section">
                    <p><?php _e('Current Environment:', 'wcospa'); ?> 
                        <strong class="wcospa-env-label <?php echo $is_production ? 'env-production' : 'env-test'; ?>">
                            <?php echo $is_production ? __('Production', 'wcospa') : __('Test', 'wcospa'); ?>
                        </strong>
                    </p>
                    <p><?php _e('Current Site URL:', 'wcospa'); ?> <code><?php echo esc_html($site_url); ?></code></p>
                    <button id="wcospa-toggle-environment" class="button" data-nonce="<?php echo wp_create_nonce('wcospa_toggle_environment_nonce'); ?>" data-current="<?php echo esc_attr($current_env); ?>">
                        <?php echo $is_production 
                            ? __('Switch to Test Environment', 'wcospa') 
                            : __('Switch to Production Environment', 'wcospa'); 
                        ?>
                    </button>
                </div>
            </div>
            
            <div class="wcospa-settings-section">
                <h2><?php _e('Debtor Code Configuration', 'wcospa'); ?></h2>
                <div class="wcospa-input-section">
                    <p><?php _e('Current Debtor Code:', 'wcospa'); ?> <strong id="current-debtor-code"><?php echo esc_html($debtor_code); ?></strong></p>
                    <p><?php _e('Default Value:', 'wcospa'); ?> <code><?php echo esc_html(WCOSPA_Order_Data_Formatter::DEFAULT_DEBTOR_CODE); ?></code></p>
                    <label for="wcospa-debtor-code"><?php _e('Custom Debtor Code:', 'wcospa'); ?></label>
                    <input type="text" id="wcospa-debtor-code" value="<?php echo esc_attr($debtor_code); ?>" class="regular-text">
                    <button id="wcospa-update-debtor-code" class="button" data-nonce="<?php echo wp_create_nonce('wcospa_update_debtor_code_nonce'); ?>">
                        <?php _e('Update Debtor Code', 'wcospa'); ?>
                    </button>
                </div>
            </div>
            
            <div class="wcospa-settings-section">
                <h2><?php _e('Afterpay Code Configuration', 'wcospa'); ?></h2>
                <div class="wcospa-input-section">
                    <p><?php _e('Current Afterpay Code:', 'wcospa'); ?> <strong id="current-afterpay-code"><?php echo esc_html($afterpay_code); ?></strong></p>
                    <p><?php _e('Default Value:', 'wcospa'); ?> <code><?php echo esc_html(WCOSPA_Order_Data_Formatter::DEFAULT_AFTERPAY_CODE); ?></code></p>
                    <p><?php _e('Site-Specific Values:', 'wcospa'); ?></p>
                    <ul>
                        <li>zerotech.com.au / store.zerotechoptics.com: <code>AFTER</code></li>
                        <li>nitecoreaustralia.com.au: <code>AFPNIT</code></li>
                        <li>skywatcheraustralia.com.au: <code>AFPSKY</code></li>
                    </ul>
                    <label for="wcospa-afterpay-code"><?php _e('Custom Afterpay Code:', 'wcospa'); ?></label>
                    <input type="text" id="wcospa-afterpay-code" value="<?php echo esc_attr($afterpay_code); ?>" class="regular-text">
                    <button id="wcospa-update-afterpay-code" class="button" data-nonce="<?php echo wp_create_nonce('wcospa_update_afterpay_code_nonce'); ?>">
                        <?php _e('Update Afterpay Code', 'wcospa'); ?>
                    </button>
                </div>
            </div>
            
            <div class="wcospa-settings-section">
                <h2><?php _e('Maintenance', 'wcospa'); ?></h2>
                <button id="wcospa-clear-all-sync-data" class="button button-large"><?php _e('Clear All Sync Data', 'wcospa'); ?></button>
                <p><?php _e('Click "Clear All Sync Data" to reset sync statuses for all orders.', 'wcospa'); ?></p>
            </div>
        </div>
        <?php
    }

    public static function enqueue_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_wcospa-sync-status') {
            return;
        }
        
        // Enqueue CSS - Use the main admin CSS file only
        wp_enqueue_style('wcospa-admin-style', WCOSPA_URL.'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
        
        // Add nonce for AJAX security
        wp_enqueue_script('wcospa-admin', WCOSPA_URL.'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
        wp_localize_script('wcospa-admin', 'wcospaAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcospa_admin_nonce')
        ]);
    }

    public static function clear_all_sync_data()
    {
        // Verify nonce for security
        check_ajax_referer('wcospa_admin_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wcospa'));
        }

        // Use WC_Order_Query instead of get_posts for better compatibility
        try {
            $query = new WC_Order_Query([
                'limit' => -1,
                'return' => 'ids',
                'type' => 'shop_order', // Explicitly set the order type
            ]);
            
            $orders = $query->get_orders();

            if (empty($orders)) {
                wp_send_json_success(__('No orders found to clear.', 'wcospa'));
                return;
            }

            foreach ($orders as $order_id) {
                delete_post_meta($order_id, '_wcospa_transaction_uuid');
                delete_post_meta($order_id, '_wcospa_pronto_order_number');
            }

            wp_send_json_success(sprintf(
                /* translators: %d: number of orders processed */
                __('Sync data cleared for %d orders.', 'wcospa'),
                count($orders)
            ));

        } catch (Exception $e) {
            wc_get_logger()->error(
                'Error clearing sync data: ' . $e->getMessage(),
                ['source' => 'wcospa']
            );
            wp_send_json_error(__('An error occurred while clearing sync data.', 'wcospa'));
        }
    }
    
    /**
     * AJAX handler for toggling environment
     */
    public static function handle_environment_toggle()
    {
        // Verify nonce for security
        check_ajax_referer('wcospa_toggle_environment_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wcospa'));
        }
        
        // Get current environment and toggle it
        $current_env = isset($_POST['current']) ? sanitize_text_field($_POST['current']) : WCOSPA_Credentials::ENV_PRODUCTION;
        $new_env = ($current_env === WCOSPA_Credentials::ENV_PRODUCTION) 
            ? WCOSPA_Credentials::ENV_TEST 
            : WCOSPA_Credentials::ENV_PRODUCTION;
            
        // Set the new environment
        $success = WCOSPA_Credentials::set_environment($new_env);
        
        if ($success) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %s: environment name */
                    __('Environment switched to %s.', 'wcospa'),
                    $new_env === WCOSPA_Credentials::ENV_PRODUCTION ? __('Production', 'wcospa') : __('Test', 'wcospa')
                ),
                'environment' => $new_env,
                'is_production' => ($new_env === WCOSPA_Credentials::ENV_PRODUCTION)
            ]);
        } else {
            wp_send_json_error(__('Failed to update environment setting.', 'wcospa'));
        }
    }
    
    /**
     * AJAX handler for updating debtor code
     */
    public static function handle_debtor_code_update()
    {
        // Verify nonce for security
        check_ajax_referer('wcospa_update_debtor_code_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wcospa'));
        }
        
        // Get debtor code from request
        $debtor_code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        
        if (empty($debtor_code)) {
            wp_send_json_error(__('Debtor code cannot be empty.', 'wcospa'));
            return;
        }
        
        // Set the debtor code
        $success = WCOSPA_Order_Data_Formatter::set_debtor_code($debtor_code);
        
        if ($success) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %s: debtor code */
                    __('Debtor code updated to %s.', 'wcospa'),
                    $debtor_code
                ),
                'code' => $debtor_code
            ]);
        } else {
            wp_send_json_error(__('Failed to update debtor code.', 'wcospa'));
        }
    }
    
    /**
     * AJAX handler for updating Afterpay code
     */
    public static function handle_afterpay_code_update()
    {
        // Verify nonce for security
        check_ajax_referer('wcospa_update_afterpay_code_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wcospa'));
        }
        
        // Get Afterpay code from request
        $afterpay_code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        
        if (empty($afterpay_code)) {
            wp_send_json_error(__('Afterpay code cannot be empty.', 'wcospa'));
            return;
        }
        
        // Set the Afterpay code
        $success = WCOSPA_Order_Data_Formatter::set_afterpay_code($afterpay_code);
        
        if ($success) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %s: afterpay code */
                    __('Afterpay code updated to %s.', 'wcospa'),
                    $afterpay_code
                ),
                'code' => $afterpay_code
            ]);
        } else {
            wp_send_json_error(__('Failed to update Afterpay code.', 'wcospa'));
        }
    }
}

// Initialize the class if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    WCOSPA_Admin_Sync_Status::init();
}
