<?php
/**
 * Per-site WooCommerce Integration settings tab under Releases -> Settings.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Admin;

use MDS\WooCommerce\Plugin;
use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the WooCommerce Integration tab in Releases -> Settings for dynamic MDS block injection.
 *
 * @since 1.0.1
 */
final class SiteSettings {

	/**
	 * Option name storing per-site product page injection configuration.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const OPTION_KEY = 'mds_wc_product_injection';

	/**
	 * Settings API group name.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	private const OPTION_GROUP = 'mds_wc_site_settings';

	/**
	 * Nonce action used to authorize Live Preview iframe placement overrides.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const PREVIEW_NONCE_ACTION = 'mds_wc_live_preview';

	/**
	 * Special block identifier for custom shortcode/block markup fallback.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	public const CUSTOM_BLOCK_KEY = '__custom_shortcode';

	/**
	 * Maximum number of injected block items allowed per site.
	 *
	 * @since 1.0.1
	 * @var int
	 */
	private const MAX_ITEMS = 12;

	/**
	 * Register site settings hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_filter( 'mds_rm_site_settings_tabs', array( self::class, 'register_tab' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( self::class, 'on_option_updated' ) );
		add_action( 'add_option_' . self::OPTION_KEY, array( self::class, 'on_option_updated' ) );
	}

	/**
	 * Request a coalesced cache purge whenever per-site product injection settings change.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function on_option_updated(): void {
		Plugin::request_cache_purge( 'woocommerce_settings' );
	}

	/**
	 * Register the per-site option with WordPress Settings API.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Return the ordered active single-product placement slots (top to bottom).
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, string> Ordered slot key => human-readable label.
	 */
	public static function get_active_slots(): array {
		return array(
			'woocommerce_single_product_summary:5'         => __( 'Above Product Title', 'mds-woocommerce' ),
			'woocommerce_single_product_summary:6'         => __( 'Below Product Title', 'mds-woocommerce' ),
			'woocommerce_single_product_summary:15'        => __( 'Below Price', 'mds-woocommerce' ),
			'woocommerce_single_product_summary:25'        => __( 'Below Short Description / Excerpt', 'mds-woocommerce' ),
			'woocommerce_single_product_summary:45'        => __( 'Below Add to Cart', 'mds-woocommerce' ),
			'woocommerce_after_single_product_summary:10'  => __( 'Below Product Summary', 'mds-woocommerce' ),
			'woocommerce_before_single_product_summary:25' => __( 'Below Product Gallery', 'mds-woocommerce' ),
			'woocommerce_after_single_product_summary:12'  => __( 'Above Product Description / Tabs', 'mds-woocommerce' ),
			'woocommerce_after_single_product_summary:15'  => __( 'Below Product Description / Tabs', 'mds-woocommerce' ),
		);
	}

	/**
	 * Return all valid placement choices including disabled.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, string> Placement key => human-readable label.
	 */
	public static function get_placement_choices(): array {
		return array_merge(
			self::get_active_slots(),
			array(
				'disabled' => __( 'Disabled', 'mds-woocommerce' ),
			)
		);
	}

