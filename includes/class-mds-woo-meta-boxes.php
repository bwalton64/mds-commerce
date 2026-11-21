<?php

/**
 * WooCommerce release meta box rendering.
 *
 * @package MDS_WooCommerce_Integration
 */

defined('ABSPATH') || exit;

final class MdsWooMetaBoxes
{
    /**
     * Register the WooCommerce integration meta box for releases.
     *
     * @param \WP_Post $post Post object.
     */
    public static function register($post)
    {
        if (! mds_woo_is_active()) {
            return;
        }

        if ($post && 'release' === $post->post_type) {
            add_meta_box(
                'mds_woo_settings',
                'WooCommerce Integration',
                'mds_woo_meta_box_render',
                'release',
                'normal',
                'default'
            );
        }
    }

    /**
     * Render WooCommerce product links for a release.
     *
     * @param \WP_Post $post Post object.
     */
    public static function render($post)
    {
        $linked_products = get_post_meta($post->ID, '_mds_linked_products', true);
        if (! is_array($linked_products)) {
            $linked_products = array();
        }

        $integrated_sites = array_unique(
            array_filter(
                array_map('absint', (array) get_site_option('mds_rm_wc_integrated_sites', array())),
                array('MdsWooRestApi', 'isIntegratedStore')
            )
        );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values in their output contexts.
        echo mds_woo_render_template(
            'product-linker-wrapper.php',
            array(
                'sites'   => self::getIntegratedSiteData($integrated_sites, $linked_products),
                'post_id' => (int) $post->ID,
            )
        );
    }

    /**
     * Prepare data for each configured store section.
     *
     * @param array   $integrated_sites Integrated site IDs.
     * @param array   $linked_products Product IDs linked by store.
     * @return array Store data for the template.
     */
    private static function getIntegratedSiteData($integrated_sites, $linked_products)
    {
        $sites = array();
        foreach ($integrated_sites as $store_id) {
            $current_linked_id = isset($linked_products[$store_id])
                ? absint($linked_products[$store_id])
                : 0;
            $product_details = $current_linked_id
                ? self::getProductDetails($store_id, $current_linked_id)
                : array(
                    'title'     => '',
                    'permalink' => '',
                );

            $sites[] = array(
                'store_id'   => $store_id,
                'product_id' => $current_linked_id,
                'title'      => $product_details['title'],
                'permalink'  => $product_details['permalink'],
            );
        }

        return $sites;
    }

    /**
     * Get product title and permalink from the store site.
     *
     * @param int $store_id Store site ID.
     * @param int $product_id Product ID.
     * @return array Product details.
     */
    private static function getProductDetails($store_id, $product_id)
    {
        switch_to_blog($store_id);
        try {
            if (! post_type_exists('product')) {
                register_post_type('product');
            }

            $product = get_post($product_id);
            if (
                ! $product
                || 'product' !== $product->post_type
                || ( 'publish' !== $product->post_status && ! current_user_can('edit_post', $product_id) )
            ) {
                return array(
                    'title'     => '',
                    'permalink' => '',
                );
            }

            return array(
                'title'     => get_the_title($product_id),
                'permalink' => get_permalink($product_id),
            );
        } finally {
            restore_current_blog();
        }
    }

}
