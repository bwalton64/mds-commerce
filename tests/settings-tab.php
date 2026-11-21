<?php
/**
 * Standalone checks for the WooCommerce settings tab.
 *
 * Run with: php tests/settings-tab.php
 */

define( 'ABSPATH', __DIR__ );
$GLOBALS['filters'] = array();
$GLOBALS['active'] = true;
$GLOBALS['saved'] = null;
class MDS_RM_Network_Admin {
	public static function extension_tabs() {}
}
class Fake_Request {
	private $params;
	public function __construct( $params ) { $this->params = $params; }
	public function get_body_params() { return $this->params; }
}
function add_filter( $hook, $callback ) { $GLOBALS['filters'][ $hook ] = $callback; }
function mds_woo_is_active() { return $GLOBALS['active']; }
function __( $text, $domain ) { return $text; }
function esc_html__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html_e( $text, $domain ) { echo esc_html__( $text, $domain ); }
function current_user_can( $capability ) { return 'manage_network_options' === $capability; }
function get_sites( $args = array() ) { return empty( $args ) ? array() : array( 2, 3 ); }
function get_site_option( $key, $default ) { return $default; }
function mds_woo_render_template( $template, $args ) { return '<div>WooCommerce sites</div>'; }
function submit_button( $label ) { echo '<button>' . esc_html__( $label, '' ) . '</button>'; }
function absint( $value ) { return abs( (int) $value ); }
function update_site_option( $key, $value ) { $GLOBALS['saved'] = array( $key, $value ); }
function rest_ensure_response( $value ) { return $value; }
require __DIR__ . '/../includes/admin-settings.php';
require __DIR__ . '/../includes/class-mds-woo-rest-api.php';

$tabs = mds_woo_network_settings_tab( array() );
if ( ! isset( $tabs['woocommerce'] ) || ! is_callable( $tabs['woocommerce']['render'] )
	|| 'existing' !== mds_woo_network_settings( 'existing' ) ) {
	throw new RuntimeException( 'WooCommerce must register one tab without duplicating its old section.' );
}
ob_start();
call_user_func( $tabs['woocommerce']['render'] );
$html = ob_get_clean();
if ( false === strpos( $html, 'id="mds-woo-network-settings-form"' ) || false === strpos( $html, 'WooCommerce sites' ) ) {
	throw new RuntimeException( 'WooCommerce tab must render its own form and site settings.' );
}
MdsWooRestApi::saveNetworkSettings( new Fake_Request( array( 'mds_rm_wc_integrated_sites' => array( 2, 3, 99, 2 ) ) ) );
if ( array( 'mds_rm_wc_integrated_sites', array( 2, 3 ) ) !== $GLOBALS['saved'] ) {
	throw new RuntimeException( 'WooCommerce site selections must be limited to known sites.' );
}
MdsWooRestApi::saveNetworkSettings( new Fake_Request( array() ) );
if ( array( 'mds_rm_wc_integrated_sites', array() ) !== $GLOBALS['saved'] ) {
	throw new RuntimeException( 'Clearing all WooCommerce sites must be saved.' );
}
$GLOBALS['active'] = false;
if ( mds_woo_network_settings_tab( array() ) ) {
	throw new RuntimeException( 'The tab must not be registered while the integration is inactive.' );
}
echo "WooCommerce settings tab checks passed.\n";
