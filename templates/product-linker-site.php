<?php
/**
 * Store card for linking or seeding a WooCommerce product on one store.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mds_wc_site          = $args['site'];
$mds_wc_store_id      = absint( $mds_wc_site['store_id'] );
$mds_wc_product       = is_array( $mds_wc_site['product'] ?? null ) ? $mds_wc_site['product'] : null;
$mds_wc_is_linked     = null !== $mds_wc_product && ! empty( $mds_wc_product['id'] );
$mds_wc_is_unassigned = ! empty( $mds_wc_site['is_unassigned'] );
$mds_wc_search_id     = 'mds-wc-search-' . $mds_wc_store_id;
$mds_wc_listbox_id    = 'mds-wc-results-' . $mds_wc_store_id;
?>
<section
	class="mds-wc-store-card <?php echo $mds_wc_is_linked ? 'is-linked' : 'is-unlinked'; ?><?php echo $mds_wc_is_unassigned ? ' is-unassigned' : ''; ?>"
	data-store-id="<?php echo esc_attr( $mds_wc_store_id ); ?>"
	data-store-name="<?php echo esc_attr( $mds_wc_site['store_name'] ); ?>"
	data-release-id="<?php echo esc_attr( $args['post_id'] ); ?>"
	aria-label="<?php echo esc_attr( $mds_wc_site['store_name'] ); ?>"
>
	<header class="mds-wc-store-card__header">
		<div class="mds-wc-store-card__identity">
			<h4 class="mds-wc-store-card__title"><?php echo esc_html( $mds_wc_site['store_name'] ); ?></h4>
			<?php if ( ! empty( $mds_wc_site['store_url'] ) ) : ?>
				<span class="mds-wc-store-card__url"><?php echo esc_html( $mds_wc_site['store_url'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $mds_wc_is_unassigned ) : ?>
			<span class="mds-wc-badge mds-wc-badge--unassigned" data-role="connection-badge">
				<?php esc_html_e( 'Unassigned Store', 'mds-woocommerce-integration' ); ?>
			</span>
		<?php else : ?>
			<span class="mds-wc-badge <?php echo $mds_wc_is_linked ? 'mds-wc-badge--linked' : 'mds-wc-badge--unlinked'; ?>" data-role="connection-badge">
				<?php
				echo $mds_wc_is_linked
					? esc_html__( 'Linked', 'mds-woocommerce-integration' )
					: esc_html__( 'Not Linked', 'mds-woocommerce-integration' );
				?>
			</span>
		<?php endif; ?>
	</header>

	<div class="mds-wc-store-card__linked" <?php echo $mds_wc_is_linked ? '' : 'hidden'; ?>>
		<div class="mds-wc-product-summary">
			<div class="mds-wc-product-summary__thumb">
				<?php if ( $mds_wc_is_linked && ! empty( $mds_wc_product['thumbnail_url'] ) ) : ?>
					<img src="<?php echo esc_url( $mds_wc_product['thumbnail_url'] ); ?>" alt="" />
				<?php else : ?>
					<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
				<?php endif; ?>
			</div>
			<div class="mds-wc-product-summary__details">
				<strong class="mds-wc-product-summary__title">
					<?php echo $mds_wc_is_linked ? esc_html( $mds_wc_product['title'] ) : ''; ?>
				</strong>
				<div class="mds-wc-product-summary__meta">
					<?php if ( $mds_wc_is_linked ) : ?>
						<?php if ( 'publish' === $mds_wc_product['status'] ) : ?>
							<span class="mds-wc-badge mds-wc-badge--publish"><?php esc_html_e( 'Published', 'mds-woocommerce-integration' ); ?></span>
						<?php else : ?>
							<span class="mds-wc-badge mds-wc-badge--draft"><?php esc_html_e( 'Draft — Pending Store Review', 'mds-woocommerce-integration' ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $mds_wc_product['price'] ) ) : ?>
							<span class="mds-wc-product-summary__price"><?php echo esc_html( $mds_wc_product['price'] ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $mds_wc_product['sku'] ) ) : ?>
							<span class="mds-wc-product-summary__sku">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: WooCommerce product SKU. */
										__( 'SKU: %s', 'mds-woocommerce-integration' ),
										$mds_wc_product['sku']
									)
								);
								?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="mds-wc-store-card__actions">
			<a
				class="button button-secondary mds-wc-action-view"
				href="<?php echo $mds_wc_is_linked && ! empty( $mds_wc_product['permalink'] ) ? esc_url( $mds_wc_product['permalink'] ) : '#'; ?>"
				target="_blank"
				rel="noopener noreferrer"
				<?php echo ( $mds_wc_is_linked && 'publish' === $mds_wc_product['status'] && ! empty( $mds_wc_product['permalink'] ) ) ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'View in Store', 'mds-woocommerce-integration' ); ?>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>

			<a
				class="button button-secondary mds-wc-action-edit"
				href="<?php echo $mds_wc_is_linked && ! empty( $mds_wc_product['edit_url'] ) ? esc_url( $mds_wc_product['edit_url'] ) : '#'; ?>"
				target="_blank"
				rel="noopener noreferrer"
				<?php echo ( $mds_wc_is_linked && ! empty( $mds_wc_product['edit_url'] ) ) ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'Edit in Store', 'mds-woocommerce-integration' ); ?>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>

			<?php if ( ! $mds_wc_is_unassigned ) : ?>
				<button type="button" class="button button-link mds-wc-action-change">
					<?php esc_html_e( 'Change Link', 'mds-woocommerce-integration' ); ?>
				</button>
			<?php endif; ?>

			<button type="button" class="button button-link-delete mds-wc-action-unlink">
				<?php esc_html_e( 'Unlink', 'mds-woocommerce-integration' ); ?>
			</button>
		</div>
	</div>

	<?php if ( ! $mds_wc_is_unassigned ) : ?>
	<div class="mds-wc-store-card__picker" <?php echo $mds_wc_is_linked ? 'hidden' : ''; ?>>
		<div class="mds-wc-combobox">
			<label class="mds-wc-linker-label" for="<?php echo esc_attr( $mds_wc_search_id ); ?>">
				<?php esc_html_e( 'Link Existing Product', 'mds-woocommerce-integration' ); ?>
			</label>
			<div class="mds-wc-combobox__row">
				<div class="mds-wc-combobox__input-wrap">
					<input
						type="search"
						id="<?php echo esc_attr( $mds_wc_search_id ); ?>"
						class="mds-wc-combobox__input regular-text"
						role="combobox"
						aria-autocomplete="list"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $mds_wc_listbox_id ); ?>"
						placeholder="<?php esc_attr_e( 'Search existing store products…', 'mds-woocommerce-integration' ); ?>"
						autocomplete="off"
					/>
					<ul
						id="<?php echo esc_attr( $mds_wc_listbox_id ); ?>"
						class="mds-wc-combobox__listbox"
						role="listbox"
						hidden
					></ul>
				</div>

				<button
					type="button"
					class="button button-primary mds-wc-open-create-modal"
					data-store-id="<?php echo esc_attr( $mds_wc_store_id ); ?>"
					data-store-name="<?php echo esc_attr( $mds_wc_site['store_name'] ); ?>"
				>
					<?php esc_html_e( 'Create Product', 'mds-woocommerce-integration' ); ?>
				</button>

				<button type="button" class="button button-secondary mds-wc-cancel-change" hidden>
					<?php esc_html_e( 'Cancel', 'mds-woocommerce-integration' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>
</section>
