<?php
/**
 * POD & Manual-Portal Provisioning Assistant modal dialog.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mds_cm_presets = \MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge::get_pod_presets();
?>
<dialog id="mds-cm-pod-modal" class="mds-wc-modal" aria-labelledby="mds-cm-pod-modal-title">
	<div class="mds-wc-modal__dialog">
		<header class="mds-wc-modal__header">
			<div>
				<h2 id="mds-cm-pod-modal-title" class="mds-wc-modal__title">
					<?php esc_html_e( 'POD & Manual-Portal Provisioning Pack', 'mds-commerce' ); ?>
				</h2>
				<p class="mds-wc-modal__subtitle">
					<?php esc_html_e( 'Verified Release metadata with 1-click Copy buttons and AI/MCP prompt export.', 'mds-commerce' ); ?>
				</p>
			</div>
			<button type="button" class="mds-wc-modal__close" data-action="close-pod-modal" aria-label="<?php esc_attr_e( 'Close dialog', 'mds-commerce' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</header>

		<div class="mds-wc-modal__body">
			<div class="mds-cm-pod-toolbar">
				<label for="mds-cm-pod-channel"><strong><?php esc_html_e( 'Channel:', 'mds-commerce' ); ?></strong></label>
				<select id="mds-cm-pod-channel">
					<?php foreach ( $mds_cm_presets as $mds_cm_slug => $mds_cm_preset ) : ?>
						<option value="<?php echo esc_attr( $mds_cm_slug ); ?>"><?php echo esc_html( (string) $mds_cm_preset['label'] ); ?></option>
					<?php endforeach; ?>
					<option value="bandcamp"><?php esc_html_e( 'Bandcamp', 'mds-commerce' ); ?></option>
				</select>
				<a id="mds-cm-pod-launch-link" class="button button-secondary" href="#" target="_blank" rel="noopener noreferrer" hidden>
					<?php esc_html_e( 'Launch Portal', 'mds-commerce' ); ?>
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
				</a>
				<button type="button" id="mds-cm-pod-copy-mcp" class="button button-secondary">
					<?php esc_html_e( 'Copy MCP Prompt for AI Agent', 'mds-commerce' ); ?>
				</button>
			</div>

			<div id="mds-cm-pod-notice" role="status" aria-live="polite"></div>
			<div id="mds-cm-pod-artwork-qa" class="mds-cm-pod-artwork-qa"></div>
			<div id="mds-cm-pod-fields" class="mds-cm-pod-fields"></div>
		</div>

		<footer class="mds-wc-modal__footer">
			<button type="button" class="button button-primary" data-action="close-pod-modal">
				<?php esc_html_e( 'Done', 'mds-commerce' ); ?>
			</button>
		</footer>
	</div>
</dialog>
