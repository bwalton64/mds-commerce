<?php
/**
 * WooCommerce release and artist meta box rendering and saving.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Admin;

use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Rest\RestApi;
use MDS\WooCommerce\Support\ProductDrift;
use MDS\WooCommerce\Support\StoreRegistry;
use MDS\WooCommerce\Support\TemplateRenderer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the WooCommerce product linker meta box on Releases
 * and the Target WooCommerce Stores meta box on Artists.
 *
 * @since 1.0.1
 */
final class MetaBoxes {

	/**
	 * Register WooCommerce integration meta boxes for Releases and Artists (Hub only).
	 *
	 * @since 1.0.1
	 *
	 * @param WP_Post|null $post Current post object.
	 * @return void
	 */
	public static function register( ?WP_Post $post ): void {
		if ( ! Plugin::is_active() || ! $post ) {
			return;
		}

		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) && ! \MDS\ReleaseManager\Api\ExtensionApi::is_hub() ) {
			return;
		}

		if ( 'release' === $post->post_type ) {
			add_meta_box(
				'mds_wc_settings',
				__( 'WooCommerce Integration', 'mds-woocommerce' ),
				array( self::class, 'render' ),
				'release',
				'normal',
				'default'
			);
		} elseif ( 'artist' === $post->post_type && is_multisite() ) {
			add_meta_box(
				'mds_wc_artist_stores',
				__( 'WooCommerce Target Stores', 'mds-woocommerce' ),
				array( self::class, 'render_artist_stores' ),
				'artist',
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render the Target WooCommerce Stores meta box on the Artist edit screen.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_Post $post Current Artist post object.
	 * @return void
	 */
	public static function render_artist_stores( WP_Post $post ): void {
		$stores        = StoreRegistry::get_available_stores();
		$assigned      = StoreRegistry::get_artist_target_stores( (int) $post->ID );
		$is_auto_draft = 'auto-draft' === $post->post_status;
		$release_count = $is_auto_draft ? 0 : self::count_artist_releases( (int) $post->ID );

		$markup = TemplateRenderer::render(
			'artist-stores-box.php',
			array(
				'artist_id'     => (int) $post->ID,
				'stores'        => array_values( $stores ),
				'assigned'      => $assigned,
				'release_count' => $release_count,
				'is_auto_draft' => $is_auto_draft,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values in their output contexts.
		echo $markup;
	}

	/**
	 * Hook callback for `mds_rm_save_artist_meta` delegating to `save_artist_stores()`.
	 *
	 * @since 2.0.0
	 *
	 * @param int     $post_id Artist post ID.
	 * @param WP_Post $post    Artist post object.
	 * @return void
	 */
	public static function save_artist_stores_hook( int $post_id, WP_Post $post ): void {
		unset( $post );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside save_artist_stores().
		self::save_artist_stores( $post_id, $_POST );
	}

	/**
	 * Persist the Artist's assigned WooCommerce target stores.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $post_id   Saved post ID.
	 * @param array<string, mixed> $post_data Submitted POST data.
	 * @return void
	 */
	public static function save_artist_stores( int $post_id, array $post_data ): void {
		if ( ! is_multisite() || 'artist' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( empty( $post_data['mds_wc_artist_stores_present'] ) ) {
			return;
		}

		$nonce = isset( $post_data['mds_wc_artist_stores_nonce'] ) && is_scalar( $post_data['mds_wc_artist_stores_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $post_data['mds_wc_artist_stores_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'mds_wc_save_artist_stores' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw_stores = isset( $post_data['mds_wc_target_stores'] ) && is_array( $post_data['mds_wc_target_stores'] )
			? $post_data['mds_wc_target_stores']
			: array();

		$sanitized = StoreRegistry::sanitize_store_ids( $raw_stores );
		$previous  = StoreRegistry::get_artist_target_stores( $post_id );

		update_post_meta( $post_id, StoreRegistry::ARTIST_META_KEY, $sanitized );
		StoreRegistry::reset_request_cache();

		$changed_stores = array_values(
			array_unique(
				array_merge(
					array_diff( $previous, $sanitized ),
					array_diff( $sanitized, $previous )
				)
			)
		);

		foreach ( $changed_stores as $store_id ) {
			if ( $store_id > 0 ) {
				Plugin::request_cache_purge( 'woocommerce_artist_stores', $store_id );
			}
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

		$artist_id       = StoreRegistry::resolve_artist_id_for_release( (int) $post->ID );
		$artist_name     = $artist_id > 0 ? (string) get_the_title( $artist_id ) : '';
		$artist_edit_url = $artist_id > 0 ? (string) get_edit_post_link( $artist_id, 'raw' ) : '';

		$assigned_stores = StoreRegistry::get_release_target_stores( (int) $post->ID );
		$legacy_stores   = array();

		foreach ( $linked_products as $linked_store_id => $linked_product_id ) {
			$sid = absint( $linked_store_id );
			$pid = absint( $linked_product_id );
			if ( $sid > 0 && $pid > 0 && ! in_array( $sid, $assigned_stores, true ) && StoreRegistry::is_woocommerce_store( $sid ) ) {
				$legacy_stores[] = $sid;
			}
		}

		$sites = array();
		if ( ! $is_auto_draft ) {
			$sites = array_merge(
				self::get_store_card_data( $post, $assigned_stores, $linked_products, false ),
				self::get_store_card_data( $post, $legacy_stores, $linked_products, true )
			);
		}

		$markup = TemplateRenderer::render(
			'product-linker-wrapper.php',
			array(
				'sites'           => $sites,
				'post_id'         => (int) $post->ID,
				'is_auto_draft'   => $is_auto_draft,
				'artist_id'       => $artist_id,
				'artist_name'     => $artist_name,
				'artist_edit_url' => $artist_edit_url,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values in their output contexts.
		echo $markup;
	}

	/**
	 * Prepare store card metadata, linked product summary, and drift report for each store.
	 *
	 * Uses cached StoreRegistry metadata (0 extra get_blog_details calls) and only
	 * switches blogs when a store actually has a linked product ID.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_Post           $release         Hub Release post object.
	 * @param array<int, int>   $store_ids       Store site IDs.
	 * @param array<int, mixed> $linked_products Product IDs linked by store.
	 * @param bool              $is_unassigned   Whether these stores are no longer assigned to the Artist.
	 * @return array<int, array<string, mixed>> Store card payloads for templates.
	 */
	private static function get_store_card_data( WP_Post $release, array $store_ids, array $linked_products, bool $is_unassigned ): array {
		$available = StoreRegistry::get_available_stores();
		$sites     = array();

		foreach ( $store_ids as $store_id ) {
			$store_meta = $available[ $store_id ] ?? null;
			$store_name = is_array( $store_meta ) && ! empty( $store_meta['site_name'] )
				? (string) $store_meta['site_name']
				/* translators: %d: Multisite blog ID of the WooCommerce store. */
				: sprintf( __( 'Store #%d', 'mds-woocommerce' ), $store_id );
			$store_url         = is_array( $store_meta ) && ! empty( $store_meta['site_url'] ) ? (string) $store_meta['site_url'] : '';
			$current_linked_id = isset( $linked_products[ $store_id ] )
				? absint( $linked_products[ $store_id ] )
				: 0;

			$product = $current_linked_id
				? self::get_product_summary( $store_id, $current_linked_id )
				: null;

			if ( $is_unassigned && null === $product ) {
				continue;
			}

			$drift = is_array( $product )
				? ProductDrift::detect( $release, $product, $store_id )
				: array(
					'has_drift' => false,
					'count'     => 0,
					'items'     => array(),
				);

			$sites[] = array(
				'store_id'      => $store_id,
				'store_name'    => $store_name,
				'store_url'     => $store_url,
				'product_id'    => $product ? (int) $product['id'] : 0,
				'product'       => $product,
				'drift'         => $drift,
				'is_unassigned' => $is_unassigned,
			);
		}

		return $sites;
	}

	/**
	 * Count Releases associated with a Hub Artist (for the Sync Artist Releases button label).
	 *
	 * @since 1.1.0
	 *
	 * @param int $artist_id Hub Artist post ID.
	 * @return int Number of associated releases.
	 */
	private static function count_artist_releases( int $artist_id ): int {
		$uuid = (string) get_post_meta( $artist_id, '_mds_artist_uuid', true );
		if ( '' === $uuid ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'release',
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => 'artist_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Counts releases belonging to this Artist on the edit screen.
				'meta_value'             => $uuid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Counts releases belonging to this Artist on the edit screen.
			)
		);

		return (int) $query->found_posts;
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
		if ( ! is_multisite() || get_current_blog_id() === $store_id ) {
			$product = get_post( $product_id );
			if ( ! $product || 'product' !== $product->post_type || 'trash' === $product->post_status ) {
				return null;
			}
			return RestApi::format_product_summary( $product );
		}

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
