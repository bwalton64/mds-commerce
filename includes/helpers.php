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
 * Render a WooCommerce integration template.
 *
 * @param string $template_name Template filename.
 * @param array  $placeholders Placeholder values.
 * @return string Rendered template.
 */
function mds_woo_render_html_template( $template_name, $placeholders = array() ) {
	return MdsWooTemplateRenderer::render( $template_name, $placeholders );
}
