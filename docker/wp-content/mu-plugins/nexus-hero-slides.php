<?php
/**
 * Plugin Name: Nexus Hero Slides
 * Description: Manage the homepage auto-slide banner from wp-admin (title,
 * copy, CTA, and a featured image per slide) instead of hardcoding it in the
 * Astro frontend. Exposes a small public REST endpoint for the frontend.
 */

add_action('init', function () {
    register_post_type('hero_slide', [
        'labels' => [
            'name' => 'แบนเนอร์หน้าแรก',
            'singular_name' => 'แบนเนอร์หน้าแรก',
            'add_new_item' => 'เพิ่มแบนเนอร์ใหม่',
            'edit_item' => 'แก้ไขแบนเนอร์',
            'all_items' => 'แบนเนอร์ทั้งหมด',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'supports' => ['title', 'thumbnail', 'page-attributes'],
        'menu_icon' => 'dashicons-images-alt2',
        'capability_type' => 'post',
    ]);
});

// Recommended-size hint under the featured image box, only for this post type.
add_filter('admin_post_thumbnail_html', function ($content, $post_id) {
    if (get_post_type($post_id) === 'hero_slide') {
        $content .= '<p class="description" style="margin-top:8px;">💡 แนะนำใช้ภาพแนวนอน (Landscape) ขนาดประมาณ 1600×800 พิกเซล หรืออัตราส่วนใกล้เคียง 2:1 เพื่อความคมชัดบนทุกอุปกรณ์</p>';
    }
    return $content;
}, 10, 2);

add_action('rest_api_init', function () {
    register_rest_route('nexus/v1', '/hero-slides', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $slides = get_posts([
                'post_type' => 'hero_slide',
                'post_status' => 'publish',
                'numberposts' => 10,
                'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
            ]);

            return array_map(function ($post) {
                $image_id = get_post_thumbnail_id($post->ID);
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'badge' => get_post_meta($post->ID, 'badge', true),
                    'subtitle' => get_post_meta($post->ID, 'subtitle', true),
                    'price' => get_post_meta($post->ID, 'price', true),
                    'unit' => get_post_meta($post->ID, 'unit', true),
                    'original_price' => get_post_meta($post->ID, 'original_price', true),
                    'discount_badge' => get_post_meta($post->ID, 'discount_badge', true),
                    'cta_primary' => get_post_meta($post->ID, 'cta_primary', true),
                    'cta_secondary' => get_post_meta($post->ID, 'cta_secondary', true),
                    'href' => get_post_meta($post->ID, 'href', true) ?: '/products',
                    'image' => $image_id ? wp_get_attachment_image_url($image_id, 'large') : null,
                ];
            }, $slides);
        },
    ]);
});

// --- wp-admin meta box: friendly slide fields ---

add_action('add_meta_boxes', function () {
    add_meta_box('nexus_hero_slide_fields', 'รายละเอียดแบนเนอร์', 'nexus_render_hero_slide_meta_box', 'hero_slide', 'normal', 'high');
});

