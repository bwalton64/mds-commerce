<?php
/**
 * Product linker controls for one store.
 *
 * @package MDS_WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site = $args['site'];
?>
<div class="mds-woo-linker-site">
	<strong>Store ID: <?php echo esc_html( $site['store_id'] ); ?></strong><br/>
	<select class="mds-wc-select2 mds-woo-linker-select" name="mds_rm_linked_product[<?php echo esc_attr( $site['store_id'] ); ?>]" data-store-id="<?php echo esc_attr( $site['store_id'] ); ?>" data-release-id="<?php echo esc_attr( $args['post_id'] ); ?>">
		<?php if ( $site['product_id'] ) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The selected-option template escapes its dynamic values.
			echo mds_woo_render_template(
				'product-linker-selected-option.php',
				array(
					'product_id' => $site['product_id'],
					'permalink'  => $site['permalink'],
					'title'      => $site['title'],
				)
			);
			?>
		<?php else : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The search-option template contains static markup.
			echo mds_woo_render_template( 'product-linker-search-option.php' );
			?>
		<?php endif; ?>
	</select>
	<?php if ( ! $site['product_id'] ) : ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The button template escapes its dynamic attributes.
		echo mds_woo_render_template(
			'product-linker-generate-button.php',
			array(
				'store_id' => $site['store_id'],
				'post_id'  => $args['post_id'],
			)
		);
		?>
	<?php endif; ?>
</div>
