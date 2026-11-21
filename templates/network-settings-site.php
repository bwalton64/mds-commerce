<?php
/**
 * WooCommerce network site checkbox.
 *
 * @package MDS_WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<label class="mds-woo-integrated-site-checkbox">
	<input type="checkbox" name="mds_rm_wc_integrated_sites[]" value="<?php echo esc_attr( $args['site_id'] ); ?>" <?php checked( $args['is_integrated'] ); ?>>
	<?php echo esc_html( $args['site_name'] ) . ' (' . esc_html( $args['site_url'] ) . ')'; ?>
</label>
