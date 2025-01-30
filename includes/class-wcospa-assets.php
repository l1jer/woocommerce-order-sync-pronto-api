/**
 * Handles asset management for the WooCommerce Order Sync Pronto API plugin
 * 
 * This class is responsible for enqueuing the necessary JavaScript and CSS files
 * for the plugin's admin interface. It ensures proper loading of assets while
 * following WordPress best practices for asset management.
 */
<?php 
class WCOSPA_Assets {

    /**
     * Enqueues admin assets for the plugin
     * 
     * This method registers and enqueues the following assets:
     * - JavaScript file for admin functionality (wcospa-admin.js)
     * - CSS stylesheet for admin interface (wcospa-admin.css)
     * 
     * The assets are only loaded when needed, following WordPress's performance best practices.
     */
    public static function enqueue_admin_assets() {
        // Enqueue admin JavaScript with jQuery dependency
        wp_enqueue_script(
            'wcospa-admin', 
            WCOSPA_URL . 'assets/js/wcospa-admin.js', 
            ['jquery'], 
            WCOSPA_VERSION, 
            true // Load in footer
        );

        // Enqueue admin CSS styles
        wp_enqueue_style(
            'wcospa-admin-style', 
            WCOSPA_URL . 'assets/css/wcospa-admin.css', 
            [], // No dependencies
            WCOSPA_VERSION
        );
    }
}