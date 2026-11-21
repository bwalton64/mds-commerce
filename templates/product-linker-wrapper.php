<?php
/**
 * WooCommerce product linker wrapper.
 *
 * @package MDS_WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mds-wc-linker-wrapper">
	<?php if ( empty( $args['sites'] ) ) : ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The empty-state template contains static markup.
		echo mds_woo_render_template( 'product-linker-empty.php' );
		?>
	<?php else : ?>
		<?php foreach ( $args['sites'] as $site ) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site template escapes its dynamic values.
			echo mds_woo_render_template(
				'product-linker-site.php',
				array(
					'site'    => $site,
					'post_id' => $args['post_id'],
				)
			);
			?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
