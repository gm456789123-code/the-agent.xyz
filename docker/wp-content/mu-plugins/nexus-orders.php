<?php
/**
 * Plugin Name: Nexus Arcade Orders
 * Description: Order tracking backed entirely by WordPress. Registers a private
 * "order" CPT (managed from wp-admin) plus a small public REST API
 * (nexus/v1) so the Astro frontend can create an order when a customer
 * clicks "buy" and look up its status on the order-tracking page.
 */

add_action('init', function () {
    register_post_type('nexus_order', [
        'label' => 'Orders',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'supports' => ['title', 'custom-fields'],
        'menu_icon' => 'dashicons-clipboard',
        'capability_type' => 'post',
    ]);
});

function nexus_generate_order_code(): string {
    return 'NX-' . strtoupper(substr(uniqid(), -6));
}

add_action('rest_api_init', function () {
    register_rest_route('nexus/v1', '/orders', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'product_id' => ['required' => true, 'type' => 'integer'],
            'phone' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            $product_id = (int) $request->get_param('product_id');
            $phone = sanitize_text_field($request->get_param('phone'));

            if (!preg_match('/^0[0-9]{8,9}$/', $phone)) {
                return new WP_Error('invalid_phone', 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง', ['status' => 400]);
            }

            $product = get_post($product_id);
            if (!$product || $product->post_type !== 'product') {
                return new WP_Error('invalid_product', 'ไม่พบสินค้านี้', ['status' => 404]);
            }

            $order_code = nexus_generate_order_code();
            $price = get_post_meta($product_id, 'price', true);

            $order_id = wp_insert_post([
                'post_type' => 'nexus_order',
                'post_title' => $order_code,
                'post_status' => 'publish',
                'meta_input' => [
                    'order_code' => $order_code,
                    'phone' => $phone,
                    'product_id' => $product_id,
                    'product_title' => $product->post_title,
                    'amount' => $price,
                    'status' => 'pending_payment',
                ],
            ], true);

            if (is_wp_error($order_id)) {
                return new WP_Error('order_failed', 'สร้างคำสั่งซื้อไม่สำเร็จ กรุณาลองใหม่', ['status' => 500]);
            }

            return [
                'order_code' => $order_code,
                'status' => 'pending_payment',
                'product_title' => $product->post_title,
                'amount' => $price,
            ];
        },
    ]);

    register_rest_route('nexus/v1', '/orders/track', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => [
            'query' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            $query = sanitize_text_field($request->get_param('query'));
            $is_order_code = stripos($query, 'NX-') === 0;

            $orders = get_posts([
                'post_type' => 'nexus_order',
                'post_status' => 'publish',
                'numberposts' => $is_order_code ? 1 : 10,
                'meta_query' => [[
                    'key' => $is_order_code ? 'order_code' : 'phone',
                    'value' => $query,
                    'compare' => '=',
                ]],
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            if (empty($orders)) {
                return new WP_Error('not_found', 'ไม่พบคำสั่งซื้อที่ตรงกับข้อมูลนี้', ['status' => 404]);
            }

            return array_map(function ($order) {
                return [
                    'order_code' => get_post_meta($order->ID, 'order_code', true),
                    'product_title' => get_post_meta($order->ID, 'product_title', true),
                    'amount' => get_post_meta($order->ID, 'amount', true),
                    'status' => get_post_meta($order->ID, 'status', true),
                    'created_at' => $order->post_date,
                ];
            }, $orders);
        },
    ]);

    register_rest_route('nexus/v1', '/orders/recent', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $limit = min(20, max(1, (int) ($request->get_param('limit') ?: 8)));

            $orders = get_posts([
                'post_type' => 'nexus_order',
                'post_status' => 'publish',
                'numberposts' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            return array_map(function ($order) {
                $phone = get_post_meta($order->ID, 'phone', true);
                $masked_phone = preg_replace('/^(\d{3})\d{3}(\d{4})$/', '$1-xxx-$2', $phone);

                $seconds_ago = time() - get_post_time('U', true, $order);
                if ($seconds_ago < 60) {
                    $time_label = 'เมื่อสักครู่';
                } elseif ($seconds_ago < 3600) {
                    $time_label = floor($seconds_ago / 60) . ' นาทีที่แล้ว';
                } else {
                    $time_label = floor($seconds_ago / 3600) . ' ชั่วโมงที่แล้ว';
                }

                return [
                    'user' => $masked_phone ?: 'xxx-xxx-xxxx',
                    'item' => get_post_meta($order->ID, 'product_title', true),
                    'amount' => '฿' . get_post_meta($order->ID, 'amount', true),
                    'store' => 'Nexus Arcade',
                    'time' => $time_label,
                ];
            }, $orders);
        },
    ]);
});

// --- wp-admin meta box: manage order status without touching raw meta ---

add_action('add_meta_boxes', function () {
    add_meta_box('nexus_order_fields', 'Order Details', 'nexus_render_order_meta_box', 'nexus_order', 'normal', 'high');
});

function nexus_render_order_meta_box(WP_Post $post) {
    wp_nonce_field('nexus_save_order', 'nexus_order_nonce');
    $phone = get_post_meta($post->ID, 'phone', true);
    $product_title = get_post_meta($post->ID, 'product_title', true);
    $amount = get_post_meta($post->ID, 'amount', true);
    $status = get_post_meta($post->ID, 'status', true) ?: 'pending_payment';
    $statuses = [
        'pending_payment' => 'รอชำระเงิน',
        'paid' => 'ชำระเงินแล้ว',
        'delivered' => 'จัดส่งสำเร็จ',
        'cancelled' => 'ยกเลิก',
    ];
    ?>
    <table class="form-table">
        <tr><th>สินค้า</th><td><?php echo esc_html($product_title); ?></td></tr>
        <tr><th>เบอร์โทร</th><td><?php echo esc_html($phone); ?></td></tr>
        <tr><th>ยอดเงิน</th><td>฿<?php echo esc_html($amount); ?></td></tr>
        <tr>
            <th><label for="nexus_order_status">สถานะ</label></th>
            <td>
                <select name="nexus_order_status" id="nexus_order_status">
                    <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}

add_action('save_post_nexus_order', function ($post_id) {
    if (!isset($_POST['nexus_order_nonce']) || !wp_verify_nonce($_POST['nexus_order_nonce'], 'nexus_save_order')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['nexus_order_status'])) {
        update_post_meta($post_id, 'status', sanitize_text_field($_POST['nexus_order_status']));
    }
});


