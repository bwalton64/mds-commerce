<?php
/**
 * Selected WooCommerce product option.
 *
 * @package MDS_WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<option value="<?php echo esc_attr( $args['product_id'] ); ?>" selected="selected" data-permalink="<?php echo esc_url( $args['permalink'] ); ?>"><?php echo esc_html( $args['title'] ); ?></option>
