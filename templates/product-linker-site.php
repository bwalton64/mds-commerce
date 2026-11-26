<?php
/**
 * Store card for linking or seeding a WooCommerce product on one store.
 *
 * @package MDS\Commerce
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
$mds_wc_drift         = is_array( $mds_wc_site['drift'] ?? null ) ? $mds_wc_site['drift'] : array();
$mds_wc_drift_items   = is_array( $mds_wc_drift['items'] ?? null ) ? $mds_wc_drift['items'] : array();
$mds_wc_has_drift     = $mds_wc_is_linked && ! empty( $mds_wc_drift_items );
$mds_wc_search_id     = 'mds-wc-search-' . $mds_wc_store_id;
$mds_wc_listbox_id    = 'mds-wc-results-' . $mds_wc_store_id;
?>
<section
	class="mds-wc-store-card <?php echo $mds_wc_is_linked ? 'is-linked' : 'is-unlinked'; ?><?php echo $mds_wc_is_unassigned ? ' is-unassigned' : ''; ?><?php echo $mds_wc_has_drift ? ' has-drift' : ''; ?>"
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
		<div class="mds-wc-store-card__badges">
			<span
				class="mds-wc-badge mds-wc-badge--drift"
				data-role="drift-badge"
				<?php echo $mds_wc_has_drift ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'Out of sync', 'mds-commerce' ); ?>
			</span>
			<?php if ( $mds_wc_is_unassigned ) : ?>
				<span class="mds-wc-badge mds-wc-badge--unassigned" data-role="connection-badge">
					<?php esc_html_e( 'Unassigned Store', 'mds-commerce' ); ?>
				</span>
			<?php else : ?>
				<span class="mds-wc-badge <?php echo $mds_wc_is_linked ? 'mds-wc-badge--linked' : 'mds-wc-badge--unlinked'; ?>" data-role="connection-badge">
					<?php
					echo $mds_wc_is_linked
						? esc_html__( 'Linked', 'mds-commerce' )
						: esc_html__( 'Not Linked', 'mds-commerce' );
					?>
				</span>
			<?php endif; ?>
		</div>
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
							<span class="mds-wc-badge mds-wc-badge--publish"><?php esc_html_e( 'Published', 'mds-commerce' ); ?></span>
						<?php else : ?>
							<span class="mds-wc-badge mds-wc-badge--draft"><?php esc_html_e( 'Draft — Pending Store Review', 'mds-commerce' ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $mds_wc_product['price'] ) ) : ?>
							<span class="mds-wc-product-summary__price"><?php echo esc_html( $mds_wc_product['price'] ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $mds_wc_product['gtin'] ) ) : ?>
							<span class="mds-wc-product-summary__gtin">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: WooCommerce product GTIN/UPC/EAN. */
										__( 'GTIN: %s', 'mds-commerce' ),
										$mds_wc_product['gtin']
									)
								);
								?>
							</span>
						<?php endif; ?>

						<?php if ( ! empty( $mds_wc_product['sku'] ) ) : ?>
							<span class="mds-wc-product-summary__sku">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: WooCommerce product SKU. */
										__( 'SKU: %s', 'mds-commerce' ),
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

		<div class="mds-wc-drift-panel" <?php echo $mds_wc_has_drift ? '' : 'hidden'; ?>>
			<div class="mds-wc-drift-panel__header">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Product differs from this Release', 'mds-commerce' ); ?></strong>
			</div>
			<ul class="mds-wc-drift-list">
				<?php foreach ( $mds_wc_drift_items as $mds_wc_item ) : ?>
					<?php
					$mds_wc_field      = isset( $mds_wc_item['field'] ) ? (string) $mds_wc_item['field'] : '';
					$mds_wc_label      = isset( $mds_wc_item['label'] ) ? (string) $mds_wc_item['label'] : '';
					$mds_wc_prod_val   = isset( $mds_wc_item['product_value'] ) ? (string) $mds_wc_item['product_value'] : '';
					$mds_wc_rel_val    = isset( $mds_wc_item['release_value'] ) ? (string) $mds_wc_item['release_value'] : '';
					$mds_wc_sku_option = is_array( $mds_wc_item['sku_option'] ?? null ) ? $mds_wc_item['sku_option'] : array();
					?>
					<li class="mds-wc-drift-item">
						<label class="mds-wc-drift-item__label">
							<input type="checkbox" class="mds-wc-drift-checkbox" value="<?php echo esc_attr( $mds_wc_field ); ?>" checked />
							<span class="mds-wc-drift-item__text">
								<strong><?php echo esc_html( $mds_wc_label ); ?>:</strong>
								<span class="mds-wc-drift-diff">
									<span class="mds-wc-drift-diff__from"><?php echo esc_html( $mds_wc_prod_val ); ?></span>
									<span class="mds-wc-drift-diff__arrow" aria-hidden="true">&rarr;</span>
									<span class="mds-wc-drift-diff__to"><?php echo esc_html( $mds_wc_rel_val ); ?></span>
								</span>
							</span>
						</label>
						<?php if ( 'upc' === $mds_wc_field && ! empty( $mds_wc_sku_option['available'] ) ) : ?>
							<label class="mds-wc-drift-item__sub">
								<input type="checkbox" class="mds-wc-drift-checkbox" value="sku" checked />
								<span>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: Current SKU value, 2: New UPC value. */
											__( 'Also update SKU (%1$s → %2$s)', 'mds-commerce' ),
											(string) ( $mds_wc_sku_option['current_sku'] ?? '' ),
											(string) ( $mds_wc_sku_option['new_sku'] ?? '' )
										)
									);
									?>
								</span>
							</label>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="mds-wc-drift-panel__actions">
				<button type="button" class="button button-secondary mds-wc-action-sync-drift">
					<?php esc_html_e( 'Update Product', 'mds-commerce' ); ?>
				</button>
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
				<?php esc_html_e( 'View in Store', 'mds-commerce' ); ?>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>

			<a
				class="button button-secondary mds-wc-action-edit"
				href="<?php echo $mds_wc_is_linked && ! empty( $mds_wc_product['edit_url'] ) ? esc_url( $mds_wc_product['edit_url'] ) : '#'; ?>"
				target="_blank"
				rel="noopener noreferrer"
				<?php echo ( $mds_wc_is_linked && ! empty( $mds_wc_product['edit_url'] ) ) ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'Edit in Store', 'mds-commerce' ); ?>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>

			<?php if ( ! $mds_wc_is_unassigned ) : ?>
				<button type="button" class="button button-link mds-wc-action-change">
					<?php esc_html_e( 'Change Link', 'mds-commerce' ); ?>
				</button>
			<?php endif; ?>

			<button type="button" class="button button-link-delete mds-wc-action-unlink">
				<?php esc_html_e( 'Unlink', 'mds-commerce' ); ?>
			</button>
		</div>
	</div>

	<?php if ( ! $mds_wc_is_unassigned ) : ?>
	<div class="mds-wc-store-card__picker" <?php echo $mds_wc_is_linked ? 'hidden' : ''; ?>>
		<div class="mds-wc-combobox">
			<label class="mds-wc-linker-label" for="<?php echo esc_attr( $mds_wc_search_id ); ?>">
				<?php esc_html_e( 'Link Existing Product', 'mds-commerce' ); ?>
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
						placeholder="<?php esc_attr_e( 'Search existing store products…', 'mds-commerce' ); ?>"
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
					<?php esc_html_e( 'Create Product', 'mds-commerce' ); ?>
				</button>

				<button type="button" class="button button-secondary mds-wc-cancel-change" hidden>
					<?php esc_html_e( 'Cancel', 'mds-commerce' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>
</section>
