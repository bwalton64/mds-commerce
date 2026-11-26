<?php
/**
 * Release-to-Product drift detection and selective synchronization.
 *
 * Compares monitored Hub Release fields (UPC/GTIN, Featured Image, Title) against
 * a linked WooCommerce product and applies only the fields an admin explicitly approves.
 *
 * @package MDS\Commerce
 * @since   2.1.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Support;

use MDS\Commerce\Rest\RestApi;
use WC_Data_Exception;
use WC_Product;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Detects differences between a Hub Release and a linked WooCommerce Product
 * and applies selective admin-approved updates on the target store.
 *
 * @since 2.1.0
 */
final class ProductDrift {

	/**
	 * Allowed field keys accepted by selective product synchronization.
	 *
	 * @since 2.1.0
	 * @var array<int, string>
	 */
	public const ALLOWED_FIELDS = array( 'upc', 'sku', 'thumbnail', 'title' );

	/**
	 * Detect differences between a Hub Release and a normalized linked Product summary.
	 *
	 * Runs on the Hub with zero extra `switch_to_blog()` calls because `format_product_summary()`
	 * already supplies `gtin`, `sku`, `title`, `thumbnail_id`, and `hub_media_id`.
	 *
	 * @since 2.1.0
	 *
	 * @param WP_Post              $release         Hub Release post object.
	 * @param array<string, mixed> $product_summary Normalized product summary from `RestApi::format_product_summary()`.
	 * @param int                  $store_id        Target WooCommerce store blog ID.
	 * @return array{has_drift: bool, count: int, items: array<int, array<string, mixed>>} Drift report payload.
	 */
	public static function detect( WP_Post $release, array $product_summary, int $store_id ): array {
		$items = array();

		// 1. UPC / EAN vs. WooCommerce GTIN (`_global_unique_id`), plus optional SKU match.
		$raw_release_upc  = get_post_meta( (int) $release->ID, 'upc_ean', true );
		$release_upc_str  = is_scalar( $raw_release_upc ) ? trim( (string) $raw_release_upc ) : '';
		$norm_release_upc = self::normalize_digits( $release_upc_str );

		$product_gtin_str  = isset( $product_summary['gtin'] ) && is_scalar( $product_summary['gtin'] )
			? trim( (string) $product_summary['gtin'] )
			: '';
		$norm_product_gtin = self::normalize_digits( $product_gtin_str );

		if ( '' !== $norm_release_upc && $norm_release_upc !== $norm_product_gtin ) {
			$product_sku_str = isset( $product_summary['sku'] ) && is_scalar( $product_summary['sku'] )
				? trim( (string) $product_summary['sku'] )
				: '';
			$norm_sku        = self::normalize_digits( $product_sku_str );

			$sku_matches_old_upc = '' !== $product_sku_str
				&& $norm_sku !== $norm_release_upc
				&& (
					( '' !== $norm_product_gtin && $norm_sku === $norm_product_gtin )
					|| ( '' === $norm_product_gtin && 1 === preg_match( '/^\d{8,14}$/', $product_sku_str ) )
				);

			$items[] = array(
				'field'         => 'upc',
				'label'         => __( 'UPC / EAN (GTIN)', 'mds-commerce' ),
				'product_value' => '' !== $product_gtin_str ? $product_gtin_str : __( 'Not set', 'mds-commerce' ),
				'release_value' => $release_upc_str,
				'sku_option'    => array(
					'available'   => $sku_matches_old_upc,
					'current_sku' => $product_sku_str,
					'new_sku'     => $release_upc_str,
				),
			);
		}

		// 2. Featured Image / Cover Art.
		$hub_thumb_id         = (int) get_post_thumbnail_id( (int) $release->ID );
		$product_thumb_id     = absint( $product_summary['thumbnail_id'] ?? 0 );
		$product_hub_media_id = absint( $product_summary['hub_media_id'] ?? 0 );
		$hub_blog_id          = class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			? \MDS\ReleaseManager\Api\ExtensionApi::hub_id()
			: ( is_multisite() ? (int) get_main_site_id() : (int) get_current_blog_id() );

		if ( $hub_thumb_id > 0 ) {
			$is_same_blog = ( $store_id === $hub_blog_id );
			$thumb_drifts = $is_same_blog
				? ( $product_thumb_id !== $hub_thumb_id )
				: ( $product_thumb_id <= 0 || $product_hub_media_id !== $hub_thumb_id );

			if ( $thumb_drifts ) {
				if ( $product_thumb_id <= 0 ) {
					$product_thumb_label = __( 'No image', 'mds-commerce' );
				} elseif ( ! $is_same_blog && 0 === $product_hub_media_id ) {
					$product_thumb_label = __( 'Custom store image', 'mds-commerce' );
				} else {
					$product_thumb_label = __( 'Previous cover art', 'mds-commerce' );
				}

				$items[] = array(
					'field'         => 'thumbnail',
					'label'         => __( 'Cover Art', 'mds-commerce' ),
					'product_value' => $product_thumb_label,
					'release_value' => __( 'Current Release cover art', 'mds-commerce' ),
				);
			}
		}

		// 3. Release Title vs. Product Name (normalizing typographic ellipsis/quotes to avoid false positives).
		$release_title = trim( html_entity_decode( (string) $release->post_title, ENT_QUOTES, 'UTF-8' ) );
		$product_title = isset( $product_summary['title'] ) && is_scalar( $product_summary['title'] )
			? trim( html_entity_decode( (string) $product_summary['title'], ENT_QUOTES, 'UTF-8' ) )
			: '';

		if ( '' !== $release_title && self::normalize_title( $release_title ) !== self::normalize_title( $product_title ) ) {
			$items[] = array(
				'field'         => 'title',
				'label'         => __( 'Product Title', 'mds-commerce' ),
				'product_value' => '' !== $product_title ? $product_title : __( 'Untitled', 'mds-commerce' ),
				'release_value' => $release_title,
			);
		}

		return array(
			'has_drift' => ! empty( $items ),
			'count'     => count( $items ),
			'items'     => $items,
		);
	}

