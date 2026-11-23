<?php
/**
 * WooCommerce release meta box rendering.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Admin;

use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Rest\RestApi;
use MDS\WooCommerce\Support\TemplateRenderer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the WooCommerce product linker meta box on releases.
 *
 * @since 1.0.1
 */
final class MetaBoxes {

	/**
	 * Register the WooCommerce integration meta box for releases.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_Post|null $post Current post object.
	 * @return void
	 */
	public static function register( ?WP_Post $post ): void {
		if ( ! Plugin::is_active() ) {
			return;
		}

		if ( class_exists( '\MDS_RM_Sync' ) && method_exists( '\MDS_RM_Sync', 'is_hub' ) && ! \MDS_RM_Sync::is_hub() ) {
			return;
		}

		if ( $post && 'release' === $post->post_type ) {
			add_meta_box(
				'mds_wc_settings',
				__( 'WooCommerce Integration', 'mds-woocommerce-integration' ),
				array( self::class, 'render' ),
				'release',
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render WooCommerce Store Cards and Create Product modal for a release.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_Post $post Current release post object.
	 * @return void
	 */
	public static function render( WP_Post $post ): void {
		$is_auto_draft = 'auto-draft' === $post->post_status;

		$linked_products = get_post_meta( $post->ID, '_mds_linked_products', true );
		if ( ! is_array( $linked_products ) ) {
			$linked_products = array();
		}

		$integrated_sites = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) get_site_option( 'mds_rm_wc_integrated_sites', array() ) ),
					array( RestApi::class, 'is_integrated_store' )
				)
			)
		);

		$markup = TemplateRenderer::render(
			'product-linker-wrapper.php',
			array(
				'sites'         => $is_auto_draft ? array() : self::get_integrated_site_data( $integrated_sites, $linked_products ),
				'post_id'       => (int) $post->ID,
				'is_auto_draft' => $is_auto_draft,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values in their output contexts.
		echo $markup;
	}

	/**
	 * Prepare store card metadata and linked product summary for each configured store.
	 *
	 * @since 1.0.1
	 *
	 * @param array<int, int>   $integrated_sites Integrated site IDs.
	 * @param array<int, mixed> $linked_products  Product IDs linked by store.
	 * @return array<int, array<string, mixed>> Store card payloads for templates.
	 */
	private static function get_integrated_site_data( array $integrated_sites, array $linked_products ): array {
		$sites = array();

		foreach ( $integrated_sites as $store_id ) {
			$details    = function_exists( 'get_blog_details' ) ? get_blog_details( $store_id ) : null;
			$store_name = $details && ! empty( $details->blogname )
				? (string) $details->blogname
				/* translators: %d: Multisite blog ID of the WooCommerce store. */
				: sprintf( __( 'Store #%d', 'mds-woocommerce-integration' ), $store_id );
			$store_url         = $details && ! empty( $details->siteurl ) ? (string) $details->siteurl : '';
			$current_linked_id = isset( $linked_products[ $store_id ] )
				? absint( $linked_products[ $store_id ] )
				: 0;

			$product = $current_linked_id
				? self::get_product_summary( $store_id, $current_linked_id )
				: null;

			$sites[] = array(
				'store_id'   => $store_id,
				'store_name' => $store_name,
				'store_url'  => $store_url,
				'product_id' => $product ? (int) $product['id'] : 0,
				'product'    => $product,
			);
		}

		return $sites;
	}

	/**
	 * Fetch a normalized linked product summary from the target store site.
	 *
	 * @since 1.0.1
	 *
	 * @param int $store_id   Store site ID.
	 * @param int $product_id Product post ID.
	 * @return array<string, mixed>|null Product summary or null when missing/trashed.
	 */
	private static function get_product_summary( int $store_id, int $product_id ): ?array {
		switch_to_blog( $store_id );
		try {
			if ( ! post_type_exists( 'product' ) ) {
				register_post_type( 'product' );
			}

			$product = get_post( $product_id );
			if ( ! $product || 'product' !== $product->post_type || 'trash' === $product->post_status ) {
				return null;
			}

			return RestApi::format_product_summary( $product );
		} finally {
			restore_current_blog();
		}
	}
}
