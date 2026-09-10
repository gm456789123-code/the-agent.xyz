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

    register_post_meta('product', 'category', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta('product', 'store_name', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta('product', 'unit', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta('product', 'featured_home', [
        'type' => 'boolean',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
});

// Expose the featured image URL directly on the REST response so the
// Astro frontend doesn't need a second request per product.
add_action('rest_api_init', function () {
    register_rest_field('product', 'featured_image_url', [
        'get_callback' => function ($post) {
            $image_id = get_post_thumbnail_id($post['id']);
            if (!$image_id) {
                return null;
            }
            $url = wp_get_attachment_image_url($image_id, 'medium_large');
            return $url ?: null;
        },
        'schema' => [
            'type' => ['string', 'null'],
        ],
    ]);

    register_rest_route('nexus/v1', '/products/featured', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $products = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'numberposts' => 8,
                'orderby' => 'menu_order',
                'order' => 'ASC',
                'meta_query' => [[
                    'key' => 'featured_home',
                    'value' => '1',
                    'compare' => '=',
                ]],
            ]);

            return array_map(function ($post) {
                $image_id = get_post_thumbnail_id($post->ID);
                return [
                    'id' => $post->ID,
                    'title' => ['rendered' => $post->post_title],
                    'excerpt' => ['rendered' => $post->post_excerpt],
                    'meta' => [
                        'price' => (float) get_post_meta($post->ID, 'price', true),
                        'category' => get_post_meta($post->ID, 'category', true),
                        'store_name' => get_post_meta($post->ID, 'store_name', true),
                        'unit' => get_post_meta($post->ID, 'unit', true),
                    ],
                    'featured_image_url' => $image_id ? wp_get_attachment_image_url($image_id, 'large') : null,
                ];
            }, $products);
        },
    ]);
});

// --- wp-admin meta box: friendly product fields instead of raw Custom Fields ---

add_action('add_meta_boxes', function () {
    add_meta_box('nexus_product_fields', 'Nexus Product Details', 'nexus_render_product_meta_box', 'product', 'normal', 'high');
});

function nexus_render_product_meta_box(WP_Post $post) {
    wp_nonce_field('nexus_save_product', 'nexus_product_nonce');
    $price = get_post_meta($post->ID, 'price', true);
    $category = get_post_meta($post->ID, 'category', true);
    $store_name = get_post_meta($post->ID, 'store_name', true);
    $unit = get_post_meta($post->ID, 'unit', true);
    $featured = get_post_meta($post->ID, 'featured_home', true);
    $categories = ['streaming', 'gaming', 'software', 'services', 'vouchers'];
    ?>
    <table class="form-table">
        <tr>
            <th><label for="nexus_price">ราคา (บาท)</label></th>
            <td><input type="number" step="0.01" name="nexus_price" id="nexus_price" value="<?php echo esc_attr($price); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="nexus_category">หมวดหมู่</label></th>
            <td>
                <select name="nexus_category" id="nexus_category">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($category, $cat); ?>><?php echo esc_html($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="nexus_store_name">ชื่อร้านค้า</label></th>
            <td><input type="text" name="nexus_store_name" id="nexus_store_name" value="<?php echo esc_attr($store_name); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="nexus_unit">รายละเอียด/แพ็กเกจ</label></th>
            <td><input type="text" name="nexus_unit" id="nexus_unit" value="<?php echo esc_attr($unit); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="nexus_featured_home">แสดงในแบนเนอร์หน้าแรก</label></th>
            <td><input type="checkbox" name="nexus_featured_home" id="nexus_featured_home" value="1" <?php checked($featured, '1'); ?> /></td>
        </tr>
    </table>
    <?php
}

add_action('save_post_product', function ($post_id) {
    if (!isset($_POST['nexus_product_nonce']) || !wp_verify_nonce($_POST['nexus_product_nonce'], 'nexus_save_product')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['nexus_price'])) {
        update_post_meta($post_id, 'price', (float) $_POST['nexus_price']);
    }
    if (isset($_POST['nexus_category'])) {
        update_post_meta($post_id, 'category', sanitize_text_field($_POST['nexus_category']));
    }
    if (isset($_POST['nexus_store_name'])) {
        update_post_meta($post_id, 'store_name', sanitize_text_field($_POST['nexus_store_name']));
    }
    if (isset($_POST['nexus_unit'])) {
        update_post_meta($post_id, 'unit', sanitize_text_field($_POST['nexus_unit']));
    }
    update_post_meta($post_id, 'featured_home', isset($_POST['nexus_featured_home']) ? '1' : '');
});
