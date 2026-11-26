<?php
/**
 * Centralized plugin configuration constants and locale-aware helpers.
 *
 * @package MDS\Commerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Provides Tier 2 system constants, locale-scoped transient keys, and lazy translated labels.
 *
 * @since 1.0.1
 */
final class Config {

	/**
	 * Primary per-site option key storing single-product block injection settings.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const OPTION_SETTINGS = 'mds_cm_wc_product_injection';

	/**
	 * Legacy per-site option key retained for zero-loss migration fallback.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const LEGACY_OPTION_SETTINGS = 'mds_wc_product_injection';

	/**
	 * Option key storing encrypted remote store connections.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const OPTION_STORE_CONNECTIONS = 'mds_cm_store_connections';

	/**
	 * Option key tracking completed schema migration version.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const OPTION_MIGRATED_VERSION = 'mds_cm_migrated_version';

	/**
	 * Artist post meta key storing assigned StoreKey strings (e.g., `wc_local:3`).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const ARTIST_STORES_META_KEY = '_mds_commerce_target_stores';

	/**
	 * Legacy Artist post meta key storing integer WooCommerce blog IDs.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const ARTIST_META_KEY = '_mds_wc_target_stores';

	/**
	 * Artist post meta key storing per-artist variant mapping strategy.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const ARTIST_VARIANT_STRATEGY_META_KEY = '_mds_commerce_variant_strategy';

	/**
	 * Hub Release post meta key storing provider-keyed store links and promo metadata.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const RELEASE_LINKS_META_KEY = '_mds_commerce_links';

	/**
	 * Legacy Hub Release post meta key storing integer-keyed WooCommerce links.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const LEGACY_RELEASE_LINKS_META_KEY = '_mds_linked_products';

	/**
	 * Network transient key caching auto-detected local WooCommerce stores.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const TRANSIENT_NETWORK_STORES = 'mds_cm_network_stores';

	/**
	 * Prefix for plugin transients.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const TRANSIENT_PREFIX = 'mds_cm_';

	/**
	 * Primary REST API namespace.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const REST_NAMESPACE = 'mds-commerce/v1';

	/**
	 * Legacy REST API namespaces retained for one major cycle.
	 *
	 * @since 3.0.0
	 * @var array<int, string>
	 */
	public const LEGACY_REST_NAMESPACES = array( 'mds-wc/v1', 'mds-woo/v1' );

	/**
	 * Allowed per-Artist variant mapping strategies.
	 *
	 * @since 3.0.0
	 * @var array<int, string>
	 */
	public const VARIANT_STRATEGIES = array(
		'variable_product',
		'separate_products',
		'ask_on_seed',
	);

	/**
	 * Allowed per-store synchronization modes.
	 *
	 * @since 3.0.0
	 * @var array<int, string>
	 */
	public const SYNC_MODES = array(
		'webhook',
		'polling',
		'manual',
	);

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
			'meta_box_title'   => __( 'MDS Commerce — Connected Stores', 'mds-commerce' ),
			'artist_box_title' => __( 'MDS Commerce — Target Stores & Variant Strategy', 'mds-commerce' ),
			'create_product'   => __( 'Create Product', 'mds-commerce' ),
			'view_in_store'    => __( 'View in Store', 'mds-commerce' ),
			'draft_pending'    => __( 'Draft — Pending Store Review', 'mds-commerce' ),
			'all_media_format' => __( 'All', 'mds-commerce' ),
		);
	}
}
