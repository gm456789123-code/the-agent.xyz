<?php
/**
 * Plugin Name: Nexus Payments (Stripe Checkout)
 * Description: Creates Stripe Checkout Sessions for pending orders and marks
 * them paid via a signature-verified webhook. Calls Stripe's REST API
 * directly via wp_remote_post (no SDK/Composer, matching this codebase's
 * convention). Inactive (returns a clear 503) until STRIPE_SECRET_KEY /
 * STRIPE_WEBHOOK_SECRET are set as environment variables.
 */

require_once __DIR__ . '/nexus-security.php';

function nexus_find_order_by_code(string $order_code): ?WP_Post {
    $orders = get_posts([
        'post_type' => 'nexus_order',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_query' => [['key' => 'order_code', 'value' => strtoupper($order_code), 'compare' => '=']],
    ]);
    return $orders[0] ?? null;
}

add_action('rest_api_init', function () {
    register_rest_route('nexus/v1', '/checkout', [
        'methods' => 'POST',
        'permission_callback' => 'nexus_require_member',
        'args' => [
            'order_code' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('checkout_create', 10, 60)) {
                return new WP_Error('rate_limit_exceeded', 'คุณทำรายการบ่อยเกินไป กรุณารอสักครู่', ['status' => 429]);
            }

            $order_code = sanitize_text_field($request->get_param('order_code'));
            $order = nexus_find_order_by_code($order_code);
            if (!$order) {
                return new WP_Error('not_found', 'ไม่พบคำสั่งซื้อนี้', ['status' => 404]);
            }

            $user = nexus_get_user_from_token($request);
            if (!$user) return new WP_Error('authentication_required', 'กรุณาเข้าสู่ระบบ', ['status' => 401]);

            $owner_id = (int) get_post_meta($order->ID, 'customer_user_id', true);
            if ($owner_id !== (int) $user->ID) {
                return new WP_Error('forbidden', 'คุณไม่มีสิทธิ์เข้าถึงคำสั่งซื้อนี้', ['status' => 403]);
            }

            $status = get_post_meta($order->ID, 'status', true) ?: 'pending_payment';
            if ($status !== 'pending_payment') {
                return new WP_Error('invalid_order_status', 'คำสั่งซื้อนี้ไม่สามารถชำระเงินได้ในสถานะปัจจุบัน', ['status' => 409]);
            }

            $secret_key = getenv('STRIPE_SECRET_KEY');
            if (!$secret_key) {
                return new WP_Error('payment_not_configured', 'ระบบชำระเงินยังไม่เปิดใช้งาน กรุณาติดต่อแอดมิน', ['status' => 503]);
            }

            $amount = (float) get_post_meta($order->ID, 'amount', true);
            $unit_amount = (int) round($amount * 100);
            if ($unit_amount <= 0) {
                return new WP_Error('invalid_amount', 'ยอดชำระเงินไม่ถูกต้อง กรุณาติดต่อแอดมิน', ['status' => 500]);
            }

            $product_title = get_post_meta($order->ID, 'product_title', true) ?: 'NEXUS.DEALS Order';

            $body = [
                'mode' => 'payment',
                'client_reference_id' => $order_code,
                'metadata' => ['order_code' => $order_code],
                'success_url' => NEXUS_FRONTEND_URL . '/order-success?order=' . rawurlencode($order_code),
                'cancel_url' => NEXUS_FRONTEND_URL . '/order-cancelled?order=' . rawurlencode($order_code),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'thb',
                        'unit_amount' => $unit_amount,
                        'product_data' => ['name' => $product_title],
                    ],
                ]],
            ];

            $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
                'headers' => ['Authorization' => 'Basic ' . base64_encode($secret_key . ':')],
                'body' => $body,
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                error_log('Stripe checkout session request failed: ' . $response->get_error_message());
                return new WP_Error('payment_gateway_error', 'ไม่สามารถเชื่อมต่อระบบชำระเงินได้ กรุณาลองใหม่', ['status' => 502]);
            }

            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ($code !== 200 || empty($data['url']) || empty($data['id'])) {
                error_log('Stripe checkout session error (HTTP ' . $code . '): ' . wp_remote_retrieve_body($response));
                return new WP_Error('payment_gateway_error', 'ไม่สามารถเชื่อมต่อระบบชำระเงินได้ กรุณาลองใหม่', ['status' => 502]);
            }

            update_post_meta($order->ID, 'stripe_checkout_session_id', $data['id']);

            return rest_ensure_response(['checkout_url' => $data['url']]);
        },
    ]);

    register_rest_route('nexus/v1', '/stripe/webhook', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $secret = getenv('STRIPE_WEBHOOK_SECRET');
            if (!$secret) {
                error_log('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured');
                return new WP_Error('webhook_not_configured', 'Not configured', ['status' => 503]);
            }

            $payload = $request->get_body();
            $sig_header = (string) $request->get_header('stripe_signature');

            $timestamp = null;
            $signatures = [];
            foreach (explode(',', $sig_header) as $pair) {
                $parts = explode('=', $pair, 2);
                if (count($parts) !== 2) continue;
                [$key, $value] = $parts;
                if ($key === 't') $timestamp = $value;
                if ($key === 'v1') $signatures[] = $value;
            }

            if (!$timestamp || empty($signatures)) {
                error_log('Stripe webhook rejected: missing timestamp or signature');
                return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 400]);
            }
            if (abs(time() - (int) $timestamp) > 300) {
                error_log('Stripe webhook rejected: stale timestamp');
                return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 400]);
            }

            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
            $verified = false;
            foreach ($signatures as $sig) {
                if (hash_equals($expected, $sig)) {
                    $verified = true;
                    break;
                }
            }
            if (!$verified) {
                error_log('Stripe webhook rejected: signature mismatch');
                return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 400]);
            }

            $event = json_decode($payload, true);
            if (!is_array($event) || ($event['type'] ?? '') !== 'checkout.session.completed') {
                return rest_ensure_response(['received' => true]);
            }

            $session = $event['data']['object'] ?? [];
            $order_code = $session['client_reference_id'] ?? ($session['metadata']['order_code'] ?? null);
            if (!$order_code) {
                error_log('Stripe webhook: checkout.session.completed with no order_code');
                return rest_ensure_response(['received' => true]);
            }

            $order = nexus_find_order_by_code($order_code);
            if (!$order) {
                error_log("Stripe webhook: order not found for order_code={$order_code}");
                return rest_ensure_response(['received' => true]);
            }

            $status = get_post_meta($order->ID, 'status', true) ?: 'pending_payment';
            if ($status === 'pending_payment') {
                update_post_meta($order->ID, 'status', 'paid');
                update_post_meta($order->ID, 'stripe_payment_intent_id', $session['payment_intent'] ?? '');
            }

            return rest_ensure_response(['received' => true]);
        },
    ]);
});
