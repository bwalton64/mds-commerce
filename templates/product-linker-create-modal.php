<?php
/**
 * Compact Create WooCommerce Product modal dialog.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<dialog id="mds-wc-create-product-modal" class="mds-wc-modal" aria-labelledby="mds-wc-modal-title">
	<div class="mds-wc-modal__dialog">
		<header class="mds-wc-modal__header">
			<div>
				<h2 id="mds-wc-modal-title" class="mds-wc-modal__title">
					<?php esc_html_e( 'Create WooCommerce Product', 'mds-woocommerce-integration' ); ?>
				</h2>
				<p class="mds-wc-modal__subtitle" id="mds-wc-modal-store-label"></p>
			</div>
			<button
				type="button"
				class="mds-wc-modal__close"
				data-action="close-modal"
				aria-label="<?php esc_attr_e( 'Close dialog', 'mds-woocommerce-integration' ); ?>"
			>
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</header>

		<div class="mds-wc-modal__notice" role="status" aria-live="polite"></div>

		<div class="mds-wc-modal__body">
			<div class="mds-wc-modal__grid">
				<div class="mds-wc-modal__main">
					<div class="mds-wc-field">
						<label class="mds-wc-field__label" for="mds-wc-create-name">
							<?php esc_html_e( 'Product Name', 'mds-woocommerce-integration' ); ?>
						</label>
						<input type="text" id="mds-wc-create-name" class="large-text" aria-required="true" />
					</div>

					<div class="mds-wc-field">
						<label class="mds-wc-field__label" for="mds-wc-create-short-desc">
							<?php esc_html_e( 'Short Description', 'mds-woocommerce-integration' ); ?>
						</label>
						<textarea id="mds-wc-create-short-desc" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Brief summary shown next to the product price…', 'mds-woocommerce-integration' ); ?>"></textarea>
					</div>

					<div class="mds-wc-field">
						<label class="mds-wc-field__label" for="mds-wc-create-desc">
							<?php esc_html_e( 'Full Description', 'mds-woocommerce-integration' ); ?>
						</label>
						<textarea id="mds-wc-create-desc" class="large-text" rows="6"></textarea>
						<p class="description">
							<?php esc_html_e( 'Pre-seeded from this Release. Store admins can freely edit this product afterward.', 'mds-woocommerce-integration' ); ?>
						</p>
					</div>
				</div>

				<div class="mds-wc-modal__sidebar">
					<div class="mds-wc-field">
						<label class="mds-wc-field__label" for="mds-wc-create-status">
							<?php esc_html_e( 'Initial Status', 'mds-woocommerce-integration' ); ?>
						</label>
						<select id="mds-wc-create-status" class="widefat">
							<option value="draft" selected="selected"><?php esc_html_e( 'Draft (Recommended for Store Review)', 'mds-woocommerce-integration' ); ?></option>
							<option value="publish"><?php esc_html_e( 'Published', 'mds-woocommerce-integration' ); ?></option>
						</select>
					</div>

					<div class="mds-wc-field-row">
						<div class="mds-wc-field">
							<label class="mds-wc-field__label" for="mds-wc-create-price">
								<?php esc_html_e( 'Regular Price', 'mds-woocommerce-integration' ); ?>
							</label>
							<input type="text" id="mds-wc-create-price" class="widefat" inputmode="decimal" placeholder="0.00" />
						</div>

						<div class="mds-wc-field">
							<label class="mds-wc-field__label" for="mds-wc-create-sku">
								<?php esc_html_e( 'SKU / UPC', 'mds-woocommerce-integration' ); ?>
							</label>
							<input type="text" id="mds-wc-create-sku" class="widefat" />
						</div>
					</div>

					<fieldset class="mds-wc-field mds-wc-categories-fieldset">
						<legend class="mds-wc-field__label">
							<?php esc_html_e( 'Categories', 'mds-woocommerce-integration' ); ?>
						</legend>
						<div id="mds-wc-create-categories" class="mds-wc-categories-list"></div>
					</fieldset>

					<div class="mds-wc-field">
						<label class="mds-wc-field__label" for="mds-wc-create-tags">
							<?php esc_html_e( 'Product Tags', 'mds-woocommerce-integration' ); ?>
						</label>
						<input
							type="text"
							id="mds-wc-create-tags"
							class="widefat"
							list="mds-wc-tag-suggestions"
							placeholder="<?php esc_attr_e( 'e.g. vinyl, limited-edition', 'mds-woocommerce-integration' ); ?>"
						/>
						<datalist id="mds-wc-tag-suggestions"></datalist>
						<p class="description">
							<?php esc_html_e( 'Separate multiple tags with commas. The "release" tag is added automatically.', 'mds-woocommerce-integration' ); ?>
						</p>
					</div>

					<div class="mds-wc-field mds-wc-thumb-preview-wrap" id="mds-wc-create-thumb-wrap" hidden>
						<span class="mds-wc-field__label"><?php esc_html_e( 'Product Artwork', 'mds-woocommerce-integration' ); ?></span>
						<div class="mds-wc-thumb-preview">
							<img id="mds-wc-create-thumb-img" src="" alt="" />
							<label class="mds-wc-thumb-preview__toggle">
								<input type="checkbox" id="mds-wc-create-sync-thumb" checked="checked" />
								<?php esc_html_e( 'Seed from Release Featured Image', 'mds-woocommerce-integration' ); ?>
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<footer class="mds-wc-modal__footer">
			<button type="button" class="button button-secondary" data-action="close-modal">
				<?php esc_html_e( 'Cancel', 'mds-woocommerce-integration' ); ?>
			</button>
			<button type="button" class="button button-primary" id="mds-wc-create-submit">
				<?php esc_html_e( 'Create & Link Product', 'mds-woocommerce-integration' ); ?>
			</button>
		</footer>
	</div>
</dialog>
