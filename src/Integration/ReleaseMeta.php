<?php
/**
 * WooCommerce-specific Release Manager display and synchronization hooks.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Integration;

use MDS\WooCommerce\Admin\SiteSettings;
use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Support\StoreRegistry;
use WC_Product;
use WP_Block;
use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates MDS Release Manager blocks, metadata rendering, and shadow-sync hooks with WooCommerce.
 *
 * Supports both Classic WooCommerce template hooks and WordPress Block Theme (`render_block`) anchors
 * for any registered block in the `mds-release-manager` category (including Bandcamp blocks).
 *
 * @since 1.0.1
 */
final class ReleaseMeta {

	/**
	 * Tracks which item IDs have already been injected during the current request.
	 *
	 * @since 1.0.1
	 * @var array<string, bool>
	 */
	private static array $rendered_items = array();

	/**
	 * Per-request memoized eligibility flag for automatic single-product block injection.
	 *
	 * @since 1.1.0
	 * @var bool|null
	 */
	private static ?bool $is_eligible_store = null;

	/**
	 * Per-request cache of linked shadow release UPCs keyed by local WooCommerce product ID.
	 *
	 * @since 2.1.0
	 * @var array<int, string>
	 */
	private static array $linked_upc_cache = array();

	/**
	 * Classic WooCommerce action/priority map keyed by placement identifier.
	 *
	 * @since 1.0.1
	 * @var array<string, array{0: string, 1: int}>
	 */
	private const CLASSIC_PLACEMENTS = array(
		'woocommerce_single_product_summary:5'         => array( 'woocommerce_single_product_summary', 5 ),
		'woocommerce_single_product_summary:6'         => array( 'woocommerce_single_product_summary', 6 ),
		'woocommerce_single_product_summary:15'        => array( 'woocommerce_single_product_summary', 15 ),
		'woocommerce_single_product_summary:25'        => array( 'woocommerce_single_product_summary', 25 ),
		'woocommerce_single_product_summary:45'        => array( 'woocommerce_single_product_summary', 45 ),
		'woocommerce_after_single_product_summary:10'  => array( 'woocommerce_after_single_product_summary', 10 ),
		'woocommerce_before_single_product_summary:25' => array( 'woocommerce_before_single_product_summary', 25 ),
		'woocommerce_after_single_product_summary:12'  => array( 'woocommerce_after_single_product_summary', 12 ),
		'woocommerce_after_single_product_summary:15'  => array( 'woocommerce_after_single_product_summary', 15 ),
	);

	/**
	 * Block Theme anchor map keyed by placement identifier.
	 *
	 * @since 1.0.1
	 * @var array<string, array{block: string, namespace?: string, position: string}>
	 */
	private const BLOCK_PLACEMENTS = array(
		'woocommerce_single_product_summary:5'         => array(
			'block'     => 'core/post-title',
			'namespace' => 'woocommerce/product-query/product-title',
			'position'  => 'before',
		),
		'woocommerce_single_product_summary:6'         => array(
			'block'     => 'core/post-title',
			'namespace' => 'woocommerce/product-query/product-title',
			'position'  => 'after',
		),
		'woocommerce_single_product_summary:15'        => array(
			'block'    => 'woocommerce/product-price',
			'position' => 'after',
		),
		'woocommerce_single_product_summary:25'        => array(
			'block'     => 'core/post-excerpt',
			'namespace' => 'woocommerce/product-query/product-summary',
			'position'  => 'after',
		),
		'woocommerce_single_product_summary:45'        => array(
			'block'    => 'woocommerce/add-to-cart-form',
			'position' => 'after',
		),
		'woocommerce_after_single_product_summary:10'  => array(
			'block'    => 'woocommerce/product-meta',
			'position' => 'after',
		),
		'woocommerce_before_single_product_summary:25' => array(
			'block'    => 'woocommerce/product-image-gallery',
			'position' => 'after',
		),
		'woocommerce_after_single_product_summary:12'  => array(
			'block'    => 'woocommerce/product-details',
			'position' => 'before',
		),
		'woocommerce_after_single_product_summary:15'  => array(
			'block'    => 'woocommerce/product-details',
			'position' => 'after',
		),
	);

