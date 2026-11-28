<?php
/**
 * Gateway-backed remote commerce provider (Shopify, Remote WooCommerce, Wix, Big Cartel, Squarespace).
 *
 * Keeps OAuth client secrets, quarterly API version migrations, GraphQL cost-bucket pacing,
 * and platform payload translators protected behind the MDS API Gateway.
 *
 * @package   MDS\Commerce
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @since     3.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Providers\Gateway;

use MDS\Commerce\Contracts\CommerceProviderInterface;
use MDS\Commerce\Contracts\EmbeddableProviderInterface;
use MDS\Commerce\Gateway\EntitlementManager;
use MDS\Commerce\Gateway\GatewayClient;
use MDS\Commerce\Model\NormalizedProduct;
use MDS\Commerce\Model\StoreConnection;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Delegates remote storefront operations to the MDS API Gateway while supporting inline Shopify embeds.
 *
 * @since 3.0.1
 */
final class GatewayRemoteProvider implements CommerceProviderInterface, EmbeddableProviderInterface {

	/**
	 * Remote provider slug (`shopify`, `wc_remote`, `wix`, `bigcartel`, `squarespace`).
	 *
	 * @since 3.0.1
	 * @var string
	 */
	private string $slug;

	/**
	 * Human-readable provider label.
	 *
	 * @since 3.0.1
	 * @var string
	 */
	private string $label;

	/**
	 * Construct a GatewayRemoteProvider instance.
	 *
	 * @since 3.0.1
	 *
	 * @param string $slug  Provider slug.
	 * @param string $label Display label.
	 */
	public function __construct( string $slug, string $label ) {
		$this->slug  = sanitize_key( $slug );
		$this->label = $label;
	}

	/**
	 * Return provider slug.
	 *
	 * @since 3.0.1
	 *
	 * @return string Provider slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Return human-readable provider label.
	 *
	 * @since 3.0.1
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Return capability flags advertised by this remote provider.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection|null $store Optional store connection.
	 * @return array<string, bool> Capability map.
	 */
	public function get_capabilities( ?StoreConnection $store = null ): array {
		unset( $store );
		return array(
			'search'                       => true,
			'link'                         => true,
			'seed_draft'                   => true,
			'sync_drift'                   => true,
			'supports_external_variations' => false,
			'frontend_inject'              => false,
			'embed_checkout'               => 'shopify' === $this->slug,
			'webhooks'                     => EntitlementManager::can_use_webhooks(),
		);
	}

