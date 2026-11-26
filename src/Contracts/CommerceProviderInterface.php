<?php
/**
 * Core contract implemented by all MDS Commerce store providers.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Contracts;

use MDS\Commerce\Model\NormalizedProduct;
use MDS\Commerce\Model\StoreConnection;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Defines standard read, draft-seeding, and selective drift-sync operations for a store provider.
 *
 * @since 3.0.0
 */
interface CommerceProviderInterface {

	/**
	 * Unique provider slug (e.g., `wc_local`, `wc_remote`, `shopify`, `wix`).
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider slug.
	 */
	public function get_slug(): string;

	/**
	 * Human-readable provider label.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider display name.
	 */
	public function get_label(): string;

	/**
	 * Return capability flags advertised by this provider for a given store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection|null $store Optional store connection for per-store capability checks.
	 * @return array<string, bool> Capability map (search, link, seed_draft, sync_drift, supports_external_variations, frontend_inject, embed_checkout, webhooks).
	 */
	public function get_capabilities( ?StoreConnection $store = null ): array;

	/**
	 * Verify connectivity and credentials for a store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection $store Store connection.
	 * @return bool|WP_Error True on success or WP_Error describing the failure.
	 */
	public function test_connection( StoreConnection $store ): bool|WP_Error;

	/**
	 * Search products on the target store.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection $store Store connection.
	 * @param string          $query Search query.
	 * @param int             $page  Pagination page (1-indexed).
	 * @return array<int, NormalizedProduct>|WP_Error Normalized product list or error.
	 */
	public function search_products( StoreConnection $store, string $query, int $page = 1 ): array|WP_Error;

	/**
	 * Retrieve a single normalized product from the target store.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection $store     Store connection.
	 * @param string          $remote_id Remote product identifier.
	 * @return NormalizedProduct|null Normalized product or null when not found.
	 */
	public function get_product( StoreConnection $store, string $remote_id ): ?NormalizedProduct;

	/**
	 * Create a new Draft (or optionally published) product seeded from a Hub Release.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection      $store   Target store connection.
	 * @param WP_Post              $release Hub Release post object.
	 * @param array<string, mixed> $options Seeding options (price, sku, status, variant_strategy, external_variations).
	 * @return NormalizedProduct|WP_Error Seeded product DTO or error.
	 */
	public function create_draft_product( StoreConnection $store, WP_Post $release, array $options = array() ): NormalizedProduct|WP_Error;

	/**
	 * Selectively push admin-approved drift fields to a linked remote product.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection    $store     Target store connection.
	 * @param WP_Post            $release   Hub Release post object.
	 * @param string             $remote_id Remote product identifier.
	 * @param array<int, string> $fields    Approved field keys (`upc`, `sku`, `thumbnail`, `title`).
	 * @return NormalizedProduct|WP_Error Updated product DTO or error.
	 */
	public function sync_fields( StoreConnection $store, WP_Post $release, string $remote_id, array $fields ): NormalizedProduct|WP_Error;
}
