<?php
/**
 * Plugin Name: Nexus Credits (Wallet)
 * Description: Credit-balance wallet. Members top up via Stripe Checkout
 * (card / PromptPay — Link isn't available for Thailand-based accounts);
 * purchases in nexus-orders.php debit straight
 * from the balance instead of paying per order. Balance lives in user meta,
 * every change is logged as a nexus_credit_txn post.
 */

require_once __DIR__ . '/nexus-security.php';

add_action('init', function () {
    register_post_type('nexus_credit_txn', [
        'label' => 'Credit Transactions',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'supports' => ['title', 'custom-fields'],
        'menu_icon' => 'dashicons-money-alt',
        'capability_type' => 'post',
    ]);
});

function nexus_get_credit_balance(int $user_id): float {
    return (float) get_user_meta($user_id, 'nexus_credit_balance', true);
}

function nexus_credit_log(int $user_id, string $type, float $amount, string $reference, string $status, ?float $balance_after, string $description) {
    return wp_insert_post([
        'post_type' => 'nexus_credit_txn',
        'post_title' => $reference,
        'post_status' => 'publish',
        'meta_input' => [
            'user_id' => $user_id,
            'type' => $type, // topup | purchase
            'amount' => $amount, // positive for topup, negative for purchase
            'reference' => $reference,
            'status' => $status, // pending | completed
            'balance_after' => $balance_after,
            'description' => $description,
        ],
    ], true);
}

// Debits credit for an instant purchase. Serializes concurrent requests for
// the same user behind a MySQL lock so two orders can't both pass the
// balance check and overspend the same credit.
function nexus_debit_credit(int $user_id, float $amount, string $reference, string $description) {
    global $wpdb;
    $lock_key = 'nexus_credit_' . $user_id;
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_key)) !== 1) {
        return new WP_Error('credit_locked', 'ระบบเครดิตไม่ว่าง กรุณาลองใหม่', ['status' => 503]);
    }
    try {
        $balance = nexus_get_credit_balance($user_id);
        if ($balance < $amount) {
            return new WP_Error('insufficient_credit', 'เครดิตของคุณไม่เพียงพอ กรุณาเติมเครดิตก่อนสั่งซื้อ', ['status' => 402]);
        }
        $new_balance = round($balance - $amount, 2);
        update_user_meta($user_id, 'nexus_credit_balance', $new_balance);
        nexus_credit_log($user_id, 'purchase', -$amount, $reference, 'completed', $new_balance, $description);
        return $new_balance;
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_key));
    }
}

function nexus_find_credit_topup_txn(string $reference): ?WP_Post {
    $txns = get_posts([
        'post_type' => 'nexus_credit_txn',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_query' => [
            ['key' => 'reference', 'value' => $reference, 'compare' => '='],
            ['key' => 'type', 'value' => 'topup', 'compare' => '='],
        ],
    ]);
    return $txns[0] ?? null;
}

// Marks a pending topup as completed and credits the balance. Called from the
// Stripe webhook; re-checks status under the lock so a retried webhook delivery
// (Stripe retries until it gets a 200, and both checkout.session.completed and
// checkout.session.async_payment_succeeded can fire for the same session) never
// credits the same topup twice.
function nexus_complete_credit_topup(string $reference, ?string $stripe_session_id): void {
    global $wpdb;
    $txn = nexus_find_credit_topup_txn($reference);
    if (!$txn) {
        error_log("Stripe webhook: credit topup not found for reference={$reference}");
        return;
    }

    $user_id = (int) get_post_meta($txn->ID, 'user_id', true);
    $amount = (float) get_post_meta($txn->ID, 'amount', true);

    $lock_key = 'nexus_credit_' . $user_id;
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_key)) !== 1) return;
    try {
        if (get_post_meta($txn->ID, 'status', true) !== 'pending') return; // already completed/failed, idempotent no-op
        $new_balance = round(nexus_get_credit_balance($user_id) + $amount, 2);
        update_user_meta($user_id, 'nexus_credit_balance', $new_balance);
        update_post_meta($txn->ID, 'status', 'completed');
        update_post_meta($txn->ID, 'balance_after', $new_balance);
        if ($stripe_session_id) update_post_meta($txn->ID, 'stripe_session_id', $stripe_session_id);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_key));
    }
}