	/**
	 * Discover all registered blocks in the `mds-release-manager` block category plus the Custom Shortcode option.
	 *
	 * Automatically includes MDS Release Manager core blocks, MDS Bandcamp Integration blocks when active,
	 * and any future MDS extension blocks registered under the shared category.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{name: string, title: string, description: string, group: string, badge: string, variant: string}>
	 */
	public static function get_available_blocks(): array {
		$blocks   = array();
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( $registry->get_all_registered() as $block_name => $block_type ) {
			if ( 'mds-release-manager' !== ( $block_type->category ?? '' ) ) {
				continue;
			}

			$title       = ! empty( $block_type->title ) ? (string) $block_type->title : (string) $block_name;
			$description = ! empty( $block_type->description ) ? (string) $block_type->description : '';
			$group       = self::resolve_block_group( (string) $block_name );
			$variant     = self::resolve_block_variant( (string) $block_name );
			$badge       = self::resolve_block_badge( (string) $block_name, $title );

			$blocks[ (string) $block_name ] = array(
				'name'        => (string) $block_name,
				'title'       => $title,
				'description' => $description,
				'group'       => $group,
				'badge'       => $badge,
				'variant'     => $variant,
			);
		}

		// Ensure core blocks remain selectable even if inspected before block init.
		if ( ! isset( $blocks['mds-rm/release-meta'] ) ) {
			$blocks['mds-rm/release-meta'] = array(
				'name'        => 'mds-rm/release-meta',
				'title'       => __( 'Release Meta', 'mds-woocommerce' ),
				'description' => __( 'Displays release metadata (year, label, distributor, UPC/EAN, format).', 'mds-woocommerce' ),
				'group'       => __( 'MDS Release Manager', 'mds-woocommerce' ),
				'badge'       => 'RM',
				'variant'     => 'meta',
			);
		}

		if ( ! isset( $blocks['mds-rm/release-links'] ) ) {
			$blocks['mds-rm/release-links'] = array(
				'name'        => 'mds-rm/release-links',
				'title'       => __( 'Release Links', 'mds-woocommerce' ),
				'description' => __( 'Displays streaming links and the configured audio preview player.', 'mds-woocommerce' ),
				'group'       => __( 'MDS Release Manager', 'mds-woocommerce' ),
				'badge'       => 'RL',
				'variant'     => 'links',
			);
		}

		$blocks[ self::CUSTOM_BLOCK_KEY ] = array(
			'name'        => self::CUSTOM_BLOCK_KEY,
			'title'       => __( 'Custom Shortcode / Block', 'mds-woocommerce' ),
			'description' => __( 'Render a custom shortcode or serialized Gutenberg block markup.', 'mds-woocommerce' ),
			'group'       => __( 'Custom', 'mds-woocommerce' ),
			'badge'       => 'CS',
			'variant'     => 'custom',
		);

		/**
		 * Filters the list of MDS blocks available for single WooCommerce product page injection.
		 *
		 * @since 1.0.1
		 *
		 * @param array<string, array{name: string, title: string, description: string, group: string, badge: string, variant: string}> $blocks Available blocks keyed by block name.
		 */
		return (array) apply_filters( 'mds_wc_injectable_blocks', $blocks );
	}

	/**
	 * Determine the optgroup label for a registered block name.
	 *
	 * @since 1.0.1
	 *
	 * @param string $block_name Block name (namespace/slug).
	 * @return string Group label.
	 */
	private static function resolve_block_group( string $block_name ): string {
		if ( str_starts_with( $block_name, 'mds-rm/' ) || str_starts_with( $block_name, 'mds-release-manager/' ) ) {
			return __( 'MDS Release Manager', 'mds-woocommerce' );
		}

		if ( str_starts_with( $block_name, 'mds-bandcamp/' ) ) {
			return __( 'MDS Bandcamp Integration', 'mds-woocommerce' );
		}

		return __( 'MDS Extensions', 'mds-woocommerce' );
	}

	/**
	 * Determine the visual color variant for a block name.
	 *
	 * @since 1.0.1
	 *
	 * @param string $block_name Block name.
	 * @return string CSS variant key.
	 */
	private static function resolve_block_variant( string $block_name ): string {
		if ( 'mds-rm/release-meta' === $block_name ) {
			return 'meta';
		}

		if ( 'mds-rm/release-links' === $block_name ) {
			return 'links';
		}

		if ( str_starts_with( $block_name, 'mds-bandcamp/' ) ) {
			return 'bandcamp';
		}

		if ( self::CUSTOM_BLOCK_KEY === $block_name ) {
			return 'custom';
		}

		return 'extension';
	}

