<?php
/**
 * WooCommerce-specific Release Manager display and synchronization hooks.
 *
 * @package MDS_WooCommerce_Integration
 */

defined( 'ABSPATH' ) || exit;

final class MDS_Woo_Release_Meta {
	public static function register() {
		if ( ! class_exists( 'MDS_RM_Release_Meta' ) || ! mds_woo_is_active() ) {
			return;
		}
		add_filter( 'mds_rm_sync_target_sites', array( self::class, 'sync_targets' ) );
		add_action( 'mds_rm_after_shadow_meta_sync', array( self::class, 'map_product' ), 10, 4 );
		add_filter( 'mds_rm_release_meta_context_upc', array( self::class, 'context_upc' ), 10, 2 );
		add_action( 'mds_rm_after_buy_now_field', array( self::class, 'buy_now_button' ) );

		$placements = array(
			'woocommerce_single_product_summary:5'        => array( 'woocommerce_single_product_summary', 5 ),
			'woocommerce_single_product_summary:15'       => array( 'woocommerce_single_product_summary', 15 ),
			'woocommerce_single_product_summary:25'       => array( 'woocommerce_single_product_summary', 25 ),
			'woocommerce_single_product_summary:45'       => array( 'woocommerce_single_product_summary', 45 ),
			'woocommerce_after_single_product_summary:10' => array( 'woocommerce_after_single_product_summary', 10 ),
		);
		$placement = get_theme_mod( 'mds_rm_woo_injection', 'woocommerce_single_product_summary:25' );
		if ( 'disabled' === $placement ) {
			return;
		}
		if ( ! is_string( $placement ) || ! isset( $placements[ $placement ] ) ) {
			$placement = 'woocommerce_single_product_summary:25';
		}
		add_action( $placements[ $placement ][0], array( self::class, 'inject' ), $placements[ $placement ][1] );
	}

	public static function sync_targets( $targets ) {
		$legacy_targets = array_map( 'intval', (array) get_site_option( 'mds_rm_woo_sites', array() ) );
		return array_values( array_unique( array_merge( (array) $targets, $legacy_targets ) ) );
	}

	public static function map_product( $shadow_id, $site_id, $_source_id, $meta ) {
		$linked = isset( $meta['_mds_linked_products'][0] ) ? maybe_unserialize( $meta['_mds_linked_products'][0] ) : array();
		if ( is_array( $linked ) && isset( $linked[ $site_id ] ) ) {
			update_post_meta( $shadow_id, '_mds_local_product_id', $linked[ $site_id ] );
		} else {
			delete_post_meta( $shadow_id, '_mds_local_product_id' );
		}
	}

	public static function context_upc( $upc, $post_id ) {
		if ( '' !== (string) $upc || ! function_exists( 'wc_get_product' ) || 'product' !== get_post_type( $post_id ) ) {
			return $upc;
		}
		$product = wc_get_product( $post_id );
		$sku     = $product instanceof WC_Product ? (string) $product->get_sku() : '';
		return preg_match( '/^\d+$/D', $sku ) ? $sku : '';
	}

	public static function inject() {
		if ( is_multisite() ) {
			$sites = array_map( 'intval', (array) get_site_option( 'mds_rm_woo_sites', get_option( 'mds_rm_woo_sites', array() ) ) );
			if ( ! in_array( (int) get_current_blog_id(), $sites, true ) ) {
				return;
			}
		}
		global $product;
		$post_id = $product instanceof WC_Product ? $product->get_id() : get_the_ID();
		// The Release Meta renderer escapes its output and validates all style values.
		echo MDS_RM_Release_Meta::render( array(), $post_id );
	}

	public static function buy_now_button() {
		echo '<button type="button" class="button button-secondary" id="mds_use_linked_product_btn" disabled>' . esc_html__( 'Use Linked Product URL', 'mds-woocommerce-integration' ) . '</button>';
	}
}
