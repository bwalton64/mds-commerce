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

defined( 'ABSPATH' ) || exit;

/**
 * Manages the WooCommerce Integration tab in Releases -> Settings for product view injection.
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
	 * Register site settings hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_filter( 'mds_rm_site_settings_tabs', array( self::class, 'register_tab' ) );
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
			'woocommerce_single_product_summary:5'        => __( 'Above Product Title', 'mds-woocommerce-integration' ),
			'woocommerce_single_product_summary:15'       => __( 'Below Price', 'mds-woocommerce-integration' ),
			'woocommerce_single_product_summary:25'       => __( 'Below Short Description / Excerpt', 'mds-woocommerce-integration' ),
			'woocommerce_single_product_summary:45'       => __( 'Below Add to Cart', 'mds-woocommerce-integration' ),
			'woocommerce_after_single_product_summary:10' => __( 'Below Product Summary', 'mds-woocommerce-integration' ),
			'woocommerce_after_single_product_summary:15' => __( 'Below Product Description / Tabs', 'mds-woocommerce-integration' ),
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
				'disabled' => __( 'Disabled', 'mds-woocommerce-integration' ),
			)
		);
	}

	/**
	 * Return normalized per-site product injection settings, honoring authorized Live Preview overrides.
	 *
	 * @since 1.0.1
	 *
	 * @return array{
	 *     release_meta_placement: string,
	 *     release_links_placement: string,
	 *     hide_buy_now: bool,
	 *     custom_placement: string,
	 *     custom_content: string
	 * }
	 */
	public static function get_settings(): array {
		$legacy_meta_placement = get_theme_mod( 'mds_rm_woo_injection', 'woocommerce_single_product_summary:25' );
		$choices               = self::get_placement_choices();

		if ( ! is_string( $legacy_meta_placement ) || ! array_key_exists( $legacy_meta_placement, $choices ) ) {
			$legacy_meta_placement = 'woocommerce_single_product_summary:25';
		}

		$defaults = array(
			'release_meta_placement'  => $legacy_meta_placement,
			'release_links_placement' => 'woocommerce_after_single_product_summary:15',
			'hide_buy_now'            => false,
			'custom_placement'        => 'disabled',
			'custom_content'          => '',
		);

		$stored   = get_option( self::OPTION_KEY, null );
		$settings = is_array( $stored ) ? self::sanitize_settings( array_merge( $defaults, $stored ) ) : $defaults;

		return self::apply_preview_overrides( $settings );
	}

	/**
	 * Apply signed, allowlisted Live Preview placement overrides for administrators viewing the preview iframe.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $settings Base saved settings.
	 * @return array{
	 *     release_meta_placement: string,
	 *     release_links_placement: string,
	 *     hide_buy_now: bool,
	 *     custom_placement: string,
	 *     custom_content: string
	 * } Settings with any authorized preview overrides applied.
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

		$choices = self::get_placement_choices();

		foreach ( array( 'release_meta_placement', 'release_links_placement', 'custom_placement' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) {
				$candidate = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
				if ( array_key_exists( $candidate, $choices ) ) {
					$settings[ $key ] = $candidate;
				}
			}
		}

		if ( isset( $_GET['hide_buy_now'] ) ) {
			$settings['hide_buy_now'] = '1' === sanitize_text_field( wp_unslash( (string) $_GET['hide_buy_now'] ) );
		}

		return $settings;
	}

	/**
	 * Sanitize submitted product injection settings.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $raw Submitted settings array.
	 * @return array{
	 *     release_meta_placement: string,
	 *     release_links_placement: string,
	 *     hide_buy_now: bool,
	 *     custom_placement: string,
	 *     custom_content: string
	 * }
	 */
	public static function sanitize_settings( mixed $raw ): array {
		$choices = self::get_placement_choices();
		$input   = is_array( $raw ) ? $raw : array();

		$meta_placement  = isset( $input['release_meta_placement'] ) && is_string( $input['release_meta_placement'] ) && array_key_exists( $input['release_meta_placement'], $choices )
			? $input['release_meta_placement']
			: 'woocommerce_single_product_summary:25';
		$links_placement = isset( $input['release_links_placement'] ) && is_string( $input['release_links_placement'] ) && array_key_exists( $input['release_links_placement'], $choices )
			? $input['release_links_placement']
			: 'woocommerce_after_single_product_summary:15';
		$custom_place    = isset( $input['custom_placement'] ) && is_string( $input['custom_placement'] ) && array_key_exists( $input['custom_placement'], $choices )
			? $input['custom_placement']
			: 'disabled';
		$hide_buy_now    = ! empty( $input['hide_buy_now'] );
		$custom_content  = isset( $input['custom_content'] ) && is_string( $input['custom_content'] )
			? trim( wp_kses_post( $input['custom_content'] ) )
			: '';

		return array(
			'release_meta_placement'  => $meta_placement,
			'release_links_placement' => $links_placement,
			'hide_buy_now'            => $hide_buy_now,
			'custom_placement'        => $custom_place,
			'custom_content'          => $custom_content,
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
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 25,
				'orderby'        => 'date',
				'order'          => 'DESC',
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
		if ( ! Plugin::is_active() ) {
			return $tabs;
		}

		$tabs['woocommerce'] = array(
			'label'  => __( 'WooCommerce Integration', 'mds-woocommerce-integration' ),
			'render' => array( self::class, 'render_tab' ),
		);

		return $tabs;
	}

	/**
	 * Render a single component placement card with Enable toggle and Up/Down stepper controls.
	 *
	 * @since 1.0.1
	 *
	 * @param string $component_key Component identifier ('meta', 'links', or 'custom').
	 * @param string $input_id      Hidden input ID.
	 * @param string $field_name    Option array key name.
	 * @param string $current_slot  Current placement value.
	 * @param string $code          Short 2-letter badge code ('RM', 'RL', 'CS').
	 * @param string $title         Human-readable component title.
	 * @param string $shortcode     Shortcode label shown in code tag.
	 * @param string $description   Component description text.
	 * @param string $extra_html    Optional extra controls HTML rendered at the bottom of the card.
	 * @return void
	 */
	private static function render_control_card(
		string $component_key,
		string $input_id,
		string $field_name,
		string $current_slot,
		string $code,
		string $title,
		string $shortcode,
		string $description,
		string $extra_html = ''
	): void {
		$choices    = self::get_placement_choices();
		$is_enabled = 'disabled' !== $current_slot;
		$slot_label = $choices[ $current_slot ] ?? __( 'Disabled', 'mds-woocommerce-integration' );
		$toggle_id  = $input_id . '-enabled';
		?>
		<div
			class="mds-wc-control-card mds-wc-control-card--<?php echo esc_attr( $component_key ); ?> <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>"
			data-component-card="<?php echo esc_attr( $component_key ); ?>"
		>
			<div class="mds-wc-control-card__top">
				<div class="mds-wc-control-card__head">
					<span class="mds-wc-pill mds-wc-pill--<?php echo esc_attr( $component_key ); ?>" aria-hidden="true">
						<?php echo esc_html( $code ); ?>
					</span>
					<div>
						<span class="mds-wc-control-card__title"><?php echo esc_html( $title ); ?></span>
						<code class="mds-wc-shortcode-tag"><?php echo esc_html( $shortcode ); ?></code>
					</div>
				</div>

				<label class="mds-wc-enable-toggle" for="<?php echo esc_attr( $toggle_id ); ?>">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $toggle_id ); ?>"
						class="mds-wc-component-toggle"
						data-component="<?php echo esc_attr( $component_key ); ?>"
						<?php checked( $is_enabled ); ?>
					/>
					<span><?php esc_html_e( 'Enabled', 'mds-woocommerce-integration' ); ?></span>
				</label>
			</div>

			<input
				type="hidden"
				id="<?php echo esc_attr( $input_id ); ?>"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_name ); ?>]"
				value="<?php echo esc_attr( $current_slot ); ?>"
				data-component-value="<?php echo esc_attr( $component_key ); ?>"
			/>

			<div class="mds-wc-stepper" role="group" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Component name. */ __( '%s placement position', 'mds-woocommerce-integration' ), $title ) ); ?>">
				<span class="mds-wc-stepper__position" data-component-label="<?php echo esc_attr( $component_key ); ?>">
					<?php echo esc_html( $slot_label ); ?>
				</span>
				<div class="mds-wc-stepper__buttons">
					<button
						type="button"
						class="button button-secondary mds-wc-step-btn"
						data-component="<?php echo esc_attr( $component_key ); ?>"
						data-direction="up"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Component name. */ __( 'Move %s up one slot', 'mds-woocommerce-integration' ), $title ) ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
						<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
					</button>
					<button
						type="button"
						class="button button-secondary mds-wc-step-btn"
						data-component="<?php echo esc_attr( $component_key ); ?>"
						data-direction="down"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Component name. */ __( 'Move %s down one slot', 'mds-woocommerce-integration' ), $title ) ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
						<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<p class="description"><?php echo esc_html( $description ); ?></p>

			<?php
			if ( '' !== $extra_html ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Extra HTML is escaped by the caller.
				echo $extra_html;
			}
			?>
		</div>
		<?php
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
			wp_die( esc_html__( 'You are not allowed to manage WooCommerce integration settings.', 'mds-woocommerce-integration' ) );
		}

		$settings         = self::get_settings();
		$preview_products = self::get_preview_products();

		ob_start();
		?>
		<label class="mds-wc-inline-toggle" for="mds-wc-hide-buy-now">
			<input
				type="checkbox"
				id="mds-wc-hide-buy-now"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hide_buy_now]"
				value="1"
				<?php checked( $settings['hide_buy_now'] ); ?>
			/>
			<span><?php esc_html_e( 'Hide Buy Now icon on product pages', 'mds-woocommerce-integration' ); ?></span>
		</label>
		<?php
		$links_extra_html = (string) ob_get_clean();

		ob_start();
		?>
		<label for="mds-wc-custom-content" class="screen-reader-text">
			<?php esc_html_e( 'Custom Shortcode or Block Markup', 'mds-woocommerce-integration' ); ?>
		</label>
		<textarea
			id="mds-wc-custom-content"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_content]"
			class="large-text code mds-wc-custom-textarea"
			rows="3"
			placeholder="[release-links show_audio=&quot;1&quot;]"
		><?php echo esc_textarea( $settings['custom_content'] ); ?></textarea>
		<?php
		$custom_extra_html = (string) ob_get_clean();
		?>
		<form id="mds-wc-site-settings-form" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<div class="mds-panel mds-wc-designer-panel">
				<div class="mds-wc-designer-header">
					<div>
						<h2><?php esc_html_e( 'Single Product Page Injection', 'mds-woocommerce-integration' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Drag chips directly onto slots in the Single Product Blueprint or use the Up / Down controls to position each component. Works on both Block Themes and Classic WooCommerce themes.', 'mds-woocommerce-integration' ); ?>
						</p>
					</div>
				</div>

				<div class="mds-wc-designer-grid">
					<div class="mds-wc-designer-controls">
						<?php
						self::render_control_card(
							'meta',
							'mds-wc-release-meta-placement',
							'release_meta_placement',
							$settings['release_meta_placement'],
							'RM',
							__( 'Release Meta', 'mds-woocommerce-integration' ),
							'[release-meta]',
							__( 'Year, label, distributor, UPC/EAN, and format resolved from the product GTIN/UPC or SKU.', 'mds-woocommerce-integration' )
						);

						self::render_control_card(
							'links',
							'mds-wc-release-links-placement',
							'release_links_placement',
							$settings['release_links_placement'],
							'RL',
							__( 'Release Links', 'mds-woocommerce-integration' ),
							'[release-links]',
							__( 'Streaming links and the configured audio preview player for the linked release.', 'mds-woocommerce-integration' ),
							$links_extra_html
						);

						self::render_control_card(
							'custom',
							'mds-wc-custom-placement',
							'custom_placement',
							$settings['custom_placement'],
							'CS',
							__( 'Custom Shortcode / Block', 'mds-woocommerce-integration' ),
							'[custom]',
							__( 'Optional shortcode or serialized Gutenberg block markup rendered at the selected slot.', 'mds-woocommerce-integration' ),
							$custom_extra_html
						);
						?>

						<?php settings_fields( self::OPTION_GROUP ); ?>
						<div class="mds-admin-save-actions mds-wc-form-actions">
							<button id="mds-wc-site-save-btn" class="button button-primary" type="submit">
								<?php esc_html_e( 'Save Settings', 'mds-woocommerce-integration' ); ?>
							</button>
							<span id="mds-wc-site-save-status" class="mds-wc-status-message" role="status" aria-live="polite"></span>
						</div>
					</div>

					<aside class="mds-wc-blueprint" aria-label="<?php esc_attr_e( 'Interactive Product Page Placement Blueprint', 'mds-woocommerce-integration' ); ?>">
						<div class="mds-wc-blueprint__bar">
							<span class="mds-wc-blueprint__dots" aria-hidden="true"><span></span><span></span><span></span></span>
							<span class="mds-wc-blueprint__label"><?php esc_html_e( 'Single Product Layout Blueprint', 'mds-woocommerce-integration' ); ?></span>
							<span class="mds-wc-blueprint__hint"><?php esc_html_e( 'Drag & drop chips between slots', 'mds-woocommerce-integration' ); ?></span>
						</div>

						<div class="mds-wc-blueprint__canvas">
							<div class="mds-wc-blueprint__top">
								<div class="mds-wc-wire-gallery" aria-hidden="true">
									<span class="dashicons dashicons-format-image"></span>
									<span><?php esc_html_e( 'Product Gallery', 'mds-woocommerce-integration' ); ?></span>
								</div>

								<div class="mds-wc-wire-summary">
									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:5">
										<span class="mds-wc-slot__label"><?php esc_html_e( 'Above Product Title', 'mds-woocommerce-integration' ); ?></span>
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--title" aria-hidden="true">
										<?php esc_html_e( 'Product Title', 'mds-woocommerce-integration' ); ?>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--price" aria-hidden="true">
										<?php esc_html_e( '$19.99', 'mds-woocommerce-integration' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:15">
										<span class="mds-wc-slot__label"><?php esc_html_e( 'Below Price', 'mds-woocommerce-integration' ); ?></span>
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--excerpt" aria-hidden="true">
										<span><?php esc_html_e( 'Short Description / Excerpt', 'mds-woocommerce-integration' ); ?></span>
										<span class="mds-wc-wire-line"></span>
										<span class="mds-wc-wire-line is-short"></span>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:25">
										<span class="mds-wc-slot__label"><?php esc_html_e( 'Below Short Description / Excerpt', 'mds-woocommerce-integration' ); ?></span>
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--cart" aria-hidden="true">
										<?php esc_html_e( 'Add to Cart', 'mds-woocommerce-integration' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_single_product_summary:45">
										<span class="mds-wc-slot__label"><?php esc_html_e( 'Below Add to Cart', 'mds-woocommerce-integration' ); ?></span>
										<div class="mds-wc-slot__chips"></div>
									</div>

									<div class="mds-wc-wire-block mds-wc-wire-block--meta" aria-hidden="true">
										<?php esc_html_e( 'SKU · Categories · Tags', 'mds-woocommerce-integration' ); ?>
									</div>

									<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:10">
										<span class="mds-wc-slot__label"><?php esc_html_e( 'Below Product Summary', 'mds-woocommerce-integration' ); ?></span>
										<div class="mds-wc-slot__chips"></div>
									</div>
								</div>
							</div>

							<div class="mds-wc-wire-block mds-wc-wire-block--tabs" aria-hidden="true">
								<strong><?php esc_html_e( 'Product Description / Tabs', 'mds-woocommerce-integration' ); ?></strong>
								<span class="mds-wc-wire-line"></span>
								<span class="mds-wc-wire-line is-short"></span>
							</div>

							<div class="mds-wc-slot" data-slot="woocommerce_after_single_product_summary:15">
								<span class="mds-wc-slot__label"><?php esc_html_e( 'Below Product Description / Tabs', 'mds-woocommerce-integration' ); ?></span>
								<div class="mds-wc-slot__chips"></div>
							</div>

							<div class="mds-wc-slot mds-wc-slot--disabled-tray" data-slot="disabled">
								<span class="mds-wc-slot__label"><?php esc_html_e( 'Inactive / Disabled (drag onto any slot above to enable)', 'mds-woocommerce-integration' ); ?></span>
								<div class="mds-wc-slot__chips"></div>
							</div>
						</div>
					</aside>
				</div>

				<details class="mds-wc-live-preview" id="mds-wc-live-preview">
					<summary class="mds-wc-live-preview__summary">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Live Preview', 'mds-woocommerce-integration' ); ?></span>
						<span class="mds-wc-live-preview__hint">
							<?php esc_html_e( 'Inspect unsaved placement changes on a real product page', 'mds-woocommerce-integration' ); ?>
						</span>
					</summary>

					<div class="mds-wc-live-preview__body">
						<?php if ( empty( $preview_products ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'No published WooCommerce products were found on this site to preview.', 'mds-woocommerce-integration' ); ?>
							</p>
						<?php else : ?>
							<div class="mds-wc-live-preview__toolbar">
								<div class="mds-wc-live-preview__product-picker">
									<label for="mds-wc-preview-product"><?php esc_html_e( 'Preview Product:', 'mds-woocommerce-integration' ); ?></label>
									<select id="mds-wc-preview-product">
										<?php foreach ( $preview_products as $product ) : ?>
											<option value="<?php echo esc_url( $product['url'] ); ?>">
												<?php echo esc_html( $product['title'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="mds-wc-live-preview__viewports" role="group" aria-label="<?php esc_attr_e( 'Preview Viewport Width', 'mds-woocommerce-integration' ); ?>">
									<button type="button" class="button button-secondary is-active" data-viewport="desktop" aria-pressed="true">
										<?php esc_html_e( 'Desktop', 'mds-woocommerce-integration' ); ?>
									</button>
									<button type="button" class="button button-secondary" data-viewport="tablet" aria-pressed="false">
										<?php esc_html_e( 'Tablet', 'mds-woocommerce-integration' ); ?>
									</button>
									<button type="button" class="button button-secondary" data-viewport="mobile" aria-pressed="false">
										<?php esc_html_e( 'Mobile', 'mds-woocommerce-integration' ); ?>
									</button>
								</div>

								<div class="mds-wc-live-preview__actions">
									<button type="button" class="button button-secondary" id="mds-wc-preview-refresh">
										<?php esc_html_e( 'Refresh', 'mds-woocommerce-integration' ); ?>
									</button>
									<a
										href="<?php echo esc_url( $preview_products[0]['url'] ); ?>"
										id="mds-wc-preview-open-tab"
										class="button button-secondary"
										target="_blank"
										rel="noopener noreferrer"
									>
										<?php esc_html_e( 'Open in New Tab', 'mds-woocommerce-integration' ); ?>
										<span class="dashicons dashicons-external" aria-hidden="true"></span>
									</a>
								</div>
							</div>

							<div class="mds-wc-live-preview__stage is-desktop" id="mds-wc-preview-stage">
								<iframe
									id="mds-wc-preview-iframe"
									class="mds-wc-live-preview__iframe"
									title="<?php esc_attr_e( 'Live WooCommerce Product Page Preview', 'mds-woocommerce-integration' ); ?>"
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