	/**
	 * Register WooCommerce Release Meta & dynamic MDS block integration hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'MDS_RM_API_VERSION' ) || ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) || ! Plugin::is_active() ) {
			return;
		}

		add_filter( 'mds_rm_sync_target_sites', array( self::class, 'sync_targets' ), 10, 3 );
		add_filter( 'mds_rm_spoke_protected_meta_keys', array( self::class, 'protect_spoke_meta_keys' ), 10, 2 );
		add_action( 'mds_rm_after_shadow_sync', array( self::class, 'map_product' ), 10, 5 );
		add_filter( 'mds_rm_release_meta_context_upc', array( self::class, 'context_upc' ), 10, 2 );
		add_action( 'mds_rm_after_buy_now_field', array( self::class, 'buy_now_button' ) );
		add_filter( 'mds_rm_default_media_formats', array( self::class, 'register_default_media_formats' ) );

		// Register product injections on `wp` so all blocks (Core + Bandcamp + extensions) are registered.
		add_action( 'wp', array( self::class, 'register_product_injections' ) );
	}

	/**
	 * Register Classic WooCommerce hooks and Block Theme `render_block` injection for configured items.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register_product_injections(): void {
		if ( is_admin() || ! is_singular( 'product' ) || ! self::is_eligible_store_site() ) {
			return;
		}

		$settings = SiteSettings::get_settings();
		$items    = $settings['items'];

		$has_any_enabled = false;

		foreach ( $items as $item ) {
			if ( empty( $item['enabled'] ) ) {
				continue;
			}

			$placement = (string) $item['placement'];
			if ( ! isset( self::CLASSIC_PLACEMENTS[ $placement ] ) ) {
				continue;
			}

			$has_any_enabled = true;
			$hook_def        = self::CLASSIC_PLACEMENTS[ $placement ];

			add_action(
				$hook_def[0],
				static function () use ( $item ): void {
					self::inject_classic_item( $item );
				},
				$hook_def[1]
			);
		}

		if ( $has_any_enabled ) {
			add_filter( 'render_block', array( self::class, 'inject_into_block' ), 10, 2 );
		}
	}

	/**
	 * Enforces Option B scoped WooCommerce store targeting for Hub-to-Spoke sync.
	 *
	 * Non-WooCommerce spokes from Network Admin's `mds_rm_spoke_sites` are preserved,
	 * while WooCommerce store sites are strictly opt-in per Artist (`_mds_wc_target_stores`)
	 * so each store only receives Artists and Releases explicitly assigned to it.
	 *
	 * @since 2.1.0
	 *
	 * @param mixed  $targets   Base sync target site IDs from Core.
	 * @param int    $post_id   Hub post ID being synchronized.
	 * @param string $post_type Post type (`artist` or `release`).
	 * @return array<int, int> Deduplicated target site IDs.
	 */
	public static function sync_targets( mixed $targets, int $post_id = 0, string $post_type = '' ): array {
		$existing         = array_map( 'intval', (array) $targets );
		$all_wc_store_ids = array_map( 'intval', array_keys( StoreRegistry::get_available_stores() ) );
		$non_wc_spokes    = array_values( array_diff( $existing, $all_wc_store_ids ) );
		$assigned_stores  = array();

		if ( $post_id > 0 && 'artist' === $post_type ) {
			$assigned_stores = StoreRegistry::get_artist_target_stores( $post_id );
		} elseif ( $post_id > 0 && 'release' === $post_type ) {
			$assigned_stores = StoreRegistry::get_release_target_stores( $post_id );
		}

		return array_values( array_unique( array_filter( array_merge( $non_wc_spokes, $assigned_stores ) ) ) );
	}

	/**
	 * Protects WooCommerce's spoke-local `_mds_local_product_id` meta from deletion during sync.
	 *
	 * @since 2.1.0
	 *
	 * @param array<int, string> $keys      Protected meta keys.
	 * @param string             $post_type Post type (`artist` or `release`).
	 * @return array<int, string> Updated protected meta keys.
	 */
	public static function protect_spoke_meta_keys( array $keys, string $post_type ): array {
		if ( 'release' === $post_type ) {
			$keys[] = '_mds_local_product_id';
		}
		return $keys;
	}

