<?php
/**
 * Hub-side WooCommerce Variations & WPC External Variations (POD) editor modal.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<dialog id="mds-cm-variations-modal" class="mds-wc-modal" aria-labelledby="mds-cm-variations-modal-title">
	<form id="mds-cm-variations-form" class="mds-wc-modal__dialog">
		<header class="mds-wc-modal__header">
			<div>
				<h2 id="mds-cm-variations-modal-title" class="mds-wc-modal__title">
					<?php esc_html_e( 'Edit Format Variations & External POD Links', 'mds-commerce' ); ?>
				</h2>
				<p class="mds-wc-modal__subtitle" id="mds-cm-variations-subtitle"></p>
			</div>
			<button type="button" class="mds-wc-modal__close" data-action="close-variations-modal" aria-label="<?php esc_attr_e( 'Close dialog', 'mds-commerce' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</header>

		<div class="mds-wc-modal__body">
			<div id="mds-cm-variations-notice" role="status" aria-live="polite"></div>
			<div id="mds-cm-variations-list"></div>
		</div>

		<footer class="mds-wc-modal__footer">
			<button type="button" class="button button-secondary" data-action="close-variations-modal">
				<?php esc_html_e( 'Cancel', 'mds-commerce' ); ?>
			</button>
			<button type="submit" id="mds-cm-variations-save" class="button button-primary">
				<?php esc_html_e( 'Save Variations', 'mds-commerce' ); ?>
			</button>
		</footer>
	</form>
</dialog>
