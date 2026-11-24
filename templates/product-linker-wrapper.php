<?php
/**
 * WooCommerce product linker wrapper.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mds_wc_is_auto_draft = ! empty( $args['is_auto_draft'] );
?>
<div class="mds-wc-linker-wrapper" data-release-id="<?php echo esc_attr( $args['post_id'] ); ?>">
	<div class="mds-wc-linker-notice" role="status" aria-live="polite"></div>

	<?php if ( $mds_wc_is_auto_draft ) : ?>
		<p class="mds-wc-draft-notice">
			<?php esc_html_e( 'Save a draft of this Release first to link or create WooCommerce products on your integrated stores.', 'mds-woocommerce-integration' ); ?>
		</p>
	<?php elseif ( empty( $args['sites'] ) ) : ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The empty-state template escapes its dynamic values.
		echo mds_wc_render_template(
			'product-linker-empty.php',
			array(
				'artist_id'       => $args['artist_id'] ?? 0,
				'artist_name'     => $args['artist_name'] ?? '',
				'artist_edit_url' => $args['artist_edit_url'] ?? '',
			)
		);
		?>
	<?php else : ?>
		<div class="mds-wc-store-cards">
			<?php foreach ( $args['sites'] as $mds_wc_site ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site template escapes its dynamic values.
				echo mds_wc_render_template(
					'product-linker-site.php',
					array(
						'site'    => $mds_wc_site,
						'post_id' => $args['post_id'],
					)
				);
				?>
			<?php endforeach; ?>
		</div>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The modal template escapes its dynamic values.
		echo mds_wc_render_template(
			'product-linker-create-modal.php',
			array(
				'post_id' => $args['post_id'],
			)
		);
		?>
	<?php endif; ?>
</div>
