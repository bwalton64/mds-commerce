<?php

/**
 * Handles saving and synchronizing WooCommerce product links.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'mds_rm_save_custom_meta', 'mds_woo_save_meta', 10, 2 );

/**
 * Saves WooCommerce product links and synchronizes linked products.
 */
final class MdsWooSaveHandler {

	/**
	 * Save submitted product links and sync each linked store product.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $post_data Submitted post data.
	 */
	public static function save( $post_id, $post_data ) {
		if ( ! mds_woo_is_active() ) {
			return;
		}

		$linked_products = get_post_meta( $post_id, '_mds_linked_products', true );
		if ( ! is_array( $linked_products ) ) {
			$linked_products = array();
		}

		$linked_products = self::saveSubmittedLinks( $post_id, $post_data, $linked_products );
		$upc             = get_post_meta( $post_id, 'upc_ean', true );
		$hub_thumb_id    = get_post_thumbnail_id( $post_id );
		$hub_img_url     = $hub_thumb_id ? wp_get_attachment_url( $hub_thumb_id ) : '';

		self::synchronizeLinkedProducts( $post_id, $linked_products, $upc, $hub_thumb_id, $hub_img_url );
	}

	/**
	 * Save submitted product IDs and update the local product reference.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $post_data Submitted post data.
	 * @param array $linked_products Existing product links.
	 * @return array Updated product links.
	 */
	private static function saveSubmittedLinks( $post_id, $post_data, $linked_products ) {
		if ( ! isset( $post_data['mds_rm_linked_product'] ) || ! is_array( $post_data['mds_rm_linked_product'] ) ) {
			return $linked_products;
		}

		foreach ( $post_data['mds_rm_linked_product'] as $store_id => $prod_id ) {
			$store_id = intval( $store_id );
			$prod_id  = intval( $prod_id );

			if ( $prod_id ) {
				$linked_products[ $store_id ] = $prod_id;
			} else {
				unset( $linked_products[ $store_id ] );
			}
		}

		update_post_meta( $post_id, '_mds_linked_products', $linked_products );

		$current_blog = get_current_blog_id();
		if ( isset( $linked_products[ $current_blog ] ) ) {
			update_post_meta( $post_id, '_mds_local_product_id', $linked_products[ $current_blog ] );
		} else {
			delete_post_meta( $post_id, '_mds_local_product_id' );
		}

		return $linked_products;
	}

	/**
	 * Synchronize each linked product on its store site.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $linked_products Product links by store.
	 * @param string $upc Release UPC.
	 * @param int    $hub_thumb_id Hub thumbnail ID.
	 * @param string $hub_img_url Hub thumbnail URL.
	 */
	private static function synchronizeLinkedProducts( $post_id, $linked_products, $upc, $hub_thumb_id, $hub_img_url ) {
		foreach ( $linked_products as $store_id => $prod_id ) {
			switch_to_blog( $store_id );

			if ( self::isWooCommerceActiveOnCurrentSite() ) {
				self::synchronizeProduct( $post_id, $prod_id, $upc, $hub_thumb_id, $hub_img_url );
			}

			restore_current_blog();
		}
	}

	/**
	 * Check whether WooCommerce is active on the current site.
	 *
	 * @return bool
	 */
	private static function isWooCommerceActiveOnCurrentSite() {
		$active_plugins = get_option( 'active_plugins', array() );
		$is_wc          = in_array( 'woocommerce/woocommerce.php', $active_plugins, true );

		if ( ! $is_wc && is_multisite() ) {
			$sitewide = get_site_option( 'active_sitewide_plugins' );
			$is_wc    = is_array( $sitewide ) && array_key_exists( 'woocommerce/woocommerce.php', $sitewide );
		}

		return $is_wc;
	}

	/**
	 * Update product data and taxonomies on the current store site.
	 *
	 * @param int    $post_id Release post ID.
	 * @param int    $product_id Product ID.
	 * @param string $upc Release UPC.
	 * @param int    $hub_thumb_id Hub thumbnail ID.
	 * @param string $hub_img_url Hub thumbnail URL.
	 */
	private static function synchronizeProduct( $post_id, $product_id, $upc, $hub_thumb_id, $hub_img_url ) {
		if ( ! post_type_exists( 'product' ) ) {
			register_post_type( 'product' );
		}
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			register_taxonomy( 'product_tag', 'product' );
		}

		wp_update_post(
			array(
				'ID'         => $product_id,
				'post_title' => get_the_title( $post_id ),
			)
		);

		if ( $upc ) {
			update_post_meta( $product_id, '_sku', $upc );
		}
		wp_set_object_terms( $product_id, 'release', 'product_tag', false );

		self::synchronizeProductThumbnail( $product_id, $hub_thumb_id, $hub_img_url );
	}

	/**
	 * Update or remove the product thumbnail on the current store site.
	 *
	 * @param int    $product_id Product ID.
	 * @param int    $hub_thumb_id Hub thumbnail ID.
	 * @param string $hub_img_url Hub thumbnail URL.
	 */
	private static function synchronizeProductThumbnail( $product_id, $hub_thumb_id, $hub_img_url ) {
		if ( ! $hub_thumb_id ) {
			delete_post_thumbnail( $product_id );
			return;
		}

		if ( get_current_blog_id() === get_main_site_id() ) {
			set_post_thumbnail( $product_id, $hub_thumb_id );
			return;
		}

		if ( function_exists( 'mds_rm_get_or_create_virtual_attachment' ) ) {
			$virtual_attachment_id = mds_rm_get_or_create_virtual_attachment( $hub_img_url, $hub_thumb_id );
			if ( $virtual_attachment_id ) {
				set_post_thumbnail( $product_id, $virtual_attachment_id );
			}
		}
	}
}

/**
 * Save product links and synchronize linked WooCommerce products.
 *
 * @param int   $post_id Post ID.
 * @param array $post_data Submitted post data.
 * @return void
 */
function mds_woo_save_meta( $post_id, $post_data ) {
	MdsWooSaveHandler::save( $post_id, $post_data );
}
