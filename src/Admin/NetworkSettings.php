<?php
/**
 * WooCommerce integration network admin settings.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Admin;

use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Support\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the WooCommerce network settings tab and section.
 *
 * @since 1.0.1
 */
final class NetworkSettings {

	/**
	 * Register Release Manager network settings hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'mds_rm_network_settings_fields', array( self::class, 'filter_network_settings' ) );
		add_filter( 'mds_rm_network_settings_tabs', array( self::class, 'register_tab' ) );
	}

	/**
	 * Append the WooCommerce network settings section to legacy Release Manager settings markup.
	 *
	 * @since 1.0.1
	 *
	 * @param string $html Existing network settings markup.
	 * @return string Combined network settings markup.
	 */
	public static function render_section( string $html ): string {
		if ( ! Plugin::is_active() ) {
			return $html;
		}

		$sites            = function_exists( 'get_sites' ) ? get_sites() : array();
		$integrated_sites = array_map( 'intval', (array) get_site_option( 'mds_rm_wc_integrated_sites', array() ) );
		$site_data        = array();

		foreach ( $sites as $site ) {
			$site_details = get_blog_details( $site->blog_id );
			$site_data[]  = array(
				'site_id'       => (int) $site->blog_id,
				'site_name'     => $site_details ? (string) $site_details->blogname : '',
				'site_url'      => $site_details ? (string) $site_details->siteurl : '',
				'is_integrated' => in_array( (int) $site->blog_id, $integrated_sites, true ),
			);
		}

		return $html . TemplateRenderer::render(
			'network-settings-section.php',
			array(
				'sites' => $site_data,
			)
		);
	}

	/**
	 * Filter legacy Release Manager network settings fields when tabbed extensions are unavailable.
	 *
	 * @since 1.0.1
	 *
	 * @param string $html Existing network settings markup.
	 * @return string Filtered network settings markup.
	 */
	public static function filter_network_settings( string $html ): string {
		if ( class_exists( '\MDS_RM_Network_Admin' ) && method_exists( '\MDS_RM_Network_Admin', 'extension_tabs' ) ) {
			return $html;
		}

		return self::render_section( $html );
	}

	/**
	 * Register the WooCommerce network settings tab.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, array{label: string, render: callable}> $tabs Existing extension tabs.
	 * @return array<string, array{label: string, render: callable}> Updated extension tabs.
	 */
	public static function register_tab( array $tabs ): array {
		if ( Plugin::is_active() ) {
			$tabs['woocommerce'] = array(
				'label'  => __( 'WooCommerce', 'mds-woocommerce-integration' ),
				'render' => array( self::class, 'render_tab' ),
			);
		}

		return $tabs;
	}

	/**
	 * Render WooCommerce settings in their own form so they save independently.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function render_tab(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage network settings.', 'mds-woocommerce-integration' ) );
		}
		?>
		<form id="mds-wc-network-settings-form">
			<div class="mds-wc-network-settings-notice" role="status" aria-live="polite"></div>
			<div class="mds-panel">
				<h2><?php esc_html_e( 'WooCommerce Sites', 'mds-woocommerce-integration' ); ?></h2>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its dynamic values.
				echo self::render_section( '' );
				?>
			</div>
			<?php submit_button( __( 'Save WooCommerce Settings', 'mds-woocommerce-integration' ) ); ?>
		</form>
		<?php
	}
}
