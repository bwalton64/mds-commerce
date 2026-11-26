<?php
/**
 * Store Chooser Gutenberg Block (`mds-commerce/store-chooser`) and Shortcode (`[mds-store-chooser]`).
 *
 * Groups available storefront and physical POD offers by Media Format (default) or Store,
 * automatically prioritizing stores with active coupons or special offers.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Blocks;

use MDS\Commerce\Config;
use MDS\Commerce\Model\StoreKey;
use MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge;
use MDS\Commerce\Support\StoreRegistry;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the visitor-facing multi-store & physical POD Store Chooser.
 *
 * @since 3.0.0
 */
final class StoreChooser {

	/**
	 * Block identifier.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const BLOCK_NAME = 'mds-commerce/store-chooser';

	/**
	 * Shortcode tag.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const SHORTCODE_TAG = 'mds-store-chooser';

	/**
	 * Register the Gutenberg block and shortcode.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE_TAG, array( self::class, 'render_shortcode' ) );

		$block_dir = MDS_CM_PATH . 'blocks/store-chooser';
		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type(
				$block_dir,
				array(
					'render_callback' => array( self::class, 'render_block' ),
				)
			);
		}
	}

	/**
	 * Shortcode renderer for `[mds-store-chooser]`.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_shortcode( array|string $atts = array() ): string {
		$parsed = shortcode_atts(
			array(
				'release_id'        => 0,
				'group_by'          => 'format',
				'layout'            => 'cards',
				'prioritize_promos' => 'true',
				'heading'           => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE_TAG
		);

		return self::render_block(
			array(
				'releaseId'        => absint( $parsed['release_id'] ),
				'groupBy'          => sanitize_key( (string) $parsed['group_by'] ),
				'layout'           => sanitize_key( (string) $parsed['layout'] ),
				'prioritizePromos' => filter_var( $parsed['prioritize_promos'], FILTER_VALIDATE_BOOLEAN ),
				'heading'          => sanitize_text_field( (string) $parsed['heading'] ),
			)
		);
	}

	/**
	 * Server-side render callback for `mds-commerce/store-chooser`.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_block( array $attributes = array() ): string {
		$release_id        = absint( $attributes['releaseId'] ?? 0 );
		$group_by          = in_array( (string) ( $attributes['groupBy'] ?? 'format' ), array( 'format', 'store', 'none' ), true )
			? (string) $attributes['groupBy']
			: 'format';
		$layout            = in_array( (string) ( $attributes['layout'] ?? 'cards' ), array( 'cards', 'list' ), true )
			? (string) $attributes['layout']
			: 'cards';
		$prioritize_promos = isset( $attributes['prioritizePromos'] ) ? (bool) $attributes['prioritizePromos'] : true;
		$heading           = isset( $attributes['heading'] ) && is_scalar( $attributes['heading'] )
			? sanitize_text_field( (string) $attributes['heading'] )
			: '';

		$release = self::resolve_release_post( $release_id );
		if ( ! $release instanceof WP_Post ) {
			return '';
		}

		$offers = self::collect_offers_for_release( $release );
		if ( empty( $offers ) ) {
			return '';
		}

		$sorted = self::sort_offers( $offers, $prioritize_promos );
		$groups = self::group_offers( $sorted, $group_by );

		self::enqueue_frontend_assets();

		ob_start();
		?>
		<section class="mds-cm-store-chooser mds-cm-layout-<?php echo esc_attr( $layout ); ?>" aria-label="<?php echo esc_attr( '' !== $heading ? $heading : __( 'Where to Buy', 'mds-commerce' ) ); ?>">
			<?php if ( '' !== $heading ) : ?>
				<h3 class="mds-cm-chooser-heading"><?php echo esc_html( $heading ); ?></h3>
			<?php endif; ?>

			<div class="mds-cm-chooser-groups">
				<?php foreach ( $groups as $group_label => $group_offers ) : ?>
					<div class="mds-cm-chooser-group">
						<?php if ( 'none' !== $group_by && '' !== $group_label ) : ?>
							<h4 class="mds-cm-group-title"><?php echo esc_html( $group_label ); ?></h4>
						<?php endif; ?>
						<ul class="mds-cm-offer-list">
							<?php foreach ( $group_offers as $offer ) : ?>
								<?php
								$has_promo   = self::is_promo_active( $offer );
								$promo_badge = $has_promo ? (string) ( $offer['promo_badge'] ?? '' ) : '';
								$coupon_code = $has_promo ? (string) ( $offer['coupon_code'] ?? '' ) : '';
								$cta_label   = ! empty( $offer['button_text'] )
									? (string) $offer['button_text']
									: __( 'Buy Now', 'mds-commerce' );
								$stock_label = self::format_stock_label( (string) ( $offer['stock_status'] ?? 'instock' ) );
								$is_external = ! empty( $offer['is_external'] );
								?>
								<li class="mds-cm-offer-item<?php echo $has_promo ? ' is-promoted' : ''; ?>">
									<div class="mds-cm-offer-main">
										<div class="mds-cm-offer-header">
											<span class="mds-cm-offer-store"><?php echo esc_html( (string) $offer['store_name'] ); ?></span>
											<?php if ( '' !== $promo_badge ) : ?>
												<span class="mds-cm-badge mds-cm-badge-promo"><?php echo esc_html( $promo_badge ); ?></span>
											<?php endif; ?>
											<?php if ( '' !== $stock_label ) : ?>
												<span class="mds-cm-badge mds-cm-badge-stock mds-cm-stock-<?php echo esc_attr( sanitize_html_class( (string) ( $offer['stock_status'] ?? 'instock' ) ) ); ?>">
													<?php echo esc_html( $stock_label ); ?>
												</span>
											<?php endif; ?>
										</div>

										<div class="mds-cm-offer-meta">
											<?php if ( 'format' !== $group_by && ! empty( $offer['format'] ) ) : ?>
												<span class="mds-cm-offer-format"><?php echo esc_html( (string) $offer['format'] ); ?></span>
											<?php endif; ?>
											<?php if ( ! empty( $offer['price'] ) ) : ?>
												<span class="mds-cm-offer-price"><?php echo esc_html( (string) $offer['price'] ); ?></span>
											<?php endif; ?>
										</div>

										<?php if ( '' !== $coupon_code ) : ?>
											<div class="mds-cm-coupon-row">
												<span class="mds-cm-coupon-label"><?php esc_html_e( 'Coupon:', 'mds-commerce' ); ?></span>
												<code class="mds-cm-coupon-code"><?php echo esc_html( $coupon_code ); ?></code>
												<button
													type="button"
													class="mds-cm-copy-coupon"
													data-coupon="<?php echo esc_attr( $coupon_code ); ?>"
													aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Coupon code. */ __( 'Copy coupon code %s', 'mds-commerce' ), $coupon_code ) ); ?>"
												>
													<?php esc_html_e( 'Copy', 'mds-commerce' ); ?>
												</button>
											</div>
										<?php endif; ?>
									</div>

									<div class="mds-cm-offer-action">
										<a
											class="mds-cm-offer-cta"
											href="<?php echo esc_url( (string) $offer['url'] ); ?>"
											<?php echo $is_external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
										>
											<?php echo esc_html( $cta_label ); ?>
										</a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="mds-cm-sr-status screen-reader-text" role="status" aria-live="polite"></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Sort normalized offers so active coupons/promos, priority weight, and stock rank first.
	 *
	 * @since 3.0.0
	 *
	 * @param array<int, array<string, mixed>> $offers            Raw offers list.
	 * @param bool                             $prioritize_promos Whether active coupons/promos float to the top.
	 * @param int|null                         $now_timestamp     Optional Unix timestamp for deterministic testing.
	 * @return array<int, array<string, mixed>> Sorted offers.
	 */
	public static function sort_offers( array $offers, bool $prioritize_promos = true, ?int $now_timestamp = null ): array {
		$now = $now_timestamp ?? time();

		usort(
			$offers,
			static function ( array $a, array $b ) use ( $prioritize_promos, $now ): int {
				if ( $prioritize_promos ) {
					$a_promo = self::is_promo_active( $a, $now ) ? 1 : 0;
					$b_promo = self::is_promo_active( $b, $now ) ? 1 : 0;
					if ( $a_promo !== $b_promo ) {
						return $b_promo <=> $a_promo;
					}
				}

				$a_weight = (int) ( $a['priority_weight'] ?? 0 );
				$b_weight = (int) ( $b['priority_weight'] ?? 0 );
				if ( $a_weight !== $b_weight ) {
					return $b_weight <=> $a_weight;
				}

				$a_stock = 'outofstock' === (string) ( $a['stock_status'] ?? 'instock' ) ? 0 : 1;
				$b_stock = 'outofstock' === (string) ( $b['stock_status'] ?? 'instock' ) ? 0 : 1;
				if ( $a_stock !== $b_stock ) {
					return $b_stock <=> $a_stock;
				}

				return strcasecmp( (string) ( $a['store_name'] ?? '' ), (string) ( $b['store_name'] ?? '' ) );
			}
		);

		return $offers;
	}

	/**
	 * Check whether an offer has a non-expired coupon code or promo badge.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $offer         Offer data array.
	 * @param int|null             $now_timestamp Optional Unix timestamp for deterministic testing.
	 * @return bool True when promo or coupon is active.
	 */
	public static function is_promo_active( array $offer, ?int $now_timestamp = null ): bool {
		$badge  = trim( (string) ( $offer['promo_badge'] ?? '' ) );
		$coupon = trim( (string) ( $offer['coupon_code'] ?? '' ) );

		if ( '' === $badge && '' === $coupon ) {
			return false;
		}

		$expires = trim( (string) ( $offer['promo_expires_gmt'] ?? '' ) );
		if ( '' === $expires ) {
			return true;
		}

		$expiry_ts = strtotime( $expires );
		if ( false === $expiry_ts ) {
			return true;
		}

		return $expiry_ts >= ( $now_timestamp ?? time() );
	}

	/**
	 * Group sorted offers by format, store, or flat list.
	 *
	 * @since 3.0.0
	 *
	 * @param array<int, array<string, mixed>> $offers   Sorted offers.
	 * @param string                           $group_by Grouping mode (`format`, `store`, `none`).
	 * @return array<string, array<int, array<string, mixed>>> Grouped offers.
	 */
	public static function group_offers( array $offers, string $group_by = 'format' ): array {
		if ( 'none' === $group_by ) {
			return array( '' => $offers );
		}

		$groups = array();
		foreach ( $offers as $offer ) {
			if ( 'store' === $group_by ) {
				$label = (string) ( $offer['store_name'] ?? __( 'Store', 'mds-commerce' ) );
			} else {
				$label = trim( (string) ( $offer['format'] ?? '' ) );
				if ( '' === $label ) {
					$label = __( 'Release Edition', 'mds-commerce' );
				}
			}

			$groups[ $label ][] = $offer;
		}

		return $groups;
	}

	/**
	 * Collect all storefront and external POD offers linked to a Release post.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post $release Release post object.
	 * @return array<int, array<string, mixed>> Normalized offer items.
	 */
	private static function collect_offers_for_release( WP_Post $release ): array {
		$release_id = (int) $release->ID;
		$offers     = array();

		$commerce_links = get_post_meta( $release_id, Config::RELEASE_LINKS_META_KEY, true );
		if ( ! is_array( $commerce_links ) || empty( $commerce_links ) ) {
			$legacy = get_post_meta( $release_id, Config::LEGACY_RELEASE_LINKS_META_KEY, true );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$commerce_links = \MDS\Commerce\Sync\UpgradeRoutine::convert_legacy_release_links( $legacy );
			} else {
				$commerce_links = array();
			}
		}

		$available_stores = StoreRegistry::get_available_stores();

		foreach ( $commerce_links as $raw_key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$store_key = StoreKey::parse( (string) ( $entry['store_key'] ?? $raw_key ) );
			if ( ! $store_key instanceof StoreKey ) {
				continue;
			}

			$blog_id    = $store_key->local_blog_id();
			$product_id = absint( $entry['remote_id'] ?? 0 );

			if ( 'wc_local' === $store_key->provider() && $blog_id > 0 && $product_id > 0 ) {
				$store_name = isset( $available_stores[ $blog_id ]['site_name'] )
					? (string) $available_stores[ $blog_id ]['site_name']
					: get_bloginfo( 'name' );

				$wc_offers = self::extract_local_wc_offers( $blog_id, $product_id, $store_name, $entry );
				foreach ( $wc_offers as $wc_offer ) {
					$offers[] = $wc_offer;
				}
				continue;
			}

			$url = esc_url_raw( (string) ( $entry['url'] ?? $entry['permalink'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}

			$offers[] = array(
				'store_key'         => $store_key->to_string(),
				'store_name'        => (string) ( $entry['store_name'] ?? ucfirst( $store_key->provider() ) ),
				'format'            => (string) ( $entry['format'] ?? '' ),
				'price'             => (string) ( $entry['price'] ?? '' ),
				'stock_status'      => (string) ( $entry['stock_status'] ?? 'instock' ),
				'url'               => $url,
				'button_text'       => (string) ( $entry['button_text'] ?? '' ),
				'is_external'       => true,
				'promo_badge'       => (string) ( $entry['promo_badge'] ?? '' ),
				'coupon_code'       => (string) ( $entry['coupon_code'] ?? '' ),
				'promo_expires_gmt' => (string) ( $entry['promo_expires_gmt'] ?? '' ),
				'priority_weight'   => (int) ( $entry['priority_weight'] ?? 0 ),
			);
		}

		/**
		 * Filters the normalized offers displayed by the MDS Commerce Store Chooser block.
		 *
		 * @since 3.0.0
		 *
		 * @param array<int, array<string, mixed>> $offers  Normalized offers.
		 * @param WP_Post                          $release Release post object.
		 */
		return (array) apply_filters( 'mds_cm_store_chooser_offers', $offers, $release );
	}

	/**
	 * Extract offers from a local WooCommerce product (including WPC External Variations when present).
	 *
	 * @since 3.0.0
	 *
	 * @param int                  $blog_id    WooCommerce blog ID.
	 * @param int                  $product_id WooCommerce product ID.
	 * @param string               $store_name Store display name.
	 * @param array<string, mixed> $link_meta  Saved link promo/priority metadata.
	 * @return array<int, array<string, mixed>> Extracted offers.
	 */
	private static function extract_local_wc_offers( int $blog_id, int $product_id, string $store_name, array $link_meta ): array {
		$switched = false;
		if ( is_multisite() && (int) get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		try {
			if ( ! function_exists( 'wc_get_product' ) ) {
				return array();
			}

			$product = wc_get_product( $product_id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				return array();
			}

			$offers = array();

			if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_children' ) ) {
				foreach ( (array) $product->get_children() as $variation_id ) {
					$variation_id = absint( $variation_id );
					$variation    = wc_get_product( $variation_id );
					if ( ! $variation ) {
						continue;
					}

					$ext_link   = ExternalVariationsBridge::get_variation_link( $variation_id );
					$is_ext     = '' !== $ext_link['url'];
					$attributes = method_exists( $variation, 'get_variation_attributes' ) ? (array) $variation->get_variation_attributes() : array();
					$format     = implode( ' / ', array_filter( array_map( 'strval', $attributes ) ) );

					$offers[] = array(
						'store_key'         => StoreKey::for_local_wc( $blog_id )->to_string(),
						'store_name'        => $store_name,
						'format'            => '' !== $format ? $format : (string) ( $link_meta['format'] ?? '' ),
						'price'             => wp_strip_all_tags( (string) $variation->get_price_html() ),
						'stock_status'      => $is_ext ? 'ondemand' : (string) $variation->get_stock_status(),
						'url'               => $is_ext ? $ext_link['url'] : (string) $variation->get_permalink(),
						'button_text'       => $is_ext ? $ext_link['text'] : '',
						'is_external'       => $is_ext,
						'promo_badge'       => (string) ( $link_meta['promo_badge'] ?? '' ),
						'coupon_code'       => (string) ( $link_meta['coupon_code'] ?? '' ),
						'promo_expires_gmt' => (string) ( $link_meta['promo_expires_gmt'] ?? '' ),
						'priority_weight'   => (int) ( $link_meta['priority_weight'] ?? 0 ),
					);
				}

				if ( ! empty( $offers ) ) {
					return $offers;
				}
			}

			$offers[] = array(
				'store_key'         => StoreKey::for_local_wc( $blog_id )->to_string(),
				'store_name'        => $store_name,
				'format'            => (string) ( $link_meta['format'] ?? '' ),
				'price'             => wp_strip_all_tags( (string) $product->get_price_html() ),
				'stock_status'      => (string) $product->get_stock_status(),
				'url'               => (string) $product->get_permalink(),
				'button_text'       => '',
				'is_external'       => false,
				'promo_badge'       => (string) ( $link_meta['promo_badge'] ?? '' ),
				'coupon_code'       => (string) ( $link_meta['coupon_code'] ?? '' ),
				'promo_expires_gmt' => (string) ( $link_meta['promo_expires_gmt'] ?? '' ),
				'priority_weight'   => (int) ( $link_meta['priority_weight'] ?? 0 ),
			);

			return $offers;
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Resolve target Release post from explicit ID or current loop/context filter.
	 *
	 * @since 3.0.0
	 *
	 * @param int $release_id Explicit Release ID (0 to resolve from context).
	 * @return WP_Post|null Resolved Release post or null.
	 */
	private static function resolve_release_post( int $release_id ): ?WP_Post {
		if ( $release_id > 0 ) {
			$post = get_post( $release_id );
			return ( $post instanceof WP_Post && 'release' === $post->post_type ) ? $post : null;
		}

		$current = get_post();
		if ( $current instanceof WP_Post && 'release' === $current->post_type ) {
			return $current;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core Release Manager context filter.
		$context = apply_filters( 'mds_rm_release_context_post', null, $current );
		return ( $context instanceof WP_Post && 'release' === $context->post_type ) ? $context : null;
	}

	/**
	 * Convert stock status slug into a visitor-friendly badge label.
	 *
	 * @since 3.0.0
	 *
	 * @param string $status Stock status key.
	 * @return string Translated label.
	 */
	private static function format_stock_label( string $status ): string {
		return match ( $status ) {
			'instock'     => __( 'In Stock', 'mds-commerce' ),
			'outofstock'  => __( 'Sold Out', 'mds-commerce' ),
			'onbackorder' => __( 'Pre-Order', 'mds-commerce' ),
			'ondemand'    => __( 'Made on Demand', 'mds-commerce' ),
			default       => '',
		};
	}

	/**
	 * Enqueue frontend Store Chooser styles and coupon-copy script on demand.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private static function enqueue_frontend_assets(): void {
		$style_path = file_exists( MDS_CM_PATH . 'build/style.css' ) ? 'build/style.css' : 'blocks/store-chooser/style.css';
		$view_path  = file_exists( MDS_CM_PATH . 'build/view.js' ) ? 'build/view.js' : 'blocks/store-chooser/view.js';

		wp_enqueue_style( 'mds_cm_store_chooser', MDS_CM_URL . $style_path, array(), MDS_CM_VERSION );
		wp_enqueue_script( 'mds_cm_store_chooser_view', MDS_CM_URL . $view_path, array(), MDS_CM_VERSION, true );
	}
}
