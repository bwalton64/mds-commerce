<?php
/**
 * Template loader and renderer for WooCommerce integration views.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Safely resolves and renders plugin or theme-overridden PHP templates.
 *
 * @since 1.0.1
 */
final class TemplateRenderer {

	/**
	 * Load a WooCommerce integration PHP template and return its rendered output.
	 *
	 * @since 1.0.1
	 *
	 * @param string               $template_name Template filename relative to templates/.
	 * @param array<string, mixed> $args          Template arguments passed via load_template().
	 * @return string Rendered template HTML.
	 */
	public static function render( string $template_name, array $args = array() ): string {
		if (
			basename( $template_name ) !== $template_name
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

		$theme_template = locate_template( 'mds-woocommerce-integration/' . $template_name );
		$template_path  = $theme_template ? $theme_template : MDS_WC_PATH . 'templates/' . $template_name;

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
}
