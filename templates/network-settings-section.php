<?php
/**
 * WooCommerce network settings section.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table mds-wc-network-settings">
	<tr>
		<th scope="row"><?php esc_html_e( 'WooCommerce Sites', 'mds-woocommerce-integration' ); ?></th>
		<td>
			<?php foreach ( $args['sites'] as $mds_wc_site ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site template escapes its dynamic values.
				echo mds_wc_render_template(
					'network-settings-site.php',
					array(
						'site_id'       => $mds_wc_site['site_id'],
						'site_name'     => $mds_wc_site['site_name'],
						'site_url'      => $mds_wc_site['site_url'],
						'is_integrated' => $mds_wc_site['is_integrated'],
					)
				);
				?>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Select sites with WooCommerce active for product linking.', 'mds-woocommerce-integration' ); ?></p>
		</td>
	</tr>
</table>
