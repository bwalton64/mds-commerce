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
        $site_markup = self::renderIntegratedSites($integrated_sites, $linked_products, $post);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template renderer escapes tokens and sanitizes HTML fragments.
        echo mds_woo_render_html_template(
            'product-linker-wrapper.html',
            array(
                'site_markup' => array(
                    'value'   => $site_markup,
                    'context' => 'html',
                ),
            )
        );
    }

    /**
     * Render all configured store sections.
     *
     * @param array   $integrated_sites Integrated site IDs.
     * @param array   $linked_products Product IDs linked by store.
     * @param \WP_Post $post Release post.
     * @return string Store section markup.
     */
    private static function renderIntegratedSites($integrated_sites, $linked_products, $post)
    {
        if (empty($integrated_sites)) {
            return mds_woo_render_html_template('product-linker-empty.html');
        }

        $markup = '';
        foreach ($integrated_sites as $store_id) {
            $markup .= self::renderStoreSection($store_id, $linked_products, $post);
        }

        return $markup;
    }

    /**
     * Render a single store section.
     *
     * @param int     $store_id Store site ID.
     * @param array   $linked_products Product IDs linked by store.
     * @param \WP_Post $post Release post.
     * @return string Store section markup.
     */
    private static function renderStoreSection($store_id, $linked_products, $post)
    {
        $current_linked_id = isset($linked_products[$store_id]) ? $linked_products[$store_id] : '';
        $product_option = self::renderProductOption($store_id, $current_linked_id);
        $generate_button = self::renderGenerateButton($store_id, $current_linked_id, $post);

        return mds_woo_render_html_template(
            'product-linker-site.html',
            array(
                'store_id_attr' => array(
                    'value'   => $store_id,
                    'context' => 'attr',
                ),
                'store_id_text' => array(
                    'value'   => $store_id,
                    'context' => 'text',
                ),
                'release_id_attr' => array(
                    'value'   => $post->ID,
                    'context' => 'attr',
                ),
                'product_option' => array(
                    'value'   => $product_option,
                    'context' => 'html',
                ),
                'generate_button' => array(
                    'value'   => $generate_button,
                    'context' => 'html',
                ),
            )
        );
    }

    /**
     * Render the current product option or search prompt.
     *
     * @param int $store_id Store site ID.
     * @param int|string $current_linked_id Linked product ID.
     * @return string Product option markup.
     */
    private static function renderProductOption($store_id, $current_linked_id)
    {
        if (! $current_linked_id) {
            return mds_woo_render_html_template('product-linker-search-option.html');
        }

        $product_details = self::getProductDetails($store_id, $current_linked_id);

        return mds_woo_render_html_template(
            'product-linker-selected-option.html',
            array(
                'product_id' => array(
                    'value'   => $current_linked_id,
                    'context' => 'attr',
                ),
                'permalink' => array(
                    'value'   => $product_details['permalink'],
                    'context' => 'url',
                ),
                'title'     => array(
                    'value'   => $product_details['title'],
                    'context' => 'text',
                ),
            )
        );
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

    /**
     * Render the generate button when the store has no linked product.
     *
     * @param int     $store_id Store site ID.
     * @param int|string $current_linked_id Linked product ID.
     * @param \WP_Post $post Release post.
     * @return string Button markup.
     */
    private static function renderGenerateButton($store_id, $current_linked_id, $post)
    {
        if ($current_linked_id) {
            return '';
        }

        return mds_woo_render_html_template(
            'product-linker-generate-button.html',
            array(
                'store_id' => array(
                    'value'   => $store_id,
                    'context' => 'attr',
                ),
                'post_id' => array(
                    'value'   => $post->ID,
                    'context' => 'attr',
                ),
            )
        );
    }
}
