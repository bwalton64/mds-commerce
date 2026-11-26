<?php
/**
 * WordPress 7.x Abilities API & MCP registration for MDS Commerce.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Api;

use MDS\Commerce\Config;
use MDS\Commerce\Providers\Prep\PodProvisioningProvider;
use MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge;
use MDS\Commerce\Support\ProductDrift;
use MDS\Commerce\Support\StoreRegistry;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers read-only, schema-validated MDS Commerce abilities with `meta.mcp.public = true`.
 *
 * @since 3.0.0
 */
final class Abilities {

	/**
	 * Hook ability registration onto WordPress 7.x `wp_abilities_api_init`.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Register MDS Commerce abilities when the WordPress Abilities API is present.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'mds-commerce/list-stores',
			array(
				'label'               => __( 'List MDS Commerce Stores', 'mds-commerce' ),
				'description'         => __( 'Returns configured local and remote commerce stores and their capabilities (never credentials).', 'mds-commerce' ),
				'category'            => 'mds-release-manager',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'execute_callback'    => array( self::class, 'execute_list_stores' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'mcp' => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'mds-commerce/get-release-links',
			array(
				'label'               => __( 'Get Release Store Links', 'mds-commerce' ),
				'description'         => __( 'Returns all linked store products and promo metadata for a Hub Release ID.', 'mds-commerce' ),
				'category'            => 'mds-release-manager',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'release_id' => array(
							'type'        => 'integer',
							'description' => __( 'Hub Release post ID.', 'mds-commerce' ),
						),
					),
					'required'   => array( 'release_id' ),
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( self::class, 'execute_get_release_links' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'mcp' => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'mds-commerce/get-pod-provisioning-pack',
			array(
				'label'               => __( 'Get Physical POD Provisioning Pack', 'mds-commerce' ),
				'description'         => __( 'Returns verified release metadata, tracklist, and artwork specs for filling manual-portal POD forms (elasticStage, TrepStar, Kunaki, Diggers Factory, Bandcamp).', 'mds-commerce' ),
				'category'            => 'mds-release-manager',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'release_id' => array(
							'type'        => 'integer',
							'description' => __( 'Hub Release post ID.', 'mds-commerce' ),
						),
						'channel'    => array(
							'type'        => 'string',
							'description' => __( 'Target channel slug (elasticstage, trepstar, kunaki, diggers_factory, qrates, bandcamp).', 'mds-commerce' ),
							'default'     => 'elasticstage',
						),
					),
					'required'   => array( 'release_id' ),
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( self::class, 'execute_get_pod_provisioning_pack' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'mcp' => array( 'public' => true ),
				),
			)
		);
	}

	/**
	 * Execute `mds-commerce/list-stores`.
	 *
	 * @since 3.0.0
	 *
	 * @return array<int, array<string, mixed>> Available stores with capability flags.
	 */
	public static function execute_list_stores(): array {
		$stores = StoreRegistry::get_available_stores();
		$output = array();

		foreach ( $stores as $blog_id => $meta ) {
			$blog_id  = (int) $blog_id;
			$output[] = array(
				'store_key'                    => 'wc_local:' . $blog_id,
				'provider'                     => 'wc_local',
				'blog_id'                      => $blog_id,
				'name'                         => (string) ( $meta['site_name'] ?? '' ),
				'url'                          => (string) ( $meta['site_url'] ?? '' ),
				'supports_external_variations' => ExternalVariationsBridge::is_active_on_blog( $blog_id ),
			);
		}

		return $output;
	}

	/**
	 * Execute `mds-commerce/get-release-links`.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error Release link map or error.
	 */
	public static function execute_get_release_links( array $input ): array|WP_Error {
		$release_id = absint( $input['release_id'] ?? 0 );
		$release    = get_post( $release_id );

		if ( ! $release instanceof WP_Post || 'release' !== $release->post_type ) {
			return new WP_Error( 'mds_cm_release_not_found', __( 'Release not found.', 'mds-commerce' ) );
		}

		$links = get_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, true );
		if ( ! is_array( $links ) || empty( $links ) ) {
			$legacy = get_post_meta( $release_id, Config::LEGACY_RELEASE_LINKS_META_KEY, true );
			$links  = is_array( $legacy ) ? \MDS\Commerce\Sync\UpgradeRoutine::convert_legacy_release_links( $legacy ) : array();
		}

		return array(
			'release_id' => $release_id,
			'title'      => (string) $release->post_title,
			'links'      => $links,
		);
	}

	/**
	 * Execute `mds-commerce/get-pod-provisioning-pack`.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error Provisioning pack or error.
	 */
	public static function execute_get_pod_provisioning_pack( array $input ): array|WP_Error {
		$release_id = absint( $input['release_id'] ?? 0 );
		$channel    = sanitize_key( (string) ( $input['channel'] ?? 'elasticstage' ) );
		$release    = get_post( $release_id );

		if ( ! $release instanceof WP_Post || 'release' !== $release->post_type ) {
			return new WP_Error( 'mds_cm_release_not_found', __( 'Release not found.', 'mds-commerce' ) );
		}

		$provider = new PodProvisioningProvider();
		return $provider->build_provisioning_pack( $release, array( 'channel' => $channel ) );
	}
}
