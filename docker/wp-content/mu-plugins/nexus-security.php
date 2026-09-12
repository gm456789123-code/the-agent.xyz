<?php
/** Shared helpers, explicitly required so plugin load order is irrelevant. */

const NEXUS_FRONTEND_URL = 'https://lime-oryx-922373.hostingersite.com';

function nexus_get_client_ip(): string {
    // The origin is directly reachable. Never trust client-supplied proxy headers.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}
function nexus_check_rate_limit(string $action, int $max_attempts = 10, int $decay_seconds = 60): bool {
    global $wpdb;
    $key = 'nexus_rl_' . substr(hash('sha256', $action . '_' . nexus_get_client_ip()), 0, 48);
    // MySQL serializes this read/update across Apache workers and app instances.
    // A lock timeout or database error fails closed instead of bypassing limits.
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $key)) !== 1) return false;
    try {
        // Another worker may have updated the shared state while we waited.
        // Do not delete the transient group: with Redis it IS the counter.
        wp_cache_delete('_transient_' . $key, 'options');
        wp_cache_delete('_transient_timeout_' . $key, 'options');
        wp_cache_delete('notoptions', 'options');
        $window = get_transient($key);
        if (!is_array($window) || ($window['reset'] ?? 0) <= time()) $window = ['count' => 0, 'reset' => time() + $decay_seconds];
        if ($window['count'] >= $max_attempts) return false;
        $window['count']++;
        return (bool) set_transient($key, $window, max(1, $window['reset'] - time()));
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
    }
}
function nexus_require_member(WP_REST_Request $request) {
    return nexus_get_user_from_token($request) ? true : new WP_Error('authentication_required', 'กรุณาเข้าสู่ระบบเพื่อทำรายการ', ['status' => 401]);
}
