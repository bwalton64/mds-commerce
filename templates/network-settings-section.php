<?php
/**
 * WooCommerce network settings section.
 *
 * @package MDS_WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table mds-woo-network-settings">
	<tr>
		<th scope="row">WooCommerce Sites</th>
		<td>
			<?php foreach ( $args['sites'] as $site ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site template escapes its dynamic values.
				echo mds_woo_render_template(
					'network-settings-site.php',
					array(
						'site_id'       => $site['site_id'],
						'site_name'     => $site['site_name'],
						'site_url'      => $site['site_url'],
						'is_integrated' => $site['is_integrated'],
					)
				);
				?>
			<?php endforeach; ?>
			<p class="description">Select sites with WooCommerce active for product linking.</p>
		</td>
	</tr>
</table>
