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

    register_taxonomy('nexus_product_tag', ['product'], [
        'labels' => [
            'name' => 'แท็กสินค้า',
            'singular_name' => 'แท็กสินค้า',
            'menu_name' => 'แท็กสินค้า',
            'add_new_item' => 'เพิ่มแท็กสินค้าใหม่',
            'edit_item' => 'แก้ไขแท็กสินค้า',
            'search_items' => 'ค้นหาแท็กสินค้า',
            'all_items' => 'แท็กสินค้าทั้งหมด',
            'separate_items_with_commas' => 'แยกแท็กด้วยเครื่องหมายจุลภาค (,)',
        ],
        'public' => true,
        // Hierarchical taxonomies get Gutenberg's checkbox-list term selector
        // (pick from existing tags) instead of the flat free-text/autocomplete
        // box, which was letting admins type ad-hoc tags instead of reusing
        // the ones already created.
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rest_base' => 'product-tags',
        'rewrite' => false,
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

    register_post_meta('product', 'pinned_home', [
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
                'numberposts' => 9,
                'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC'],
                'meta_query' => ['relation' => 'AND', [
                    'key' => 'featured_home',
                    'value' => '1',
                    'compare' => '=',
                ], nexus_home_product_categories()],
            ]);

            return array_map('nexus_home_product_response', $products);
        },
    ]);

    register_rest_route('nexus/v1', '/products/pinned', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $limit = max(1, min(24, (int) $request->get_param('limit') ?: 6));
            $products = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'numberposts' => $limit,
                'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC'],
                'meta_query' => ['relation' => 'AND', [
                    'key' => 'pinned_home',
                    'value' => '1',
                    'compare' => '=',
                ], nexus_home_product_categories()],
            ]);

            return array_map('nexus_home_product_response', $products);
        },
    ]);

    register_rest_route('nexus/v1', '/products/latest', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            return array_map('nexus_home_product_response', get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'numberposts' => 6,
                'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
                'meta_query' => [nexus_home_product_categories()],
            ]));
        },
    ]);
});

function nexus_home_product_categories() {
    return [
        'relation' => 'OR',
        ['key' => 'category', 'value' => 'services', 'compare' => '!='],
        ['key' => 'category', 'compare' => 'NOT EXISTS'],
    ];
}

function nexus_home_product_response($post) {
    $image_id = get_post_thumbnail_id($post->ID);
    $tag_ids = wp_get_object_terms($post->ID, 'nexus_product_tag', ['fields' => 'ids']);
    return [
        'id' => $post->ID,
        'product-tags' => is_wp_error($tag_ids) ? [] : $tag_ids,
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
}

// One-time migration: preserve the existing filter labels and assignments.
// Subsequent admin edits/removals are never overwritten.
function nexus_migrate_product_tags() {
    if (get_option('nexus_product_tags_version') === '1') return;
    $defaults = [
        'streaming' => 'แอป & สตรีมมิ่ง',
        'gaming' => 'เติมเกม & บัตรเติมเงิน',
        'software' => 'ซอฟต์แวร์ & คีย์ดิจิทัล',
        'vouchers' => 'บัตรดิจิทัล',
    ];
    foreach ($defaults as $slug => $name) {
        $term = term_exists($slug, 'nexus_product_tag');
        if (!$term) $term = wp_insert_term($name, 'nexus_product_tag', ['slug' => $slug]);
        if (is_wp_error($term)) return;
        $term_id = (int) (is_array($term) ? $term['term_id'] : $term);
        $product_ids = get_posts([
            'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1,
            'fields' => 'ids', 'meta_key' => 'category', 'meta_value' => $slug,
        ]);
        foreach ($product_ids as $product_id) {
            $result = wp_set_object_terms($product_id, [$term_id], 'nexus_product_tag', true);
            if (is_wp_error($result)) return;
        }
    }
    update_option('nexus_product_tags_version', '1');
}

add_action('admin_init', function () {
    if (current_user_can('manage_categories')) nexus_migrate_product_tags();
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
    $pinned = get_post_meta($post->ID, 'pinned_home', true);
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
            <th><label for="nexus_featured_home">ปักหมุดสินค้าแนะนำหน้าแรก</label></th>
            <td><input type="checkbox" name="nexus_featured_home" id="nexus_featured_home" value="1" <?php checked($featured, '1'); ?> /></td>
        </tr>
        <tr>
            <th><label for="nexus_pinned_home">ปักหมุดสินค้ายอดนิยม</label></th>
            <td><input type="checkbox" name="nexus_pinned_home" id="nexus_pinned_home" value="1" <?php checked($pinned, '1'); ?> /></td>
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
    update_post_meta($post_id, 'pinned_home', isset($_POST['nexus_pinned_home']) ? '1' : '');
});
