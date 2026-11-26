<?php
/**
 * Local Multisite WooCommerce provider implementing CommerceProviderInterface.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Providers\WooCommerceLocal;

use MDS\Commerce\Contracts\CommerceProviderInterface;
use MDS\Commerce\Model\NormalizedProduct;
use MDS\Commerce\Model\StoreConnection;
use MDS\Commerce\Rest\RestApi;
use MDS\Commerce\Support\ProductDrift;
use MDS\Commerce\Support\StoreRegistry;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps local single-site and Multisite WooCommerce stores behind the unified CommerceProviderInterface.
 *
 * @since 3.0.0
 */
final class WooCommerceLocalProvider implements CommerceProviderInterface {

	/**
	 * Unique provider slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider slug.
	 */
	public function get_slug(): string {
		return 'wc_local';
	}

	/**
	 * Human-readable provider label.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider display name.
	 */
	public function get_label(): string {
		return __( 'WooCommerce (Local / Multisite)', 'mds-commerce' );
	}

	/**
	 * Return capability flags advertised by this provider for a given store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection|null $store Optional store connection for per-store capability checks.
	 * @return array<string, bool> Capability map.
	 */
	public function get_capabilities( ?StoreConnection $store = null ): array {
		$blog_id             = $store instanceof StoreConnection ? $store->key->local_blog_id() : 0;
		$external_variations = $blog_id > 0
			? ExternalVariationsBridge::is_active_on_blog( $blog_id )
			: ExternalVariationsBridge::is_active_on_current_site();

		return array(
			'search'                       => true,
			'link'                         => true,
			'seed_draft'                   => true,
			'sync_drift'                   => true,
			'supports_external_variations' => $external_variations,
			'frontend_inject'              => true,
			'embed_checkout'               => false,
			'webhooks'                     => false,
		);
	}

	/**
	 * Verify connectivity for a local WooCommerce store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection $store Store connection.
	 * @return bool|WP_Error True on success or WP_Error describing the failure.
	 */
	public function test_connection( StoreConnection $store ): bool|WP_Error {
		$blog_id = $store->key->local_blog_id();
		if ( $blog_id <= 0 || ! StoreRegistry::is_woocommerce_store( $blog_id ) ) {
			return new WP_Error(
				'mds_cm_invalid_wc_store',
				__( 'The specified local WooCommerce store is not active.', 'mds-commerce' )
			);
		}

		return true;
	}

	/**
	 * Search products on the target local WooCommerce store.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection $store Store connection.
	 * @param string          $query Search query.
	 * @param int             $page  Pagination page (1-indexed).
	 * @return array<int, NormalizedProduct>|WP_Error Normalized product list or error.
	 */
	public function search_products( StoreConnection $store, string $query, int $page = 1 ): array|WP_Error {
		$blog_id = $store->key->local_blog_id();
		if ( $blog_id <= 0 ) {
			return new WP_Error( 'mds_cm_invalid_wc_store', __( 'Invalid local WooCommerce blog ID.', 'mds-commerce' ) );
		}

		$switched = false;
		if ( is_multisite() && (int) get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		try {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'paged'          => max( 1, $page ),
				's'              => $query,
				'fields'         => 'ids',
			);

			$ids     = get_posts( $args );
			$results = array();
			foreach ( $ids as $product_id ) {
				$summary = RestApi::format_product_summary( (int) $product_id, $blog_id );
				if ( is_array( $summary ) ) {
					$results[] = new NormalizedProduct( $summary );
				}
			}

			return $results;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Retrieve a single normalized product from the target local WooCommerce store.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection $store     Store connection.
	 * @param string          $remote_id Remote product identifier.
	 * @return NormalizedProduct|null Normalized product or null when not found.
	 */
	public function get_product( StoreConnection $store, string $remote_id ): ?NormalizedProduct {
		$blog_id    = $store->key->local_blog_id();
		$product_id = absint( $remote_id );
		if ( $blog_id <= 0 || $product_id <= 0 ) {
			return null;
		}

		$switched = false;
		if ( is_multisite() && (int) get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		try {
			$summary = RestApi::format_product_summary( $product_id, $blog_id );
			return is_array( $summary ) ? new NormalizedProduct( $summary ) : null;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Create a new Draft product seeded from a Hub Release.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection      $store   Target store connection.
	 * @param WP_Post              $release Hub Release post object.
	 * @param array<string, mixed> $options Seeding options.
	 * @return NormalizedProduct|WP_Error Seeded product DTO or error.
	 */
	public function create_draft_product( StoreConnection $store, WP_Post $release, array $options = array() ): NormalizedProduct|WP_Error {
		unset( $store, $release, $options );
		return new WP_Error(
			'mds_cm_use_rest_generate',
			__( 'Local WooCommerce draft seeding is handled via POST /mds-commerce/v1/generate.', 'mds-commerce' )
		);
	}

	/**
	 * Selectively push admin-approved drift fields to a linked local WooCommerce product.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection    $store     Target store connection.
	 * @param WP_Post            $release   Hub Release post object.
	 * @param string             $remote_id Remote product identifier.
	 * @param array<int, string> $fields    Approved field keys (`upc`, `sku`, `thumbnail`, `title`).
	 * @return NormalizedProduct|WP_Error Updated product DTO or error.
	 */
	public function sync_fields( StoreConnection $store, WP_Post $release, string $remote_id, array $fields ): NormalizedProduct|WP_Error {
		$blog_id    = $store->key->local_blog_id();
		$product_id = absint( $remote_id );

		$result = ProductDrift::apply( $release, $blog_id, $product_id, $fields );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$updated = $this->get_product( $store, (string) $product_id );
		return $updated instanceof NormalizedProduct
			? $updated
			: new WP_Error( 'mds_cm_product_missing', __( 'Updated product could not be reloaded.', 'mds-commerce' ) );
	}
}