	/**
	 * Generate a concise 2-character badge code for a block.
	 *
	 * @since 1.0.1
	 *
	 * @param string $block_name Block name.
	 * @param string $title      Block title.
	 * @return string 2-letter uppercase badge code.
	 */
	private static function resolve_block_badge( string $block_name, string $title ): string {
		if ( 'mds-rm/release-meta' === $block_name ) {
			return 'RM';
		}

		if ( 'mds-rm/release-links' === $block_name ) {
			return 'RL';
		}

		if ( 'mds-rm/artist-discography' === $block_name ) {
			return 'AD';
		}

		if ( 'mds-rm/release-player' === $block_name ) {
			return 'RP';
		}

		if ( str_starts_with( $block_name, 'mds-bandcamp/' ) ) {
			return 'BC';
		}

		$words = preg_split( '/\s+/', trim( $title ) );
		if ( is_array( $words ) && count( $words ) >= 2 ) {
			return strtoupper( substr( $words[0], 0, 1 ) . substr( $words[1], 0, 1 ) );
		}

		return strtoupper( substr( $title, 0, 2 ) );
	}

	/**
	 * Return default seeded injection items (Release Meta, Release Links, and Release Player).
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{id: string, block: string, placement: string, enabled: bool, hide_buy_now: bool, content: string}>
	 */
	private static function get_default_items(): array {
		$legacy_meta = get_theme_mod( 'mds_rm_woo_injection', 'woocommerce_single_product_summary:6' );
		$choices     = self::get_placement_choices();

		if ( ! is_string( $legacy_meta ) || ! array_key_exists( $legacy_meta, $choices ) ) {
			$legacy_meta = 'woocommerce_single_product_summary:6';
		}

		return array(
			array(
				'id'           => 'item_release_meta',
				'block'        => 'mds-rm/release-meta',
				'placement'    => 'disabled' === $legacy_meta ? 'woocommerce_single_product_summary:6' : $legacy_meta,
				'enabled'      => 'disabled' !== $legacy_meta,
				'hide_buy_now' => false,
				'content'      => '',
			),
			array(
				'id'           => 'item_release_links',
				'block'        => 'mds-rm/release-links',
				'placement'    => 'woocommerce_single_product_summary:25',
				'enabled'      => true,
				'hide_buy_now' => true,
				'content'      => '',
			),
			array(
				'id'           => 'item_release_player',
				'block'        => 'mds-rm/release-player',
				'placement'    => 'woocommerce_single_product_summary:25',
				'enabled'      => true,
				'hide_buy_now' => false,
				'content'      => '',
			),
		);
	}

	/**
	 * Convert legacy flat option format into the dynamic `items` list schema.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $legacy Legacy option array.
	 * @return array<int, array<string, mixed>> Converted item entries.
	 */
	private static function convert_legacy_settings( array $legacy ): array {
		$items = array();

		$meta_place = isset( $legacy['release_meta_placement'] ) && is_string( $legacy['release_meta_placement'] )
			? $legacy['release_meta_placement']
			: 'woocommerce_single_product_summary:25';
		$items[]    = array(
			'id'           => 'item_release_meta',
			'block'        => 'mds-rm/release-meta',
			'placement'    => 'disabled' === $meta_place ? 'woocommerce_single_product_summary:25' : $meta_place,
			'enabled'      => 'disabled' !== $meta_place,
			'hide_buy_now' => false,
			'content'      => '',
		);

		$links_place = isset( $legacy['release_links_placement'] ) && is_string( $legacy['release_links_placement'] )
			? $legacy['release_links_placement']
			: 'woocommerce_after_single_product_summary:15';
		$items[]     = array(
			'id'           => 'item_release_links',
			'block'        => 'mds-rm/release-links',
			'placement'    => 'disabled' === $links_place ? 'woocommerce_after_single_product_summary:15' : $links_place,
			'enabled'      => 'disabled' !== $links_place,
			'hide_buy_now' => ! empty( $legacy['hide_buy_now'] ),
			'content'      => '',
		);

		$custom_place   = isset( $legacy['custom_placement'] ) && is_string( $legacy['custom_placement'] )
			? $legacy['custom_placement']
			: 'disabled';
		$custom_content = isset( $legacy['custom_content'] ) && is_string( $legacy['custom_content'] )
			? trim( $legacy['custom_content'] )
			: '';

		if ( 'disabled' !== $custom_place || '' !== $custom_content ) {
			$items[] = array(
				'id'           => 'item_custom',
				'block'        => self::CUSTOM_BLOCK_KEY,
				'placement'    => 'disabled' === $custom_place ? 'woocommerce_after_single_product_summary:15' : $custom_place,
				'enabled'      => 'disabled' !== $custom_place,
				'hide_buy_now' => false,
				'content'      => $custom_content,
			);
		}

		return $items;
	}