// A delayed payment method (e.g. a bank transfer) can still fail after Checkout
// "completes". Mark the topup failed so it stops showing as pending forever.
function nexus_fail_credit_topup(string $reference): void {
    $txn = nexus_find_credit_topup_txn($reference);
    if (!$txn) return;
    if (get_post_meta($txn->ID, 'status', true) !== 'pending') return;
    update_post_meta($txn->ID, 'status', 'failed');
}

add_action('rest_api_init', function () {
    register_rest_route('nexus/v1', '/credits/balance', [
        'methods' => 'GET',
        'permission_callback' => 'nexus_require_member',
        'callback' => function (WP_REST_Request $request) {
            $user = nexus_get_user_from_token($request);
            return ['balance' => nexus_get_credit_balance($user->ID)];
        },
    ]);

    register_rest_route('nexus/v1', '/credits/topup/status', [
        'methods' => 'GET',
        'permission_callback' => 'nexus_require_member',
        'args' => [
            'reference' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            $user = nexus_get_user_from_token($request);
            $reference = sanitize_text_field($request->get_param('reference'));

            $txns = get_posts([
                'post_type' => 'nexus_credit_txn',
                'post_status' => 'publish',
                'numberposts' => 1,
                'meta_query' => [
                    ['key' => 'reference', 'value' => $reference, 'compare' => '='],
                    ['key' => 'type', 'value' => 'topup', 'compare' => '='],
                    ['key' => 'user_id', 'value' => $user->ID, 'compare' => '='],
                ],
            ]);
            $txn = $txns[0] ?? null;
            if (!$txn) return new WP_Error('not_found', 'ไม่พบรายการเติมเครดิตนี้', ['status' => 404]);

            return [
                'status' => get_post_meta($txn->ID, 'status', true) ?: 'pending',
                'balance' => nexus_get_credit_balance($user->ID),
            ];
        },
    ]);

    register_rest_route('nexus/v1', '/credits/topup', [
        'methods' => 'POST',
        'permission_callback' => 'nexus_require_member',
        'args' => [
            'amount' => ['required' => true, 'type' => 'number'],
            'payment_method' => ['required' => false, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('credit_topup', 10, 60)) {
                return new WP_Error('rate_limit_exceeded', 'คุณทำรายการบ่อยเกินไป กรุณารอสักครู่', ['status' => 429]);
            }

            $amount = (float) $request->get_param('amount');
            if (!is_finite($amount) || $amount < 20 || $amount > 50000) {
                return new WP_Error('invalid_amount', 'กรุณาระบุยอดเติมเครดิตระหว่าง 20 - 50,000 บาท', ['status' => 400]);
            }

            // Only card and PromptPay are available for a Thailand-based Stripe
            // account (Link is explicitly unsupported there). Default to both so
            // Stripe Checkout shows its own method picker if the caller omits one.
            $allowed_methods = ['card', 'promptpay'];
            $payment_method = (string) $request->get_param('payment_method');
            $payment_method_types = in_array($payment_method, $allowed_methods, true)
                ? [$payment_method]
                : $allowed_methods;

            $user = nexus_get_user_from_token($request);
            if (!$user) return new WP_Error('authentication_required', 'กรุณาเข้าสู่ระบบ', ['status' => 401]);

            $secret_key = getenv('STRIPE_SECRET_KEY');
            if (!$secret_key) {
                return new WP_Error('payment_not_configured', 'ระบบเติมเครดิตยังไม่เปิดใช้งาน กรุณาติดต่อแอดมิน', ['status' => 503]);
            }

            $reference = 'TOPUP-' . strtoupper(bin2hex(random_bytes(16)));
            $unit_amount = (int) round($amount * 100);

            // Log the pending topup before calling Stripe: if we called Stripe first
            // and this insert failed afterwards, a successful payment would have no
            // matching record for the webhook to credit against.
            $txn_id = nexus_credit_log($user->ID, 'topup', $amount, $reference, 'pending', null, 'รอชำระเงินเติมเครดิต');
            if (is_wp_error($txn_id)) {
                return new WP_Error('order_failed', 'ไม่สามารถเริ่มการเติมเครดิตได้ กรุณาลองใหม่', ['status' => 500]);
            }

            $body = [
                'mode' => 'payment',
                'payment_method_types' => $payment_method_types,
                'client_reference_id' => $reference,
                'metadata' => ['type' => 'credit_topup', 'reference' => $reference, 'user_id' => $user->ID],
                'success_url' => NEXUS_FRONTEND_URL . '/topup-success?ref=' . rawurlencode($reference),
                'cancel_url' => NEXUS_FRONTEND_URL . '/account',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'thb',
                        'unit_amount' => $unit_amount,
                        'product_data' => ['name' => 'เติมเครดิต NEXUS.DEALS'],
                    ],
                ]],
            ];

            // Idempotency-Key (Stripe's recommended practice for POST requests that
            // create a resource): if this request times out and the caller retries,
            // Stripe returns the original session instead of creating a second one.
            $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
                    'Idempotency-Key' => $reference,
                ],
                'body' => $body,
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                error_log('Stripe topup session request failed: ' . $response->get_error_message());
                update_post_meta($txn_id, 'status', 'failed');
                return new WP_Error('payment_gateway_error', 'ไม่สามารถเชื่อมต่อระบบชำระเงินได้ กรุณาลองใหม่', ['status' => 502]);
            }

            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ($code !== 200 || empty($data['url']) || empty($data['id'])) {
                error_log('Stripe topup session error (HTTP ' . $code . '): ' . wp_remote_retrieve_body($response));
                update_post_meta($txn_id, 'status', 'failed');
                return new WP_Error('payment_gateway_error', 'ไม่สามารถเชื่อมต่อระบบชำระเงินได้ กรุณาลองใหม่', ['status' => 502]);
            }

            update_post_meta($txn_id, 'stripe_session_id', $data['id']);

            return rest_ensure_response(['checkout_url' => $data['url']]);
        },
    ]);

    // Stripe requires this endpoint to keep responding 200 to a retried
    // delivery even after we've already processed the event once.
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
            $event_type = is_array($event) ? ($event['type'] ?? '') : '';

            // PromptPay/card confirm inline (checkout.session.completed), but some
            // payment methods confirm later (checkout.session.async_payment_succeeded /
            // _failed) per Stripe's fulfillment guide: both success events must be
            // handled, and completed can fire before payment actually succeeds.
            $success_events = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];
            $failure_events = ['checkout.session.async_payment_failed', 'checkout.session.expired'];

            if (!in_array($event_type, array_merge($success_events, $failure_events), true)) {
                return rest_ensure_response(['received' => true]);
            }

            $session = $event['data']['object'] ?? [];
            if (($session['metadata']['type'] ?? '') !== 'credit_topup') {
                return rest_ensure_response(['received' => true]);
            }

            $reference = $session['client_reference_id'] ?? ($session['metadata']['reference'] ?? null);
            if (!$reference) {
                error_log('Stripe webhook: credit_topup session with no reference');
                return rest_ensure_response(['received' => true]);
            }

            if (in_array($event_type, $failure_events, true)) {
                nexus_fail_credit_topup($reference);
                return rest_ensure_response(['received' => true]);
            }

            // "completed" can still carry payment_status=unpaid for a payment method
            // that confirms asynchronously; only credit once payment actually landed.
            if (($session['payment_status'] ?? 'unpaid') === 'unpaid') {
                return rest_ensure_response(['received' => true]);
            }

            nexus_complete_credit_topup($reference, $session['id'] ?? null);

            return rest_ensure_response(['received' => true]);
        },
    ]);
});
