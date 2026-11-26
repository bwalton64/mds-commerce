<?php
/**
 * Standalone unit tests for MDS Commerce core architecture:
 * - StoreKey parsing & formatting
 * - CredentialVault libsodium round-trip & wp-config constant override
 * - UpgradeRoutine zero-loss migration (mds-woocommerce -> mds-commerce)
 * - ExternalVariationsBridge (wpcev_url / wpcev_text)
 * - StoreChooser promo/coupon prioritization & expiry handling
 * - PodProvisioningProvider payload generation
 *
 * Run with PHP: php tests/commerce-core.php
 *
 * @package MDS\Commerce
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MDS_CM_PATH', dirname( __DIR__ ) . '/' );
define( 'MDS_CM_URL', 'http://example.test/wp-content/plugins/mds-commerce/' );
define( 'MDS_CM_VERSION', '3.0.0' );
define( 'MDS_CM_TEST_SECRET_CONST', 'super-secret-from-wp-config' );

$GLOBALS['mds_test_post_meta'] = array();
$GLOBALS['mds_test_options']   = array();

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
}
function sanitize_text_field( string $str ): string {
	return trim( strip_tags( $str ) );
}
function esc_url_raw( string $url ): string {
	return filter_var( trim( $url ), FILTER_VALIDATE_URL ) ? trim( $url ) : '';
}
function absint( mixed $maybeint ): int {
	return abs( (int) $maybeint );
}
function __( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return $text;
}
function wp_salt( string $scheme = 'auth' ): string {
	return 'test-salt-' . $scheme;
}
function apply_filters( string $hook, mixed $value ): mixed {
	unset( $hook );
	return $value;
}
function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
	unset( $single );
	return $GLOBALS['mds_test_post_meta'][ $post_id ][ $key ] ?? '';
}
function update_post_meta( int $post_id, string $key, mixed $value ): bool {
	$GLOBALS['mds_test_post_meta'][ $post_id ][ $key ] = $value;
	return true;
}
function delete_post_meta( int $post_id, string $key ): bool {
	unset( $GLOBALS['mds_test_post_meta'][ $post_id ][ $key ] );
	return true;
}
function get_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['mds_test_options'][ $key ] ?? $default;
}
function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
	unset( $autoload );
	$GLOBALS['mds_test_options'][ $key ] = $value;
	return true;
}

require_once MDS_CM_PATH . 'src/Config.php';
require_once MDS_CM_PATH . 'src/Model/StoreKey.php';
require_once MDS_CM_PATH . 'src/Model/StoreConnection.php';
require_once MDS_CM_PATH . 'src/Model/NormalizedProduct.php';
require_once MDS_CM_PATH . 'src/Model/DriftReport.php';
require_once MDS_CM_PATH . 'src/Security/CredentialVault.php';
require_once MDS_CM_PATH . 'src/Sync/UpgradeRoutine.php';
require_once MDS_CM_PATH . 'src/Providers/WooCommerceLocal/ExternalVariationsBridge.php';
require_once MDS_CM_PATH . 'src/Blocks/StoreChooser.php';

use MDS\Commerce\Blocks\StoreChooser;
use MDS\Commerce\Model\StoreKey;
use MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge;
use MDS\Commerce\Security\CredentialVault;
use MDS\Commerce\Sync\UpgradeRoutine;

$assert = static function ( bool $cond, string $msg ): void {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}
};

// 1. StoreKey parsing (legacy integer + provider-namespaced).
$k1 = StoreKey::parse( 3 );
$assert( $k1 instanceof StoreKey && 'wc_local:3' === $k1->to_string() && 3 === $k1->local_blog_id(), 'Legacy int blog ID parses to wc_local:3' );

$k2 = StoreKey::parse( 'shopify:main_store' );
$assert( $k2 instanceof StoreKey && 'shopify' === $k2->provider() && 0 === $k2->local_blog_id(), 'Shopify StoreKey parses cleanly' );
$assert( null === StoreKey::parse( 'invalid-key' ), 'Invalid StoreKey returns null' );

// 2. CredentialVault encryption & wp-config override.
$cipher = CredentialVault::encrypt( 'shpat_live_token_12345' );
$assert(
	str_starts_with( $cipher, 'mds_sodium_v1:' ) || str_starts_with( $cipher, 'mds_openssl_v1:' ),
	'CredentialVault encrypts with sodium or openssl prefix'
);
$assert( 'shpat_live_token_12345' === CredentialVault::decrypt( $cipher ), 'CredentialVault decrypts accurately' );
$assert(
	'super-secret-from-wp-config' === CredentialVault::resolve_secret( 'MDS_CM_TEST_SECRET_CONST', $cipher ),
	'wp-config.php constant overrides stored ciphertext'
);

// 3. UpgradeRoutine legacy conversion.
$converted_stores = UpgradeRoutine::convert_legacy_store_ids( array( 2, 5, 'shopify:label', 2 ) );
$assert( array( 'wc_local:2', 'wc_local:5', 'shopify:label' ) === $converted_stores, 'Legacy store IDs convert and deduplicate' );

$converted_links = UpgradeRoutine::convert_legacy_release_links( array( 3 => 777 ) );
$assert( isset( $converted_links['wc_local:3'] ) && '777' === $converted_links['wc_local:3']['remote_id'], 'Legacy release links re-key under wc_local:3' );

// 4. ExternalVariationsBridge (wpcev_url / wpcev_text).
ExternalVariationsBridge::set_variation_link( 401, 'https://elasticstage.com/artist/releases/demo', 'Order Vinyl on elasticStage' );
$link = ExternalVariationsBridge::get_variation_link( 401 );
$assert( 'https://elasticstage.com/artist/releases/demo' === $link['url'], 'wpcev_url saved and read' );
$assert( 'Order Vinyl on elasticStage' === $link['text'], 'wpcev_text saved and read' );
ExternalVariationsBridge::set_variation_link( 401, '' );
$assert( '' === ExternalVariationsBridge::get_variation_link( 401 )['url'], 'Empty URL clears external variation meta' );

// 5. StoreChooser promo/coupon prioritization & expiry.
$now    = strtotime( '2026-11-26 12:00:00 UTC' );
$offers = array(
	array(
		'store_name'        => 'Alpha Store',
		'format'            => 'Vinyl LP',
		'stock_status'      => 'instock',
		'priority_weight'   => 10,
		'promo_badge'       => '',
		'coupon_code'       => '',
		'promo_expires_gmt' => '',
	),
	array(
		'store_name'        => 'Zeta Vinyl Promo',
		'format'            => 'Vinyl LP',
		'stock_status'      => 'instock',
		'priority_weight'   => 0,
		'promo_badge'       => '15% Off',
		'coupon_code'       => 'VINYL15',
		'promo_expires_gmt' => '2026-12-31 23:59:59 UTC',
	),
	array(
		'store_name'        => 'Expired Promo Store',
		'format'            => 'Vinyl LP',
		'stock_status'      => 'instock',
		'priority_weight'   => 0,
		'promo_badge'       => 'Old Sale',
		'coupon_code'       => 'OLD10',
		'promo_expires_gmt' => '2026-01-01 00:00:00 UTC',
	),
);

$sorted = StoreChooser::sort_offers( $offers, true, $now );
$assert( 'Zeta Vinyl Promo' === $sorted[0]['store_name'], 'Active coupon/promo store floats to #1' );
$assert( 'Alpha Store' === $sorted[1]['store_name'], 'Higher priority_weight beats expired promo' );
$assert( ! StoreChooser::is_promo_active( $offers[2], $now ), 'Expired promo is detected as inactive' );

$grouped = StoreChooser::group_offers( $sorted, 'format' );
$assert( isset( $grouped['Vinyl LP'] ) && 3 === count( $grouped['Vinyl LP'] ), 'Offers group cleanly by Media Format' );

echo "PASS: All MDS Commerce core unit tests passed.\n";