	/**
	 * Return normalized per-site product injection settings (`items`), honoring authorized Live Preview overrides.
	 *
	 * @since 1.0.1
	 *
	 * @return array{items: array<int, array{id: string, block: string, placement: string, enabled: bool, hide_buy_now: bool, content: string}>}
	 */
	public static function get_settings(): array {
		$stored = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $stored ) ) {
			$settings = array( 'items' => self::get_default_items() );
		} elseif ( isset( $stored['items'] ) && is_array( $stored['items'] ) ) {
			$settings = self::sanitize_settings( $stored );
		} else {
			$settings = self::sanitize_settings(
				array(
					'items' => self::convert_legacy_settings( $stored ),
				)
			);
		}

		return self::apply_preview_overrides( $settings );
	}

	/**
	 * Apply signed, allowlisted Live Preview overrides for administrators viewing the preview iframe.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $settings Base saved settings.
	 * @return array{items: array<int, array{id: string, block: string, placement: string, enabled: bool, hide_buy_now: bool, content: string}>}
	 */
	private static function apply_preview_overrides( array $settings ): array {
		if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
			return $settings;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below via wp_verify_nonce().
		if ( empty( $_GET['mds_wc_preview'] ) || empty( $_GET['mds_wc_preview_nonce'] ) ) {
			return $settings;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['mds_wc_preview_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::PREVIEW_NONCE_ACTION ) ) {
			return $settings;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce already verified above.
		if ( empty( $_GET['mds_wc_preview_items'] ) || ! is_string( $_GET['mds_wc_preview_items'] ) ) {
			return $settings;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload sanitized element-by-element via sanitize_settings().
		$decoded = json_decode( wp_unslash( $_GET['mds_wc_preview_items'] ), true );
		if ( ! is_array( $decoded ) ) {
			return $settings;
		}

		// Preserve saved custom shortcode content by ID (never accept raw markup from query params).
		$saved_custom_by_id = array();
		foreach ( $settings['items'] as $saved_item ) {
			if ( self::CUSTOM_BLOCK_KEY === $saved_item['block'] && '' !== $saved_item['content'] ) {
				$saved_custom_by_id[ $saved_item['id'] ] = $saved_item['content'];
			}
		}

		$merged_preview = array();
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_id           = isset( $row['id'] ) && is_string( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
			$row['content']   = $saved_custom_by_id[ $row_id ] ?? '';
			$merged_preview[] = $row;
		}

		return self::sanitize_settings( array( 'items' => $merged_preview ) );
	}

	/**
	 * Sanitize submitted product injection settings.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $raw Submitted settings array.
	 * @return array{items: array<int, array{id: string, block: string, placement: string, enabled: bool, hide_buy_now: bool, content: string}>}
	 */
	public static function sanitize_settings( mixed $raw ): array {
		$input        = is_array( $raw ) ? $raw : array();
		$raw_items    = isset( $input['items'] ) && is_array( $input['items'] )
			? $input['items']
			: self::convert_legacy_settings( $input );
		$active_slots = self::get_active_slots();
		$valid_blocks = self::get_available_blocks();

		$sanitized_items = array();
		$counter         = 0;

		foreach ( $raw_items as $row ) {
			if ( ! is_array( $row ) || $counter >= self::MAX_ITEMS ) {
				continue;
			}

			$block = isset( $row['block'] ) && is_string( $row['block'] ) ? trim( $row['block'] ) : '';
			if ( 'mds-release-manager/release-player' === $block ) {
				$block = 'mds-rm/release-player';
			}
			if ( '' === $block || ! isset( $valid_blocks[ $block ] ) ) {
				continue;
			}

			$placement = isset( $row['placement'] ) && is_string( $row['placement'] ) ? $row['placement'] : '';
			$enabled   = array_key_exists( 'enabled', $row ) ? (bool) $row['enabled'] : ( 'disabled' !== $placement );

			if ( ! array_key_exists( $placement, $active_slots ) ) {
				$placement = 'mds-rm/release-meta' === $block
					? 'woocommerce_single_product_summary:25'
					: 'woocommerce_after_single_product_summary:15';
			}

			++$counter;
			$id = isset( $row['id'] ) && is_string( $row['id'] ) && '' !== sanitize_key( $row['id'] )
				? sanitize_key( $row['id'] )
				: 'item_' . $counter;

			$content = self::CUSTOM_BLOCK_KEY === $block && isset( $row['content'] ) && is_string( $row['content'] )
				? trim( wp_kses_post( $row['content'] ) )
				: '';

			$sanitized_items[] = array(
				'id'           => $id,
				'block'        => $block,
				'placement'    => $placement,
				'enabled'      => $enabled,
				'hide_buy_now' => 'mds-rm/release-links' === $block && ! empty( $row['hide_buy_now'] ),
				'content'      => $content,
			);
		}

		return array(
			'items' => $sanitized_items,
		);
	}

	/**
	 * Fetch published WooCommerce products on the current site for the Live Preview selector.
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{id: int, title: string, url: string}> Previewable products.
	 */
	private static function get_preview_products(): array {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 25,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$products = array();
		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post->ID );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				$products[] = array(
					'id'    => (int) $post->ID,
					'title' => (string) get_the_title( $post->ID ),
					'url'   => $permalink,
				);
			}
		}

		return $products;
	}

	/**
	 * Register the WooCommerce Integration tab in Releases -> Settings.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing settings tabs.
	 * @return array<string, array<string, mixed>> Updated settings tabs.
	 */
	public static function register_tab( array $tabs ): array {
		if ( ! Plugin::is_active() || ! Plugin::is_woocommerce_active_on_current_site() ) {
			return $tabs;
		}

		$tabs['woocommerce'] = array(
			'label'  => __( 'WooCommerce Integration', 'mds-woocommerce' ),
			'render' => array( self::class, 'render_tab' ),
		);

		return $tabs;
	}

	/**
	 * Render the designer-oriented WooCommerce Integration settings tab inside Releases -> Settings.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function render_tab(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage WooCommerce integration settings.', 'mds-woocommerce' ) );
		}

		$available_blocks = self::get_available_blocks();
		$grouped_blocks   = array();
		foreach ( $available_blocks as $block ) {
			$grouped_blocks[ $block['group'] ][] = $block;
		}

		$preview_products = self::get_preview_products();
		?>
		<form id="mds-wc-site-settings-form" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<div class="mds-panel mds-wc-designer-panel">
				<div class="mds-wc-designer-header">
					<div>
						<h2><?php esc_html_e( 'Single Product Page Injection', 'mds-woocommerce' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Add any registered MDS block (including Bandcamp blocks when active), then drag blocks onto slots in the Single Product Blueprint or use the Up / Down controls to position them. Works on both Block Themes and Classic WooCommerce themes.', 'mds-woocommerce' ); ?>
						</p>
					</div>
				</div>

				<div class="mds-wc-designer-grid">
					<div class="mds-wc-designer-controls">
						<div class="mds-wc-add-block-bar">
							<label for="mds-wc-add-block-select" class="screen-reader-text">
								<?php esc_html_e( 'Select an MDS block to add', 'mds-woocommerce' ); ?>
							</label>
							<select id="mds-wc-add-block-select" class="mds-wc-add-block-bar__select">
								<?php foreach ( $grouped_blocks as $group_label => $group_items ) : ?>
									<optgroup label="<?php echo esc_attr( $group_label ); ?>">
										<?php foreach ( $group_items as $block ) : ?>
											<option value="<?php echo esc_attr( $block['name'] ); ?>">
												<?php echo esc_html( $block['title'] ); ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<button type="button" id="mds-wc-add-block-btn" class="button button-secondary">
								<?php esc_html_e( '+ Add Block', 'mds-woocommerce' ); ?>
							</button>
						</div>

						<div id="mds-wc-block-cards" class="mds-wc-block-cards" aria-live="polite"></div>

						<?php settings_fields( self::OPTION_GROUP ); ?>
						<div class="mds-admin-save-actions mds-wc-form-actions">
							<button id="mds-wc-site-save-btn" class="button button-primary" type="submit">
								<?php esc_html_e( 'Save Settings', 'mds-woocommerce' ); ?>
							</button>
							<span id="mds-wc-site-save-status" class="mds-wc-status-message" role="status" aria-live="polite"></span>
						</div>
					</div>

					<aside class="mds-wc-blueprint" aria-label="<?php esc_attr_e( 'Interactive Product Page Placement Blueprint', 'mds-woocommerce' ); ?>">
						<div class="mds-wc-blueprint__bar">
							<span class="mds-wc-blueprint__dots" aria-hidden="true"><span></span><span></span><span></span></span>
							<span class="mds-wc-blueprint__label"><?php esc_html_e( 'Single Product Layout Blueprint', 'mds-woocommerce' ); ?></span>
							<span class="mds-wc-blueprint__hint"><?php esc_html_e( 'Drag & drop blocks between slots', 'mds-woocommerce' ); ?></span>
						</div>

						<div class="mds-wc-blueprint__canvas">
							<div class="mds-wc-blueprint__top">
								<div class="mds-wc-wire-gallery-col">
									<div class="mds-wc-wire-gallery" aria-hidden="true">
										<span class="dashicons dashicons-format-image"></span>
										<span><?php esc_html_e( 'Product Gallery', 'mds-woocommerce' ); ?></span>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_before_single_product_summary:25" data-slot-label="<?php esc_attr_e( 'Below Product Gallery', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>
								</div>

								<div class="mds-wc-wire-summary">
									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:5" data-slot-label="<?php esc_attr_e( 'Above Product Title', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--title" aria-hidden="true">
										<?php esc_html_e( 'Product Title', 'mds-woocommerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:6" data-slot-label="<?php esc_attr_e( 'Below Product Title', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--price" aria-hidden="true">
										<?php esc_html_e( '$19.99', 'mds-woocommerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:15" data-slot-label="<?php esc_attr_e( 'Below Price', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--excerpt" aria-hidden="true">
										<span><?php esc_html_e( 'Short Description / Excerpt', 'mds-woocommerce' ); ?></span>
										<span class="mds-wc-wire-line"></span>
										<span class="mds-wc-wire-line is-short"></span>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:25" data-slot-label="<?php esc_attr_e( 'Below Short Description / Excerpt', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--cart" aria-hidden="true">
										<?php esc_html_e( 'Add to Cart', 'mds-woocommerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:45" data-slot-label="<?php esc_attr_e( 'Below Add to Cart', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--meta" aria-hidden="true">
										<?php esc_html_e( 'SKU · Categories · Tags', 'mds-woocommerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:10" data-slot-label="<?php esc_attr_e( 'Below Product Summary', 'mds-woocommerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>
								</div>
							</div>

							<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:12" data-slot-label="<?php esc_attr_e( 'Above Product Description / Tabs', 'mds-woocommerce' ); ?>">
								<div class="mds-wc-slot__chips"></div>
							</div>

							<div class="mds-wc-wire-block mds-wc-wire-block--tabs" aria-hidden="true">
								<strong><?php esc_html_e( 'Product Description / Tabs', 'mds-woocommerce' ); ?></strong>
								<span class="mds-wc-wire-line"></span>
								<span class="mds-wc-wire-line is-short"></span>
							</div>

							<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:15" data-slot-label="<?php esc_attr_e( 'Below Product Description / Tabs', 'mds-woocommerce' ); ?>">
								<div class="mds-wc-slot__chips"></div>
							</div>

							<div class="mds-wc-slot mds-wc-slot--disabled-tray" data-slot="disabled" data-slot-label="<?php esc_attr_e( 'Disable Block', 'mds-woocommerce' ); ?>">
								<span class="mds-wc-slot__tray-label"><?php esc_html_e( 'Inactive / Disabled', 'mds-woocommerce' ); ?></span>
								<div class="mds-wc-slot__chips"></div>
							</div>
						</div>
					</aside>
				</div>

				<details class="mds-wc-live-preview" id="mds-wc-live-preview">
					<summary class="mds-wc-live-preview__summary">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Live Preview', 'mds-woocommerce' ); ?></span>
						<span class="mds-wc-live-preview__hint">
							<?php esc_html_e( 'Inspect unsaved placement changes on a real product page', 'mds-woocommerce' ); ?>
						</span>
					</summary>

					<div class="mds-wc-live-preview__body">
						<?php if ( empty( $preview_products ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'No published WooCommerce products were found on this site to preview.', 'mds-woocommerce' ); ?>
							</p>
						<?php else : ?>
							<div class="mds-wc-live-preview__toolbar">
								<div class="mds-wc-live-preview__product-picker">
									<label for="mds-wc-preview-product"><?php esc_html_e( 'Preview Product:', 'mds-woocommerce' ); ?></label>
									<select id="mds-wc-preview-product">
										<?php foreach ( $preview_products as $product ) : ?>
											<option value="<?php echo esc_url( $product['url'] ); ?>">
												<?php echo esc_html( $product['title'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="mds-wc-live-preview__viewports" role="group" aria-label="<?php esc_attr_e( 'Preview Viewport Width', 'mds-woocommerce' ); ?>">
									<button type="button" class="button button-secondary is-active" data-viewport="desktop" aria-pressed="true">
										<?php esc_html_e( 'Desktop', 'mds-woocommerce' ); ?>
									</button>
									<button type="button" class="button button-secondary" data-viewport="tablet" aria-pressed="false">
										<?php esc_html_e( 'Tablet', 'mds-woocommerce' ); ?>
									</button>
									<button type="button" class="button button-secondary" data-viewport="mobile" aria-pressed="false">
										<?php esc_html_e( 'Mobile', 'mds-woocommerce' ); ?>
									</button>
								</div>

								<div class="mds-wc-live-preview__actions">
									<button type="button" class="button button-secondary" id="mds-wc-preview-refresh">
										<?php esc_html_e( 'Refresh', 'mds-woocommerce' ); ?>
									</button>
									<a
										href="<?php echo esc_url( $preview_products[0]['url'] ); ?>"
										id="mds-wc-preview-open-tab"
										class="button button-secondary"
										target="_blank"
										rel="noopener noreferrer"
									>
										<?php esc_html_e( 'Open in New Tab', 'mds-woocommerce' ); ?>
										<span class="dashicons dashicons-external" aria-hidden="true"></span>
									</a>
								</div>
							</div>

							<div class="mds-wc-live-preview__stage is-desktop" id="mds-wc-preview-stage">
								<iframe
									id="mds-wc-preview-iframe"
									class="mds-wc-live-preview__iframe"
									title="<?php esc_attr_e( 'Live WooCommerce Product Page Preview', 'mds-woocommerce' ); ?>"
									loading="lazy"
								></iframe>
							</div>
						<?php endif; ?>
					</div>
				</details>
			</div>
		</form>
		<?php
	}
}
