<?php
/**
 * Cached WooCommerce store discovery and per-Artist store assignment registry.
 *
 * @package MDS\WooCommerce
 * @since   1.1.0
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Support;

use MDS\WooCommerce\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers active WooCommerce sites across the network (cached) and resolves
 * per-Artist / per-Release target stores with per-request memoization.
 *
 * @since 1.1.0
 */
final class StoreRegistry {

	/**
	 * Artist post meta key storing assigned WooCommerce blog IDs.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const ARTIST_META_KEY = '_mds_wc_target_stores';

	/**
	 * Network transient key caching auto-detected WooCommerce stores.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const NETWORK_TRANSIENT_KEY = 'mds_wc_network_stores';

	/**
	 * Hub site option key marking completion of the one-time legacy Artist backfill.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const MIGRATION_OPTION_KEY = 'mds_wc_artist_stores_migrated';

	/**
	 * Transient TTL in seconds (1 hour).
	 *
	 * @since 1.1.0
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Per-request memoized map of available WooCommerce stores keyed by blog ID.
	 *
	 * @since 1.1.0
	 * @var array<int, array{site_id: int, site_name: string, site_url: string}>|null
	 */
	private static ?array $stores_cache = null;

	/**
	 * Per-request memoized target store IDs keyed by Artist post ID.
	 *
	 * @since 1.1.0
	 * @var array<int, array<int, int>>
	 */
	private static array $artist_stores_cache = array();

	/**
	 * Per-request memoized Artist post IDs keyed by `_mds_artist_uuid`.
	 *
	 * @since 1.1.0
	 * @var array<string, int>
	 */
	private static array $artist_id_by_uuid = array();

