<?php
/**
 * Integration bridge for WPC External Variations for WooCommerce (`wpc-external-variations`).
 *
 * Verified against wpc-external-variations v1.1.1:
 * - Detection: `class_exists('WPCleverWpcev')` / `defined('WPCEV_VERSION')`
 * - Variation post meta keys: `wpcev_url` and `wpcev_text`
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Providers\WooCommerceLocal;

defined( 'ABSPATH' ) || exit;

/**
 * Detects WPC External Variations on local/spoke WooCommerce stores and reads/writes
 * external variation URLs for on-demand physical media manufacturers (elasticStage, TrepStar, Diggers Factory, Qrates).
 *
 * @since 3.0.0
 */
final class ExternalVariationsBridge {

	/**
	 * Plugin basename for WPC External Variations for WooCommerce.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const PLUGIN_BASENAME = 'wpc-external-variations/wpc-external-variations.php';

	/**
	 * Exact variation post meta key storing the external checkout URL.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const META_URL = 'wpcev_url';

	/**
	 * Exact variation post meta key storing the external button text override.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const META_TEXT = 'wpcev_text';

	/**
	 * Check whether WPC External Variations is active on the current blog (or network-wide).
	 *
	 * @since 3.0.0
	 *
	 * @return bool True when active on the current site.
	 */
	public static function is_active_on_current_site(): bool {
		if ( class_exists( 'WPCleverWpcev' ) || defined( 'WPCEV_VERSION' ) ) {
			return true;
		}

		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_array( $active_plugins ) && in_array( self::PLUGIN_BASENAME, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$sitewide = get_site_option( 'active_sitewide_plugins', array() );
			return is_array( $sitewide ) && array_key_exists( self::PLUGIN_BASENAME, $sitewide );
		}

		return false;
	}

	/**
	 * Check whether WPC External Variations is active on a specific Multisite blog ID.
	 *
	 * @since 3.0.0
	 *
	 * @param int $blog_id Target Multisite blog ID.
	 * @return bool True when active on that store.
	 */
	public static function is_active_on_blog( int $blog_id ): bool {
		if ( ! is_multisite() || $blog_id <= 0 || (int) get_current_blog_id() === $blog_id ) {
			return self::is_active_on_current_site();
		}

		$sitewide = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $sitewide ) && array_key_exists( self::PLUGIN_BASENAME, $sitewide ) ) {
			return true;
		}

		switch_to_blog( $blog_id );
		try {
			$active_plugins = get_option( 'active_plugins', array() );
			return is_array( $active_plugins ) && in_array( self::PLUGIN_BASENAME, $active_plugins, true );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Read the external variation configuration for a WooCommerce `product_variation` post ID.
	 *
	 * @since 3.0.0
	 *
	 * @param int $variation_id Variation post ID.
	 * @return array{url: string, text: string} External variation link metadata.
	 */
	public static function get_variation_link( int $variation_id ): array {
		if ( $variation_id <= 0 ) {
			return array(
				'url'  => '',
				'text' => '',
			);
		}

		$raw_url  = get_post_meta( $variation_id, self::META_URL, true );
		$raw_text = get_post_meta( $variation_id, self::META_TEXT, true );

		return array(
			'url'  => is_scalar( $raw_url ) ? esc_url_raw( trim( (string) $raw_url ) ) : '',
			'text' => is_scalar( $raw_text ) ? sanitize_text_field( (string) $raw_text ) : '',
		);
	}

	/**
	 * Persist or clear WPC External Variation metadata on a WooCommerce `product_variation` post ID.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $variation_id Variation post ID.
	 * @param string $url          External checkout URL (empty clears the external link).
	 * @param string $button_text  Optional custom button text (e.g., `Order Vinyl via elasticStage`).
	 * @return void
	 */
	public static function set_variation_link( int $variation_id, string $url, string $button_text = '' ): void {
		if ( $variation_id <= 0 ) {
			return;
		}

		$clean_url  = esc_url_raw( trim( $url ) );
		$clean_text = sanitize_text_field( trim( $button_text ) );

		if ( '' === $clean_url ) {
			delete_post_meta( $variation_id, self::META_URL );
			delete_post_meta( $variation_id, self::META_TEXT );
			return;
		}

		update_post_meta( $variation_id, self::META_URL, $clean_url );

		if ( '' !== $clean_text ) {
			update_post_meta( $variation_id, self::META_TEXT, $clean_text );
		} else {
			delete_post_meta( $variation_id, self::META_TEXT );
		}
	}

	/**
	 * Return known physical media POD manufacturer presets for admin quick-selection.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, array{label: string, formats: array<int, string>, model: string, portal_url: string, default_button: string}>
	 */
	public static function get_pod_presets(): array {
		return array(
			'elasticstage'    => array(
				'label'          => 'elasticStage',
				'formats'        => array( 'Vinyl', 'CD' ),
				'model'          => 'external_checkout',
				'portal_url'     => 'https://elasticstage.com/',
				'default_button' => __( 'Order On-Demand via elasticStage', 'mds-commerce' ),
			),
			'trepstar'        => array(
				'label'          => 'TrepStar',
				'formats'        => array( 'CD', 'DVD', 'USB' ),
				'model'          => 'dropship_or_external',
				'portal_url'     => 'https://www.trepstar.com/',
				'default_button' => __( 'Order CD via TrepStar', 'mds-commerce' ),
			),
			'kunaki'          => array(
				'label'          => 'Kunaki',
				'formats'        => array( 'Vinyl', 'CD' ),
				'model'          => 'dropship_or_external',
				'portal_url'     => 'https://www.kunaki.com/',
				'default_button' => __( 'Order On-Demand via Kunaki', 'mds-commerce' ),
			),
			'diggers_factory' => array(
				'label'          => 'Diggers Factory',
				'formats'        => array( 'Vinyl', 'CD', 'Cassette' ),
				'model'          => 'external_checkout',
				'portal_url'     => 'https://www.diggersfactory.com/',
				'default_button' => __( 'Pre-Order via Diggers Factory', 'mds-commerce' ),
			),
			'qrates'          => array(
				'label'          => 'Qrates',
				'formats'        => array( 'Vinyl' ),
				'model'          => 'external_checkout',
				'portal_url'     => 'https://qrates.com/',
				'default_button' => __( 'Order Vinyl via Qrates', 'mds-commerce' ),
			),
		);
	}
}
