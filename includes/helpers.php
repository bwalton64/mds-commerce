<?php
/**
 * Helper functions for the WooCommerce integration.
 *
 * @package MdsWooCommerceIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check whether the Release Manager plugin is active.
 *
 * @return bool
 */
function mds_woo_is_active() {
	$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

	if ( ! is_array( $active_plugins ) ) {
		$active_plugins = array();
	}

	return in_array( 'mds-release-manager/mds-release-manager.php', $active_plugins, true )
		|| (
			is_multisite()
			&& array_key_exists(
				'mds-release-manager/mds-release-manager.php',
				get_site_option( 'active_sitewide_plugins', array() )
			)
		);
}

/**
 * Load a WooCommerce integration PHP template and return its rendered output.
 *
 * @param string $template_name Template filename.
 * @param array  $args Template arguments.
 * @return string Rendered template.
 */
function mds_woo_render_template( $template_name, $args = array() ) {
	if (
		! is_string( $template_name )
		|| basename( $template_name ) !== $template_name
		|| 'php' !== strtolower( pathinfo( $template_name, PATHINFO_EXTENSION ) )
	) {
		wp_die(
			esc_html__(
				'The requested WooCommerce integration template is invalid.',
				'mds-woocommerce-integration'
			)
		);
		return '';
	}

	if ( ! is_array( $args ) ) {
		wp_die(
			esc_html__(
				'The requested WooCommerce integration template arguments are invalid.',
				'mds-woocommerce-integration'
			)
		);
		return '';
	}

	$theme_template = locate_template( 'mds-woocommerce-integration/' . $template_name );
	$template_path  = $theme_template ? $theme_template : MDS_WOO_PATH . 'templates/' . $template_name;

	if ( ! is_readable( $template_path ) ) {
		wp_die(
			esc_html__(
				'The requested WooCommerce integration template could not be found.',
				'mds-woocommerce-integration'
			)
		);
		return '';
	}

	ob_start();
	load_template( $template_path, false, $args );

	return (string) ob_get_clean();
}