	/**
	 * Map the linked local WooCommerce product ID onto a synced shadow release post.
	 *
	 * @since 2.0.0
	 *
	 * @param int                  $shadow_id Shadow post ID on the spoke site.
	 * @param int                  $site_id   Spoke site ID.
	 * @param int                  $source_id Source post ID on the hub site.
	 * @param string               $post_type Post type (`release` or `artist`).
	 * @param array<string, mixed> $meta      Source post meta array.
	 * @return void
	 */
	public static function map_product( int $shadow_id, int $site_id, int $source_id, string $post_type, array $meta ): void {
		unset( $source_id );

		if ( 'release' !== $post_type ) {
			return;
		}

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
	 * Resolve a WooCommerce product's linked Release UPC (via `_mds_local_product_id`),
	 * falling back to its GTIN/UPC/EAN or numeric SKU for Release Meta / Links / Player context.
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

		// 1. Prefer an explicit `_mds_local_product_id` link on a local shadow Release.
		if ( ! array_key_exists( $post_id, self::$linked_upc_cache ) ) {
			$linked_upc = '';
			$linked_ids = get_posts(
				array(
					'post_type'              => 'release',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'meta_key'               => '_mds_local_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Memoized lookup for the explicitly linked shadow Release.
					'meta_value'             => (string) $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Memoized lookup for the explicitly linked shadow Release.
				)
			);
			if ( ! empty( $linked_ids ) ) {
				$raw_upc = get_post_meta( (int) $linked_ids[0], 'upc_ean', true );
				if ( is_scalar( $raw_upc ) ) {
					$linked_upc = trim( (string) $raw_upc );
				}
			}
			self::$linked_upc_cache[ $post_id ] = $linked_upc;
		}

		if ( '' !== self::$linked_upc_cache[ $post_id ] ) {
			return self::$linked_upc_cache[ $post_id ];
		}

		// 2. Fall back to WooCommerce product GTIN/UPC/EAN or numeric SKU.
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
	 * Check whether the current site is eligible for automatic WooCommerce product injection.
	 *
	 * Memoized per request so `render_block` never performs repeated option/site lookups
	 * or cross-blog Hub switches on frontend product page views.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when injection is permitted on this site.
	 */
	private static function is_eligible_store_site(): bool {
		if ( null !== self::$is_eligible_store ) {
			return self::$is_eligible_store;
		}

		self::$is_eligible_store = Plugin::is_woocommerce_active_on_current_site();

		return self::$is_eligible_store;
	}

	/**
	 * Render a single configured injection item for a given WooCommerce product ID.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $item    Normalized injection item config.
	 * @param int                  $post_id WooCommerce product post ID.
	 * @return string Rendered HTML markup, or empty string.
	 */
	private static function render_item( array $item, int $post_id ): string {
		$item_id = isset( $item['id'] ) ? (string) $item['id'] : '';
		if ( $post_id <= 0 || '' === $item_id || ! empty( self::$rendered_items[ $item_id ] ) ) {
			return '';
		}

		$block_name = isset( $item['block'] ) ? (string) $item['block'] : '';
		$markup     = '';

		if ( 'mds-rm/release-meta' === $block_name && class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			$markup = \MDS\ReleaseManager\Api\ExtensionApi::render_release_meta( array(), $post_id );
		} elseif ( 'mds-rm/release-links' === $block_name && class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			$attributes = array();
			if ( ! empty( $item['hide_buy_now'] ) ) {
				$attributes['items'] = array(
					array(
						'name'    => '__buy_now',
						'enabled' => false,
					),
				);
			}
			$markup = \MDS\ReleaseManager\Api\ExtensionApi::render_release_links( $attributes, $post_id );
		} elseif ( SiteSettings::CUSTOM_BLOCK_KEY === $block_name ) {
			$content = isset( $item['content'] ) && is_string( $item['content'] ) ? $item['content'] : '';
			if ( '' !== $content ) {
				$markup = do_shortcode( do_blocks( $content ) );
			}
		} elseif ( '' !== $block_name && WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
			$markup = self::render_dynamic_mds_block( $block_name, $post_id );
		}

		if ( '' === trim( $markup ) ) {
			return '';
		}

		self::$rendered_items[ $item_id ] = true;
		$slug_class                       = sanitize_html_class( str_replace( array( '/', '_' ), '-', $block_name ) );

		return sprintf(
			'<div class="mds-wc-injected mds-wc-injected--%1$s">%2$s</div>',
			esc_attr( $slug_class ),
			$markup
		);
	}

	/**
	 * Render any registered MDS dynamic block (Core, Bandcamp, or other extensions) using resolved Release context.
	 *
	 * @since 1.0.1
	 *
	 * @param string $block_name Registered block name.
	 * @param int    $product_id WooCommerce product post ID.
	 * @return string Rendered block HTML.
	 */
	private static function render_dynamic_mds_block( string $block_name, int $product_id ): string {
		$release      = class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			? \MDS\ReleaseManager\Api\ExtensionApi::resolve_post( '', $product_id )
			: null;
		$context_id   = $release ? (int) $release->ID : $product_id;
		$context_type = $release ? 'release' : 'product';
		$attrs        = $release && class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			? \MDS\ReleaseManager\Api\ExtensionApi::resolve_dynamic_block_attrs( $block_name, $release )
			: array();

		$block_instance = new WP_Block(
			array(
				'blockName'    => $block_name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'postId'   => $context_id,
				'postType' => $context_type,
			)
		);

		return (string) $block_instance->render();
	}

	/**
	 * Output a configured item on a Classic WooCommerce product template hook.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $item Normalized injection item config.
	 * @return void
	 */
	private static function inject_classic_item( array $item ): void {
		if ( ! self::is_eligible_store_site() ) {
			return;
		}

		global $product;
		$post_id = $product instanceof WC_Product ? $product->get_id() : (int) get_the_ID();
		$markup  = self::render_item( $item, $post_id );

		if ( '' !== $markup ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block/component renderers escape and sanitize their output.
			echo $markup;
		}
	}

	/**
	 * Inject configured MDS blocks around WooCommerce single-product blocks in Block Themes.
	 *
	 * @since 1.0.1
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block data.
	 * @return string Filtered block HTML.
	 */
	public static function inject_into_block( string $block_content, array $block ): string {
		if ( is_admin() || ! is_singular( 'product' ) || ! self::is_eligible_store_site() ) {
			return $block_content;
		}

		$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
		if ( '' === $block_name ) {
			return $block_content;
		}

		$queried_id = (int) get_queried_object_id();
		$current_id = (int) get_the_ID();
		if ( $queried_id <= 0 || ( $current_id > 0 && $current_id !== $queried_id ) ) {
			return $block_content;
		}

		$settings  = SiteSettings::get_settings();
		$namespace = isset( $block['attrs']['__woocommerceNamespace'] ) && is_string( $block['attrs']['__woocommerceNamespace'] )
			? $block['attrs']['__woocommerceNamespace']
			: '';

		$before = '';
		$after  = '';

		foreach ( $settings['items'] as $item ) {
			$item_id   = (string) $item['id'];
			$placement = (string) $item['placement'];

			if ( empty( $item['enabled'] ) || ! empty( self::$rendered_items[ $item_id ] ) || ! isset( self::BLOCK_PLACEMENTS[ $placement ] ) ) {
				continue;
			}

			$anchor = self::BLOCK_PLACEMENTS[ $placement ];
			if ( $anchor['block'] !== $block_name ) {
				continue;
			}

			if ( isset( $anchor['namespace'] ) && $anchor['namespace'] !== $namespace ) {
				continue;
			}

			$rendered = self::render_item( $item, $queried_id );
			if ( '' === $rendered ) {
				continue;
			}

			if ( 'before' === $anchor['position'] ) {
				$before .= $rendered;
			} else {
				$after .= $rendered;
			}
		}

		return $before . $block_content . $after;
	}

	/**
	 * Registers the "All" default media format with Core's hash-seeded registry.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array{label: string, icon: string}> $formats Default media format registry.
	 * @return array<string, array{label: string, icon: string}> Updated registry.
	 */
	public static function register_default_media_formats( array $formats ): array {
		if ( ! isset( $formats['all'] ) ) {
			$formats['all'] = array(
				'label' => __( 'All', 'mds-woocommerce' ),
				'icon'  => 'fas fa-boxes-stacked',
			);
		}
		return $formats;
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
			. esc_html__( 'Use Linked Product URL', 'mds-woocommerce' )
			. '</button>';
	}
}
