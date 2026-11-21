<?php
/**
 * Generate-product button.
 *
 * @package MDS_WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<button type="button" class="button mds-wc-generate-btn mds-woo-generate-button" data-store-id="<?php echo esc_attr( $args['store_id'] ); ?>" data-post-id="<?php echo esc_attr( $args['post_id'] ); ?>">Generate New</button>