	/**
	 * Register meta, cache invalidation, and one-time backfill hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'admin_init', array( self::class, 'maybe_backfill_artist_stores' ) );
		add_action( 'activated_plugin', array( self::class, 'on_plugin_toggled' ), 10, 2 );
		add_action( 'deactivated_plugin', array( self::class, 'on_plugin_toggled' ), 10, 2 );
		add_action( 'wp_insert_site', array( self::class, 'invalidate_cache' ) );
		add_action( 'wp_update_site', array( self::class, 'invalidate_cache' ) );
		add_action( 'wp_delete_site', array( self::class, 'invalidate_cache' ) );
	}

	/**
	 * Register the `_mds_wc_target_stores` post meta key on the `artist` post type.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}

		register_post_meta(
			'artist',
			self::ARTIST_META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_store_ids' ),
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', $post_id );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize a raw list of store blog IDs against currently available WooCommerce stores.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $raw_ids Raw store IDs array.
	 * @return array<int, int> Validated, deduplicated positive integer blog IDs.
	 */
	public static function sanitize_store_ids( mixed $raw_ids ): array {
		if ( ! is_array( $raw_ids ) ) {
			return array();
		}

		$available = array_keys( self::get_available_stores() );
		$ids       = array();

		foreach ( $raw_ids as $raw_id ) {
			if ( ! is_scalar( $raw_id ) ) {
				continue;
			}
			$id = absint( $raw_id );
			if ( $id > 0 && in_array( $id, $available, true ) ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Return all active WooCommerce stores on the site or multisite network (cached).
	 *
	 * @since 1.1.0
	 *
	 * @param bool $force_refresh Whether to bypass both request and network transient caches.
	 * @return array<int, array{site_id: int, site_name: string, site_url: string}> Stores keyed by blog ID.
	 */
	public static function get_available_stores( bool $force_refresh = false ): array {
		if ( ! $force_refresh && null !== self::$stores_cache ) {
			return self::$stores_cache;
		}

		if ( ! is_multisite() ) {
			$stores = array();
			if ( Plugin::is_woocommerce_active_on_current_site() ) {
				$blog_id            = (int) get_current_blog_id();
				$blog_name          = (string) get_bloginfo( 'name' );
				$stores[ $blog_id ] = array(
					'site_id'   => $blog_id,
					'site_name' => '' !== $blog_name
						? $blog_name
						/* translators: %d: WordPress site ID. */
						: sprintf( __( 'Store #%d', 'mds-woocommerce' ), $blog_id ),
					'site_url'  => (string) home_url(),
				);
			}

			self::$stores_cache = $stores;
			return $stores;
		}

		$lock_acquired = false;

		if ( ! $force_refresh && function_exists( 'get_site_transient' ) ) {
			$cached = get_site_transient( self::NETWORK_TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				self::$stores_cache = $cached;
				return $cached;
			}

			// Stampede guard: wait briefly for an in-flight rebuild, then fall back to last-known stores.
			if ( get_site_transient( self::NETWORK_TRANSIENT_KEY . '_lock' ) ) {
				for ( $attempt = 0; $attempt < 3; ++$attempt ) {
					usleep( 100000 );
					$cached = get_site_transient( self::NETWORK_TRANSIENT_KEY );
					if ( is_array( $cached ) ) {
						self::$stores_cache = $cached;
						return $cached;
					}
				}

				$fallback = get_site_option( self::NETWORK_TRANSIENT_KEY . '_fallback', array() );
				return is_array( $fallback ) ? $fallback : array();
			}

			if ( function_exists( 'set_site_transient' ) ) {
				set_site_transient( self::NETWORK_TRANSIENT_KEY . '_lock', '1', 30 );
				$lock_acquired = true;
			}
		}

		$stores = array();
		try {
			$sitewide_plugins  = get_site_option( 'active_sitewide_plugins', array() );
			$wc_network_active = is_array( $sitewide_plugins ) && array_key_exists( 'woocommerce/woocommerce.php', $sitewide_plugins );

			$sites = function_exists( 'get_sites' )
				? get_sites(
					array(
						'number'   => 0,
						'archived' => 0,
						'deleted'  => 0,
						'spam'     => 0,
					)
				)
				: array();

			foreach ( $sites as $site ) {
				$blog_id = (int) $site->blog_id;
				if ( $blog_id <= 0 ) {
					continue;
				}

				$has_wc = $wc_network_active;
				if ( ! $has_wc ) {
					switch_to_blog( $blog_id );
					try {
						$has_wc = Plugin::is_woocommerce_active_on_current_site();
					} finally {
						restore_current_blog();
					}
				}

				if ( ! $has_wc ) {
					continue;
				}

				$site_name = isset( $site->blogname ) ? (string) $site->blogname : '';
				$site_url  = isset( $site->siteurl ) ? (string) $site->siteurl : '';

				$stores[ $blog_id ] = array(
					'site_id'   => $blog_id,
					'site_name' => '' !== $site_name
						? $site_name
						/* translators: %d: Multisite blog ID of the WooCommerce store. */
						: sprintf( __( 'Store #%d', 'mds-woocommerce' ), $blog_id ),
					'site_url'  => $site_url,
				);
			}

			if ( function_exists( 'set_site_transient' ) ) {
				set_site_transient( self::NETWORK_TRANSIENT_KEY, $stores, self::CACHE_TTL );
			}
			if ( function_exists( 'update_site_option' ) ) {
				update_site_option( self::NETWORK_TRANSIENT_KEY . '_fallback', $stores );
			}
		} finally {
			if ( $lock_acquired && function_exists( 'delete_site_transient' ) ) {
				delete_site_transient( self::NETWORK_TRANSIENT_KEY . '_lock' );
			}
		}

		self::$stores_cache = $stores;
		return $stores;
	}

	/**
	 * Check whether a given blog ID is an active WooCommerce store.
	 *
	 * @since 1.1.0
	 *
	 * @param int $site_id Site / blog ID.
	 * @return bool True when WooCommerce is active on the site.
	 */
	public static function is_woocommerce_store( int $site_id ): bool {
		if ( $site_id <= 0 ) {
			return false;
		}

		$stores = self::get_available_stores();

		return isset( $stores[ $site_id ] );
	}

	/**
	 * Return the active WooCommerce target store IDs assigned to an Artist.
	 *
	 * @since 1.1.0
	 *
	 * @param int $artist_id Hub Artist post ID.
	 * @return array<int, int> Deduplicated list of active WooCommerce store IDs.
	 */
	public static function get_artist_target_stores( int $artist_id ): array {
		if ( $artist_id <= 0 ) {
			return array();
		}

		if ( isset( self::$artist_stores_cache[ $artist_id ] ) ) {
			return self::$artist_stores_cache[ $artist_id ];
		}

		if ( ! is_multisite() ) {
			$stores                                  = array_keys( self::get_available_stores() );
			self::$artist_stores_cache[ $artist_id ] = $stores;
			return $stores;
		}

		$raw = get_post_meta( $artist_id, self::ARTIST_META_KEY, true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			self::$artist_stores_cache[ $artist_id ] = array();
			return array();
		}

		$available = array_keys( self::get_available_stores() );
		$valid     = array();

		foreach ( $raw as $site_id ) {
			$id = absint( $site_id );
			if ( $id > 0 && in_array( $id, $available, true ) ) {
				$valid[] = $id;
			}
		}

		$valid                                   = array_values( array_unique( $valid ) );
		self::$artist_stores_cache[ $artist_id ] = $valid;

		return $valid;
	}

	/**
	 * Resolve the Hub Artist post ID associated with a Release post.
	 *
	 * Memoizes `_mds_artist_uuid` lookups per request to prevent N+1 queries during batch syncs.
	 *
	 * @since 1.1.0
	 *
	 * @param int $release_id Hub Release post ID.
	 * @return int Artist post ID, or 0 when unassigned / not found.
	 */
	public static function resolve_artist_id_for_release( int $release_id ): int {
		if ( $release_id <= 0 ) {
			return 0;
		}

		$raw_artist = get_post_meta( $release_id, 'artist_id', true );
		if ( ! is_scalar( $raw_artist ) ) {
			return 0;
		}

		$artist_ref = trim( (string) $raw_artist );
		if ( '' === $artist_ref ) {
			return 0;
		}

		if ( isset( self::$artist_id_by_uuid[ $artist_ref ] ) ) {
			return self::$artist_id_by_uuid[ $artist_ref ];
		}

		if ( ctype_digit( $artist_ref ) ) {
			$candidate_id = absint( $artist_ref );
			if ( $candidate_id > 0 && 'artist' === get_post_type( $candidate_id ) ) {
				self::$artist_id_by_uuid[ $artist_ref ] = $candidate_id;
				return $candidate_id;
			}
		}

		$matches = get_posts(
			array(
				'post_type'              => 'artist',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_mds_artist_uuid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Memoized UUID lookup for Artist-to-Release store resolution.
				'meta_value'             => $artist_ref, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Memoized UUID lookup for Artist-to-Release store resolution.
			)
		);

		$resolved                               = ! empty( $matches ) ? absint( $matches[0] ) : 0;
		self::$artist_id_by_uuid[ $artist_ref ] = $resolved;

		return $resolved;
	}

	/**
	 * Return the active WooCommerce target store IDs for a Release via its assigned Artist.
	 *
	 * @since 1.1.0
	 *
	 * @param int $release_id Hub Release post ID.
	 * @return array<int, int> Deduplicated list of active WooCommerce store IDs.
	 */
	public static function get_release_target_stores( int $release_id ): array {
		if ( ! is_multisite() ) {
			return array_keys( self::get_available_stores() );
		}

		$artist_id = self::resolve_artist_id_for_release( $release_id );
		if ( $artist_id <= 0 ) {
			return array();
		}

		return self::get_artist_target_stores( $artist_id );
	}

	/**
	 * Invalidate the cached network store list when WooCommerce is activated or deactivated.
	 *
	 * @since 1.1.0
	 *
	 * @param string $plugin       Plugin basename being toggled.
	 * @param bool   $network_wide Whether toggled network-wide.
	 * @return void
	 */
	public static function on_plugin_toggled( string $plugin, bool $network_wide = false ): void {
		unset( $network_wide );

		if ( 'woocommerce/woocommerce.php' === $plugin ) {
			self::invalidate_cache();
		}
	}

	/**
	 * Clear both the network transient and per-request store caches.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( self::NETWORK_TRANSIENT_KEY );
		}

		self::reset_request_cache();
	}

	/**
	 * Reset in-memory per-request caches (used after Artist saves and in tests).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset_request_cache(): void {
		self::$stores_cache        = null;
		self::$artist_stores_cache = array();
		self::$artist_id_by_uuid   = array();
	}

	/**
	 * One-time idempotent backfill copying legacy network-wide WooCommerce stores
	 * onto existing Hub Artist posts that do not yet have `_mds_wc_target_stores`.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function maybe_backfill_artist_stores(): void {
		if ( ! is_multisite() || ! Plugin::is_active() ) {
			return;
		}

		if ( '1' === (string) get_option( self::MIGRATION_OPTION_KEY, '0' ) ) {
			return;
		}

		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) && ! \MDS\ReleaseManager\Api\ExtensionApi::is_hub() ) {
			return;
		}

		$legacy_current = (array) get_site_option( 'mds_rm_wc_integrated_sites', array() );
		$legacy_old     = (array) get_site_option( 'mds_rm_woo_sites', array() );
		$legacy_ids     = self::sanitize_store_ids( array_merge( $legacy_current, $legacy_old ) );

		if ( empty( $legacy_ids ) ) {
			update_option( self::MIGRATION_OPTION_KEY, '1', false );
			return;
		}

		$artist_ids = get_posts(
			array(
				'post_type'              => 'artist',
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-time bounded migration batch for existing Hub Artists.
					array(
						'key'     => self::ARTIST_META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $artist_ids as $artist_id ) {
			update_post_meta( (int) $artist_id, self::ARTIST_META_KEY, $legacy_ids );
		}

		if ( count( $artist_ids ) < 100 ) {
			update_option( self::MIGRATION_OPTION_KEY, '1', false );
		}
	}
}
