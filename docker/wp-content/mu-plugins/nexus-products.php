<?php
/**
 * Plugin Name: Nexus Arcade Products
 * Description: Registers the "product" custom post type (title, price, description, featured image) exposed via the WP REST API for the Astro frontend. No WooCommerce.
 */

add_action('init', function () {
    register_post_type('product', [
        'label' => 'Products',
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'rest_base' => 'product',
        // 'custom-fields' is required for registered meta to appear in the REST response at all
        // (WP_REST_Posts_Controller only adds the `meta` property when the post type supports it).
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
        'menu_icon' => 'dashicons-cart',
    ]);

    register_post_meta('product', 'price', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
});
