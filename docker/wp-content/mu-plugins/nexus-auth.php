<?php
/**
 * Plugin Name: Nexus Member Auth
 * Description: Registration and authentication REST endpoints (nexus/v1/register, nexus/v1/login) for NEXUS.DEALS
 */

require_once __DIR__ . '/nexus-security.php';

add_action('rest_api_init', function () {
    register_rest_route('nexus/v1', '/register', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'username' => ['required' => true, 'type' => 'string'],
            'email'    => ['required' => true, 'type' => 'string'],
            'phone'    => ['required' => true, 'type' => 'string'],
            'password' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('auth_register', 5, 300)) {
                return new WP_Error('rate_limit_exceeded', 'คุณส่งคำขอสมัครสมาชิกถี่เกินไป กรุณารอ 5 นาทีแล้วลองใหม่อีกครั้ง', ['status' => 429]);
            }

            $username = sanitize_user($request->get_param('username'), true);
            $email    = sanitize_email($request->get_param('email'));
            $phone    = sanitize_text_field($request->get_param('phone'));
            $password = $request->get_param('password');

            if (empty($username) || strlen($username) < 4 || strlen($username) > 50) {
                return new WP_Error('invalid_username', 'ชื่อผู้ใช้ต้องมีความยาว 4 - 50 ตัวอักษร', ['status' => 400]);
            }

            if (!validate_username($username)) {
                return new WP_Error('invalid_username_format', 'ชื่อผู้ใช้มีตัวอักษรที่ไม่ได้รับอนุญาต', ['status' => 400]);
            }

            if (username_exists($username)) {
                return new WP_Error('username_taken', 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น', ['status' => 409]);
            }

            if (!is_email($email)) {
                return new WP_Error('invalid_email', 'รูปแบบอีเมลไม่ถูกต้อง', ['status' => 400]);
            }

            if (email_exists($email)) {
                return new WP_Error('email_taken', 'อีเมลนี้ลงทะเบียนแล้ว กรุณาใช้อีเมลอื่น', ['status' => 409]);
            }

            if (!preg_match('/^0[0-9]{8,9}$/', preg_replace('/[-\s]/', '', $phone))) {
                return new WP_Error('invalid_phone', 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (10 หลัก)', ['status' => 400]);
            }

            if (strlen($password) < 8) {
                return new WP_Error('weak_password', 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร', ['status' => 400]);
            }

            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                return new WP_Error('create_user_failed', $user_id->get_error_message(), ['status' => 500]);
            }

            // Save phone and member role
            update_user_meta($user_id, 'nexus_phone', $phone);
            update_user_meta($user_id, 'nexus_registered_at', current_time('mysql'));

            $user = get_user_by('id', $user_id);
            $user->set_role('subscriber');

            $verify_token = bin2hex(random_bytes(32));
            update_user_meta($user_id, 'nexus_email_verify_token', hash('sha256', $verify_token));
            update_user_meta($user_id, 'nexus_email_verify_token_created', time());
            nexus_send_verification_email($user_id, $email, $verify_token);

            return rest_ensure_response([
                'success' => true,
                'message' => 'สมัครสมาชิกสำเร็จ เรียบร้อยแล้ว',
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
            ]);
        },
    ]);

    register_rest_route('nexus/v1', '/login', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'username' => ['required' => true, 'type' => 'string'],
            'password' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('auth_login', 5, 60)) {
                return new WP_Error('rate_limit_exceeded', 'คุณพยายามเข้าสู่ระบบถี่เกินไป กรุณารอ 1 นาทีแล้วลองใหม่อีกครั้ง', ['status' => 429]);
            }

            $username = sanitize_text_field($request->get_param('username'));
            $password = $request->get_param('password');

            $user = wp_authenticate($username, $password);

            if (is_wp_error($user)) {
                return new WP_Error('invalid_credentials', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', ['status' => 401]);
            }

            $token = bin2hex(random_bytes(32));
            update_user_meta($user->ID, 'nexus_auth_token', hash('sha256', $token));
            update_user_meta($user->ID, 'nexus_auth_token_created', time());

            return rest_ensure_response([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'phone' => get_user_meta($user->ID, 'nexus_phone', true),
                ],
            ]);
        },
    ]);

    register_rest_route('nexus/v1', '/me', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $user = nexus_get_user_from_token($request);

            if (!$user) {
                return new WP_Error('invalid_token', 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่', ['status' => 401]);
            }

            return rest_ensure_response([
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'phone' => get_user_meta($user->ID, 'nexus_phone', true),
                'email_verified' => (bool) get_user_meta($user->ID, 'user_email_verified', true),
            ]);
        },
    ]);

    register_rest_route('nexus/v1', '/logout', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $user = nexus_get_user_from_token($request);
            if ($user) {
                delete_user_meta($user->ID, 'nexus_auth_token');
                delete_user_meta($user->ID, 'nexus_auth_token_created');
            }
            return rest_ensure_response(['success' => true]);
        },
    ]);

    register_rest_route('nexus/v1', '/forgot-password', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'login' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('auth_forgot_password', 5, 300)) {
                return new WP_Error('rate_limit_exceeded', 'คุณส่งคำขอบ่อยเกินไป กรุณารอ 5 นาทีแล้วลองใหม่อีกครั้ง', ['status' => 429]);
            }

            $login = sanitize_text_field($request->get_param('login'));
            $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);

            if ($user) {
                $key = get_password_reset_key($user);
                if (!is_wp_error($key)) {
                    $link = NEXUS_FRONTEND_URL . '/reset-password?login=' . rawurlencode($user->user_login) . '&key=' . rawurlencode($key);
                    wp_mail(
                        $user->user_email,
                        'รีเซ็ตรหัสผ่าน NEXUS.DEALS',
                        "คลิกลิงก์ด้านล่างเพื่อตั้งรหัสผ่านใหม่ (ลิงก์นี้ใช้ได้ครั้งเดียว):\n\n{$link}\n\nหากคุณไม่ได้ร้องขอ กรุณาเพิกเฉยต่ออีเมลนี้"
                    );
                }
            }

            // Always return the same generic response, whether or not the account exists,
            // and regardless of mail delivery outcome, to avoid leaking account existence.
            return rest_ensure_response([
                'success' => true,
                'message' => 'หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปแล้ว',
            ]);
        },
    ]);

    register_rest_route('nexus/v1', '/reset-password', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'login' => ['required' => true, 'type' => 'string'],
            'key' => ['required' => true, 'type' => 'string'],
            'new_password' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('auth_reset_password', 5, 300)) {
                return new WP_Error('rate_limit_exceeded', 'คุณลองรีเซ็ตรหัสผ่านบ่อยเกินไป กรุณารอ 5 นาทีแล้วลองใหม่อีกครั้ง', ['status' => 429]);
            }

            $login = sanitize_text_field($request->get_param('login'));
            $key = sanitize_text_field($request->get_param('key'));
            $new_password = $request->get_param('new_password');

            if (strlen($new_password) < 8) {
                return new WP_Error('weak_password', 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร', ['status' => 400]);
            }

            $user = check_password_reset_key($key, $login);
            if (is_wp_error($user)) {
                return new WP_Error('invalid_reset_key', 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุ กรุณาขอลิงก์ใหม่', ['status' => 400]);
            }

            reset_password($user, $new_password);

            return rest_ensure_response([
                'success' => true,
                'message' => 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่',
            ]);
        },
    ]);

    register_rest_route('nexus/v1', '/verify-email', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'uid' => ['required' => true, 'type' => 'integer'],
            'token' => ['required' => true, 'type' => 'string'],
        ],
        'callback' => function (WP_REST_Request $request) {
            $uid = (int) $request->get_param('uid');
            $token = sanitize_text_field($request->get_param('token'));

            if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
                return new WP_Error('invalid_token', 'ลิงก์ยืนยันอีเมลไม่ถูกต้องหรือหมดอายุ', ['status' => 400]);
            }

            $stored = get_user_meta($uid, 'nexus_email_verify_token', true);
            $created = (int) get_user_meta($uid, 'nexus_email_verify_token_created', true);

            if (!$stored || !hash_equals($stored, hash('sha256', $token)) || $created <= 0 || (time() - $created) > NEXUS_VERIFY_TOKEN_TTL_SECONDS) {
                return new WP_Error('invalid_token', 'ลิงก์ยืนยันอีเมลไม่ถูกต้องหรือหมดอายุ', ['status' => 400]);
            }

            update_user_meta($uid, 'user_email_verified', '1');
            delete_user_meta($uid, 'nexus_email_verify_token');
            delete_user_meta($uid, 'nexus_email_verify_token_created');

            return rest_ensure_response(['success' => true, 'message' => 'ยืนยันอีเมลสำเร็จ']);
        },
    ]);

    register_rest_route('nexus/v1', '/resend-verification', [
        'methods' => 'POST',
        'permission_callback' => 'nexus_require_member',
        'callback' => function (WP_REST_Request $request) {
            if (!nexus_check_rate_limit('auth_resend_verification', 3, 300)) {
                return new WP_Error('rate_limit_exceeded', 'คุณขอส่งอีเมลยืนยันบ่อยเกินไป กรุณารอสักครู่', ['status' => 429]);
            }

            $user = nexus_get_user_from_token($request);
            if (!$user) return new WP_Error('authentication_required', 'กรุณาเข้าสู่ระบบ', ['status' => 401]);

            $verify_token = bin2hex(random_bytes(32));
            update_user_meta($user->ID, 'nexus_email_verify_token', hash('sha256', $verify_token));
            update_user_meta($user->ID, 'nexus_email_verify_token_created', time());
            nexus_send_verification_email($user->ID, $user->user_email, $verify_token);

            return rest_ensure_response(['success' => true, 'message' => 'ส่งอีเมลยืนยันอีกครั้งแล้ว']);
        },
    ]);
});

