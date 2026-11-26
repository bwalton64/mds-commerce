<?php
/**
 * Empty WooCommerce product linker state.
 *
 * @package MDS\Commerce
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mds_wc_artist_id       = isset( $args['artist_id'] ) ? absint( $args['artist_id'] ) : 0;
$mds_wc_artist_name     = isset( $args['artist_name'] ) ? (string) $args['artist_name'] : '';
$mds_wc_artist_edit_url = isset( $args['artist_edit_url'] ) ? (string) $args['artist_edit_url'] : '';
?>
<p class="mds-wc-no-integrated-sites">
	<?php if ( $mds_wc_artist_id > 0 && '' !== $mds_wc_artist_edit_url ) : ?>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: Artist edit URL, 2: Artist name. */
				__( 'No WooCommerce stores are assigned to <a href="%1$s">%2$s</a> yet. Assign one or more target stores on the Artist edit screen to link or create products for this Release.', 'mds-commerce' ),
				esc_url( $mds_wc_artist_edit_url ),
				esc_html( '' !== $mds_wc_artist_name ? $mds_wc_artist_name : __( 'this Artist', 'mds-commerce' ) )
			),
			array(
				'a' => array(
					'href' => array(),
				),
			)
		);
		?>
	<?php else : ?>
		<?php esc_html_e( 'Assign an Artist with one or more WooCommerce Target Stores configured to link or create products for this Release.', 'mds-commerce' ); ?>
	<?php endif; ?>
</p>
