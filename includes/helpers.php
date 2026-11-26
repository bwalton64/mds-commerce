<?php
/**
 * Procedural helper wrappers for MDS Commerce (with legacy mds_wc_* shims).
 *
 * @package MDS\Commerce
 * @since   1.0.1
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use MDS\Commerce\Plugin;
use MDS\Commerce\Support\TemplateRenderer;

if ( ! function_exists( 'mds_cm_is_active' ) ) {
	/**
	 * Check whether the Release Manager plugin is active.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True when MDS Release Manager is active.
	 */
	function mds_cm_is_active(): bool {
		return Plugin::is_active();
	}
}

if ( ! function_exists( 'mds_cm_render_template' ) ) {
	/**
	 * Load an MDS Commerce PHP template and return its rendered output.
	 *
	 * @since 3.0.0
	 *
	 * @param string               $template_name Template filename.
	 * @param array<string, mixed> $args          Template arguments.
	 * @return string Rendered template HTML.
	 */
	function mds_cm_render_template( string $template_name, array $args = array() ): string {
		return TemplateRenderer::render( $template_name, $args );
	}
}

if ( ! function_exists( 'mds_wc_is_active' ) ) {
	/**
	 * Backward-compatible alias for mds_cm_is_active().
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when MDS Release Manager is active.
	 */
	function mds_wc_is_active(): bool {
		return mds_cm_is_active();
	}
}

if ( ! function_exists( 'mds_wc_render_template' ) ) {
	/**
	 * Backward-compatible alias for mds_cm_render_template().
	 *
	 * @since 1.0.1
	 *
	 * @param string               $template_name Template filename.
	 * @param array<string, mixed> $args          Template arguments.
	 * @return string Rendered template HTML.
	 */
	function mds_wc_render_template( string $template_name, array $args = array() ): string {
		return mds_cm_render_template( $template_name, $args );
	}
}