const NEXUS_TOKEN_TTL_SECONDS = 30 * DAY_IN_SECONDS;
const NEXUS_VERIFY_TOKEN_TTL_SECONDS = 48 * HOUR_IN_SECONDS;

function nexus_send_verification_email(int $user_id, string $email, string $token): void {
    $link = NEXUS_FRONTEND_URL . '/verify-email?uid=' . $user_id . '&token=' . rawurlencode($token);
    wp_mail(
        $email,
        'ยืนยันอีเมลของคุณ - NEXUS.DEALS',
        "คลิกลิงก์ด้านล่างเพื่อยืนยันอีเมลของคุณ:\n\n{$link}\n\nลิงก์นี้จะหมดอายุใน 48 ชั่วโมง"
    );
}

function nexus_get_user_from_token(WP_REST_Request $request): ?WP_User {
    $token = $request->get_header('x-nexus-token');
    if (!$token) {
        $auth = $request->get_header('authorization');
        if ($auth && stripos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
        }
    }
    if (!$token || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return null;
    }

    $users = get_users([
        'meta_key' => 'nexus_auth_token',
        'meta_value' => hash('sha256', $token),
        'number' => 1,
    ]);

    if (empty($users)) {
        return null;
    }

    $user = $users[0];
    $created = (int) get_user_meta($user->ID, 'nexus_auth_token_created', true);
    if ($created <= 0 || $created > time() || (time() - $created) > NEXUS_TOKEN_TTL_SECONDS) {
        delete_user_meta($user->ID, 'nexus_auth_token');
        delete_user_meta($user->ID, 'nexus_auth_token_created');
        return null;
    }

    return $user;
}

add_action('after_password_reset', function ($user) {
    delete_user_meta($user->ID, 'nexus_auth_token');
    delete_user_meta($user->ID, 'nexus_auth_token_created');
});
add_action('wp_set_password', function ($password, $user_id) {
    delete_user_meta($user_id, 'nexus_auth_token');
    delete_user_meta($user_id, 'nexus_auth_token_created');
}, 10, 2);

// wp-admin profile edits use wp_update_user, not wp_set_password. Revoking on
// any profile/role change also covers email and account privilege changes.
function nexus_revoke_member_tokens($user_id) {
    delete_user_meta($user_id, 'nexus_auth_token');
    delete_user_meta($user_id, 'nexus_auth_token_created');
}
add_action('profile_update', 'nexus_revoke_member_tokens');
add_action('set_user_role', 'nexus_revoke_member_tokens');
