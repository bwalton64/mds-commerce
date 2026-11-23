<?php
/**
 * Centralized plugin configuration constants and locale-aware helpers.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Provides Tier 2 system constants, locale-scoped transient keys, and lazy translated labels.
 *
 * @since 1.0.1
 */
final class Config {

	/**
	 * Primary network site option key storing integrated WooCommerce store IDs.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const OPTION_SETTINGS = 'mds_rm_wc_integrated_sites';

	/**
	 * Prefix for plugin transients.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const TRANSIENT_PREFIX = 'mds_wc_';

	/**
	 * Primary REST API namespace.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const REST_NAMESPACE = 'mds-wc/v1';

	/**
	 * Build a locale-aware transient key for cached plugin data.
	 *
	 * @since 1.0.1
	 *
	 * @param string $suffix Descriptive transient suffix.
	 * @return string Locale-scoped transient key.
	 */
	public static function transient_key( string $suffix ): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		return self::TRANSIENT_PREFIX . sanitize_key( $suffix ) . '_' . sanitize_key( (string) $locale );
	}

	/**
	 * Return lazily translated default UI labels (never called at class-load time).
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, string> Default translated UI labels.
	 */
	public static function get_default_labels(): array {
		return array(
			'meta_box_title'   => __( 'WooCommerce Integration', 'mds-woocommerce-integration' ),
			'network_tab'      => __( 'WooCommerce', 'mds-woocommerce-integration' ),
			'create_product'   => __( 'Create Product', 'mds-woocommerce-integration' ),
			'view_in_store'    => __( 'View in Store', 'mds-woocommerce-integration' ),
			'draft_pending'    => __( 'Draft — Pending Store Review', 'mds-woocommerce-integration' ),
			'all_media_format' => __( 'All', 'mds-woocommerce-integration' ),
		);
	}
}
