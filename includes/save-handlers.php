<?php
/**
 * Handles saving and synchronizing WooCommerce product links.
 *
 * @package MDS_WooCommerce_Integration
 */

defined( 'ABSPATH' ) || exit;

add_action( 'mds_rm_save_custom_meta', 'mds_woo_save_meta', 10, 2 );

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
