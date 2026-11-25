<?php
/**
 * Artist CPT WooCommerce Target Stores meta box template.
 *
 * @package MDS\WooCommerce
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mds_wc_artist_id     = isset( $args['artist_id'] ) ? absint( $args['artist_id'] ) : 0;
$mds_wc_stores        = isset( $args['stores'] ) && is_array( $args['stores'] ) ? $args['stores'] : array();
$mds_wc_assigned      = isset( $args['assigned'] ) && is_array( $args['assigned'] ) ? $args['assigned'] : array();
$mds_wc_release_count = isset( $args['release_count'] ) ? absint( $args['release_count'] ) : 0;
$mds_wc_is_auto_draft = ! empty( $args['is_auto_draft'] );
?>
<div class="mds-wc-artist-stores" data-artist-id="<?php echo esc_attr( (string) $mds_wc_artist_id ); ?>">
	<?php wp_nonce_field( 'mds_wc_save_artist_stores', 'mds_wc_artist_stores_nonce' ); ?>
	<input type="hidden" name="mds_wc_artist_stores_present" value="1" />

	<p class="description">
		<?php esc_html_e( 'Select the WooCommerce store(s) where this Artist and their Releases should synchronize and allow product linking.', 'mds-woocommerce' ); ?>
	</p>

	<div class="mds-wc-artist-stores__notice" role="status" aria-live="polite"></div>

	<fieldset class="mds-wc-artist-stores__fieldset">
		<legend class="screen-reader-text">
			<?php esc_html_e( 'Target WooCommerce Stores', 'mds-woocommerce' ); ?>
		</legend>

		<div class="mds-wc-artist-stores__list">
			<?php if ( empty( $mds_wc_stores ) ) : ?>
				<p class="mds-wc-artist-stores__empty">
					<?php esc_html_e( 'No active WooCommerce stores were detected on this network. Activate WooCommerce on a site and refresh the list.', 'mds-woocommerce' ); ?>
				</p>
			<?php else : ?>
				<?php foreach ( $mds_wc_stores as $mds_wc_store ) : ?>
					<?php
					$mds_wc_site_id  = absint( $mds_wc_store['site_id'] ?? 0 );
					$mds_wc_checked  = in_array( $mds_wc_site_id, $mds_wc_assigned, true );
					$mds_wc_input_id = 'mds_wc_target_store_' . $mds_wc_site_id;
					?>
					<label class="mds-wc-artist-stores__item" for="<?php echo esc_attr( $mds_wc_input_id ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $mds_wc_input_id ); ?>"
							name="mds_wc_target_stores[]"
							value="<?php echo esc_attr( (string) $mds_wc_site_id ); ?>"
							<?php checked( $mds_wc_checked ); ?>
						/>
						<span class="mds-wc-artist-stores__label">
							<strong><?php echo esc_html( (string) ( $mds_wc_store['site_name'] ?? '' ) ); ?></strong>
							<?php if ( ! empty( $mds_wc_store['site_url'] ) ) : ?>
								<span class="mds-wc-artist-stores__url"><?php echo esc_html( (string) $mds_wc_store['site_url'] ); ?></span>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</fieldset>

	<div class="mds-wc-artist-stores__actions">
		<button type="button" class="button button-secondary mds-wc-refresh-stores-btn">
			<?php esc_html_e( 'Refresh Store List', 'mds-woocommerce' ); ?>
		</button>

		<?php if ( ! $mds_wc_is_auto_draft && $mds_wc_release_count > 0 ) : ?>
			<button
				type="button"
				class="button button-secondary mds-wc-sync-artist-releases-btn"
				<?php disabled( empty( $mds_wc_assigned ) ); ?>
			>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: Number of releases belonging to this artist. */
						_n(
							'Sync %d Release to Selected Stores',
							'Sync %d Releases to Selected Stores',
							$mds_wc_release_count,
							'mds-woocommerce'
						),
						$mds_wc_release_count
					)
				);
				?>
			</button>
		<?php endif; ?>
	</div>

	<p class="description mds-wc-artist-stores__dirty-hint" hidden>
		<?php esc_html_e( 'Save or Update this Artist first before syncing Releases to newly selected stores.', 'mds-woocommerce' ); ?>
	</p>
</div>
