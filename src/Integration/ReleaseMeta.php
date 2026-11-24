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
		if ( ! class_exists( '\MDS_RM_Release_Meta' ) || ! Plugin::is_active() ) {
			return;
		}

		add_filter( 'mds_rm_sync_target_sites', array( self::class, 'sync_targets' ) );
		add_action( 'mds_rm_after_shadow_meta_sync', array( self::class, 'map_product' ), 10, 4 );
		add_filter( 'mds_rm_release_meta_context_upc', array( self::class, 'context_upc' ), 10, 2 );
		add_action( 'mds_rm_after_buy_now_field', array( self::class, 'buy_now_button' ) );
		add_action( 'init', array( self::class, 'ensure_all_media_format' ), 20 );

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
	 * Check whether the current site is eligible for automatic WooCommerce product injection.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when injection is permitted on this site.
	 */
	private static function is_eligible_store_site(): bool {
		if ( ! is_multisite() ) {
			return true;
		}

		$sites = Plugin::get_integrated_sites();

		return in_array( (int) get_current_blog_id(), $sites, true );
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

		if ( 'mds-rm/release-meta' === $block_name && class_exists( '\MDS_RM_Release_Meta' ) ) {
			$markup = (string) \MDS_RM_Release_Meta::render( array(), $post_id );
		} elseif ( 'mds-rm/release-links' === $block_name && class_exists( '\MDS_RM_Release_Links' ) ) {
			$attributes = array();
			if ( ! empty( $item['hide_buy_now'] ) ) {
				$attributes['items'] = array(
					array(
						'name'    => '__buy_now',
						'enabled' => false,
					),
				);
			}
			$markup = (string) \MDS_RM_Release_Links::render( $attributes, $post_id );
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
		$release      = class_exists( '\MDS_RM_Release_Meta' ) ? \MDS_RM_Release_Meta::resolve_post( '', $product_id ) : null;
		$context_id   = $release ? (int) $release->ID : $product_id;
		$context_type = $release ? 'release' : 'product';
		$attrs        = array();

		if ( $release ) {
			$attrs['releaseId'] = (int) $release->ID;

			if ( str_starts_with( $block_name, 'mds-bandcamp/' ) ) {
				$bc_album_id = (string) get_post_meta( $release->ID, 'bandcamp_album_id', true );
				$bc_url      = (string) get_post_meta( $release->ID, 'bandcamp_album_url', true );
				if ( '' !== $bc_album_id ) {
					$attrs['albumID'] = $bc_album_id;
				}
				if ( '' !== $bc_url ) {
					$attrs['url'] = $bc_url;
				}
			}
		}

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