	/**
	 * Apply selected Release field updates to a WooCommerce product on the current spoke site.
	 *
	 * Must be called while switched to the target store site.
	 *
	 * @since 2.1.0
	 *
	 * @param int                  $product_id Product post ID on the current site.
	 * @param array<string, mixed> $payload    Hub Release data (`title`, `upc`, `hub_thumb_id`, `hub_img_url`, `media_info`).
	 * @param array<int, string>   $fields     Sanitized list of fields to update (`upc`, `sku`, `thumbnail`, `title`).
	 * @return array{updated: array<int, string>, warnings: array<int, string>} Result summary.
	 */
	public static function apply( int $product_id, array $payload, array $fields ): array {
		$updated  = array();
		$warnings = array();

		$want_title     = in_array( 'title', $fields, true );
		$want_upc       = in_array( 'upc', $fields, true );
		$want_sku       = in_array( 'sku', $fields, true );
		$want_thumbnail = in_array( 'thumbnail', $fields, true );

		$wc_product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$needs_save = false;

		// Server-side guard: only allow overwriting SKU when current SKU still matches the previous GTIN/UPC.
		if ( $want_sku ) {
			$current_sku  = $wc_product instanceof WC_Product
				? trim( (string) $wc_product->get_sku() )
				: trim( (string) get_post_meta( $product_id, '_sku', true ) );
			$current_gtin = ( $wc_product instanceof WC_Product && method_exists( $wc_product, 'get_global_unique_id' ) )
				? trim( (string) $wc_product->get_global_unique_id() )
				: trim( (string) get_post_meta( $product_id, '_global_unique_id', true ) );

			$norm_sku  = self::normalize_digits( $current_sku );
			$norm_gtin = self::normalize_digits( $current_gtin );
			$can_sync  = '' === $current_sku
				|| ( '' !== $norm_gtin && $norm_sku === $norm_gtin )
				|| ( '' === $norm_gtin && 1 === preg_match( '/^\d{8,14}$/', $current_sku ) );

			if ( ! $can_sync ) {
				$want_sku   = false;
				$warnings[] = __( 'Skipped SKU update because this product uses a custom SKU.', 'mds-commerce' );
			}
		}

		if ( $wc_product instanceof WC_Product ) {
			if ( $want_title && '' !== (string) ( $payload['title'] ?? '' ) ) {
				$wc_product->set_name( (string) $payload['title'] );
				$needs_save = true;
				$updated[]  = 'title';
			}

			if ( $want_upc && '' !== (string) ( $payload['upc'] ?? '' ) ) {
				if ( method_exists( $wc_product, 'set_global_unique_id' ) ) {
					try {
						$wc_product->set_global_unique_id( (string) $payload['upc'] );
						$needs_save = true;
						$updated[]  = 'upc';
					} catch ( WC_Data_Exception $exception ) {
						$warnings[] = $exception->getMessage();
					}
				} else {
					$sanitized_gtin = preg_replace( '/[^0-9Xx\-]/', '', (string) $payload['upc'] );
					if ( is_string( $sanitized_gtin ) && '' !== $sanitized_gtin ) {
						update_post_meta( $product_id, '_global_unique_id', $sanitized_gtin );
						$updated[] = 'upc';
					}
				}
			}

			if ( $want_sku && '' !== (string) ( $payload['upc'] ?? '' ) ) {
				try {
					$wc_product->set_sku( (string) $payload['upc'] );
					$needs_save = true;
					$updated[]  = 'sku';
				} catch ( WC_Data_Exception $exception ) {
					$warnings[] = $exception->getMessage();
				}
			}

			if ( $needs_save ) {
				$wc_product->save();
			}
		} else {
			$lookup_updates = array();

			if ( $want_title && '' !== (string) ( $payload['title'] ?? '' ) ) {
				wp_update_post(
					array(
						'ID'         => $product_id,
						'post_title' => (string) $payload['title'],
					)
				);
				$updated[] = 'title';
			}

			if ( $want_upc && '' !== (string) ( $payload['upc'] ?? '' ) ) {
				$sanitized_gtin = preg_replace( '/[^0-9Xx\-]/', '', (string) $payload['upc'] );
				if ( is_string( $sanitized_gtin ) && '' !== $sanitized_gtin ) {
					update_post_meta( $product_id, '_global_unique_id', $sanitized_gtin );
					$lookup_updates['global_unique_id'] = $sanitized_gtin;
					$updated[]                          = 'upc';
				}
			}

			if ( $want_sku && '' !== (string) ( $payload['upc'] ?? '' ) ) {
				$new_sku = (string) $payload['upc'];
				if ( self::sku_exists_on_other_product( $new_sku, $product_id ) ) {
					$warnings[] = __( 'Skipped SKU update because another product on this store already uses that SKU.', 'mds-commerce' );
				} else {
					update_post_meta( $product_id, '_sku', $new_sku );
					$lookup_updates['sku'] = $new_sku;
					$updated[]             = 'sku';
				}
			}

			if ( ! empty( $lookup_updates ) ) {
				self::sync_wc_lookup_table( $product_id, $lookup_updates );
			}
		}

		if ( $want_thumbnail && ! empty( $payload['hub_thumb_id'] ) ) {
			RestApi::seed_product_thumbnail(
				$product_id,
				(int) $payload['hub_thumb_id'],
				(string) ( $payload['hub_img_url'] ?? '' ),
				is_array( $payload['media_info'] ?? null ) ? $payload['media_info'] : array()
			);
			$updated[] = 'thumbnail';
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
		clean_post_cache( $product_id );

		return array(
			'updated'  => array_values( array_unique( $updated ) ),
			'warnings' => $warnings,
		);
	}

	/**
	 * Strip non-digit characters for reliable UPC/EAN/GTIN comparisons.
	 *
	 * @since 2.1.0
	 *
	 * @param string $value Raw numeric identifier.
	 * @return string Digits-only string.
	 */
	private static function normalize_digits( string $value ): string {
		$digits = preg_replace( '/\D+/', '', $value );
		return is_string( $digits ) ? $digits : '';
	}

	/**
	 * Normalize typographic punctuation (ellipsis, curly quotes) for title drift comparisons.
	 *
	 * @since 2.1.0
	 *
	 * @param string $title Raw title string.
	 * @return string Normalized title string.
	 */
	private static function normalize_title( string $title ): string {
		$replaced = str_replace(
			array( "\u{2026}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}" ),
			array( '...', "'", "'", '"', '"' ),
			$title
		);
		return trim( $replaced );
	}

	/**
	 * Check whether another non-trashed product on the current store already uses a SKU.
	 *
	 * @since 2.1.0
	 *
	 * @param string $sku        Candidate SKU value.
	 * @param int    $product_id Current product ID to exclude.
	 * @return bool True when another product already owns this SKU.
	 */
	private static function sku_exists_on_other_product( string $sku, int $product_id ): bool {
		$matches = get_posts(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'exclude'                => array( $product_id ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_sku', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact SKU collision guard during cross-blog drift sync.
				'meta_value'             => $sku, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact SKU collision guard during cross-blog drift sync.
			)
		);

		return ! empty( $matches );
	}

	/**
	 * Keep WooCommerce's `wc_product_meta_lookup` table aligned during cross-blog meta updates.
	 *
	 * @since 2.1.0
	 *
	 * @param int                   $product_id Product post ID.
	 * @param array<string, string> $updates    Column map (`sku`, `global_unique_id`).
	 * @return void
	 */
	private static function sync_wc_lookup_table( int $product_id, array $updates ): void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}

		$table = $wpdb->prefix . 'wc_product_meta_lookup';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking WooCommerce lookup table existence during cross-blog sync.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$data = array();
		if ( isset( $updates['sku'] ) ) {
			$data['sku'] = $updates['sku'];
		}
		if ( isset( $updates['global_unique_id'] ) ) {
			$has_gtin_col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'global_unique_id' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Checking optional WC 9.2+ column on $wpdb->prefix table.
			if ( ! empty( $has_gtin_col ) ) {
				$data['global_unique_id'] = $updates['global_unique_id'];
			}
		}

		if ( empty( $data ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Syncing WooCommerce product lookup row after cross-blog meta update.
		$wpdb->update(
			$table,
			$data,
			array( 'product_id' => $product_id ),
			array_fill( 0, count( $data ), '%s' ),
			array( '%d' )
		);
	}
}
