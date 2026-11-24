<?php
/**
 * WooCommerce integration REST API operations.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Rest;

use MDS\WooCommerce\Admin\SiteSettings;
use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Support\StoreRegistry;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles REST endpoints for network settings, product search, linking, taxonomies, and product seeding.
 *
 * @since 1.0.1
 */
final class RestApi {

	/**
	 * Primary REST namespace.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	private const NAMESPACE_PRIMARY = 'mds-wc/v1';

	/**
	 * Legacy REST namespace retained for backward compatibility.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	private const NAMESPACE_LEGACY = 'mds-woo/v1';

	/**
	 * Register integration REST routes.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		if ( ! Plugin::is_active() ) {
			return;
		}

		foreach ( array( self::NAMESPACE_PRIMARY, self::NAMESPACE_LEGACY ) as $rest_namespace ) {
			self::register_product_routes( $rest_namespace );
			self::register_settings_and_artist_routes( $rest_namespace );
		}
	}

	/**
	 * Register product search, linking, taxonomy, and seeding routes for a namespace.
	 *
	 * @since 1.0.1
	 *
	 * @param string $rest_namespace REST API namespace.
	 * @return void
	 */
	private static function register_product_routes( string $rest_namespace ): void {
		$edit_posts_permission = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		register_rest_route(
			$rest_namespace,
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'generate_product' ),
				'permission_callback' => $edit_posts_permission,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'search_products' ),
				'permission_callback' => $edit_posts_permission,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/link',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'link_product' ),
					'permission_callback' => $edit_posts_permission,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'unlink_product' ),
					'permission_callback' => $edit_posts_permission,
				),
			)
		);

		register_rest_route(
			$rest_namespace,
			'/taxonomies',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_taxonomies' ),
				'permission_callback' => $edit_posts_permission,
			)
		);
	}

	/**
	 * Register site settings, store discovery refresh, and Artist batch-sync routes.
	 *
	 * @since 1.1.0
	 *
	 * @param string $rest_namespace REST API namespace.
	 * @return void
	 */
	private static function register_settings_and_artist_routes( string $rest_namespace ): void {
		register_rest_route(
			$rest_namespace,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'save_site_settings' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			$rest_namespace,
			'/stores/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'refresh_stores' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			$rest_namespace,
			'/artists/(?P<id>\d+)/sync-releases',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'sync_artist_releases' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					$artist_id = absint( $request->get_param( 'id' ) );
					$is_hub    = ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) || \MDS\ReleaseManager\Api\ExtensionApi::is_hub();
					return $artist_id > 0 && $is_hub && current_user_can( 'edit_post', $artist_id );
				},
			)
		);
	}

	/**
	 * Save per-site WooCommerce product injection settings.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response REST response.
	 */
	public static function save_site_settings( WP_REST_Request $request ): WP_REST_Response {
		$params    = $request->get_json_params();
		$raw       = is_array( $params ) ? $params : $request->get_body_params();
		$sanitized = SiteSettings::sanitize_settings( $raw );

		update_option( SiteSettings::OPTION_KEY, $sanitized, false );
		Plugin::request_cache_purge( 'woocommerce_settings' );

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $sanitized,
				'message'  => __( 'WooCommerce integration settings saved.', 'mds-woocommerce-integration' ),
			)
		);
	}

	/**
	 * Flush the cached network WooCommerce store list and return the refreshed stores.
	 *
	 * @since 1.1.0
	 *
	 * @return WP_REST_Response Refreshed store list.
	 */
	public static function refresh_stores(): WP_REST_Response {
		StoreRegistry::invalidate_cache();
		$stores = StoreRegistry::get_available_stores( true );

		return rest_ensure_response(
			array(
				'success' => true,
				'stores'  => array_values( $stores ),
			)
		);
	}

	/**
	 * Sync one bounded batch of an Artist's Releases (plus the Artist on page 1)
	 * to the Artist's assigned WooCommerce target stores.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error Batch progress response or error.
	 */
	public static function sync_artist_releases( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$artist_id = absint( $request->get_param( 'id' ) );
		$artist    = get_post( $artist_id );

		if ( ! $artist instanceof WP_Post || 'artist' !== $artist->post_type ) {
			return new WP_Error(
				'invalid_artist',
				__( 'Artist not found.', 'mds-woocommerce-integration' ),
				array( 'status' => 404 )
			);
		}

		$target_stores = StoreRegistry::get_artist_target_stores( $artist_id );
		if ( empty( $target_stores ) ) {
			return new WP_Error(
				'no_target_stores',
				__( 'Assign at least one WooCommerce store to this Artist before syncing.', 'mds-woocommerce-integration' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			return new WP_Error(
				'sync_unavailable',
				__( 'MDS Release Manager synchronization is unavailable.', 'mds-woocommerce-integration' ),
				array( 'status' => 500 )
			);
		}

		$page       = max( 1, absint( $request->get_param( 'page' ) ) );
		$batch_size = 10;
		$items      = array();

		if ( 1 === $page ) {
			$items[] = array(
				'id'        => $artist_id,
				'post_type' => 'artist',
			);
		}

		$artist_uuid = (string) get_post_meta( $artist_id, '_mds_artist_uuid', true );
		$release_ids = array();

		if ( '' !== $artist_uuid ) {
			$release_ids = get_posts(
				array(
					'post_type'              => 'release',
					'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'posts_per_page'         => $batch_size,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_key'               => 'artist_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded batch sync for one Artist's releases.
					'meta_value'             => $artist_uuid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded batch sync for one Artist's releases.
				)
			);
		}

		foreach ( $release_ids as $release_id ) {
			$items[] = array(
				'id'        => (int) $release_id,
				'post_type' => 'release',
			);
		}

		$synced      = 0;
		$error_count = 0;

		if ( ! empty( $items ) ) {
			$results = \MDS\ReleaseManager\Api\ExtensionApi::broadcast_batch_to_spokes( $items );
			foreach ( $items as $item ) {
				$key    = $item['post_type'] . ':' . $item['id'];
				$result = $results[ $key ] ?? null;
				if ( is_wp_error( $result ) ) {
					++$error_count;
				} elseif ( 'release' === $item['post_type'] ) {
					++$synced;
				}
			}
		}

		$done = count( $release_ids ) < $batch_size;
		if ( $done ) {
			foreach ( $target_stores as $store_id ) {
				Plugin::request_cache_purge( 'woocommerce_artist_sync', $store_id );
			}
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'synced'      => $synced,
				'error_count' => $error_count,
				'done'        => $done,
			)
		);
	}

	/**
	 * Link an existing spoke WooCommerce product to a Hub release and broadcast to spokes.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public static function link_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_id   = self::get_request_id( $request, 'store_id' );
		$release_id = self::get_request_id( $request, 'release_id' );
		$product_id = self::get_request_id( $request, 'product_id' );

		if ( ! $store_id || ! $release_id || ! $product_id ) {
			return new WP_Error(
				'missing_data',
				__( 'Missing Store ID, Release ID, or Product ID.', 'mds-woocommerce-integration' ),
				array( 'status' => 400 )
			);
		}

		$access_error = self::validate_release_and_store_access( $release_id, $store_id );
		if ( is_wp_error( $access_error ) ) {
			return $access_error;
		}

		switch_to_blog( $store_id );
		try {
			if ( ! Plugin::is_woocommerce_active_on_current_site() ) {
				return new WP_Error(
					'no_wc',
					__( 'WooCommerce is not active on the selected site.', 'mds-woocommerce-integration' ),
					array( 'status' => 400 )
				);
			}

			self::register_product_types( false );
			$product = get_post( $product_id );

			if ( ! $product || 'product' !== $product->post_type || 'trash' === $product->post_status ) {
				return new WP_Error(
					'invalid_product',
					__( 'The selected product could not be found on the store.', 'mds-woocommerce-integration' ),
					array( 'status' => 404 )
				);
			}

			$summary = self::format_product_summary( $product );
		} finally {
			restore_current_blog();
		}

		self::save_product_link( $release_id, $store_id, $product_id );
		self::broadcast_release_link( $release_id );
		Plugin::request_cache_purge( 'woocommerce_link', $store_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'product' => $summary,
			)
		);
	}

	/**
	 * Remove a store's linked WooCommerce product from a Hub release and broadcast to spokes.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public static function unlink_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_id   = self::get_request_id( $request, 'store_id' );
		$release_id = self::get_request_id( $request, 'release_id' );

		if ( ! $store_id || ! $release_id ) {
			return new WP_Error(
				'missing_data',
				__( 'Missing Store ID or Release ID.', 'mds-woocommerce-integration' ),
				array( 'status' => 400 )
			);
		}

		$access_error = self::validate_release_and_store_access( $release_id, $store_id, true );
		if ( is_wp_error( $access_error ) ) {
			return $access_error;
		}

		$linked = get_post_meta( $release_id, '_mds_linked_products', true );
		if ( is_array( $linked ) ) {
			unset( $linked[ $store_id ] );
			update_post_meta( $release_id, '_mds_linked_products', $linked );
		}

		if ( get_current_blog_id() === $store_id ) {
			delete_post_meta( $release_id, '_mds_local_product_id' );
		}

		self::broadcast_release_link( $release_id );
		Plugin::request_cache_purge( 'woocommerce_unlink', $store_id );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Return product categories and tags from an integrated spoke store for the Create Product modal.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public static function get_taxonomies( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_id   = self::get_request_id( $request, 'store_id' );
		$release_id = self::get_request_id( $request, 'release_id' );

		$access_error = self::validate_release_and_store_access( $release_id, $store_id );
		if ( is_wp_error( $access_error ) ) {
			return $access_error;
		}

		switch_to_blog( $store_id );
		try {
			if ( ! Plugin::is_woocommerce_active_on_current_site() ) {
				return rest_ensure_response(
					array(
						'categories' => array(),
						'tags'       => array(),
					)
				);
			}

			self::register_product_types( false );

			$raw_cats = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);

			$raw_tags = get_terms(
				array(
					'taxonomy'   => 'product_tag',
					'hide_empty' => false,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);

			$categories = array();
			if ( is_array( $raw_cats ) ) {
				foreach ( $raw_cats as $cat ) {
					$categories[] = array(
						'id'     => (int) $cat->term_id,
						'name'   => (string) $cat->name,
						'parent' => (int) $cat->parent,
					);
				}
			}

			$tags = array();
			if ( is_array( $raw_tags ) ) {
				foreach ( $raw_tags as $tag ) {
					$tags[] = array(
						'id'   => (int) $tag->term_id,
						'name' => (string) $tag->name,
					);
				}
			}
		} finally {
			restore_current_blog();
		}

		return rest_ensure_response(
			array(
				'categories' => $categories,
				'tags'       => $tags,
			)
		);
	}

	/**
	 * Seed and link a new WooCommerce product on an integrated store (fire-and-forget).
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public static function generate_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_id   = self::get_request_id( $request, 'store_id' );
		$release_id = self::get_request_id( $request, 'release_id' );

		if ( ! $store_id || ! $release_id ) {
			return new WP_Error(
				'missing_data',
				__( 'Missing Store ID or Release ID.', 'mds-woocommerce-integration' ),
				array( 'status' => 400 )
			);
		}

		$access_error = self::validate_release_and_store_access( $release_id, $store_id );
		if ( is_wp_error( $access_error ) ) {
			return $access_error;
		}

		$release      = get_post( $release_id );
		$hub_thumb_id = (int) get_post_thumbnail_id( $release_id );
		$hub_img_url  = $hub_thumb_id ? (string) wp_get_attachment_url( $hub_thumb_id ) : '';
		$media_info   = self::get_hub_thumbnail_info( $hub_thumb_id );

		$raw_name        = $request->get_param( 'name' );
		$raw_description = $request->get_param( 'description' );
		$raw_short_desc  = $request->get_param( 'short_description' );
		$raw_sku         = $request->get_param( 'sku' );
		$raw_price       = $request->get_param( 'regular_price' );
		$raw_status      = $request->get_param( 'status' );
		$raw_categories  = $request->get_param( 'categories' );
		$raw_tags        = $request->get_param( 'tags' );
		$raw_sync_thumb  = $request->get_param( 'sync_thumbnail' );

		$release_upc_value = get_post_meta( $release_id, 'upc_ean', true );
		$default_upc       = is_scalar( $release_upc_value ) ? sanitize_text_field( (string) $release_upc_value ) : '';

		$seed_data = array(
			'name'              => is_scalar( $raw_name ) && '' !== trim( (string) $raw_name )
				? sanitize_text_field( (string) $raw_name )
				: (string) get_the_title( $release_id ),
			'description'       => is_scalar( $raw_description )
				? wp_kses_post( (string) $raw_description )
				: ( $release instanceof WP_Post ? wp_kses_post( (string) $release->post_content ) : '' ),
			'short_description' => is_scalar( $raw_short_desc )
				? wp_kses_post( (string) $raw_short_desc )
				: ( $release instanceof WP_Post ? wp_kses_post( (string) $release->post_excerpt ) : '' ),
			'sku'               => is_scalar( $raw_sku ) && '' !== trim( (string) $raw_sku )
				? sanitize_text_field( (string) $raw_sku )
				: $default_upc,
			'global_unique_id'  => '' !== $default_upc
				? $default_upc
				: ( is_scalar( $raw_sku ) ? sanitize_text_field( (string) $raw_sku ) : '' ),
			'regular_price'     => is_scalar( $raw_price ) ? sanitize_text_field( (string) $raw_price ) : '',
			'status'            => 'publish' === $raw_status ? 'publish' : 'draft',
			'categories'        => is_array( $raw_categories ) ? array_values( array_filter( array_map( 'absint', $raw_categories ) ) ) : array(),
			'tags'              => self::sanitize_tag_list( $raw_tags ),
			'sync_thumbnail'    => null === $raw_sync_thumb ? true : rest_sanitize_boolean( $raw_sync_thumb ),
			'hub_thumb_id'      => $hub_thumb_id,
			'hub_img_url'       => $hub_img_url,
			'media_info'        => $media_info,
			'release_id'        => $release_id,
			'hub_user_id'       => get_current_user_id(),
		);

		switch_to_blog( $store_id );
		try {
			if ( ! Plugin::is_woocommerce_active_on_current_site() ) {
				return new WP_Error(
					'no_wc',
					__( 'WooCommerce is not active on the selected site.', 'mds-woocommerce-integration' ),
					array( 'status' => 400 )
				);
			}

			// Allow publish only if the user also holds publish_products on the spoke; otherwise default to draft.
			if ( 'publish' === $seed_data['status'] && ! current_user_can( 'publish_products' ) ) {
				$seed_data['status'] = 'draft';
			}

			self::register_product_types( true );
			$product_id = self::create_seeded_product( $seed_data );

			if ( ! $product_id ) {
				return new WP_Error(
					'create_failed',
					__( 'Failed to create product.', 'mds-woocommerce-integration' ),
					array( 'status' => 500 )
				);
			}

			$product_post = get_post( $product_id );
			$summary      = $product_post instanceof WP_Post ? self::format_product_summary( $product_post ) : array();
		} finally {
			restore_current_blog();
		}

		self::save_product_link( $release_id, $store_id, $product_id );
		self::broadcast_release_link( $release_id );
		Plugin::request_cache_purge( 'woocommerce_generate', $store_id );

		return rest_ensure_response(
			array(
				'success'       => true,
				'product_id'    => $product_id,
				'product_title' => $seed_data['name'],
				'permalink'     => $summary['permalink'] ?? '',
				'product'       => $summary,
			)
		);
	}

	/**
	 * Check whether a site is an active WooCommerce store in StoreRegistry.
	 *
	 * @since 1.0.1
	 *
	 * @param int $store_id Store site ID.
	 * @return bool True when the site is an active WooCommerce store.
	 */
	public static function is_integrated_store( int $store_id ): bool {
		return StoreRegistry::is_woocommerce_store( absint( $store_id ) );
	}

	/**
	 * Search WooCommerce products in an integrated store (both published and draft, without tag lock-in).
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public static function search_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_id     = self::get_request_id( $request, 'store_id' );
		$release_id   = self::get_request_id( $request, 'release_id' );
		$search_value = $request->get_param( 'q' );
		$search       = is_scalar( $search_value ) ? sanitize_text_field( (string) $search_value ) : '';

		$access_error = self::validate_release_and_store_access( $release_id, $store_id );
		if ( is_wp_error( $access_error ) ) {
			return $access_error;
		}

		switch_to_blog( $store_id );
		try {
			if ( ! Plugin::is_woocommerce_active_on_current_site() ) {
				return rest_ensure_response( array( 'results' => array() ) );
			}

			self::register_product_types( false );
			$products = get_posts(
				array(
					'post_type'              => 'product',
					'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page'         => 20,
					's'                      => $search,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);

			$results = array();
			foreach ( $products as $product ) {
				if ( $product instanceof WP_Post ) {
					$results[] = self::format_product_summary( $product );
				}
			}
		} finally {
			restore_current_blog();
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Validate that the current Hub user can manage a link/seed action for a release and store.
	 *
	 * @since 1.0.1
	 *
	 * @param int  $release_id          Release post ID.
	 * @param int  $store_id            Store site ID.
	 * @param bool $allow_existing_link Allow unassigned stores that still hold a legacy link (for unlink).
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function validate_release_and_store_access(
		int $release_id,
		int $store_id,
		bool $allow_existing_link = false
	): bool|WP_Error {
		$release = get_post( $release_id );
		if (
			! $release_id
			|| ! $release
			|| 'release' !== $release->post_type
			|| ! current_user_can( 'edit_post', $release_id )
		) {
			return new WP_Error(
				'forbidden_release',
				__( 'You are not allowed to edit this release.', 'mds-woocommerce-integration' ),
				array( 'status' => 403 )
			);
		}

		if ( ! self::is_integrated_store( $store_id ) ) {
			return new WP_Error(
				'invalid_store',
				__( 'The selected store does not have WooCommerce active.', 'mds-woocommerce-integration' ),
				array( 'status' => 403 )
			);
		}

		if ( ! Plugin::user_can_manage_spoke_product( $release_id, $store_id, $allow_existing_link ) ) {
			return new WP_Error(
				'forbidden_store',
				__( 'This store is not assigned to the Release’s Artist or you do not have permission to manage it.', 'mds-woocommerce-integration' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Read a positive scalar request parameter as an integer ID.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request   REST request.
	 * @param string          $parameter Parameter name.
	 * @return int Positive integer ID or 0.
	 */
	private static function get_request_id( WP_REST_Request $request, string $parameter ): int {
		$value = $request->get_param( $parameter );

		if ( ! is_scalar( $value ) || ! preg_match( '/^\d+$/D', (string) $value ) ) {
			return 0;
		}

		return absint( $value );
	}

	/**
	 * Register product post type and taxonomies when switched to a spoke site.
	 *
	 * @since 1.0.1
	 *
	 * @param bool $include_product_type Whether to register the product_type taxonomy.
	 * @return void
	 */
	private static function register_product_types( bool $include_product_type ): void {
		if ( ! post_type_exists( 'product' ) ) {
			register_post_type( 'product' );
		}

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			register_taxonomy( 'product_cat', 'product', array( 'hierarchical' => true ) );
		}

		if ( ! taxonomy_exists( 'product_tag' ) ) {
			register_taxonomy( 'product_tag', 'product' );
		}

		if ( $include_product_type && ! taxonomy_exists( 'product_type' ) ) {
			register_taxonomy( 'product_type', 'product' );
		}
	}

	/**
	 * Insert and seed a WooCommerce product on the current spoke site.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $seed_data Sanitized product seed fields.
	 * @return int Created product post ID, or 0 on failure.
	 */
	private static function create_seeded_product( array $seed_data ): int {
		$product_id = 0;

		if ( class_exists( '\WC_Product_Simple' ) ) {
			$wc_product = new \WC_Product_Simple();
			$wc_product->set_name( (string) $seed_data['name'] );
			$wc_product->set_status( (string) $seed_data['status'] );
			$wc_product->set_description( (string) $seed_data['description'] );
			$wc_product->set_short_description( (string) $seed_data['short_description'] );
			$wc_product->set_catalog_visibility( 'visible' );

			if ( '' !== (string) $seed_data['regular_price'] ) {
				$wc_product->set_regular_price( (string) $seed_data['regular_price'] );
			}

			if ( '' !== (string) $seed_data['sku'] ) {
				try {
					$wc_product->set_sku( (string) $seed_data['sku'] );
				} catch ( \WC_Data_Exception $exception ) {
					// Duplicate SKU on spoke store: leave SKU blank so Store Admin can resolve.
					unset( $exception );
				}
			}

			if ( '' !== (string) $seed_data['global_unique_id'] && method_exists( $wc_product, 'set_global_unique_id' ) ) {
				try {
					$wc_product->set_global_unique_id( (string) $seed_data['global_unique_id'] );
				} catch ( \WC_Data_Exception $exception ) {
					// Invalid or duplicate GTIN/UPC/EAN on spoke store: leave blank for Store Admin review.
					unset( $exception );
				}
			}

			if ( ! empty( $seed_data['categories'] ) ) {
				$wc_product->set_category_ids( $seed_data['categories'] );
			}

			$product_id = (int) $wc_product->save();
		} else {
			$inserted = wp_insert_post(
				array(
					'post_title'   => (string) $seed_data['name'],
					'post_content' => (string) $seed_data['description'],
					'post_excerpt' => (string) $seed_data['short_description'],
					'post_type'    => 'product',
					'post_status'  => (string) $seed_data['status'],
				)
			);

			if ( ! $inserted || is_wp_error( $inserted ) ) {
				return 0;
			}

			$product_id = (int) $inserted;

			if ( '' !== (string) $seed_data['sku'] ) {
				update_post_meta( $product_id, '_sku', (string) $seed_data['sku'] );
			}

			if ( '' !== (string) $seed_data['global_unique_id'] ) {
				$sanitized_gtin = preg_replace( '/[^0-9Xx\-]/', '', (string) $seed_data['global_unique_id'] );
				if ( is_string( $sanitized_gtin ) && '' !== $sanitized_gtin ) {
					update_post_meta( $product_id, '_global_unique_id', $sanitized_gtin );
				}
			}

			if ( '' !== (string) $seed_data['regular_price'] ) {
				update_post_meta( $product_id, '_regular_price', (string) $seed_data['regular_price'] );
				update_post_meta( $product_id, '_price', (string) $seed_data['regular_price'] );
			}

			wp_set_object_terms( $product_id, 'simple', 'product_type', false );

			if ( ! empty( $seed_data['categories'] ) ) {
				wp_set_object_terms( $product_id, $seed_data['categories'], 'product_cat', false );
			}
		}

		if ( ! $product_id ) {
			return 0;
		}

		$tags = array_values( array_unique( array_merge( array( 'release' ), (array) $seed_data['tags'] ) ) );
		wp_set_object_terms( $product_id, $tags, 'product_tag', false );

		if ( ! empty( $seed_data['sync_thumbnail'] ) && ! empty( $seed_data['hub_thumb_id'] ) ) {
			self::seed_product_thumbnail(
				$product_id,
				(int) $seed_data['hub_thumb_id'],
				(string) $seed_data['hub_img_url'],
				(array) $seed_data['media_info']
			);
		}

		update_post_meta( $product_id, '_mds_wc_seeded_from_release', (int) $seed_data['release_id'] );
		update_post_meta( $product_id, '_mds_wc_seeded_by', (int) $seed_data['hub_user_id'] );
		update_post_meta( $product_id, '_mds_wc_seeded_gmt', gmdate( 'Y-m-d H:i:s' ) );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
		clean_post_cache( $product_id );

		return $product_id;
	}

	/**
	 * Seed the product featured image on the current spoke site using Release Manager virtual media.
	 *
	 * @since 1.0.1
	 *
	 * @param int                  $product_id   Product post ID.
	 * @param int                  $hub_thumb_id Hub thumbnail attachment ID.
	 * @param string               $hub_img_url  Hub thumbnail URL.
	 * @param array<string, mixed> $media_info   Optional hub attachment metadata.
	 * @return void
	 */
	private static function seed_product_thumbnail(
		int $product_id,
		int $hub_thumb_id,
		string $hub_img_url,
		array $media_info
	): void {
		$hub_blog_id = ! empty( $media_info['hub_blog_id'] )
			? absint( $media_info['hub_blog_id'] )
			: self::get_hub_blog_id();

		if ( get_current_blog_id() === $hub_blog_id ) {
			set_post_thumbnail( $product_id, $hub_thumb_id );
			return;
		}

		if ( '' === $hub_img_url ) {
			return;
		}

		$virtual_attachment_id = 0;

		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			$virtual_attachment_id = \MDS\ReleaseManager\Api\ExtensionApi::get_or_create_virtual_attachment(
				$hub_img_url,
				$hub_thumb_id,
				$media_info
			);
		}

		if ( $virtual_attachment_id > 0 ) {
			set_post_thumbnail( $product_id, $virtual_attachment_id );
		}
	}

	/**
	 * Gather flat metadata for a hub thumbnail attachment matching MDS_RM_Virtual_Media.
	 *
	 * @since 1.0.1
	 *
	 * @param int $thumb_id Attachment post ID on the hub site.
	 * @return array{width: int, height: int, mime_type: string, hub_blog_id: int} Attachment metadata for virtual media replication.
	 */
	private static function get_hub_thumbnail_info( int $thumb_id ): array {
		$hub_blog_id = self::get_hub_blog_id();

		if ( $thumb_id <= 0 ) {
			return array(
				'width'       => 800,
				'height'      => 800,
				'mime_type'   => 'image/jpeg',
				'hub_blog_id' => $hub_blog_id,
			);
		}

		$mime   = get_post_mime_type( $thumb_id );
		$meta   = wp_get_attachment_metadata( $thumb_id );
		$width  = is_array( $meta ) && ! empty( $meta['width'] ) ? absint( $meta['width'] ) : 800;
		$height = is_array( $meta ) && ! empty( $meta['height'] ) ? absint( $meta['height'] ) : 800;

		return array(
			'width'       => $width > 0 ? $width : 800,
			'height'      => $height > 0 ? $height : 800,
			'mime_type'   => is_string( $mime ) && '' !== $mime ? $mime : 'image/jpeg',
			'hub_blog_id' => $hub_blog_id,
		);
	}

	/**
	 * Resolve the configured Hub blog ID from Release Manager, falling back to main site ID.
	 *
	 * @since 1.0.1
	 *
	 * @return int Hub blog ID.
	 */
	private static function get_hub_blog_id(): int {
		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			return \MDS\ReleaseManager\Api\ExtensionApi::hub_id();
		}

		return is_multisite() ? (int) get_main_site_id() : (int) get_current_blog_id();
	}

	/**
	 * Sanitize a submitted list of product tag names.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $raw_tags Comma-separated string or array of tag names.
	 * @return array<int, string> Sanitized non-empty tag names.
	 */
	private static function sanitize_tag_list( mixed $raw_tags ): array {
		if ( is_string( $raw_tags ) ) {
			$raw_tags = explode( ',', $raw_tags );
		}

		if ( ! is_array( $raw_tags ) ) {
			return array();
		}

		$tags = array();
		foreach ( $raw_tags as $tag ) {
			if ( is_scalar( $tag ) ) {
				$clean = trim( sanitize_text_field( (string) $tag ) );
				if ( '' !== $clean ) {
					$tags[] = $clean;
				}
			}
		}

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Save a linked product ID on its hub release post.
	 *
	 * @since 1.0.1
	 *
	 * @param int $release_id Release post ID.
	 * @param int $store_id   Store site ID.
	 * @param int $product_id Product post ID.
	 * @return void
	 */
	private static function save_product_link( int $release_id, int $store_id, int $product_id ): void {
		$linked = get_post_meta( $release_id, '_mds_linked_products', true );
		if ( ! is_array( $linked ) ) {
			$linked = array();
		}

		$linked[ $store_id ] = $product_id;
		update_post_meta( $release_id, '_mds_linked_products', $linked );

		if ( get_current_blog_id() === $store_id ) {
			update_post_meta( $release_id, '_mds_local_product_id', $product_id );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking MDS Release Manager core cache purge hook.
		do_action( 'mds_rm_purge_cache', 'woocommerce_link', array( 'blog_id' => $store_id ) );
	}

	/**
	 * Broadcast updated release link metadata to spoke sites when Release Manager sync is available.
	 *
	 * @since 1.0.1
	 *
	 * @param int $release_id Release post ID.
	 * @return void
	 */
	private static function broadcast_release_link( int $release_id ): void {
		if ( 'auto-draft' === get_post_status( $release_id ) ) {
			return;
		}

		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::broadcast_to_spokes( $release_id, 'release' );
			\MDS\ReleaseManager\Api\ExtensionApi::purge_cache( 'woocommerce_link' );
		}
	}

	/**
	 * Format a WooCommerce product post on the current spoke site for UI cards and search results.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_Post $product Product post object on the current spoke blog.
	 * @return array<string, mixed> Normalized product summary payload.
	 */
	public static function format_product_summary( WP_Post $product ): array {
		$product_id    = (int) $product->ID;
		$status        = (string) $product->post_status;
		$permalink     = 'publish' === $status ? get_permalink( $product_id ) : '';
		$can_edit      = current_user_can( 'edit_post', $product_id );
		$edit_url      = $can_edit ? get_edit_post_link( $product_id, 'raw' ) : '';
		$thumb_url     = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
		$price_display = '';
		$sku           = (string) get_post_meta( $product_id, '_sku', true );

		if ( function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $product_id );
			if ( $wc_product instanceof \WC_Product ) {
				$raw_price     = $wc_product->get_price();
				$price_display = '' !== $raw_price ? wp_strip_all_tags( wc_price( (float) $raw_price ) ) : '';
				$sku           = (string) $wc_product->get_sku();
			}
		} else {
			$raw_price     = (string) get_post_meta( $product_id, '_price', true );
			$price_display = '' !== $raw_price ? $raw_price : '';
		}

		return array(
			'id'            => $product_id,
			'title'         => (string) $product->post_title,
			'text'          => $product->post_title . ' (ID: ' . $product_id . ')',
			'status'        => $status,
			'price'         => $price_display,
			'sku'           => $sku,
			'permalink'     => is_string( $permalink ) ? $permalink : '',
			'edit_url'      => is_string( $edit_url ) ? $edit_url : '',
			'thumbnail_url' => is_string( $thumb_url ) ? $thumb_url : '',
		);
	}
}