	/**
	 * Verify connectivity for a remote store via the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection $store Store connection.
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public function test_connection( StoreConnection $store ): bool|WP_Error {
		$res = GatewayClient::request(
			'commerce/stores/test',
			'POST',
			array(
				'provider'  => $this->slug,
				'store_key' => $store->key->to_string(),
				'url'       => $store->url,
			)
		);

		return is_wp_error( $res ) ? $res : ! empty( $res['ok'] );
	}

	/**
	 * Search remote store products via the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection $store Store connection.
	 * @param string          $query Search query.
	 * @param int             $page  Pagination page.
	 * @return array<int, NormalizedProduct>|WP_Error Normalized products or error.
	 */
	public function search_products( StoreConnection $store, string $query, int $page = 1 ): array|WP_Error {
		$res = GatewayClient::request(
			'commerce/products/search',
			'POST',
			array(
				'provider'  => $this->slug,
				'store_key' => $store->key->to_string(),
				'query'     => $query,
				'page'      => max( 1, $page ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$items   = is_array( $res['products'] ?? null ) ? $res['products'] : array();
		$results = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$results[] = new NormalizedProduct( $item );
			}
		}

		return $results;
	}

	/**
	 * Fetch a single normalized product from the remote store via the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection $store     Store connection.
	 * @param string          $remote_id Remote product identifier.
	 * @return NormalizedProduct|null Product DTO or null.
	 */
	public function get_product( StoreConnection $store, string $remote_id ): ?NormalizedProduct {
		$res = GatewayClient::request(
			'commerce/products/get',
			'POST',
			array(
				'provider'  => $this->slug,
				'store_key' => $store->key->to_string(),
				'remote_id' => $remote_id,
			)
		);

		if ( is_wp_error( $res ) || ! is_array( $res['product'] ?? null ) ) {
			return null;
		}

		return new NormalizedProduct( $res['product'] );
	}

	/**
	 * Seed a new draft product on the remote store via the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection      $store   Target store connection.
	 * @param WP_Post              $release Hub Release post object.
	 * @param array<string, mixed> $options Seeding options.
	 * @return NormalizedProduct|WP_Error Seeded product DTO or error.
	 */
	public function create_draft_product( StoreConnection $store, WP_Post $release, array $options = array() ): NormalizedProduct|WP_Error {
		$payload = array(
			'provider'  => $this->slug,
			'store_key' => $store->key->to_string(),
			'release'   => array(
				'id'           => (int) $release->ID,
				'title'        => (string) $release->post_title,
				'excerpt'      => wp_strip_all_tags( (string) $release->post_excerpt ),
				'content'      => wp_strip_all_tags( (string) $release->post_content ),
				'upc_ean'      => (string) get_post_meta( (int) $release->ID, 'upc_ean', true ),
				'release_date' => (string) get_post_meta( (int) $release->ID, 'release_date', true ),
				'record_label' => (string) get_post_meta( (int) $release->ID, 'record_label', true ),
				'image_url'    => (string) get_the_post_thumbnail_url( (int) $release->ID, 'full' ),
			),
			'options'   => $options,
		);

		/**
		 * Filters the normalized release payload before sending to the MDS API Gateway.
		 *
		 * @since 3.0.1
		 *
		 * @param array<string, mixed> $payload Gateway payload.
		 * @param WP_Post              $release Hub Release post object.
		 * @param StoreConnection      $store   Target store connection.
		 */
		$payload = (array) apply_filters( 'mds_cm_release_payload', $payload, $release, $store );

		$res = GatewayClient::request( 'commerce/products/create', 'POST', $payload );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		if ( ! is_array( $res['product'] ?? null ) ) {
			return new WP_Error( 'mds_cm_invalid_gateway_product', __( 'Gateway did not return a valid product payload.', 'mds-commerce' ) );
		}

		return new NormalizedProduct( $res['product'] );
	}

	/**
	 * Selectively sync admin-approved drift fields via the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection    $store     Target store connection.
	 * @param WP_Post            $release   Hub Release post object.
	 * @param string             $remote_id Remote product ID.
	 * @param array<int, string> $fields    Approved field keys.
	 * @return NormalizedProduct|WP_Error Updated product DTO or error.
	 */
	public function sync_fields( StoreConnection $store, WP_Post $release, string $remote_id, array $fields ): NormalizedProduct|WP_Error {
		$res = GatewayClient::request(
			'commerce/products/sync',
			'POST',
			array(
				'provider'  => $this->slug,
				'store_key' => $store->key->to_string(),
				'remote_id' => $remote_id,
				'fields'    => array_values( $fields ),
				'release'   => array(
					'id'        => (int) $release->ID,
					'title'     => (string) $release->post_title,
					'upc_ean'   => (string) get_post_meta( (int) $release->ID, 'upc_ean', true ),
					'image_url' => (string) get_the_post_thumbnail_url( (int) $release->ID, 'full' ),
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		if ( ! is_array( $res['product'] ?? null ) ) {
			return new WP_Error( 'mds_cm_invalid_gateway_product', __( 'Gateway did not return an updated product payload.', 'mds-commerce' ) );
		}

		return new NormalizedProduct( $res['product'] );
	}

	/**
	 * Render an inline Shopify Storefront Web Component embed when configured.
	 *
	 * @since 3.0.1
	 *
	 * @param StoreConnection      $store     Store connection.
	 * @param string               $remote_id Shopify product handle or GID.
	 * @param array<string, mixed> $options   Optional rendering options (`storefront_token`).
	 * @return string Escaped HTML embed markup (empty for non-Shopify providers).
	 */
	public function render_embed( StoreConnection $store, string $remote_id, array $options = array() ): string {
		if ( 'shopify' !== $this->slug || '' === $store->url || '' === $remote_id ) {
			return '';
		}

		$token = sanitize_text_field( (string) ( $options['storefront_token'] ?? '' ) );
		if ( '' === $token ) {
			return '';
		}

		$domain = (string) wp_parse_url( $store->url, PHP_URL_HOST );
		if ( '' === $domain ) {
			return '';
		}

		return sprintf(
			'<shopify-store store-domain="%1$s" public-access-token="%2$s"><shopify-context type="product" handle="%3$s"><template><button type="button" class="mds-cm-offer-cta" onclick="document.querySelector(\'shopify-cart\')?.addLine(event)">%4$s</button></template></shopify-context></shopify-store>',
			esc_attr( $domain ),
			esc_attr( $token ),
			esc_attr( $remote_id ),
			esc_html__( 'Quick Add to Cart', 'mds-commerce' )
		);
	}
}
