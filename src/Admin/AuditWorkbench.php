<?php
/**
 * Catalog Drift & Sync Activity Workbench (`Releases -> Commerce Audit`).
 *
 * Hub-only operations table listing Hub Releases alongside their linked store status,
 * active promo/coupon badges, and quick navigation to edit or sync.
 *
 * @package   MDS\Commerce
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @since     3.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Admin;

use MDS\Commerce\Blocks\StoreChooser;
use MDS\Commerce\Config;
use MDS\Commerce\Gateway\EntitlementManager;
use MDS\Commerce\Plugin;
use MDS\Commerce\Sync\UpgradeRoutine;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the Hub Catalog Commerce Audit submenu page.
 *
 * @since 3.0.1
 */
final class AuditWorkbench {

	/**
	 * Register the Hub submenu hook.
	 *
	 * @since 3.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'register_submenu' ), 40 );
	}

	/**
	 * Register `Releases -> Commerce Audit` on the Hub site.
	 *
	 * @since 3.0.1
	 *
	 * @return void
	 */
	public static function register_submenu(): void {
		if ( ! Plugin::is_active() ) {
			return;
		}

		$is_hub = ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			|| \MDS\ReleaseManager\Api\ExtensionApi::is_hub();
		if ( ! $is_hub ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=release',
			__( 'MDS Commerce Audit', 'mds-commerce' ),
			__( 'Commerce Audit', 'mds-commerce' ),
			'edit_posts',
			'mds-commerce-audit',
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Render the Hub Catalog Commerce Audit workbench.
	 *
	 * @since 3.0.1
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the Commerce Audit.', 'mds-commerce' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter.
		$filter_status = isset( $_GET['commerce_status'] ) ? sanitize_key( wp_unslash( $_GET['commerce_status'] ) ) : 'all';
		$tier          = EntitlementManager::get_tier();

		$releases = get_posts(
			array(
				'post_type'      => 'release',
				'post_status'    => array( 'publish', 'draft', 'future' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MDS Commerce — Catalog Audit Workbench', 'mds-commerce' ); ?></h1>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: Active license tier label. */
						__( 'Audit linked storefronts, active Store Chooser promotions, and unlinked releases across your catalog. Active Gateway Tier: %s.', 'mds-commerce' ),
						strtoupper( $tier )
					)
				);
				?>
			</p>

			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=release&page=mds-commerce-audit&commerce_status=all' ) ); ?>" class="<?php echo 'all' === $filter_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'All Releases', 'mds-commerce' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=release&page=mds-commerce-audit&commerce_status=linked' ) ); ?>" class="<?php echo 'linked' === $filter_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Linked', 'mds-commerce' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=release&page=mds-commerce-audit&commerce_status=unlinked' ) ); ?>" class="<?php echo 'unlinked' === $filter_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Unlinked', 'mds-commerce' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=release&page=mds-commerce-audit&commerce_status=promoted' ) ); ?>" class="<?php echo 'promoted' === $filter_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Active Promos / Coupons', 'mds-commerce' ); ?>
					</a>
				</li>
			</ul>

			<table class="widefat striped" style="margin-top: 12px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Release', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'UPC / EAN', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Connected Stores', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Active Promos', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'mds-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$shown = 0;
					foreach ( $releases as $release ) :
						if ( ! $release instanceof WP_Post ) {
							continue;
						}

						$links = get_post_meta( (int) $release->ID, Config::RELEASE_LINKS_META_KEY, true );
						if ( ! is_array( $links ) || empty( $links ) ) {
							$legacy = get_post_meta( (int) $release->ID, Config::LEGACY_RELEASE_LINKS_META_KEY, true );
							$links  = is_array( $legacy ) ? UpgradeRoutine::convert_legacy_release_links( $legacy ) : array();
						}

						$link_count   = count( $links );
						$active_promo = 0;
						foreach ( $links as $entry ) {
							if ( is_array( $entry ) && StoreChooser::is_promo_active( $entry ) ) {
								++$active_promo;
							}
						}

						if ( 'linked' === $filter_status && 0 === $link_count ) {
							continue;
						}
						if ( 'unlinked' === $filter_status && $link_count > 0 ) {
							continue;
						}
						if ( 'promoted' === $filter_status && 0 === $active_promo ) {
							continue;
						}

						++$shown;
						$upc = (string) get_post_meta( (int) $release->ID, 'upc_ean', true );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( (string) $release->post_title ); ?></strong>
								<span class="description">(<?php echo esc_html( (string) $release->post_status ); ?>)</span>
							</td>
							<td><code><?php echo esc_html( '' !== $upc ? $upc : '—' ); ?></code></td>
							<td>
								<?php if ( $link_count > 0 ) : ?>
									<?php foreach ( array_keys( $links ) as $skey ) : ?>
										<code><?php echo esc_html( (string) $skey ); ?></code>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Not linked', 'mds-commerce' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $active_promo > 0 ) : ?>
									<span class="mds-cm-cap-badge">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: Number of active promos. */
												_n( '%d active promo', '%d active promos', $active_promo, 'mds-commerce' ),
												$active_promo
											)
										);
										?>
									</span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<a class="button button-secondary" href="<?php echo esc_url( (string) get_edit_post_link( (int) $release->ID ) ); ?>">
									<?php esc_html_e( 'Manage Stores', 'mds-commerce' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( 0 === $shown ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No releases matched the selected filter.', 'mds-commerce' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
