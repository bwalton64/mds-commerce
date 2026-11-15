<?php
/**
 * Plugin Name: MDS WooCommerce Integration
 * Description: Extends MDS Release Manager with Network WooCommerce syncing via the Hook Registry.
 * Version: 1.0.0
 * Author: MDS
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Dependency Check
function mds_woo_is_active() {
    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    return in_array( 'mds-release-manager/mds-release-manager.php', $active_plugins, true ) || ( is_multisite() && array_key_exists( 'mds-release-manager/mds-release-manager.php', get_site_option( 'active_sitewide_plugins', array() ) ) );
}

// 2. Inject Network Settings
add_filter( 'mds_rm_network_settings_fields', 'mds_woo_network_settings' );
function mds_woo_network_settings( $html ) {
    if ( ! mds_woo_is_active() ) return $html;
    $sites = function_exists( 'get_sites' ) ? get_sites() : array();
    $integrated_sites = array_map( 'intval', (array) get_site_option( 'mds_rm_wc_integrated_sites', array() ) );
    
    $woo_sites_html = '';
    foreach ( $sites as $site ) {
        $checked = in_array( (int) $site->blog_id, $integrated_sites, true ) ? 'checked' : '';
        $woo_sites_html .= '<label style="display:block; margin-bottom: 5px;">';
        $woo_sites_html .= '<input type="checkbox" name="mds_rm_wc_integrated_sites[]" value="' . esc_attr( $site->blog_id ) . '" ' . $checked . '> Site #' . esc_html( $site->blog_id );
        $woo_sites_html .= '</label>';
    }

    $html .= '<tr><th scope="row">WooCommerce Integrated Sites</th><td>' . $woo_sites_html . '<p class="description">Select sites with WooCommerce active for product linking.</p></td></tr>';
    return $html;
}

// Ensure the Network Setting actually saves by extending the core save if possible, or using a dedicated API.
// Since core is extensible, we hook into the generic update_site_option action if we were native, 
// but our core network settings uses a custom REST API. We should hook into the core API or run a separate one.
// Let's add a REST endpoint specifically for this plugin to save its own settings!
add_action( 'rest_api_init', 'mds_woo_register_rest_routes' );
function mds_woo_register_rest_routes() {
    if ( ! mds_woo_is_active() ) return;
    
    register_rest_route( 'mds-woo/v1', '/generate', array(
        'methods'             => 'POST',
        'callback'            => 'mds_woo_generate_api',
        'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
    ) );
    
    register_rest_route( 'mds-woo/v1', '/search', array(
        'methods'             => 'GET',
        'callback'            => 'mds_woo_search_api',
        'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
    ) );
    
    // Hook to save network setting
    register_rest_route( 'mds-woo/v1', '/network-settings', array(
        'methods'             => 'POST',
        'callback'            => 'mds_woo_save_network_settings',
        'permission_callback' => function () { return current_user_can( 'manage_network_options' ); },
    ) );
}

function mds_woo_save_network_settings( $request ) {
    $params = $request->get_body_params();
    $wc_sites = array();
    if ( isset( $params['mds_rm_wc_integrated_sites'] ) && is_array( $params['mds_rm_wc_integrated_sites'] ) ) {
        $wc_sites = array_values( array_unique( array_filter( array_map( 'absint', $params['mds_rm_wc_integrated_sites'] ) ) ) );
    }
    update_site_option( 'mds_rm_wc_integrated_sites', $wc_sites );
    return rest_ensure_response( array( 'success' => true ) );
}

// We need to inject JS into the network admin to save our new fields.
add_action( 'admin_footer', 'mds_woo_network_admin_js' );
function mds_woo_network_admin_js() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'settings_page_mds-release-network-settings-network' ) return;
    ?>
    <script>
    jQuery(document).ready(function($){
        $('#mds-network-settings-form').on('submit', function() {
            var data = $(this).serialize();
            $.post( '<?php echo esc_url_raw( rest_url( 'mds-woo/v1/network-settings' ) ); ?>', data + '&_wpnonce=<?php echo wp_create_nonce( 'wp_rest' ); ?>' );
        });
    });
    </script>
    <?php
}

// 3. Inject Meta Box into Release CPT
add_action( 'mds_rm_register_custom_meta_boxes', 'mds_woo_register_meta_box' );
function mds_woo_register_meta_box( $post ) {
    if ( ! mds_woo_is_active() ) return;
    if ( $post && $post->post_type === 'release' ) {
        add_meta_box( 'mds_woo_settings', 'WooCommerce Integration', 'mds_woo_meta_box_render', 'release', 'normal', 'default' );
    }
}

function mds_woo_meta_box_render( $post ) {
    $linked_products = get_post_meta( $post->ID, '_mds_linked_products', true );
    if ( ! is_array( $linked_products ) ) $linked_products = array();

    $integrated_sites = array_map( 'intval', (array) get_site_option( 'mds_rm_wc_integrated_sites', array() ) );
    
    echo '<div class="mds-wc-linker-wrapper">';
    if ( empty( $integrated_sites ) ) {
        echo '<p style="color:#d63638;">No WooCommerce Integrated Sites configured in Network Settings.</p>';
    } else {
        foreach ( $integrated_sites as $store_id ) {
            $current_linked_id = $linked_products[ $store_id ] ?? '';
            $title = ''; $permalink = '';
            if ( $current_linked_id ) {
                switch_to_blog( $store_id );
                if ( ! post_type_exists( 'product' ) ) register_post_type( 'product' );
                $title = get_the_title( $current_linked_id );
                $permalink = get_permalink( $current_linked_id );
                restore_current_blog();
            }
            
            echo '<div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">';
            echo '<strong>Store ID: ' . esc_html( $store_id ) . '</strong><br/>';
            echo '<select class="mds-wc-select2" name="mds_rm_linked_product[' . esc_attr($store_id) . ']" data-store-id="' . esc_attr($store_id) . '" style="width:100%;">';
            if ( $current_linked_id ) {
                echo '<option value="' . esc_attr($current_linked_id) . '" selected="selected" data-permalink="' . esc_url($permalink) . '">' . esc_html($title) . '</option>';
            } else {
                echo '<option value="">Search existing products...</option>';
            }
            echo '</select>';
            if ( ! $current_linked_id ) echo '<button type="button" class="button mds-wc-generate-btn" data-store-id="' . esc_attr($store_id) . '" data-post-id="' . esc_attr($post->ID) . '" style="margin-top:10px;">Generate New</button>';
            echo '</div>';
        }
    }
    echo '</div>';
    
    // Inject JS strictly for WooCommerce
    echo '<script>
        jQuery(document).ready(function($){
            var wc_search_url = "' . esc_url_raw( rest_url( 'mds-woo/v1/search' ) ) . '";
            var nonce = "' . wp_create_nonce( 'wp_rest' ) . '";
            
            if ( $.fn.selectWoo || $.fn.select2 ) {
                var selectMethod = $.fn.selectWoo ? "selectWoo" : "select2";
                $(".mds-wc-select2").each(function() {
                    var $el = $(this);
                    var storeId = $el.data("store-id");
                    $el[selectMethod]({
                        ajax: {
                            url: wc_search_url,
                            dataType: "json",
                            delay: 250,
                            beforeSend: function(xhr) { xhr.setRequestHeader("X-WP-Nonce", nonce); },
                            data: function(params) { return { q: params.term, store_id: storeId }; },
                            processResults: function(data) { return { results: data.results }; }
                        },
                        minimumInputLength: 3
                    });
                });
            }

            $(".mds-wc-generate-btn").on("click", function(e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.text("Generating...").prop("disabled", true);
                $.ajax({ 
                    url: "' . esc_url_raw( rest_url( 'mds-woo/v1/generate' ) ) . '",
                    method: "POST",
                    beforeSend: function(xhr) { xhr.setRequestHeader("X-WP-Nonce", nonce); },
                    data: { store_id: $btn.data("store-id"), release_id: $btn.data("post-id") },
                    success: function(res) {
                        if (res.success) {
                            $btn.hide();
                            var opt = new Option(res.product_title, res.product_id, true, true);
                            $(opt).attr("data-permalink", res.permalink);
                            $btn.siblings("select").append(opt).trigger("change");
                        } else {
                            alert("Error: " + res.message);
                            $btn.text("Generate New").prop("disabled", false);
                        }
                    }
                });
            });
        });
    </script>';
}

// 4. Save Logic
add_action( 'mds_rm_save_custom_meta', 'mds_woo_save_meta', 10, 2 );
function mds_woo_save_meta( $post_id, $post_data ) {
    if ( ! mds_woo_is_active() ) return;
    
    // Save Links
    $linked_products = get_post_meta( $post_id, '_mds_linked_products', true );
    if ( ! is_array( $linked_products ) ) $linked_products = array();
    
    if ( isset( $post_data['mds_rm_linked_product'] ) && is_array( $post_data['mds_rm_linked_product'] ) ) {
        foreach ( $post_data['mds_rm_linked_product'] as $store_id => $prod_id ) {
            $store_id = intval( $store_id );
            $prod_id = intval( $prod_id );
            if ( $prod_id ) {
                $linked_products[ $store_id ] = $prod_id;
            } else {
                unset( $linked_products[ $store_id ] );
            }
        }
        update_post_meta( $post_id, '_mds_linked_products', $linked_products );
        
        $current_blog = get_current_blog_id();
        if ( isset( $linked_products[ $current_blog ] ) ) {
            update_post_meta( $post_id, '_mds_local_product_id', $linked_products[ $current_blog ] );
        } else {
            delete_post_meta( $post_id, '_mds_local_product_id' );
        }
    }

    // Auto-Sync
    $upc = get_post_meta( $post_id, 'upc_ean', true );
    $hub_thumb_id = get_post_thumbnail_id( $post_id );
    $hub_img_url = $hub_thumb_id ? wp_get_attachment_url( $hub_thumb_id ) : '';
    
    if ( ! empty( $linked_products ) ) {
        foreach ( $linked_products as $store_id => $prod_id ) {
            switch_to_blog( $store_id );
            
            $active_plugins = get_option( 'active_plugins', array() );
            $is_wc = in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
            if ( ! $is_wc && is_multisite() ) {
                $sitewide = get_site_option( 'active_sitewide_plugins' );
                if ( is_array( $sitewide ) && array_key_exists( 'woocommerce/woocommerce.php', $sitewide ) ) $is_wc = true;
            }
            
            if ( $is_wc ) {
                if ( ! post_type_exists( 'product' ) ) register_post_type( 'product' );
                if ( ! taxonomy_exists( 'product_tag' ) ) register_taxonomy( 'product_tag', 'product' );
                
                wp_update_post( array(
                    'ID'         => $prod_id,
                    'post_title' => get_the_title( $post_id )
                ) );
                
                if ( $upc ) update_post_meta( $prod_id, '_sku', $upc );
                wp_set_object_terms( $prod_id, 'release', 'product_tag', false );
                
                if ( $hub_thumb_id ) {
                    if ( get_current_blog_id() === get_main_site_id() ) {
                        set_post_thumbnail( $prod_id, $hub_thumb_id );
                    } else {
                        // Normally calls virtual attachment function from core.
                        if ( function_exists('mds_rm_get_or_create_virtual_attachment') ) {
                            $virt_id = mds_rm_get_or_create_virtual_attachment( $hub_img_url, $hub_thumb_id );
                            if ( $virt_id ) set_post_thumbnail( $prod_id, $virt_id );
                        }
                    }
                } else {
                    delete_post_thumbnail( $prod_id );
                }
            }
            restore_current_blog();
        }
    }
}

// 5. REST APIs (Ported from Core)
function mds_woo_generate_api( $request ) {
    $store_id = (int) $request->get_param( 'store_id' );
    $release_id = (int) $request->get_param( 'release_id' );
    
    if ( ! $store_id || ! $release_id ) {
        return new WP_Error( 'missing_data', 'Missing Store ID or Release ID', array( 'status' => 400 ) );
    }

    $release_title = get_the_title( $release_id );
    $release_upc = get_post_meta( $release_id, 'upc_ean', true );

    switch_to_blog( $store_id );
    
    $active_plugins = get_option( 'active_plugins', array() );
    $is_wc = in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
    if ( ! $is_wc && is_multisite() ) {
        $sitewide = get_site_option( 'active_sitewide_plugins' );
        if ( is_array( $sitewide ) && array_key_exists( 'woocommerce/woocommerce.php', $sitewide ) ) $is_wc = true;
    }
    if ( ! $is_wc ) {
        restore_current_blog();
        return new WP_Error( 'no_wc', 'WooCommerce is not active on the selected site.', array( 'status' => 400 ) );
    }

    if ( ! post_type_exists( 'product' ) ) register_post_type( 'product' );
    if ( ! taxonomy_exists( 'product_tag' ) ) register_taxonomy( 'product_tag', 'product' );
    if ( ! taxonomy_exists( 'product_type' ) ) register_taxonomy( 'product_type', 'product' );

    $product_data = array( 'post_title' => $release_title, 'post_type' => 'product', 'post_status' => 'publish' );
    $product_id = wp_insert_post( $product_data );

    if ( $product_id && ! is_wp_error( $product_id ) ) {
        if ( $release_upc ) update_post_meta( $product_id, '_sku', $release_upc );
        update_post_meta( $product_id, '_visibility', 'visible' );
        wp_set_object_terms( $product_id, 'simple', 'product_type', false );
        wp_set_object_terms( $product_id, 'release', 'product_tag', false );
    } else {
        $product_id = 0;
    }
    
    $permalink = get_permalink( $product_id );
    restore_current_blog();
    
    if ( $product_id ) {
        $linked = get_post_meta( $release_id, '_mds_linked_products', true );
        if ( ! is_array( $linked ) ) $linked = array();
        $linked[ $store_id ] = $product_id;
        update_post_meta( $release_id, '_mds_linked_products', $linked );
        
        if ( $store_id === get_current_blog_id() ) update_post_meta( $release_id, '_mds_local_product_id', $product_id );
        
        return rest_ensure_response( array( 'success' => true, 'product_id' => $product_id, 'product_title' => $release_title, 'permalink' => $permalink ) );
    }
    
    return new WP_Error( 'create_failed', 'Failed to create product.', array( 'status' => 500 ) );
}

function mds_woo_search_api( $request ) {
    $store_id = (int) $request->get_param( 'store_id' );
    $search   = sanitize_text_field( $request->get_param( 'q' ) );
    
    if ( ! $store_id ) return rest_ensure_response( array() );
    
    switch_to_blog( $store_id );
    
    $active_plugins = get_option( 'active_plugins', array() );
    $is_wc = in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
    if ( ! $is_wc && is_multisite() ) {
        $sitewide = get_site_option( 'active_sitewide_plugins' );
        if ( is_array( $sitewide ) && array_key_exists( 'woocommerce/woocommerce.php', $sitewide ) ) $is_wc = true;
    }
    if ( ! $is_wc ) {
        restore_current_blog();
        return rest_ensure_response( array( 'results' => array() ) );
    }

    if ( ! post_type_exists( 'product' ) ) register_post_type( 'product' );
    if ( ! taxonomy_exists( 'product_tag' ) ) register_taxonomy( 'product_tag', 'product' );
    
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        's'              => $search,
        'tax_query'      => array( array( 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => 'release' ) ),
    );
    
    $term = get_term_by( 'slug', 'release', 'product_tag' );
    if ( ! $term ) unset( $args['tax_query'] );

    $products = get_posts( $args );
    $results = array();
    foreach ( $products as $p ) {
        $results[] = array( 'id' => $p->ID, 'text' => $p->post_title . ' (ID: ' . $p->ID . ')', 'permalink' => get_permalink( $p->ID ) );
    }
    
    restore_current_blog();
    return rest_ensure_response( array( 'results' => $results ) );
}
