<?php
/**
 * Plugin Name: Nexus Member Auth
 * Description: Registration and authentication REST endpoints (nexus/v1/register, nexus/v1/login) for NEXUS.DEALS
 */

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
            $username = sanitize_user($request->get_param('username'), true);
            $email    = sanitize_email($request->get_param('email'));
            $phone    = sanitize_text_field($request->get_param('phone'));
            $password = $request->get_param('password');

            if (empty($username) || strlen($username) < 4) {
                return new WP_Error('invalid_username', 'ชื่อผู้ใช้ต้องมีอย่างน้อย 4 ตัวอักษร', ['status' => 400]);
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

            return rest_ensure_response([
                'success' => true,
                'message' => 'สมัครสมาชิกสำเร็จ เรียบร้อยแล้ว',
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
            ]);
        },
    ]);
});

