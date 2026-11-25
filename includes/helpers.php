<?php
/**
 * Procedural helper wrappers for the WooCommerce integration.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Support\TemplateRenderer;

if ( ! function_exists( 'mds_wc_is_active' ) ) {
	/**
	 * Check whether the Release Manager plugin is active.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when MDS Release Manager is active.
	 */
	function mds_wc_is_active(): bool {
		return Plugin::is_active();
	}
}

if ( ! function_exists( 'mds_wc_render_template' ) ) {
	/**
	 * Load a WooCommerce integration PHP template and return its rendered output.
	 *
	 * @since 1.0.1
	 *
	 * @param string               $template_name Template filename.
	 * @param array<string, mixed> $args          Template arguments.
	 * @return string Rendered template HTML.
	 */
	function mds_wc_render_template( string $template_name, array $args = array() ): string {
		return TemplateRenderer::render( $template_name, $args );
	}
}
