<?php
/**
 * WooCommerce integration admin settings.
 *
 * @category Core
 * @package  MDS_WooCommerce_Integration
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @license  ©2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'mds_rm_network_settings_fields', 'mds_woo_network_settings' );
add_filter( 'mds_rm_network_settings_tabs', 'mds_woo_network_settings_tab' );

/**
 * Builds the WooCommerce network settings section.
 *
 * @category Core
 * @package  MDS_WooCommerce_Integration
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @license  ©2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */
/**
 * Append the integration settings to the Release Manager settings.
 *
 * @param  string $html Existing network settings markup.
 * @return string
 */
function mds_woo_render_network_settings( $html ) {
	if ( ! mds_woo_is_active() ) {
		return $html;
	}

	$sites            = function_exists( 'get_sites' ) ? get_sites() : array();
	$integrated_sites = array_map( 'intval', (array) get_site_option( 'mds_rm_wc_integrated_sites', array() ) );
	$site_data        = array();

	foreach ( $sites as $site ) {
		$site_details = get_blog_details( $site->blog_id );
		$site_data[]  = array(
			'site_id'       => (int) $site->blog_id,
			'site_name'     => $site_details ? $site_details->blogname : '',
			'site_url'      => $site_details ? $site_details->siteurl : '',
			'is_integrated' => in_array( (int) $site->blog_id, $integrated_sites, true ),
		);
	}

	return $html . mds_woo_render_template(
		'network-settings-section.php',
		array(
			'sites' => $site_data,
		)
	);
}

/**
 * Render WooCommerce network settings.
 *
 * @param  string $html Existing network settings markup.
 *
 * @return string
 */
function mds_woo_network_settings( $html ) {
	if ( class_exists( 'MDS_RM_Network_Admin' ) && method_exists( 'MDS_RM_Network_Admin', 'extension_tabs' ) ) {
		return $html;
	}
	return mds_woo_render_network_settings( $html );
}

/**
 * Register the WooCommerce network settings tab.
 *
 * @param array $tabs Existing extension tabs.
 * @return array
 */
function mds_woo_network_settings_tab( $tabs ) {
	if ( mds_woo_is_active() ) {
		$tabs['woocommerce'] = array(
			'label'  => __( 'WooCommerce', 'mds-woocommerce-integration' ),
			'render' => 'mds_woo_render_network_settings_tab',
		);
	}
	return $tabs;
}

/**
 * Render WooCommerce settings in their own form and save independently.
 */
function mds_woo_render_network_settings_tab() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage network settings.', 'mds-woocommerce-integration' ) );
	}
	?>
	<form id="mds-woo-network-settings-form">
		<div class="mds-panel">
			<h2><?php esc_html_e( 'WooCommerce Sites', 'mds-woocommerce-integration' ); ?></h2>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its dynamic values.
			echo mds_woo_render_network_settings( '' );
			?>
		</div>
		<?php submit_button( __( 'Save WooCommerce Settings', 'mds-woocommerce-integration' ) ); ?>
	</form>
	<?php
}
