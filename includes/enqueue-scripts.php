<?php

defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', 'mds_woo_enqueue_admin_scripts');

/**
 * Enqueues WooCommerce integration admin scripts.
 */
final class MdsWooAdminScripts
{
    /**
     * Enqueue scripts for the current admin screen.
     */
    public static function enqueue()
    {
        if (! mds_woo_is_active()) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        if ('settings_page_mds-release-network-settings-network' === $screen->id) {
            self::enqueueNetworkSettingsScript();
        }

        if ('release' === $screen->post_type) {
            self::enqueueProductLinkerScript();
        }
    }

    /**
     * Enqueue the network settings script and its REST configuration.
     */
    private static function enqueueNetworkSettingsScript()
    {
        $network_settings_handle = 'mds-woo-network-settings';
        wp_enqueue_script(
            $network_settings_handle,
            MDS_WOO_URL . 'js/network-settings.js',
            array('jquery'),
            MDS_WOO_VERSION,
            true
        );
        wp_localize_script(
            $network_settings_handle,
            'mdsWooNetworkSettings',
            array(
                'url'   => esc_url_raw(rest_url('mds-woo/v1/network-settings')),
                'nonce' => wp_create_nonce('wp_rest'),
            )
        );
    }

    /**
     * Enqueue the release product linker and its REST configuration.
     */
    private static function enqueueProductLinkerScript()
    {
        $product_linker_handle = 'mds-woo-product-linker';
        wp_enqueue_script(
            $product_linker_handle,
            MDS_WOO_URL . 'js/product-linker.js',
            array('jquery'),
            MDS_WOO_VERSION,
            true
        );
        wp_localize_script(
            $product_linker_handle,
            'mdsWooProductLinker',
            array(
                'searchUrl'   => esc_url_raw(rest_url('mds-woo/v1/search')),
                'generateUrl' => esc_url_raw(rest_url('mds-woo/v1/generate')),
                'nonce'       => wp_create_nonce('wp_rest'),
            )
        );
    }
}

/**
 * Enqueue WooCommerce integration scripts on their required admin screens.
 */
function mds_woo_enqueue_admin_scripts()
{
    MdsWooAdminScripts::enqueue();
}