function nexus_render_hero_slide_meta_box(WP_Post $post) {
    wp_nonce_field('nexus_save_hero_slide', 'nexus_hero_slide_nonce');
    $badge = get_post_meta($post->ID, 'badge', true);
    $subtitle = get_post_meta($post->ID, 'subtitle', true);
    $price = get_post_meta($post->ID, 'price', true);
    $unit = get_post_meta($post->ID, 'unit', true);
    $original_price = get_post_meta($post->ID, 'original_price', true);
    $discount_badge = get_post_meta($post->ID, 'discount_badge', true);
    $cta_primary = get_post_meta($post->ID, 'cta_primary', true);
    $cta_secondary = get_post_meta($post->ID, 'cta_secondary', true);
    $href = get_post_meta($post->ID, 'href', true);
    ?>
    <p class="description">ชื่อเรื่อง (Title) ด้านบนคือหัวข้อหลักที่แสดงบนแบนเนอร์ ส่วน "ลำดับ" ในกล่อง Page Attributes ทางขวาใช้กำหนดลำดับการแสดงสไลด์ (เลขน้อยแสดงก่อน) ตั้งสถานะเป็นแบบร่างเพื่อซ่อนสไลด์ชั่วคราวโดยไม่ต้องลบ</p>
    <table class="form-table">
        <tr>
            <th><label for="nexus_hs_badge">ป้ายข้อความเล็ก (Badge)</label></th>
            <td><input type="text" name="nexus_hs_badge" id="nexus_hs_badge" value="<?php echo esc_attr($badge); ?>" class="large-text" placeholder="เช่น 🔥 ดีลนายหน้าฮิตอันดับ 1 • StreamVip Official (★ 4.9)" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_subtitle">คำอธิบายย่อย</label></th>
            <td><textarea name="nexus_hs_subtitle" id="nexus_hs_subtitle" rows="3" class="large-text"><?php echo esc_textarea($subtitle); ?></textarea></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_price">ราคา</label></th>
            <td><input type="text" name="nexus_hs_price" id="nexus_hs_price" value="<?php echo esc_attr($price); ?>" class="regular-text" placeholder="เช่น ฿39" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_unit">หน่วย/รายละเอียดราคา</label></th>
            <td><input type="text" name="nexus_hs_unit" id="nexus_hs_unit" value="<?php echo esc_attr($unit); ?>" class="regular-text" placeholder="เช่น / 30 วัน" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_original_price">ราคาเดิม (แสดงขีดฆ่า)</label></th>
            <td><input type="text" name="nexus_hs_original_price" id="nexus_hs_original_price" value="<?php echo esc_attr($original_price); ?>" class="regular-text" placeholder="เช่น ฿159" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_discount_badge">ป้ายส่วนลด</label></th>
            <td><input type="text" name="nexus_hs_discount_badge" id="nexus_hs_discount_badge" value="<?php echo esc_attr($discount_badge); ?>" class="regular-text" placeholder="เช่น ลด 75%" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_cta_primary">ข้อความปุ่มหลัก</label></th>
            <td><input type="text" name="nexus_hs_cta_primary" id="nexus_hs_cta_primary" value="<?php echo esc_attr($cta_primary); ?>" class="regular-text" placeholder="เช่น สั่งซื้อผ่านนายหน้า ฿39" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_cta_secondary">ข้อความปุ่มรอง</label></th>
            <td><input type="text" name="nexus_hs_cta_secondary" id="nexus_hs_cta_secondary" value="<?php echo esc_attr($cta_secondary); ?>" class="regular-text" placeholder="เช่น ดูแพ็กเกจทั้งหมด" /></td>
        </tr>
        <tr>
            <th><label for="nexus_hs_href">ลิงก์ปุ่มหลัก</label></th>
            <td><input type="text" name="nexus_hs_href" id="nexus_hs_href" value="<?php echo esc_attr($href); ?>" class="regular-text" placeholder="/products" /></td>
        </tr>
    </table>
    <?php
}

add_action('save_post_hero_slide', function ($post_id) {
    if (!isset($_POST['nexus_hero_slide_nonce']) || !wp_verify_nonce($_POST['nexus_hero_slide_nonce'], 'nexus_save_hero_slide')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $fields = [
        'nexus_hs_badge' => 'badge',
        'nexus_hs_price' => 'price',
        'nexus_hs_unit' => 'unit',
        'nexus_hs_original_price' => 'original_price',
        'nexus_hs_discount_badge' => 'discount_badge',
        'nexus_hs_cta_primary' => 'cta_primary',
        'nexus_hs_cta_secondary' => 'cta_secondary',
        'nexus_hs_href' => 'href',
    ];
    foreach ($fields as $post_key => $meta_key) {
        if (isset($_POST[$post_key])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        }
    }
    if (isset($_POST['nexus_hs_subtitle'])) {
        update_post_meta($post_id, 'subtitle', sanitize_textarea_field($_POST['nexus_hs_subtitle']));
    }
});
