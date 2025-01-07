class WCOSPA_Assets {
public static function enqueue_admin_assets() {
wp_enqueue_script('wcospa-admin', WCOSPA_URL . 'assets/js/wcospa-admin.js', ['jquery'], WCOSPA_VERSION, true);
wp_enqueue_style('wcospa-admin-style', WCOSPA_URL . 'assets/css/wcospa-admin.css', [], WCOSPA_VERSION);
}
}