<?php
/**
 * Per-site WooCommerce Integration settings tab under Releases -> Settings.
 *
 * @package MDS\Commerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Admin;

use MDS\Commerce\Plugin;
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
			'woocommerce_single_product_summary:5'         => __( 'Above Product Title', 'mds-commerce' ),
			'woocommerce_single_product_summary:6'         => __( 'Below Product Title', 'mds-commerce' ),
			'woocommerce_single_product_summary:15'        => __( 'Below Price', 'mds-commerce' ),
			'woocommerce_single_product_summary:25'        => __( 'Below Short Description / Excerpt', 'mds-commerce' ),
			'woocommerce_single_product_summary:45'        => __( 'Below Add to Cart', 'mds-commerce' ),
			'woocommerce_after_single_product_summary:10'  => __( 'Below Product Summary', 'mds-commerce' ),
			'woocommerce_before_single_product_summary:25' => __( 'Below Product Gallery', 'mds-commerce' ),
			'woocommerce_after_single_product_summary:12'  => __( 'Above Product Description / Tabs', 'mds-commerce' ),
			'woocommerce_after_single_product_summary:15'  => __( 'Below Product Description / Tabs', 'mds-commerce' ),
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
				'disabled' => __( 'Disabled', 'mds-commerce' ),
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
				'title'       => __( 'Release Meta', 'mds-commerce' ),
				'description' => __( 'Displays release metadata (year, label, distributor, UPC/EAN, format).', 'mds-commerce' ),
				'group'       => __( 'MDS Release Manager', 'mds-commerce' ),
				'badge'       => 'RM',
				'variant'     => 'meta',
			);
		}

		if ( ! isset( $blocks['mds-rm/release-links'] ) ) {
			$blocks['mds-rm/release-links'] = array(
				'name'        => 'mds-rm/release-links',
				'title'       => __( 'Release Links', 'mds-commerce' ),
				'description' => __( 'Displays streaming links and the configured audio preview player.', 'mds-commerce' ),
				'group'       => __( 'MDS Release Manager', 'mds-commerce' ),
				'badge'       => 'RL',
				'variant'     => 'links',
			);
		}

		$blocks[ self::CUSTOM_BLOCK_KEY ] = array(
			'name'        => self::CUSTOM_BLOCK_KEY,
			'title'       => __( 'Custom Shortcode / Block', 'mds-commerce' ),
			'description' => __( 'Render a custom shortcode or serialized Gutenberg block markup.', 'mds-commerce' ),
			'group'       => __( 'Custom', 'mds-commerce' ),
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
			return __( 'MDS Release Manager', 'mds-commerce' );
		}

		if ( str_starts_with( $block_name, 'mds-commerce/' ) ) {
			return __( 'MDS Commerce', 'mds-commerce' );
		}

		if ( str_starts_with( $block_name, 'mds-bandcamp/' ) ) {
			return __( 'MDS Bandcamp Integration', 'mds-commerce' );
		}

		return __( 'MDS Extensions', 'mds-commerce' );
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
	 * Register the unified Commerce tab in Releases -> Settings.
	 *
	 * Consolidates Hub Store Connections, Store Chooser Defaults, and the migrated
	 * WooCommerce Single Product Page Injection designer under one tab.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing settings tabs.
	 * @return array<string, array<string, mixed>> Updated settings tabs.
	 */
	public static function register_tab( array $tabs ): array {
		if ( ! Plugin::is_active() ) {
			return $tabs;
		}

		$is_hub = ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			|| \MDS\ReleaseManager\Api\ExtensionApi::is_hub();
		$has_wc = Plugin::is_woocommerce_active_on_current_site();

		if ( ! $is_hub && ! $has_wc ) {
			return $tabs;
		}

		$tabs['commerce'] = array(
			'label'  => __( 'Commerce', 'mds-commerce' ),
			'render' => array( self::class, 'render_tab' ),
		);

		return $tabs;
	}

	/**
	 * Render the unified Commerce settings tab inside Releases -> Settings.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function render_tab(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage MDS Commerce settings.', 'mds-commerce' ) );
		}

		$is_hub = ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			|| \MDS\ReleaseManager\Api\ExtensionApi::is_hub();
		$has_wc = Plugin::is_woocommerce_active_on_current_site();

		$default_subtab = $is_hub ? 'connections' : 'wc-injection';
		?>
		<div class="mds-cm-settings-shell">
			<div class="mds-cm-subtabs" role="tablist" aria-label="<?php esc_attr_e( 'Commerce settings sections', 'mds-commerce' ); ?>">
				<?php if ( $is_hub ) : ?>
					<button
						type="button"
						role="tab"
						class="mds-cm-subtab-btn <?php echo 'connections' === $default_subtab ? 'is-active' : ''; ?>"
						data-subtab="connections"
						aria-selected="<?php echo 'connections' === $default_subtab ? 'true' : 'false'; ?>"
					>
						<?php esc_html_e( 'Store Connections', 'mds-commerce' ); ?>
					</button>
					<button
						type="button"
						role="tab"
						class="mds-cm-subtab-btn"
						data-subtab="chooser"
						aria-selected="false"
					>
						<?php esc_html_e( 'Store Chooser Defaults', 'mds-commerce' ); ?>
					</button>
					<button
						type="button"
						role="tab"
						class="mds-cm-subtab-btn"
						data-subtab="license"
						aria-selected="false"
					>
						<?php esc_html_e( 'License & API Gateway', 'mds-commerce' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $has_wc ) : ?>
					<button
						type="button"
						role="tab"
						class="mds-cm-subtab-btn <?php echo 'wc-injection' === $default_subtab ? 'is-active' : ''; ?>"
						data-subtab="wc-injection"
						aria-selected="<?php echo 'wc-injection' === $default_subtab ? 'true' : 'false'; ?>"
					>
						<?php esc_html_e( 'WooCommerce Product Injection', 'mds-commerce' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $is_hub ) : ?>
				<div class="mds-cm-subtab-panel" data-subtab-panel="connections">
					<?php self::render_store_connections_panel(); ?>
				</div>

				<div class="mds-cm-subtab-panel" data-subtab-panel="chooser" hidden>
					<?php self::render_chooser_defaults_panel(); ?>
				</div>

				<div class="mds-cm-subtab-panel" data-subtab-panel="license" hidden>
					<?php self::render_license_gateway_panel(); ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_wc ) : ?>
				<div class="mds-cm-subtab-panel" data-subtab-panel="wc-injection" <?php echo 'wc-injection' === $default_subtab ? '' : 'hidden'; ?>>
					<?php self::render_wc_injection_panel(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Hub Store Connections manager and Connection Wizard modal.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private static function render_store_connections_panel(): void {
		$local_stores = \MDS\Commerce\Support\StoreRegistry::get_available_stores();
		$remote       = \MDS\Commerce\Registry\ConnectionRepository::get_all_safe();
		$providers    = \MDS\Commerce\Registry\ConnectionRepository::get_supported_remote_providers();
		?>
		<div class="mds-panel">
			<div class="mds-wc-designer-header">
				<div>
					<h2><?php esc_html_e( 'Connected Commerce Stores', 'mds-commerce' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Manage local Multisite WooCommerce stores and external storefronts (Shopify, Remote WooCommerce, Wix, Big Cartel, Squarespace, Bandcamp, elasticStage). Credentials are encrypted at rest and support wp-config.php constant overrides.', 'mds-commerce' ); ?>
					</p>
				</div>
				<div>
					<button type="button" id="mds-cm-add-store-btn" class="button button-primary">
						<?php esc_html_e( '+ Add Store Connection', 'mds-commerce' ); ?>
					</button>
				</div>
			</div>

			<div id="mds-cm-stores-notice" role="status" aria-live="polite"></div>

			<h3><?php esc_html_e( 'Local WooCommerce Stores (Auto-Detected)', 'mds-commerce' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Store', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Store Key', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Capabilities', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'mds-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $local_stores ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'No active local WooCommerce stores detected.', 'mds-commerce' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $local_stores as $blog_id => $meta ) : ?>
							<?php
							$blog_id     = (int) $blog_id;
							$store_key   = 'wc_local:' . $blog_id;
							$has_ext_var = \MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge::is_active_on_blog( $blog_id );
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( (string) ( $meta['site_name'] ?? '' ) ); ?></strong><br />
									<span class="description"><?php echo esc_html( (string) ( $meta['site_url'] ?? '' ) ); ?></span>
								</td>
								<td><code><?php echo esc_html( $store_key ); ?></code></td>
								<td>
									<span class="mds-wc-badge mds-wc-badge--linked"><?php esc_html_e( 'WooCommerce Local', 'mds-commerce' ); ?></span>
									<?php if ( $has_ext_var ) : ?>
										<span class="mds-cm-cap-badge"><?php esc_html_e( 'External Variations Ready', 'mds-commerce' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-secondary mds-cm-test-store-btn" data-store-key="<?php echo esc_attr( $store_key ); ?>">
										<?php esc_html_e( 'Test Connection', 'mds-commerce' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h3 style="margin-top:1.5rem;"><?php esc_html_e( 'Remote & External Storefronts', 'mds-commerce' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Store', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Sync Mode', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Credentials', 'mds-commerce' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'mds-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody id="mds-cm-remote-stores-tbody">
					<?php if ( empty( $remote ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No remote storefronts configured yet. Click "+ Add Store Connection" to connect Shopify, Remote WooCommerce, Wix, Big Cartel, or an external POD storefront.', 'mds-commerce' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $remote as $conn ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( (string) $conn['name'] ); ?></strong><br />
									<span class="description"><?php echo esc_html( (string) $conn['url'] ); ?></span>
								</td>
								<td><code><?php echo esc_html( (string) $conn['provider'] ); ?></code></td>
								<td><?php echo esc_html( ucfirst( (string) $conn['sync_mode'] ) ); ?></td>
								<td>
									<?php
									if ( 'wp_config' === $conn['secret_source'] ) {
										esc_html_e( 'wp-config.php constant', 'mds-commerce' );
									} elseif ( 'vault' === $conn['secret_source'] ) {
										esc_html_e( 'Encrypted Vault', 'mds-commerce' );
									} else {
										esc_html_e( 'None / Public URL', 'mds-commerce' );
									}
									?>
								</td>
								<td>
									<button type="button" class="button button-secondary mds-cm-test-store-btn" data-store-key="<?php echo esc_attr( (string) $conn['store_key'] ); ?>">
										<?php esc_html_e( 'Test', 'mds-commerce' ); ?>
									</button>
									<button type="button" class="button button-secondary mds-cm-edit-store-btn" data-store-json="<?php echo esc_attr( (string) wp_json_encode( $conn ) ); ?>">
										<?php esc_html_e( 'Edit', 'mds-commerce' ); ?>
									</button>
									<button type="button" class="button button-link-delete mds-cm-delete-store-btn" data-store-key="<?php echo esc_attr( (string) $conn['store_key'] ); ?>">
										<?php esc_html_e( 'Delete', 'mds-commerce' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<dialog id="mds-cm-store-modal" class="mds-wc-modal" aria-labelledby="mds-cm-store-modal-title">
			<form id="mds-cm-store-form" class="mds-wc-modal__dialog">
				<header class="mds-wc-modal__header">
					<h2 id="mds-cm-store-modal-title" class="mds-wc-modal__title"><?php esc_html_e( 'Add Store Connection', 'mds-commerce' ); ?></h2>
					<button type="button" class="mds-wc-modal__close" data-action="close-store-modal" aria-label="<?php esc_attr_e( 'Close dialog', 'mds-commerce' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</header>
				<div class="mds-wc-modal__body">
					<input type="hidden" name="store_id" value="" />
					<p>
						<label for="mds-cm-provider"><strong><?php esc_html_e( 'Platform / Provider', 'mds-commerce' ); ?></strong></label><br />
						<select id="mds-cm-provider" name="provider" class="widefat">
							<?php foreach ( $providers as $slug => $info ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="mds-cm-store-name"><strong><?php esc_html_e( 'Store Display Name', 'mds-commerce' ); ?></strong></label><br />
						<input type="text" id="mds-cm-store-name" name="name" class="widefat" required placeholder="<?php esc_attr_e( 'e.g. Official Shopify Merch Store', 'mds-commerce' ); ?>" />
					</p>
					<p>
						<label for="mds-cm-store-url"><strong><?php esc_html_e( 'Storefront URL', 'mds-commerce' ); ?></strong></label><br />
						<input type="url" id="mds-cm-store-url" name="url" class="widefat" placeholder="https://store.example.com" />
					</p>
					<p>
						<label for="mds-cm-api-key"><strong><?php esc_html_e( 'API Key / Client ID / Site ID (optional)', 'mds-commerce' ); ?></strong></label><br />
						<input type="text" id="mds-cm-api-key" name="api_key" class="widefat" autocomplete="off" />
					</p>
					<p>
						<label for="mds-cm-api-secret"><strong><?php esc_html_e( 'Access Token / Consumer Secret (encrypted at rest)', 'mds-commerce' ); ?></strong></label><br />
						<input type="password" id="mds-cm-api-secret" name="api_secret" class="widefat" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep existing secret', 'mds-commerce' ); ?>" />
					</p>
					<p>
						<label for="mds-cm-secret-const"><strong><?php esc_html_e( 'wp-config.php Constant Override (optional)', 'mds-commerce' ); ?></strong></label><br />
						<input type="text" id="mds-cm-secret-const" name="secret_constant" class="widefat" placeholder="MDS_CM_SHOPIFY_TOKEN_MAIN" />
					</p>
					<p>
						<label for="mds-cm-sync-mode"><strong><?php esc_html_e( 'Sync Mode', 'mds-commerce' ); ?></strong></label><br />
						<select id="mds-cm-sync-mode" name="sync_mode" class="widefat">
							<option value="manual"><?php esc_html_e( 'Manual Only (On-Demand)', 'mds-commerce' ); ?></option>
							<option value="polling"><?php esc_html_e( 'Scheduled Polling (Action Scheduler / WP-Cron)', 'mds-commerce' ); ?></option>
							<option value="webhook"><?php esc_html_e( 'Real-Time Webhooks', 'mds-commerce' ); ?></option>
						</select>
					</p>
					<input type="hidden" name="poll_interval" value="hourly" />
					<details>
						<summary><strong><?php esc_html_e( 'Store-Level Promo & Coupon Defaults (Store Chooser)', 'mds-commerce' ); ?></strong></summary>
						<p>
							<label><?php esc_html_e( 'Default Promo Badge', 'mds-commerce' ); ?><br />
							<input type="text" name="promo_badge" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Free Shipping', 'mds-commerce' ); ?>" /></label>
						</p>
						<p>
							<label><?php esc_html_e( 'Default Coupon Code', 'mds-commerce' ); ?><br />
							<input type="text" name="coupon_code" class="widefat" placeholder="VINYL15" /></label>
						</p>
						<p>
							<label><?php esc_html_e( 'Promo Expiration (UTC YYYY-MM-DD HH:MM:SS)', 'mds-commerce' ); ?><br />
							<input type="text" name="promo_expires_gmt" class="widefat" placeholder="2026-12-31 23:59:59" /></label>
						</p>
						<p>
							<label><?php esc_html_e( 'Priority Weight (higher floats up)', 'mds-commerce' ); ?><br />
							<input type="number" name="priority_weight" class="small-text" value="0" /></label>
						</p>
					</details>
				</div>
				<footer class="mds-wc-modal__footer">
					<button type="button" class="button button-secondary" data-action="close-store-modal"><?php esc_html_e( 'Cancel', 'mds-commerce' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Store Connection', 'mds-commerce' ); ?></button>
				</footer>
			</form>
		</dialog>
		<?php
	}

	/**
	 * Render the Store Chooser Global Defaults panel and WP 7.0 Core AI/MCP pointers.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private static function render_chooser_defaults_panel(): void {
		$defaults = \MDS\Commerce\Registry\ConnectionRepository::get_chooser_defaults();
		?>
		<div class="mds-panel">
			<h2><?php esc_html_e( 'Store Chooser Global Defaults', 'mds-commerce' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Default presentation settings for the mds-commerce/store-chooser Gutenberg block and [mds-store-chooser] shortcode.', 'mds-commerce' ); ?>
			</p>
			<div id="mds-cm-chooser-notice" role="status" aria-live="polite"></div>

			<form id="mds-cm-chooser-defaults-form">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mds-cm-def-heading"><?php esc_html_e( 'Default Heading', 'mds-commerce' ); ?></label></th>
						<td><input type="text" id="mds-cm-def-heading" name="heading" class="regular-text" value="<?php echo esc_attr( $defaults['heading'] ); ?>" placeholder="<?php esc_attr_e( 'Where to Buy', 'mds-commerce' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mds-cm-def-groupby"><?php esc_html_e( 'Default Grouping', 'mds-commerce' ); ?></label></th>
						<td>
							<select id="mds-cm-def-groupby" name="group_by">
								<option value="format" <?php selected( $defaults['group_by'], 'format' ); ?>><?php esc_html_e( 'Media Format (Vinyl, CD, Digital)', 'mds-commerce' ); ?></option>
								<option value="store" <?php selected( $defaults['group_by'], 'store' ); ?>><?php esc_html_e( 'Storefront', 'mds-commerce' ); ?></option>
								<option value="none" <?php selected( $defaults['group_by'], 'none' ); ?>><?php esc_html_e( 'Flat List', 'mds-commerce' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mds-cm-def-layout"><?php esc_html_e( 'Default Layout', 'mds-commerce' ); ?></label></th>
						<td>
							<select id="mds-cm-def-layout" name="layout">
								<option value="cards" <?php selected( $defaults['layout'], 'cards' ); ?>><?php esc_html_e( 'Cards', 'mds-commerce' ); ?></option>
								<option value="list" <?php selected( $defaults['layout'], 'list' ); ?>><?php esc_html_e( 'Compact List', 'mds-commerce' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Coupon & Promo Priority', 'mds-commerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="prioritize_promos" value="1" <?php checked( $defaults['prioritize_promos'] ); ?> />
								<?php esc_html_e( 'Automatically float stores with active coupon codes or special offers to the top of each format group', 'mds-commerce' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Store Chooser Defaults', 'mds-commerce' ); ?></button></p>
			</form>

			<hr />
			<h3><?php esc_html_e( 'WordPress 7.0 AI & MCP Discoverability', 'mds-commerce' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'MDS Commerce registers read-only Abilities (mds-commerce/list-stores, mds-commerce/get-release-links, mds-commerce/get-pod-provisioning-pack) with MCP discovery enabled. Manage AI provider keys in WordPress Settings → Connectors and audit registered abilities in Tools → Abilities Explorer.', 'mds-commerce' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the License & MDS API Gateway management panel.
	 *
	 * @since 3.0.1
	 *
	 * @return void
	 */
	private static function render_license_gateway_panel(): void {
		$status      = \MDS\Commerce\Gateway\EntitlementManager::get_status();
		$is_active   = \MDS\Commerce\Gateway\EntitlementManager::is_active();
		$tier        = \MDS\Commerce\Gateway\EntitlementManager::get_tier();
		$max_stores  = \MDS\Commerce\Gateway\EntitlementManager::max_remote_stores();
		$gateway_url = \MDS\Commerce\Gateway\GatewayClient::get_base_url();
		$webhook_url = rest_url( 'mds-commerce/v1/webhooks/gateway' );
		?>
		<div class="mds-panel">
			<h2><?php esc_html_e( 'Commercial License & MDS API Gateway', 'mds-commerce' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Connect your commercial license key to enable remote storefront connectors (Shopify, Wix, Big Cartel, Squarespace, Remote WooCommerce), real-time Webhook Relay, and authenticated automatic updates via the MDS API Gateway.', 'mds-commerce' ); ?>
			</p>
			<div id="mds-cm-license-notice" role="status" aria-live="polite"></div>

			<form id="mds-cm-license-form">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Status & Tier', 'mds-commerce' ); ?></th>
						<td>
							<span class="mds-wc-badge <?php echo $is_active ? 'mds-wc-badge--linked' : 'mds-wc-badge--unlinked'; ?>">
								<?php echo $is_active ? esc_html( strtoupper( $tier ) . ' — Active' ) : esc_html__( 'Inactive', 'mds-commerce' ); ?>
							</span>
							<?php if ( $is_active ) : ?>
								<span class="description" style="margin-left:8px;">
									<?php
									echo esc_html(
										-1 === $max_stores
											? __( 'Unlimited remote stores · Webhook Relay enabled', 'mds-commerce' )
											/* translators: %d: Maximum remote stores allowed on Pro tier. */
											: sprintf( __( 'Up to %d remote stores', 'mds-commerce' ), $max_stores )
									);
									?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mds-cm-license-key"><?php esc_html_e( 'License Key', 'mds-commerce' ); ?></label></th>
						<td>
							<input
								type="password"
								id="mds-cm-license-key"
								name="license_key"
								class="regular-text"
								autocomplete="off"
								placeholder="<?php echo ! empty( $status['masked_key'] ) ? esc_attr( (string) $status['masked_key'] ) : 'MDS-XXXX-XXXX-XXXX'; ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Stored encrypted at rest. You can also define MDS_CM_LICENSE_KEY in wp-config.php.', 'mds-commerce' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Gateway Endpoint', 'mds-commerce' ); ?></th>
						<td><code><?php echo esc_html( $gateway_url ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Signed Webhook Receiver', 'mds-commerce' ); ?></th>
						<td><code><?php echo esc_html( $webhook_url ); ?></code></td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Activate License', 'mds-commerce' ); ?></button>
					<button type="button" id="mds-cm-license-refresh" class="button button-secondary"><?php esc_html_e( 'Refresh Entitlements', 'mds-commerce' ); ?></button>
					<?php if ( $is_active ) : ?>
						<button type="button" id="mds-cm-license-deactivate" class="button button-link-delete"><?php esc_html_e( 'Deactivate', 'mds-commerce' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the migrated WooCommerce Single Product Page Injection designer panel.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private static function render_wc_injection_panel(): void {
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
						<h2><?php esc_html_e( 'Single Product Page Injection', 'mds-commerce' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Add any registered MDS block (including Bandcamp blocks when active), then drag blocks onto slots in the Single Product Blueprint or use the Up / Down controls to position them. Works on both Block Themes and Classic WooCommerce themes.', 'mds-commerce' ); ?>
						</p>
					</div>
				</div>

				<div class="mds-wc-designer-grid">
					<div class="mds-wc-designer-controls">
						<div class="mds-wc-add-block-bar">
							<label for="mds-wc-add-block-select" class="screen-reader-text">
								<?php esc_html_e( 'Select an MDS block to add', 'mds-commerce' ); ?>
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
								<?php esc_html_e( '+ Add Block', 'mds-commerce' ); ?>
							</button>
						</div>

						<div id="mds-wc-block-cards" class="mds-wc-block-cards" aria-live="polite"></div>

						<?php settings_fields( self::OPTION_GROUP ); ?>
						<div class="mds-admin-save-actions mds-wc-form-actions">
							<button id="mds-wc-site-save-btn" class="button button-primary" type="submit">
								<?php esc_html_e( 'Save Settings', 'mds-commerce' ); ?>
							</button>
							<span id="mds-wc-site-save-status" class="mds-wc-status-message" role="status" aria-live="polite"></span>
						</div>
					</div>

					<aside class="mds-wc-blueprint" aria-label="<?php esc_attr_e( 'Interactive Product Page Placement Blueprint', 'mds-commerce' ); ?>">
						<div class="mds-wc-blueprint__bar">
							<span class="mds-wc-blueprint__dots" aria-hidden="true"><span></span><span></span><span></span></span>
							<span class="mds-wc-blueprint__label"><?php esc_html_e( 'Single Product Layout Blueprint', 'mds-commerce' ); ?></span>
							<span class="mds-wc-blueprint__hint"><?php esc_html_e( 'Drag & drop blocks between slots', 'mds-commerce' ); ?></span>
						</div>

						<div class="mds-wc-blueprint__canvas">
							<div class="mds-wc-blueprint__top">
								<div class="mds-wc-wire-gallery-col">
									<div class="mds-wc-wire-gallery" aria-hidden="true">
										<span class="dashicons dashicons-format-image"></span>
										<span><?php esc_html_e( 'Product Gallery', 'mds-commerce' ); ?></span>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_before_single_product_summary:25" data-slot-label="<?php esc_attr_e( 'Below Product Gallery', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>
								</div>

								<div class="mds-wc-wire-summary">
									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:5" data-slot-label="<?php esc_attr_e( 'Above Product Title', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--title" aria-hidden="true">
										<?php esc_html_e( 'Product Title', 'mds-commerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:6" data-slot-label="<?php esc_attr_e( 'Below Product Title', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--price" aria-hidden="true">
										<?php esc_html_e( '$19.99', 'mds-commerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:15" data-slot-label="<?php esc_attr_e( 'Below Price', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--excerpt" aria-hidden="true">
										<span><?php esc_html_e( 'Short Description / Excerpt', 'mds-commerce' ); ?></span>
										<span class="mds-wc-wire-line"></span>
										<span class="mds-wc-wire-line is-short"></span>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:25" data-slot-label="<?php esc_attr_e( 'Below Short Description / Excerpt', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--cart" aria-hidden="true">
										<?php esc_html_e( 'Add to Cart', 'mds-commerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:45" data-slot-label="<?php esc_attr_e( 'Below Add to Cart', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--meta" aria-hidden="true">
										<?php esc_html_e( 'SKU · Categories · Tags', 'mds-commerce' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:10" data-slot-label="<?php esc_attr_e( 'Below Product Summary', 'mds-commerce' ); ?>">
										<div class="mds-wc-slot__chips"></div>
									</div>
								</div>
							</div>

							<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:12" data-slot-label="<?php esc_attr_e( 'Above Product Description / Tabs', 'mds-commerce' ); ?>">
								<div class="mds-wc-slot__chips"></div>
							</div>

							<div class="mds-wc-wire-block mds-wc-wire-block--tabs" aria-hidden="true">
								<strong><?php esc_html_e( 'Product Description / Tabs', 'mds-commerce' ); ?></strong>
								<span class="mds-wc-wire-line"></span>
								<span class="mds-wc-wire-line is-short"></span>
							</div>

							<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:15" data-slot-label="<?php esc_attr_e( 'Below Product Description / Tabs', 'mds-commerce' ); ?>">
								<div class="mds-wc-slot__chips"></div>
							</div>

							<div class="mds-wc-slot mds-wc-slot--disabled-tray" data-slot="disabled" data-slot-label="<?php esc_attr_e( 'Disable Block', 'mds-commerce' ); ?>">
								<span class="mds-wc-slot__tray-label"><?php esc_html_e( 'Inactive / Disabled', 'mds-commerce' ); ?></span>
								<div class="mds-wc-slot__chips"></div>
							</div>
						</div>
					</aside>
				</div>

				<details class="mds-wc-live-preview" id="mds-wc-live-preview">
					<summary class="mds-wc-live-preview__summary">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Live Preview', 'mds-commerce' ); ?></span>
						<span class="mds-wc-live-preview__hint">
							<?php esc_html_e( 'Inspect unsaved placement changes on a real product page', 'mds-commerce' ); ?>
						</span>
					</summary>

					<div class="mds-wc-live-preview__body">
						<?php if ( empty( $preview_products ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'No published WooCommerce products were found on this site to preview.', 'mds-commerce' ); ?>
							</p>
						<?php else : ?>
							<div class="mds-wc-live-preview__toolbar">
								<div class="mds-wc-live-preview__product-picker">
									<label for="mds-wc-preview-product"><?php esc_html_e( 'Preview Product:', 'mds-commerce' ); ?></label>
									<select id="mds-wc-preview-product">
										<?php foreach ( $preview_products as $product ) : ?>
											<option value="<?php echo esc_url( $product['url'] ); ?>">
												<?php echo esc_html( $product['title'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="mds-wc-live-preview__viewports" role="group" aria-label="<?php esc_attr_e( 'Preview Viewport Width', 'mds-commerce' ); ?>">
									<button type="button" class="button button-secondary is-active" data-viewport="desktop" aria-pressed="true">
										<?php esc_html_e( 'Desktop', 'mds-commerce' ); ?>
									</button>
									<button type="button" class="button button-secondary" data-viewport="tablet" aria-pressed="false">
										<?php esc_html_e( 'Tablet', 'mds-commerce' ); ?>
									</button>
									<button type="button" class="button button-secondary" data-viewport="mobile" aria-pressed="false">
										<?php esc_html_e( 'Mobile', 'mds-commerce' ); ?>
									</button>
								</div>

								<div class="mds-wc-live-preview__actions">
									<button type="button" class="button button-secondary" id="mds-wc-preview-refresh">
										<?php esc_html_e( 'Refresh', 'mds-commerce' ); ?>
									</button>
									<a
										href="<?php echo esc_url( $preview_products[0]['url'] ); ?>"
										id="mds-wc-preview-open-tab"
										class="button button-secondary"
										target="_blank"
										rel="noopener noreferrer"
									>
										<?php esc_html_e( 'Open in New Tab', 'mds-commerce' ); ?>
										<span class="dashicons dashicons-external" aria-hidden="true"></span>
									</a>
								</div>
							</div>

							<div class="mds-wc-live-preview__stage is-desktop" id="mds-wc-preview-stage">
								<iframe
									id="mds-wc-preview-iframe"
									class="mds-wc-live-preview__iframe"
									title="<?php esc_attr_e( 'Live WooCommerce Product Page Preview', 'mds-commerce' ); ?>"
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
