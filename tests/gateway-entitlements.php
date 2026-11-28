<?php
/**
 * Standalone PHP unit tests for MDS Commerce API Gateway Entitlements & Remote Providers.
 *
 * Run with PHP: php tests/gateway-entitlements.php
 *
 * @package MDS\Commerce
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MDS_CM_PATH', dirname( __DIR__ ) . '/' );
define( 'MDS_CM_VERSION', '3.0.1' );

$GLOBALS['mds_test_options'] = array();

function is_multisite(): bool {
	return false;
}
function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
}
function sanitize_text_field( string $str ): string {
	return trim( strip_tags( $str ) );
}
function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_html__( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return esc_html( $text );
}
function __( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return $text;
}
function wp_parse_url( string $url, int $component = -1 ): mixed {
	return parse_url( $url, $component );
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
require_once MDS_CM_PATH . 'src/Contracts/CommerceProviderInterface.php';
require_once MDS_CM_PATH . 'src/Contracts/EmbeddableProviderInterface.php';
require_once MDS_CM_PATH . 'src/Model/StoreKey.php';
require_once MDS_CM_PATH . 'src/Model/StoreConnection.php';
require_once MDS_CM_PATH . 'src/Model/NormalizedProduct.php';
require_once MDS_CM_PATH . 'src/Gateway/EntitlementManager.php';
require_once MDS_CM_PATH . 'src/Providers/Gateway/GatewayRemoteProvider.php';

use MDS\Commerce\Gateway\EntitlementManager;
use MDS\Commerce\Model\StoreConnection;
use MDS\Commerce\Model\StoreKey;
use MDS\Commerce\Providers\Gateway\GatewayRemoteProvider;

$assert = static function ( bool $cond, string $msg ): void {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}
};

// 1. Default inactive state.
$assert( ! EntitlementManager::is_active(), 'Inactive by default' );
$assert( 'none' === EntitlementManager::get_tier(), 'Default tier is none' );
$assert( 0 === EntitlementManager::max_remote_stores(), 'Inactive tier allows 0 remote stores' );

// 2. Pro tier entitlement & 7-day grace window.
$base = strtotime( '2026-11-28 00:00:00 UTC' );
EntitlementManager::store_entitlements(
	EntitlementManager::normalize_entitlement_payload(
		array(
			'status'            => 'active',
			'tier'              => 'pro',
			'max_remote_stores' => 3,
			'expires_at'        => $base + 3600,
		)
	)
);

$assert( EntitlementManager::is_active( $base ), 'Pro tier active before expiry' );
$assert( 'pro' === EntitlementManager::get_tier( $base ), 'Tier resolves to pro' );
$assert( 3 === EntitlementManager::max_remote_stores( $base ), 'Pro tier allows 3 remote stores' );
$assert( ! EntitlementManager::can_use_webhooks( $base ), 'Pro tier does not unlock webhook relay' );

// Inside 7-day grace window (3 days past expiry).
$assert( EntitlementManager::is_active( $base + 3600 + ( 3 * 86400 ) ), 'Active during 7-day offline grace window' );
// Beyond 7-day grace window (8 days past expiry).
$assert( ! EntitlementManager::is_active( $base + 3600 + ( 8 * 86400 ) ), 'Inactive after 7-day grace window expires' );

// 3. Label tier unlocks unlimited stores, Multisite Hub & Webhooks.
EntitlementManager::store_entitlements(
	EntitlementManager::normalize_entitlement_payload(
		array(
			'status'     => 'active',
			'tier'       => 'label',
			'expires_at' => $base + 86400,
		)
	)
);
$assert( -1 === EntitlementManager::max_remote_stores( $base ), 'Label tier allows unlimited remote stores' );
$assert( EntitlementManager::can_use_webhooks( $base ), 'Label tier unlocks webhook relay' );
$assert( EntitlementManager::can_use_multisite_hub( $base ), 'Label tier unlocks Multisite Hub workflows' );

// 4. GatewayRemoteProvider Shopify Storefront Web Component embed.
$shopify = new GatewayRemoteProvider( 'shopify', 'Shopify' );
$conn    = new StoreConnection( new StoreKey( 'shopify', 'main' ), 'Label Merch', 'https://shop.mustydungeon.com' );
$embed   = $shopify->render_embed( $conn, 'midnight-echoes-lp', array( 'storefront_token' => 'pub_token_123' ) );
$assert( str_contains( $embed, '<shopify-store' ) && str_contains( $embed, 'midnight-echoes-lp' ), 'Shopify embed renders Web Component markup' );

echo "PASS: All MDS Commerce Gateway & Entitlement tests passed.\n";
