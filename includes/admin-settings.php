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
	$woo_sites_html   = mds_woo_render_site_checkboxes( $sites, $integrated_sites );

	return $html . mds_woo_render_html_template(
		'network-settings-section.html',
		array(
			'woo_sites_html' => array(
				'value'   => $woo_sites_html,
				'context' => 'html',
			),
		)
	);
}

/**
 * Render all network sites as checkbox rows.
 *
 * @param  array $sites            Network sites.
 * @param  array $integrated_sites Selected site IDs.
 * @return string Checkbox markup.
 */
function mds_woo_render_site_checkboxes( $sites, $integrated_sites ) {
	$html = '';

	foreach ( $sites as $site ) {
		$html .= mds_woo_render_site_checkbox( $site, $integrated_sites );
	}

	return $html;
}

/**
 * Render one network site's checkbox.
 *
 * @param  \WP_Site $site             Network site.
 * @param  array    $integrated_sites Selected site IDs.
 * @return string Checkbox markup.
 */
function mds_woo_render_site_checkbox( $site, $integrated_sites ) {
	$checked = in_array(
		(int) $site->blog_id,
		$integrated_sites,
		true
	) ? 'checked' : '';

	return mds_woo_render_html_template(
		'network-settings-site.html',
		array(
			'site_id_attr' => array(
				'value'   => $site->blog_id,
				'context' => 'attr',
			),
			'site_id_text' => array(
				'value'   => $site->blog_id,
				'context' => 'text',
			),
			'checked'      => array(
				'value'   => $checked,
				'context' => 'attr',
			),
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
	return mds_woo_render_network_settings( $html );
}
