<?php
/**
 * REST API endpoints for MDS Commerce Admin Tools:
 * - Store Connections CRUD & Test Connection
 * - Store Chooser global defaults
 * - Hub Release per-link Promo/Coupon/Priority editor
 * - Hub-side WooCommerce Variable product & WPC External Variations editor
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Rest;

use MDS\Commerce\Config;
use MDS\Commerce\Model\StoreKey;
use MDS\Commerce\Plugin;
use MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge;
use MDS\Commerce\Registry\ConnectionRepository;
use MDS\Commerce\Support\StoreRegistry;
use MDS\Commerce\Sync\RateLimiter;
use MDS\Commerce\Sync\UpgradeRoutine;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles Hub admin tool REST endpoints under `mds-commerce/v1`.
 *
 * @since 3.0.0
 */
final class CommerceAdminRest {

	/**
	 * Register admin-tool REST routes for a namespace.
	 *
	 * @since 3.0.0
	 *
	 * @param string $rest_namespace REST namespace.
	 * @return void
	 */
	public static function register_routes( string $rest_namespace ): void {
		$manage_options = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		$edit_posts = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		register_rest_route(
			$rest_namespace,
			'/stores',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_stores' ),
					'permission_callback' => $edit_posts,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'save_store' ),
					'permission_callback' => $manage_options,
				),
			)
		);

		register_rest_route(
			$rest_namespace,
			'/stores/(?P<store_key>[a-z0-9_:\-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'delete_store' ),
				'permission_callback' => $manage_options,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/stores/(?P<store_key>[a-z0-9_:\-]+)/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'test_store' ),
				'permission_callback' => $manage_options,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/chooser-defaults',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'save_chooser_defaults' ),
				'permission_callback' => $manage_options,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/links/promo',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'save_link_promo' ),
				'permission_callback' => $edit_posts,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/variations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_variations' ),
					'permission_callback' => $edit_posts,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'save_variations' ),
					'permission_callback' => $edit_posts,
				),
			)
		);

		register_rest_route(
			$rest_namespace,
			'/license/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'activate_license' ),
				'permission_callback' => $manage_options,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/license/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'deactivate_license' ),
				'permission_callback' => $manage_options,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/license/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'refresh_license' ),
				'permission_callback' => $manage_options,
			)
		);

		register_rest_route(
			$rest_namespace,
			'/webhooks/gateway',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_gateway_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * List all local auto-detected WooCommerce stores and saved remote connections.
	 *
	 * @since 3.0.0
	 *
	 * @return WP_REST_Response Store list payload.
	 */
	public static function list_stores(): WP_REST_Response {
		$local  = array();
		$stores = StoreRegistry::get_available_stores();

		foreach ( $stores as $blog_id => $meta ) {
			$blog_id   = (int) $blog_id;
			$store_key = StoreKey::for_local_wc( $blog_id )->to_string();
			$local[]   = array(
				'store_key'                    => $store_key,
				'provider'                     => 'wc_local',
				'store_id'                     => (string) $blog_id,
				'blog_id'                      => $blog_id,
				'name'                         => (string) ( $meta['site_name'] ?? '' ),
				'url'                          => (string) ( $meta['site_url'] ?? '' ),
				'sync_mode'                    => 'local',
				'supports_external_variations' => ExternalVariationsBridge::is_active_on_blog( $blog_id ),
				'cooldown_remaining'           => RateLimiter::get_remaining_seconds( $store_key ),
			);
		}

		$remote = ConnectionRepository::get_all_safe();
		foreach ( $remote as &$conn ) {
			$conn['cooldown_remaining'] = RateLimiter::get_remaining_seconds( (string) $conn['store_key'] );
		}
		unset( $conn );

		return new WP_REST_Response(
			array(
				'local'            => $local,
				'remote'           => $remote,
				'chooser_defaults' => ConnectionRepository::get_chooser_defaults(),
				'pod_presets'      => ExternalVariationsBridge::get_pod_presets(),
			),
			200
		);
	}

	/**
	 * Create or update a remote store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Saved connection or error.
	 */
	public static function save_store( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		if ( empty( $params['name'] ) ) {
			return new WP_Error( 'mds_cm_missing_name', __( 'Store name is required.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$saved = ConnectionRepository::save( $params );
		Plugin::request_cache_purge( 'commerce_settings' );

		return new WP_REST_Response(
			array(
				'store'   => $saved,
				'message' => __( 'Store connection saved.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Delete a remote store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Deletion result.
	 */
	public static function delete_store( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_key = sanitize_text_field( (string) $request->get_param( 'store_key' ) );
		if ( ! ConnectionRepository::delete( $store_key ) ) {
			return new WP_Error( 'mds_cm_not_found', __( 'Store connection not found.', 'mds-commerce' ), array( 'status' => 404 ) );
		}

		Plugin::request_cache_purge( 'commerce_settings' );
		return new WP_REST_Response(
			array(
				'deleted' => true,
				'message' => __( 'Store connection deleted.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Test connectivity for a local or remote store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Diagnostic test result.
	 */
	public static function test_store( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$store_key_str = sanitize_text_field( (string) $request->get_param( 'store_key' ) );
		$store_key     = StoreKey::parse( $store_key_str );
		if ( ! $store_key instanceof StoreKey ) {
			return new WP_Error( 'mds_cm_invalid_key', __( 'Invalid store key.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$start = microtime( true );

		if ( 'wc_local' === $store_key->provider() ) {
			$blog_id = $store_key->local_blog_id();
			if ( ! StoreRegistry::is_woocommerce_store( $blog_id ) ) {
				return new WP_Error( 'mds_cm_wc_inactive', __( 'WooCommerce is not active on this site.', 'mds-commerce' ), array( 'status' => 400 ) );
			}

			return new WP_REST_Response(
				array(
					'ok'                           => true,
					'latency_ms'                   => (int) round( ( microtime( true ) - $start ) * 1000 ),
					'supports_external_variations' => ExternalVariationsBridge::is_active_on_blog( $blog_id ),
					'message'                      => __( 'Local WooCommerce store is active and reachable.', 'mds-commerce' ),
				),
				200
			);
		}

		$all = ConnectionRepository::get_all_raw();
		if ( ! isset( $all[ $store_key->to_string() ] ) ) {
			return new WP_Error( 'mds_cm_not_found', __( 'Store connection not found.', 'mds-commerce' ), array( 'status' => 404 ) );
		}

		$record = $all[ $store_key->to_string() ];
		$url    = (string) ( $record['url'] ?? '' );

		if ( '' === $url ) {
			return new WP_Error( 'mds_cm_missing_url', __( 'Store URL is required to test connectivity.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'MDS-Commerce/' . MDS_CM_VERSION . '; ' . home_url( '/' ),
			)
		);

		$latency = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mds_cm_unreachable',
				sprintf(
					/* translators: %s: Underlying HTTP error message. */
					__( 'Connection failed: %s', 'mds-commerce' ),
					$response->get_error_message()
				),
				array( 'status' => 502 )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		return new WP_REST_Response(
			array(
				'ok'          => $status_code >= 200 && $status_code < 400,
				'http_status' => $status_code,
				'latency_ms'  => $latency,
				'message'     => sprintf(
					/* translators: 1: HTTP status code, 2: Latency in milliseconds. */
					__( 'Store responded with HTTP %1$d in %2$dms.', 'mds-commerce' ),
					$status_code,
					$latency
				),
			),
			200
		);
	}

	/**
	 * Save global Store Chooser defaults.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response Saved defaults.
	 */
	public static function save_chooser_defaults( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$saved = ConnectionRepository::save_chooser_defaults( $params );
		Plugin::request_cache_purge( 'commerce_settings' );

		return new WP_REST_Response(
			array(
				'defaults' => $saved,
				'message'  => __( 'Store Chooser defaults saved.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Save per-release Store Chooser promo, coupon, expiration, format, and priority weight.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Saved link metadata or error.
	 */
	public static function save_link_promo( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$release_id = absint( $params['release_id'] ?? 0 );
		$release    = get_post( $release_id );
		if ( ! $release instanceof WP_Post || 'release' !== $release->post_type || ! current_user_can( 'edit_post', $release_id ) ) {
			return new WP_Error( 'mds_cm_forbidden', __( 'You do not have permission to edit this release.', 'mds-commerce' ), array( 'status' => 403 ) );
		}

		$raw_key   = $params['store_key'] ?? $params['store_id'] ?? '';
		$store_key = StoreKey::parse( is_int( $raw_key ) ? $raw_key : (string) $raw_key );
		if ( ! $store_key instanceof StoreKey ) {
			return new WP_Error( 'mds_cm_invalid_store', __( 'Invalid store identifier.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$links = get_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, true );
		if ( ! is_array( $links ) || empty( $links ) ) {
			$legacy = get_post_meta( $release_id, Config::LEGACY_RELEASE_LINKS_META_KEY, true );
			$links  = is_array( $legacy ) ? UpgradeRoutine::convert_legacy_release_links( $legacy ) : array();
		}

		$key_str = $store_key->to_string();
		$entry   = isset( $links[ $key_str ] ) && is_array( $links[ $key_str ] )
			? $links[ $key_str ]
			: array(
				'store_key' => $key_str,
				'provider'  => $store_key->provider(),
				'remote_id' => sanitize_text_field( (string) ( $params['remote_id'] ?? '' ) ),
			);

		$entry['format']            = sanitize_text_field( (string) ( $params['format'] ?? $entry['format'] ?? '' ) );
		$entry['promo_badge']       = sanitize_text_field( (string) ( $params['promo_badge'] ?? '' ) );
		$entry['coupon_code']       = sanitize_text_field( (string) ( $params['coupon_code'] ?? '' ) );
		$entry['promo_expires_gmt'] = sanitize_text_field( (string) ( $params['promo_expires_gmt'] ?? '' ) );
		$entry['priority_weight']   = (int) ( $params['priority_weight'] ?? 0 );

		$links[ $key_str ] = $entry;
		update_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, $links );

		Plugin::request_cache_purge( 'commerce_promo_update', $store_key->local_blog_id() );

		return new WP_REST_Response(
			array(
				'link'    => $entry,
				'message' => __( 'Promo & Store Chooser settings saved.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Fetch WooCommerce product variations and their WPC External Variations (`wpcev_url` / `wpcev_text`) from the Hub.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Variations list or error.
	 */
	public static function get_variations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$release_id = absint( $request->get_param( 'release_id' ) );
		$store_id   = absint( $request->get_param( 'store_id' ) );
		$product_id = absint( $request->get_param( 'product_id' ) );

		if ( ! Plugin::user_can_manage_spoke_product( $release_id, $store_id, true ) || $product_id <= 0 ) {
			return new WP_Error( 'mds_cm_forbidden', __( 'You do not have permission to manage variations on this store.', 'mds-commerce' ), array( 'status' => 403 ) );
		}

		$switched = false;
		if ( is_multisite() && (int) get_current_blog_id() !== $store_id ) {
			switch_to_blog( $store_id );
			$switched = true;
		}

		try {
			$supports_ext  = ExternalVariationsBridge::is_active_on_current_site();
			$variation_ids = get_posts(
				array(
					'post_type'      => 'product_variation',
					'post_status'    => array( 'publish', 'private', 'draft' ),
					'post_parent'    => $product_id,
					'posts_per_page' => 100,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);

			$variations = array();
			foreach ( $variation_ids as $vid ) {
				$vid      = (int) $vid;
				$ext      = ExternalVariationsBridge::get_variation_link( $vid );
				$sku      = (string) get_post_meta( $vid, '_sku', true );
				$price    = (string) get_post_meta( $vid, '_regular_price', true );
				$post_obj = get_post( $vid );

				$variations[] = array(
					'id'         => $vid,
					'title'      => $post_obj instanceof WP_Post ? (string) $post_obj->post_title : sprintf( '#%d', $vid ),
					'sku'        => $sku,
					'price'      => $price,
					'wpcev_url'  => $ext['url'],
					'wpcev_text' => $ext['text'],
				);
			}

			return new WP_REST_Response(
				array(
					'product_id'                   => $product_id,
					'store_id'                     => $store_id,
					'supports_external_variations' => $supports_ext,
					'variations'                   => $variations,
					'pod_presets'                  => ExternalVariationsBridge::get_pod_presets(),
				),
				200
			);
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Update `wpcev_url`, `wpcev_text`, and optional SKU on WooCommerce product variations from the Hub.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Update confirmation or error.
	 */
	public static function save_variations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$release_id = absint( $params['release_id'] ?? 0 );
		$store_id   = absint( $params['store_id'] ?? 0 );
		$product_id = absint( $params['product_id'] ?? 0 );
		$rows       = is_array( $params['variations'] ?? null ) ? $params['variations'] : array();

		if ( ! Plugin::user_can_manage_spoke_product( $release_id, $store_id, false ) || $product_id <= 0 ) {
			return new WP_Error( 'mds_cm_forbidden', __( 'You do not have permission to update variations on this store.', 'mds-commerce' ), array( 'status' => 403 ) );
		}

		$switched = false;
		if ( is_multisite() && (int) get_current_blog_id() !== $store_id ) {
			switch_to_blog( $store_id );
			$switched = true;
		}

		$updated = 0;
		try {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$vid = absint( $row['id'] ?? 0 );
				if ( $vid <= 0 || (int) wp_get_post_parent_id( $vid ) !== $product_id ) {
					continue;
				}

				$url  = isset( $row['wpcev_url'] ) && is_scalar( $row['wpcev_url'] ) ? (string) $row['wpcev_url'] : '';
				$text = isset( $row['wpcev_text'] ) && is_scalar( $row['wpcev_text'] ) ? (string) $row['wpcev_text'] : '';
				ExternalVariationsBridge::set_variation_link( $vid, $url, $text );

				if ( isset( $row['sku'] ) && is_scalar( $row['sku'] ) ) {
					update_post_meta( $vid, '_sku', sanitize_text_field( (string) $row['sku'] ) );
				}

				++$updated;
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}

		Plugin::request_cache_purge( 'commerce_variations_update', $store_id );

		return new WP_REST_Response(
			array(
				'updated' => $updated,
				'message' => sprintf(
					/* translators: %d: Number of updated variations. */
					_n( 'Updated %d variation.', 'Updated %d variations.', $updated, 'mds-commerce' ),
					$updated
				),
			),
			200
		);
	}

	/**
	 * Activate a commercial license key against the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Activation status or error.
	 */
	public static function activate_license( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$key = isset( $params['license_key'] ) && is_scalar( $params['license_key'] )
			? trim( (string) $params['license_key'] )
			: '';

		$result = \MDS\Commerce\Gateway\EntitlementManager::activate( $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'entitlements' => $result,
				'message'      => __( 'MDS Commerce license activated.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Deactivate the active license key.
	 *
	 * @since 3.0.1
	 *
	 * @return WP_REST_Response Cleared entitlement state.
	 */
	public static function deactivate_license(): WP_REST_Response {
		$result = \MDS\Commerce\Gateway\EntitlementManager::deactivate();
		return new WP_REST_Response(
			array(
				'entitlements' => $result,
				'message'      => __( 'MDS Commerce license deactivated.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Refresh commercial entitlements from the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @return WP_REST_Response|WP_Error Refreshed entitlements or error.
	 */
	public static function refresh_license(): WP_REST_Response|WP_Error {
		$result = \MDS\Commerce\Gateway\EntitlementManager::refresh();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'entitlements' => $result,
				'message'      => __( 'Entitlements refreshed from the MDS API Gateway.', 'mds-commerce' ),
			),
			200
		);
	}

	/**
	 * Receive HMAC-SHA256 signed real-time stock/price/URL updates relayed by the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param WP_REST_Request $request Incoming webhook request.
	 * @return WP_REST_Response|WP_Error Processing result or auth error.
	 */
	public static function handle_gateway_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$raw_body  = $request->get_body();
		$signature = (string) $request->get_header( 'x-mds-signature' );

		$entitlements = \MDS\Commerce\Gateway\EntitlementManager::get_status();
		$secret       = \MDS\Commerce\Security\CredentialVault::resolve_secret(
			'MDS_CM_WEBHOOK_SECRET',
			(string) ( $entitlements['webhook_secret'] ?? '' )
		);

		if ( '' === $secret || '' === $signature ) {
			return new WP_Error( 'mds_cm_webhook_unauthorized', __( 'Missing webhook signature or secret.', 'mds-commerce' ), array( 'status' => 401 ) );
		}

		$expected = hash_hmac( 'sha256', $raw_body, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'mds_cm_webhook_invalid_sig', __( 'Invalid webhook signature.', 'mds-commerce' ), array( 'status' => 403 ) );
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'mds_cm_webhook_bad_json', __( 'Invalid JSON payload.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$release_id = absint( $payload['release_id'] ?? 0 );
		$store_key  = StoreKey::parse( (string) ( $payload['store_key'] ?? '' ) );

		if ( $release_id <= 0 || ! $store_key instanceof StoreKey ) {
			return new WP_Error( 'mds_cm_webhook_missing_ids', __( 'Webhook requires valid release_id and store_key.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$links = get_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, true );
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		$k     = $store_key->to_string();
		$entry = isset( $links[ $k ] ) && is_array( $links[ $k ] )
			? $links[ $k ]
			: array(
				'store_key' => $k,
				'provider'  => $store_key->provider(),
			);

		foreach ( array( 'remote_id', 'price', 'stock_status', 'permalink', 'url', 'store_name', 'format' ) as $field ) {
			if ( isset( $payload[ $field ] ) && is_scalar( $payload[ $field ] ) ) {
				$entry[ $field ] = in_array( $field, array( 'permalink', 'url' ), true )
					? esc_url_raw( (string) $payload[ $field ] )
					: sanitize_text_field( (string) $payload[ $field ] );
			}
		}
		$entry['last_synced_gmt'] = gmdate( 'Y-m-d H:i:s' );

		$links[ $k ] = $entry;
		update_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, $links );
		Plugin::request_cache_purge( 'commerce_webhook' );

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}
}
