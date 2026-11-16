<?php

/**
 * WooCommerce REST API operations.
 *
 * @package MDS_WooCommerce_Integration
 */

defined('ABSPATH') || exit;

final class MdsWooRestApi
{
    /**
     * Register integration routes.
     */
    public static function registerRoutes()
    {
        if (! mds_woo_is_active()) {
            return;
        }

        self::registerProductRoutes();
        self::registerNetworkSettingsRoute();
    }

    /**
     * Register product search and generation routes.
     */
    private static function registerProductRoutes()
    {
        register_rest_route('mds-woo/v1', '/generate', array(
            'methods'             => 'POST',
            'callback'            => 'mds_woo_generate_api',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_rest_route('mds-woo/v1', '/search', array(
            'methods'             => 'GET',
            'callback'            => 'mds_woo_search_api',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    /**
     * Register the network settings route.
     */
    private static function registerNetworkSettingsRoute()
    {
        register_rest_route('mds-woo/v1', '/network-settings', array(
            'methods'             => 'POST',
            'callback'            => 'mds_woo_save_network_settings',
            'permission_callback' => function () {
                return current_user_can('manage_network_options');
            },
        ));
    }

    /**
     * Save WooCommerce network settings.
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response
     */
    public static function saveNetworkSettings($request)
    {
        $params = $request->get_body_params();
        $wc_sites = array();

        if (isset($params['mds_rm_wc_integrated_sites']) && is_array($params['mds_rm_wc_integrated_sites'])) {
            $available_sites = array_map(
                'intval',
                get_sites(
                    array(
                        'fields' => 'ids',
                        'number' => 0,
                    )
                )
            );
            $requested_sites = array_map(
                static function ($site_id) {
                    return is_scalar($site_id) ? absint($site_id) : 0;
                },
                $params['mds_rm_wc_integrated_sites']
            );
            $wc_sites = array_values(array_intersect(array_unique($requested_sites), $available_sites));
        }

        update_site_option('mds_rm_wc_integrated_sites', $wc_sites);

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Generate and link a WooCommerce product for a release.
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response|\WP_Error
     */
    public static function generateProduct($request)
    {
        $store_id = self::getRequestId($request, 'store_id');
        $release_id = self::getRequestId($request, 'release_id');

        if (! $store_id || ! $release_id) {
            return new \WP_Error(
                'missing_data',
                'Missing Store ID or Release ID',
                array('status' => 400)
            );
        }

        $release = get_post($release_id);
        if (! $release || 'release' !== $release->post_type || ! current_user_can('edit_post', $release_id)) {
            return new \WP_Error(
                'forbidden_release',
                'You are not allowed to edit this release.',
                array('status' => 403)
            );
        }

        if (! self::isIntegratedStore($store_id)) {
            return new \WP_Error(
                'invalid_store',
                'The selected store is not integrated.',
                array('status' => 403)
            );
        }

        $release_title = get_the_title($release_id);
        $release_upc_value = get_post_meta($release_id, 'upc_ean', true);
        $release_upc = is_scalar($release_upc_value)
            ? sanitize_text_field((string) $release_upc_value)
            : '';

        switch_to_blog($store_id);
        try {
            if (! self::isWooCommerceActiveOnCurrentSite()) {
                return new \WP_Error(
                    'no_wc',
                    'WooCommerce is not active on the selected site.',
                    array('status' => 400)
                );
            }

            if (! self::canCreatePublishedProducts()) {
                return new \WP_Error(
                    'forbidden_product_creation',
                    'You are not allowed to create products on the selected site.',
                    array('status' => 403)
                );
            }

            self::registerProductTypes(true);
            $product_id = self::createProduct($release_title, $release_upc);
            $permalink = $product_id ? get_permalink($product_id) : '';
        } finally {
            restore_current_blog();
        }

        if ($product_id) {
            self::saveProductLink($release_id, $store_id, $product_id);

            return rest_ensure_response(
                array(
                    'success'       => true,
                    'product_id'    => $product_id,
                    'product_title' => $release_title,
                    'permalink'     => $permalink,
                )
            );
        }

        return new \WP_Error('create_failed', 'Failed to create product.', array('status' => 500));
    }

    /**
     * Check whether a site is configured as an integrated store.
     *
     * @param int $store_id Store site ID.
     * @return bool
     */
    public static function isIntegratedStore($store_id)
    {
        $store_id = absint($store_id);
        $integrated_sites = array_map(
            'absint',
            (array) get_site_option('mds_rm_wc_integrated_sites', array())
        );

        if (! $store_id || ! in_array($store_id, $integrated_sites, true) || ! get_site($store_id)) {
            return false;
        }

        return is_multisite() || $store_id === get_current_blog_id();
    }

    /**
     * Check whether the current user can create and publish products here.
     *
     * @return bool
     */
    private static function canCreatePublishedProducts()
    {
        return current_user_can('edit_products') && current_user_can('publish_products');
    }

    /**
     * Read a positive scalar request parameter as an ID.
     *
     * @param \WP_REST_Request $request REST request.
     * @param string           $parameter Parameter name.
     * @return int
     */
    private static function getRequestId($request, $parameter)
    {
        $value = $request->get_param($parameter);

        if (! is_scalar($value) || ! preg_match('/^\d+$/D', (string) $value)) {
            return 0;
        }

        return absint($value);
    }

    /**
     * Check whether WooCommerce is active on the current site.
     *
     * @return bool
     */
    private static function isWooCommerceActiveOnCurrentSite()
    {
        $active_plugins = get_option('active_plugins', array());
        if (in_array('woocommerce/woocommerce.php', $active_plugins, true)) {
            return true;
        }

        if (! is_multisite()) {
            return false;
        }

        $sitewide = get_site_option('active_sitewide_plugins');

        return is_array($sitewide) && array_key_exists('woocommerce/woocommerce.php', $sitewide);
    }

    /**
     * Register product and taxonomy types needed by the integration.
     *
     * @param bool $include_product_type Whether to register product_type.
     */
    private static function registerProductTypes($include_product_type)
    {
        if (! post_type_exists('product')) {
            register_post_type('product');
        }
        if (! taxonomy_exists('product_tag')) {
            register_taxonomy('product_tag', 'product');
        }
        if ($include_product_type && ! taxonomy_exists('product_type')) {
            register_taxonomy('product_type', 'product');
        }
    }

    /**
     * Insert a WooCommerce product and set its integration metadata.
     *
     * @param string $release_title Release title.
     * @param string $release_upc Release UPC.
     * @return int Product ID, or zero on failure.
     */
    private static function createProduct($release_title, $release_upc)
    {
        $product_id = wp_insert_post(
            array(
                'post_title'  => $release_title,
                'post_type'   => 'product',
                'post_status' => 'publish',
            )
        );

        if (! $product_id || is_wp_error($product_id)) {
            return 0;
        }

        if ($release_upc) {
            update_post_meta($product_id, '_sku', $release_upc);
        }
        update_post_meta($product_id, '_visibility', 'visible');
        wp_set_object_terms($product_id, 'simple', 'product_type', false);
        wp_set_object_terms($product_id, 'release', 'product_tag', false);

        return $product_id;
    }

    /**
     * Save a newly generated product link on its release.
     *
     * @param int $release_id Release post ID.
     * @param int $store_id Store site ID.
     * @param int $product_id Product ID.
     */
    private static function saveProductLink($release_id, $store_id, $product_id)
    {
        $linked = get_post_meta($release_id, '_mds_linked_products', true);
        if (! is_array($linked)) {
            $linked = array();
        }

        $linked[$store_id] = $product_id;
        update_post_meta($release_id, '_mds_linked_products', $linked);

        if ($store_id === get_current_blog_id()) {
            update_post_meta($release_id, '_mds_local_product_id', $product_id);
        }
    }

    /**
     * Search WooCommerce products in a store.
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response
     */
    public static function searchProducts($request)
    {
        $store_id = self::getRequestId($request, 'store_id');
        $release_id = self::getRequestId($request, 'release_id');
        $search_value = $request->get_param('q');
        $search = is_scalar($search_value) ? sanitize_text_field($search_value) : '';

        $release = get_post($release_id);
        if (
            ! $release_id
            || ! $release
            || 'release' !== $release->post_type
            || ! current_user_can('edit_post', $release_id)
        ) {
            return new \WP_Error(
                'forbidden_release',
                'You are not allowed to edit this release.',
                array('status' => 403)
            );
        }

        if (! self::isIntegratedStore($store_id)) {
            return new \WP_Error(
                'invalid_store',
                'The selected store is not integrated.',
                array('status' => 403)
            );
        }

        switch_to_blog($store_id);
        try {
            if (! self::isWooCommerceActiveOnCurrentSite()) {
                return rest_ensure_response(array('results' => array()));
            }

            self::registerProductTypes(false);
            $products = get_posts(self::getProductSearchArgs($search));
            $results = self::formatProductSearchResults($products);
        } finally {
            restore_current_blog();
        }

        return rest_ensure_response(array('results' => $results));
    }

    /**
     * Build the product search query.
     *
     * @param string $search Search terms.
     * @return array Query arguments.
     */
    private static function getProductSearchArgs($search)
    {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $search,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => 'release',
                ),
            ),
        );

        if (! get_term_by('slug', 'release', 'product_tag')) {
            unset($args['tax_query']);
        }

        return $args;
    }

    /**
     * Format product data for the Select2 response.
     *
     * @param array $products Product posts.
     * @return array Formatted search results.
     */
    private static function formatProductSearchResults($products)
    {
        $results = array();

        foreach ($products as $product) {
            $results[] = array(
                'id'        => $product->ID,
                'text'      => $product->post_title . ' (ID: ' . $product->ID . ')',
                'permalink' => get_permalink($product->ID),
            );
        }

        return $results;
    }
}
