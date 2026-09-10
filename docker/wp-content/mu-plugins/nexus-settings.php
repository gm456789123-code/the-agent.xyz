<?php
/**
 * Plugin Name: Nexus Arcade Settings
 * Description: Site-wide singleton settings (flash deal, system status list,
 * agent online count) stored as one WP option, editable from wp-admin and
 * exposed read-only via the public nexus/v1 REST API for the Astro frontend.
 */

define('NEXUS_SETTINGS_OPTION', 'nexus_settings');

function nexus_default_settings(): array {
    return [
        'flash_deal' => [
            'ends_at' => gmdate('c', time() + 4 * 3600 + 35 * 60),
            'percent_off' => 35,
            'promo_code' => 'NEXUS25',
        ],
        'system_status' => [
            ['service' => 'ระบบนายหน้าตรวจสลิป (PromptPay/TrueMoney)', 'status' => 'ONLINE', 'speed' => '5-10 วินาที'],
            ['service' => 'API จัดส่งสตรีมมิ่ง (StreamVip Gateway)', 'status' => 'ONLINE', 'speed' => 'ทันที'],
            ['service' => 'ระบบเติมตรง UID (GameShop API)', 'status' => 'ONLINE', 'speed' => 'ปกติ'],
            ['service' => 'คลังซอฟต์แวร์ & โค้ดคีย์ออโต้', 'status' => 'ONLINE', 'speed' => 'พร้อมส่ง 24/7'],
        ],
        'agents_online' => 8,
        'agents_total' => 10,
    ];
}

function nexus_get_settings(): array {
    $stored = get_option(NEXUS_SETTINGS_OPTION);
    if (!is_array($stored)) {
        $stored = nexus_default_settings();
        update_option(NEXUS_SETTINGS_OPTION, $stored);
    }
    return array_merge(nexus_default_settings(), $stored);
}

add_action('rest_api_init', function () {
    register_rest_route('nexus/v1', '/settings', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            return nexus_get_settings();
        },
    ]);
});

// --- wp-admin settings page (Settings -> Nexus) ---

add_action('admin_menu', function () {
    add_options_page('Nexus Settings', 'Nexus', 'manage_options', 'nexus-settings', 'nexus_render_settings_page');
});

function nexus_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['nexus_settings_nonce']) && wp_verify_nonce($_POST['nexus_settings_nonce'], 'nexus_save_settings')) {
        $settings = nexus_get_settings();

        $settings['flash_deal']['percent_off'] = (int) ($_POST['percent_off'] ?? $settings['flash_deal']['percent_off']);
        $settings['flash_deal']['promo_code'] = sanitize_text_field($_POST['promo_code'] ?? $settings['flash_deal']['promo_code']);
        $hours = (float) ($_POST['ends_in_hours'] ?? 4);
        $settings['flash_deal']['ends_at'] = gmdate('c', time() + (int) ($hours * 3600));

        $settings['agents_online'] = (int) ($_POST['agents_online'] ?? $settings['agents_online']);
        $settings['agents_total'] = (int) ($_POST['agents_total'] ?? $settings['agents_total']);

        $services = $_POST['status_service'] ?? [];
        $statuses = $_POST['status_value'] ?? [];
        $speeds = $_POST['status_speed'] ?? [];
        $system_status = [];
        foreach ($services as $i => $service) {
            if (trim($service) === '') {
                continue;
            }
            $system_status[] = [
                'service' => sanitize_text_field($service),
                'status' => sanitize_text_field($statuses[$i] ?? 'ONLINE'),
                'speed' => sanitize_text_field($speeds[$i] ?? ''),
            ];
        }
        if (!empty($system_status)) {
            $settings['system_status'] = $system_status;
        }

        update_option(NEXUS_SETTINGS_OPTION, $settings);
        echo '<div class="notice notice-success"><p>บันทึกการตั้งค่าแล้ว</p></div>';
    }

    $settings = nexus_get_settings();
    $hours_left = max(0, (strtotime($settings['flash_deal']['ends_at']) - time()) / 3600);
    ?>
    <div class="wrap">
        <h1>Nexus Arcade Settings</h1>
        <form method="post">
            <?php wp_nonce_field('nexus_save_settings', 'nexus_settings_nonce'); ?>

            <h2>Flash Deal</h2>
            <table class="form-table">
                <tr>
                    <th><label for="percent_off">ส่วนลด (%)</label></th>
                    <td><input type="number" name="percent_off" id="percent_off" value="<?php echo esc_attr($settings['flash_deal']['percent_off']); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="promo_code">โค้ดโปรโมชั่น</label></th>
                    <td><input type="text" name="promo_code" id="promo_code" value="<?php echo esc_attr($settings['flash_deal']['promo_code']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="ends_in_hours">หมดเขตใน (ชั่วโมงจากตอนนี้)</label></th>
                    <td>
                        <input type="number" step="0.5" name="ends_in_hours" id="ends_in_hours" value="<?php echo esc_attr(round($hours_left, 1)); ?>" class="small-text" />
                        <p class="description">บันทึกแล้วจะคำนวณเวลาหมดเขตใหม่จากตอนนี้ + จำนวนชั่วโมงนี้</p>
                    </td>
                </tr>
            </table>

            <h2>Agent Online Count</h2>
            <table class="form-table">
                <tr>
                    <th><label for="agents_online">ออนไลน์</label></th>
                    <td><input type="number" name="agents_online" id="agents_online" value="<?php echo esc_attr($settings['agents_online']); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="agents_total">ทั้งหมด</label></th>
                    <td><input type="number" name="agents_total" id="agents_total" value="<?php echo esc_attr($settings['agents_total']); ?>" class="small-text" /></td>
                </tr>
            </table>

            <h2>System Status List</h2>
            <table class="widefat">
                <thead>
                    <tr><th>Service</th><th>Status</th><th>Speed</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($settings['system_status'] as $row): ?>
                    <tr>
                        <td><input type="text" name="status_service[]" value="<?php echo esc_attr($row['service']); ?>" class="large-text" /></td>
                        <td>
                            <select name="status_value[]">
                                <?php foreach (['ONLINE', 'BUSY', 'MAINTENANCE'] as $opt): ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($row['status'], $opt); ?>><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="status_speed[]" value="<?php echo esc_attr($row['speed']); ?>" class="regular-text" /></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><input type="text" name="status_service[]" value="" class="large-text" placeholder="เพิ่มบริการใหม่..." /></td>
                        <td>
                            <select name="status_value[]">
                                <option value="ONLINE">ONLINE</option>
                                <option value="BUSY">BUSY</option>
                                <option value="MAINTENANCE">MAINTENANCE</option>
                            </select>
                        </td>
                        <td><input type="text" name="status_speed[]" value="" class="regular-text" /></td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button('บันทึกการตั้งค่า'); ?>
        </form>
    </div>
    <?php
}
