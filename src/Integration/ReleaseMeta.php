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

defined( 'ABSPATH' ) || exit;

/**
 * Integrates MDS Release Manager metadata/links rendering and shadow-sync hooks with WooCommerce.
 *
 * Supports both Classic WooCommerce template hooks and WordPress Block Theme (`render_block`) anchors.
 *
 * @since 1.0.1
 */
final class ReleaseMeta {

	/**
	 * Tracks which components have already been injected during the current request.
	 *
	 * @since 1.0.1
	 * @var array<string, bool>
	 */
	private static array $rendered_components = array();

	/**
	 * Classic WooCommerce action/priority map keyed by placement identifier.
	 *
	 * @since 1.0.1
	 * @var array<string, array{0: string, 1: int}>
	 */
	private const CLASSIC_PLACEMENTS = array(
		'woocommerce_single_product_summary:5'        => array( 'woocommerce_single_product_summary', 5 ),
		'woocommerce_single_product_summary:15'       => array( 'woocommerce_single_product_summary', 15 ),
		'woocommerce_single_product_summary:25'       => array( 'woocommerce_single_product_summary', 25 ),
		'woocommerce_single_product_summary:45'       => array( 'woocommerce_single_product_summary', 45 ),
		'woocommerce_after_single_product_summary:10' => array( 'woocommerce_after_single_product_summary', 10 ),
		'woocommerce_after_single_product_summary:15' => array( 'woocommerce_after_single_product_summary', 15 ),
	);

	/**
	 * Block Theme anchor map keyed by placement identifier.
	 *
	 * @since 1.0.1
	 * @var array<string, array{block: string, namespace?: string, position: string}>
	 */
	private const BLOCK_PLACEMENTS = array(
		'woocommerce_single_product_summary:5'        => array(
			'block'     => 'core/post-title',
			'namespace' => 'woocommerce/product-query/product-title',
			'position'  => 'before',
		),
		'woocommerce_single_product_summary:15'       => array(
			'block'    => 'woocommerce/product-price',
			'position' => 'after',
		),
		'woocommerce_single_product_summary:25'       => array(
			'block'     => 'core/post-excerpt',
			'namespace' => 'woocommerce/product-query/product-summary',
			'position'  => 'after',
		),
		'woocommerce_single_product_summary:45'       => array(
			'block'    => 'woocommerce/add-to-cart-form',
			'position' => 'after',
		),
		'woocommerce_after_single_product_summary:10' => array(
			'block'    => 'woocommerce/product-meta',
			'position' => 'after',
		),
		'woocommerce_after_single_product_summary:15' => array(
			'block'    => 'woocommerce/product-details',
			'position' => 'after',
		),
	);

	/**
	 * Register WooCommerce Release Meta & Release Links integration hooks.
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

		self::register_product_injections();
	}

	/**
	 * Register Classic WooCommerce hooks and Block Theme `render_block` injection for configured slots.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	private static function register_product_injections(): void {
		$settings = SiteSettings::get_settings();
		$active   = array(
			'release_meta'  => $settings['release_meta_placement'],
			'release_links' => $settings['release_links_placement'],
			'custom'        => '' !== $settings['custom_content'] ? $settings['custom_placement'] : 'disabled',
		);

		$has_any_enabled = false;

		foreach ( $active as $component => $placement ) {
			if ( 'disabled' === $placement || ! isset( self::CLASSIC_PLACEMENTS[ $placement ] ) ) {
				continue;
			}

			$has_any_enabled = true;
			$hook_def        = self::CLASSIC_PLACEMENTS[ $placement ];

			add_action(
				$hook_def[0],
				static function () use ( $component ): void {
					self::inject_classic( $component );
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
	 * Render a single configured product-page component for a given product post ID.
	 *
	 * @since 1.0.1
	 *
	 * @param string $component Component key ('release_meta', 'release_links', or 'custom').
	 * @param int    $post_id   WooCommerce product post ID.
	 * @return string Rendered HTML markup, or empty string.
	 */
	private static function render_component( string $component, int $post_id ): string {
		if ( $post_id <= 0 || ! empty( self::$rendered_components[ $component ] ) ) {
			return '';
		}

		$settings = SiteSettings::get_settings();
		$markup   = '';

		if ( 'release_meta' === $component && class_exists( '\MDS_RM_Release_Meta' ) ) {
			$markup = (string) \MDS_RM_Release_Meta::render( array(), $post_id );
		} elseif ( 'release_links' === $component && class_exists( '\MDS_RM_Release_Links' ) ) {
			$attributes = array();
			if ( ! empty( $settings['hide_buy_now'] ) ) {
				$attributes['items'] = array(
					array(
						'name'    => '__buy_now',
						'enabled' => false,
					),
				);
			}
			$markup = (string) \MDS_RM_Release_Links::render( $attributes, $post_id );
		} elseif ( 'custom' === $component && '' !== $settings['custom_content'] ) {
			$markup = do_shortcode( do_blocks( $settings['custom_content'] ) );
		}

		if ( '' === trim( $markup ) ) {
			return '';
		}

		self::$rendered_components[ $component ] = true;

		return sprintf(
			'<div class="mds-wc-injected mds-wc-injected--%1$s">%2$s</div>',
			esc_attr( str_replace( '_', '-', $component ) ),
			$markup
		);
	}

	/**
	 * Output a configured component on a Classic WooCommerce product template hook.
	 *
	 * @since 1.0.1
	 *
	 * @param string $component Component key ('release_meta', 'release_links', or 'custom').
	 * @return void
	 */
	private static function inject_classic( string $component ): void {
		if ( ! self::is_eligible_store_site() ) {
			return;
		}

		global $product;
		$post_id = $product instanceof WC_Product ? $product->get_id() : (int) get_the_ID();
		$markup  = self::render_component( $component, $post_id );

		if ( '' !== $markup ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component renderers escape and sanitize their output.
			echo $markup;
		}
	}

	/**
	 * Inject configured components around WooCommerce single-product blocks in Block Themes.
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

		$settings   = SiteSettings::get_settings();
		$components = array(
			'release_meta'  => $settings['release_meta_placement'],
			'release_links' => $settings['release_links_placement'],
			'custom'        => '' !== $settings['custom_content'] ? $settings['custom_placement'] : 'disabled',
		);

		$namespace = isset( $block['attrs']['__woocommerceNamespace'] ) && is_string( $block['attrs']['__woocommerceNamespace'] )
			? $block['attrs']['__woocommerceNamespace']
			: '';

		$before = '';
		$after  = '';

		foreach ( $components as $component => $placement ) {
			if ( ! empty( self::$rendered_components[ $component ] ) || ! isset( self::BLOCK_PLACEMENTS[ $placement ] ) ) {
				continue;
			}

			$anchor = self::BLOCK_PLACEMENTS[ $placement ];
			if ( $anchor['block'] !== $block_name ) {
				continue;
			}

			if ( isset( $anchor['namespace'] ) && $anchor['namespace'] !== $namespace ) {
				continue;
			}

			$rendered = self::render_component( $component, $queried_id );
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
