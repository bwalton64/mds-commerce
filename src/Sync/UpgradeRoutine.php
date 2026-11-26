<?php
/**
 * Idempotent zero-loss data upgrade routine from mds-woocommerce (2.x) to mds-commerce (3.0.0).
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Sync;

use MDS\Commerce\Config;
use MDS\Commerce\Model\StoreKey;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates legacy integer-keyed WooCommerce options and post meta to provider-keyed MDS Commerce structures
 * while preserving `_mds_local_product_id` on Spoke shadow releases unchanged.
 *
 * @since 3.0.0
 */
final class UpgradeRoutine {

	/**
	 * Target schema version.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const SCHEMA_VERSION = '3.0.0';

	/**
	 * Run the upgrade routine once when the current site has not yet migrated to 3.0.0.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$current = (string) get_option( Config::OPTION_MIGRATED_VERSION, '' );
		if ( version_compare( $current, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		self::migrate_site_options();

		$is_hub = ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			|| \MDS\ReleaseManager\Api\ExtensionApi::is_hub();

		if ( $is_hub ) {
			self::migrate_hub_artists();
			self::migrate_hub_releases();
		}

		delete_site_transient( 'mds_wc_network_stores' );
		delete_site_transient( 'mds_wc_network_stores_lock' );

		update_option( Config::OPTION_MIGRATED_VERSION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Copy legacy `mds_wc_product_injection` to `mds_cm_wc_product_injection` when not already set.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function migrate_site_options(): void {
		$existing = get_option( Config::OPTION_SETTINGS, null );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return;
		}

		$legacy = get_option( Config::LEGACY_OPTION_SETTINGS, null );
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			update_option( Config::OPTION_SETTINGS, $legacy, false );
		}
	}

	/**
	 * Convert legacy `_mds_wc_target_stores` integer arrays on Hub Artists to `_mds_commerce_target_stores` StoreKeys.
	 *
	 * @since 3.0.0
	 *
	 * @return int Number of Artist posts migrated.
	 */
	public static function migrate_hub_artists(): int {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}

		$artist_ids = get_posts(
			array(
				'post_type'      => 'artist',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Config::ARTIST_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time migration query.
				'no_found_rows'  => true,
			)
		);

		$migrated = 0;
		foreach ( $artist_ids as $artist_id ) {
			$artist_id = (int) $artist_id;
			$already   = get_post_meta( $artist_id, Config::ARTIST_STORES_META_KEY, true );
			if ( is_array( $already ) && ! empty( $already ) ) {
				continue;
			}

			$legacy = get_post_meta( $artist_id, Config::ARTIST_META_KEY, true );
			if ( ! is_array( $legacy ) || empty( $legacy ) ) {
				continue;
			}

			$store_keys = self::convert_legacy_store_ids( $legacy );
			if ( ! empty( $store_keys ) ) {
				update_post_meta( $artist_id, Config::ARTIST_STORES_META_KEY, $store_keys );
				++$migrated;
			}
		}

		return $migrated;
	}

	/**
	 * Convert legacy `_mds_linked_products` maps on Hub Releases to `_mds_commerce_links`.
	 *
	 * @since 3.0.0
	 *
	 * @return int Number of Release posts migrated.
	 */
	public static function migrate_hub_releases(): int {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}

		$release_ids = get_posts(
			array(
				'post_type'      => 'release',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Config::LEGACY_RELEASE_LINKS_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time migration query.
				'no_found_rows'  => true,
			)
		);

		$migrated = 0;
		foreach ( $release_ids as $release_id ) {
			$release_id = (int) $release_id;
			$already    = get_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, true );
			if ( is_array( $already ) && ! empty( $already ) ) {
				continue;
			}

			$legacy = get_post_meta( $release_id, Config::LEGACY_RELEASE_LINKS_META_KEY, true );
			if ( ! is_array( $legacy ) || empty( $legacy ) ) {
				continue;
			}

			$normalized = self::convert_legacy_release_links( $legacy );
			if ( ! empty( $normalized ) ) {
				update_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, $normalized );
				++$migrated;
			}
		}

		return $migrated;
	}

	/**
	 * Convert an array of legacy integer blog IDs (or mixed StoreKey strings) into canonical StoreKey strings.
	 *
	 * @since 3.0.0
	 *
	 * @param array<int, mixed> $legacy_ids Raw blog IDs or StoreKey strings.
	 * @return array<int, string> Deduplicated canonical StoreKey strings.
	 */
	public static function convert_legacy_store_ids( array $legacy_ids ): array {
		$keys = array();
		foreach ( $legacy_ids as $raw ) {
			if ( ! is_int( $raw ) && ! is_string( $raw ) ) {
				continue;
			}
			$parsed = StoreKey::parse( $raw );
			if ( $parsed instanceof StoreKey ) {
				$keys[] = $parsed->to_string();
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Convert a legacy `_mds_linked_products` map (`blog_id => product_id`) to `_mds_commerce_links`.
	 *
	 * @since 3.0.0
	 *
	 * @param array<int|string, mixed> $legacy_links Legacy map.
	 * @return array<string, array<string, mixed>> Provider-keyed links map.
	 */
	public static function convert_legacy_release_links( array $legacy_links ): array {
		$converted = array();
		foreach ( $legacy_links as $raw_store_id => $raw_product ) {
			$store_key = StoreKey::parse( is_int( $raw_store_id ) ? $raw_store_id : (string) $raw_store_id );
			if ( ! $store_key instanceof StoreKey ) {
				continue;
			}

			$remote_id = is_array( $raw_product )
				? (string) ( $raw_product['remote_id'] ?? $raw_product['id'] ?? '' )
				: ( is_scalar( $raw_product ) ? (string) absint( $raw_product ) : '' );

			if ( '' === $remote_id || '0' === $remote_id ) {
				continue;
			}

			$converted[ $store_key->to_string() ] = array(
				'store_key'         => $store_key->to_string(),
				'provider'          => $store_key->provider(),
				'remote_id'         => $remote_id,
				'format'            => is_array( $raw_product ) ? (string) ( $raw_product['format'] ?? '' ) : '',
				'promo_badge'       => is_array( $raw_product ) ? (string) ( $raw_product['promo_badge'] ?? '' ) : '',
				'coupon_code'       => is_array( $raw_product ) ? (string) ( $raw_product['coupon_code'] ?? '' ) : '',
				'promo_expires_gmt' => is_array( $raw_product ) ? (string) ( $raw_product['promo_expires_gmt'] ?? '' ) : '',
				'priority_weight'   => is_array( $raw_product ) ? (int) ( $raw_product['priority_weight'] ?? 0 ) : 0,
			);
		}

		return $converted;
	}
}
