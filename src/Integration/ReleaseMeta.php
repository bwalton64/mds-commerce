<?php
/**
 * WooCommerce-specific Release Manager display and synchronization hooks.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Integration;

use MDS\WooCommerce\Plugin;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates MDS Release Manager metadata rendering and shadow-sync hooks with WooCommerce.
 *
 * @since 1.0.1
 */
final class ReleaseMeta {

	/**
	 * Register WooCommerce Release Meta integration hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( '\MDS_RM_Release_Meta' ) || ! Plugin::is_active() ) {
			return;
		}

		add_filter( 'mds_rm_sync_target_sites', array( self::class, 'sync_targets' ) );
		add_action( 'mds_rm_after_shadow_meta_sync', array( self::class, 'map_product' ), 10, 4 );
		add_filter( 'mds_rm_release_meta_context_upc', array( self::class, 'context_upc' ), 10, 2 );
		add_action( 'mds_rm_after_buy_now_field', array( self::class, 'buy_now_button' ) );
		add_action( 'init', array( self::class, 'ensure_all_media_format' ), 20 );

		$placements = array(
			'woocommerce_single_product_summary:5'        => array( 'woocommerce_single_product_summary', 5 ),
			'woocommerce_single_product_summary:15'       => array( 'woocommerce_single_product_summary', 15 ),
			'woocommerce_single_product_summary:25'       => array( 'woocommerce_single_product_summary', 25 ),
			'woocommerce_single_product_summary:45'       => array( 'woocommerce_single_product_summary', 45 ),
			'woocommerce_after_single_product_summary:10' => array( 'woocommerce_after_single_product_summary', 10 ),
		);

		$placement = get_theme_mod( 'mds_rm_woo_injection', 'woocommerce_single_product_summary:25' );
		if ( 'disabled' === $placement ) {
			return;
		}

		if ( ! is_string( $placement ) || ! isset( $placements[ $placement ] ) ) {
			$placement = 'woocommerce_single_product_summary:25';
		}

		add_action( $placements[ $placement ][0], array( self::class, 'inject' ), $placements[ $placement ][1] );
	}

	/**
	 * Merge configured WooCommerce stores (current and legacy options) into Release Manager sync targets.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $targets Existing sync target site IDs.
	 * @return array<int, int> Deduplicated target site IDs.
	 */
	public static function sync_targets( mixed $targets ): array {
		$integrated = Plugin::get_integrated_sites();

		return array_values( array_unique( array_merge( array_map( 'intval', (array) $targets ), $integrated ) ) );
	}

	/**
	 * Map the linked local WooCommerce product ID onto a synced shadow release post.
	 *
	 * @since 1.0.1
	 *
	 * @param int                  $shadow_id Shadow post ID on the spoke site.
	 * @param int                  $site_id   Spoke site ID.
	 * @param int                  $source_id Source release post ID on the hub site.
	 * @param array<string, mixed> $meta      Source post meta array.
	 * @return void
	 */
	public static function map_product( int $shadow_id, int $site_id, int $source_id, array $meta ): void {
		unset( $source_id );

		$linked = isset( $meta['_mds_linked_products'][0] )
			? maybe_unserialize( $meta['_mds_linked_products'][0] )
			: array();

		if ( is_array( $linked ) && isset( $linked[ $site_id ] ) ) {
			update_post_meta( $shadow_id, '_mds_local_product_id', absint( $linked[ $site_id ] ) );
		} else {
			delete_post_meta( $shadow_id, '_mds_local_product_id' );
		}
	}

	/**
	 * Resolve a numeric WooCommerce product GTIN/UPC/EAN or SKU as the fallback UPC context for Release Meta.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $upc     Existing UPC string.
	 * @param int   $post_id Current post ID.
	 * @return mixed Resolved UPC or original value.
	 */
	public static function context_upc( mixed $upc, int $post_id ): mixed {
		if ( '' !== (string) $upc || ! function_exists( 'wc_get_product' ) || 'product' !== get_post_type( $post_id ) ) {
			return $upc;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$gtin = preg_replace( '/\D+/', '', (string) $product->get_global_unique_id() );
			if ( is_string( $gtin ) && '' !== $gtin ) {
				return $gtin;
			}
		}

		$sku = (string) $product->get_sku();

		return preg_match( '/^\d+$/D', $sku ) ? $sku : '';
	}

	/**
	 * Inject Release Manager metadata markup on single WooCommerce product pages.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function inject(): void {
		if ( is_multisite() ) {
			$sites = Plugin::get_integrated_sites();

			if ( ! in_array( (int) get_current_blog_id(), $sites, true ) ) {
				return;
			}
		}

		global $product;
		$post_id = $product instanceof WC_Product ? $product->get_id() : (int) get_the_ID();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MDS_RM_Release_Meta::render() escapes its output and validates all style values.
		echo \MDS_RM_Release_Meta::render( array(), $post_id );
	}

	/**
	 * Ensure the "All" release_media_format term exists on the Hub with its configured icon.
	 *
	 * Used when inserting a WooCommerce linked store row into the Multi-Format Buy Now repeater.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function ensure_all_media_format(): void {
		if ( ! is_admin() || ! taxonomy_exists( 'release_media_format' ) ) {
			return;
		}

		if ( class_exists( '\MDS_RM_Sync' ) && method_exists( '\MDS_RM_Sync', 'is_hub' ) && ! \MDS_RM_Sync::is_hub() ) {
			return;
		}

		$icon_meta_key = class_exists( '\MDS_RM_Release_Model' ) && defined( '\MDS_RM_Release_Model::MEDIA_FORMAT_ICON_META_KEY' )
			? \MDS_RM_Release_Model::MEDIA_FORMAT_ICON_META_KEY
			: '_mds_media_format_icon';

		$existing = get_term_by( 'slug', 'all', 'release_media_format' );
		if ( $existing && ! is_wp_error( $existing ) ) {
			if ( '' === (string) get_term_meta( (int) $existing->term_id, $icon_meta_key, true ) ) {
				update_term_meta( (int) $existing->term_id, $icon_meta_key, 'fas fa-boxes-stacked' );
			}
			return;
		}

		$inserted = wp_insert_term(
			__( 'All', 'mds-woocommerce-integration' ),
			'release_media_format',
			array(
				'slug'        => 'all',
				'description' => __( 'All supported media formats.', 'mds-woocommerce-integration' ),
			)
		);

		if ( ! is_wp_error( $inserted ) && ! empty( $inserted['term_id'] ) ) {
			update_term_meta( (int) $inserted['term_id'], $icon_meta_key, 'fas fa-boxes-stacked' );
		}
	}

	/**
	 * Output the helper button next to the Release Manager Buy Now repeater controls.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function buy_now_button(): void {
		echo '<button type="button" class="button button-secondary" id="mds_use_linked_product_btn" disabled>'
			. esc_html__( 'Use Linked Product URL', 'mds-woocommerce-integration' )
			. '</button>';
	}
}
