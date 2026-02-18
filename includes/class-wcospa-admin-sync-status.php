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
        add_action('wp_ajax_wcospa_debug_scheduled_events', [__CLASS__, 'handle_debug_scheduled_events']);
        add_action('wp_ajax_wcospa_test_shipment_processing', [__CLASS__, 'handle_test_shipment_processing']);
        add_action('wp_ajax_wcospa_reset_scheduled_events', [__CLASS__, 'handle_reset_scheduled_events']);
        add_action('wp_ajax_wcospa_recover_orphaned_orders', [__CLASS__, 'handle_recover_orphaned_orders']);
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
                    <p><?php _e('Site-Specific Debtor Codes:', 'wcospa'); ?></p>
                    <ul>
                        <li>zerotech.com.au / store.zerotechoptics.com: <code>210942</code></li>
                        <li>zerotechoutdoors.com.au: <code>211027</code></li>
                        <li>nitecoreaustralia.com.au: <code>211023</code></li>
                        <li>skywatcheraustralia.com.au: <code>211026</code></li>
                        <li>pulsaroutdoors.com.au: <code>211035</code></li>
                        <li>pulsarvision.com.au: <code>211036</code></li>
                        <li>pulsarwildlife.com.au: <code>211037</code></li>
                    </ul>
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
                <h2><?php _e('Plugin Logs', 'wcospa'); ?></h2>
                <?php 
                $log_stats = WCOSPA_Logger::get_log_stats();
                ?>
                <div class="wcospa-log-stats">
                    <p><strong><?php _e('Log Statistics:', 'wcospa'); ?></strong></p>
                    <ul>
                        <li><?php _e('Total log files:', 'wcospa'); ?> <?php echo esc_html($log_stats['total_files']); ?></li>
                        <li><?php _e('General logs:', 'wcospa'); ?> <?php echo esc_html($log_stats['general_files']); ?></li>
                        <li><?php _e('Order-specific logs:', 'wcospa'); ?> <?php echo esc_html($log_stats['order_files']); ?></li>
                        <li><?php _e('Order directories:', 'wcospa'); ?> <?php echo esc_html($log_stats['order_directories']); ?></li>
                        <li><?php _e('Total size:', 'wcospa'); ?> <?php echo esc_html(size_format($log_stats['total_size'])); ?></li>
                        <li><?php _e('Retention period:', 'wcospa'); ?> 14 days</li>
                        <li><?php _e('Log location:', 'wcospa'); ?> <code>plugins/woocommerce-order-sync-pronto-api/logs/</code></li>
                        <li><?php _e('Order logs path:', 'wcospa'); ?> <code>plugins/woocommerce-order-sync-pronto-api/logs/orders/XXXX/order-XXXX.log</code></li>
                    </ul>
                </div>
            </div>
            
            <div class="wcospa-settings-section">
                <h2><?php _e('Shipment Tracking Management', 'wcospa'); ?></h2>
                <p><?php _e('Recover orphaned orders and manage shipment tracking:', 'wcospa'); ?></p>
                <button id="wcospa-recover-orphaned-orders" class="button button-primary" style="background: #d63638; border-color: #d63638;"><?php _e('Recover Orphaned Orders', 'wcospa'); ?></button>
                <p class="description" style="margin-top: 5px;">
                    <?php _e('This will find all "Preparing to Ship" orders with Pronto numbers but missing shipment tracking setup, and add them to the tracking queue.', 'wcospa'); ?>
                </p>
                <div id="wcospa-recovery-output" style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-left: 4px solid #d63638; display: none;">
                    <h4><?php _e('Recovery Output:', 'wcospa'); ?></h4>
                    <pre id="wcospa-recovery-content"></pre>
                </div>
            </div>

            <div class="wcospa-settings-section">
                <h2><?php _e('Scheduled Events Debug', 'wcospa'); ?></h2>
                <p><?php _e('Debug and test the scheduled shipment tracking system:', 'wcospa'); ?></p>
                <button id="wcospa-debug-scheduled-events" class="button button-secondary"><?php _e('Debug Scheduled Events', 'wcospa'); ?></button>
                <button id="wcospa-test-shipment-processing" class="button button-secondary"><?php _e('Test Shipment Processing', 'wcospa'); ?></button>
                <button id="wcospa-reset-scheduled-events" class="button button-primary"><?php _e('Reset Scheduled Events', 'wcospa'); ?></button>
                <div id="wcospa-debug-output" style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-left: 4px solid #0073aa; display: none;">
                    <h4><?php _e('Debug Output:', 'wcospa'); ?></h4>
                    <pre id="wcospa-debug-content"></pre>
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

    /**
     * Handle debug scheduled events AJAX request
     */
    public static function handle_debug_scheduled_events()
    {
        check_ajax_referer('wcospa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Start output buffering to capture debug output
        ob_start();
        
        // Call the debug function
        WCOSPA_Shipment_Handler::debug_scheduled_events();
        
        // Get the captured debug output from the logger
        $log_file = WCOSPA_Logger::get_recent_logs(100);
        $debug_output = implode("\n", array_slice($log_file, -20)); // Get last 20 log entries
        
        ob_end_clean();
        
        wp_send_json_success(['debug_output' => $debug_output]);
    }

    /**
     * Handle test shipment processing AJAX request
     */
    public static function handle_test_shipment_processing()
    {
        check_ajax_referer('wcospa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        WCOSPA_Logger::info('Manual test of shipment processing initiated from admin');
        
        // Test the shipment processing function
        WCOSPA_Shipment_Handler::process_pending_shipments();
        
        $log_entries = WCOSPA_Logger::get_recent_logs(50);
        $test_output = implode("\n", array_slice($log_entries, -10)); // Get last 10 log entries
        
        wp_send_json_success(['test_output' => $test_output]);
    }

    /**
     * Handle reset scheduled events AJAX request
     */
    public static function handle_reset_scheduled_events()
    {
        check_ajax_referer('wcospa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        WCOSPA_Logger::info('Manual reset of scheduled events initiated from admin');
        
        // Reset the scheduled events
        WCOSPA_Shipment_Handler::deactivate();
        WCOSPA_Shipment_Handler::activate();
        
        $log_entries = WCOSPA_Logger::get_recent_logs(50);
        $reset_output = implode("\n", array_slice($log_entries, -15)); // Get last 15 log entries
        
        wp_send_json_success(['reset_output' => $reset_output]);
    }

    /**
     * Handle recover orphaned orders AJAX request
     */
    public static function handle_recover_orphaned_orders()
    {
        check_ajax_referer('wcospa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        WCOSPA_Logger::info('Manual recovery of orphaned orders initiated from admin');
        
        // Get count before recovery
        global $wpdb;
        $before_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT o.ID)
            FROM {$wpdb->posts} o
            JOIN {$wpdb->postmeta} pm1 ON o.ID = pm1.post_id
            WHERE o.post_type = 'shop_order'
            AND o.post_status = 'wc-preparing-to-ship'
            AND pm1.meta_key = '_wcospa_pronto_order_number'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = o.ID
                AND pm2.meta_key = '_wcospa_shipment_number'
            )
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm3
                WHERE pm3.post_id = o.ID
                AND pm3.meta_key = '_wcospa_shipment_tracking_start'
            )
        ");
        
        // Run recovery
        WCOSPA_Shipment_Handler::recover_orphaned_orders();
        
        // Get the detailed log output
        $log_entries = WCOSPA_Logger::get_recent_logs(100);
        $recovery_output = implode("\n", array_slice($log_entries, -30)); // Get last 30 log entries
        
        $message = sprintf('Recovery complete. Found and recovered %d orphaned order(s).', $before_count);
        WCOSPA_Logger::info($message);
        
        wp_send_json_success([
            'recovery_output' => $recovery_output,
            'message' => $message,
            'recovered_count' => $before_count
        ]);
    }
}

// Initialize the class if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    WCOSPA_Admin_Sync_Status::init();
}
