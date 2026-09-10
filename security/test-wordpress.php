<?php
// Regression checks using actual plugins with synthetic WP/database functions.
$routes = []; $posts = []; $meta = []; $user_meta = []; $transients = [];
define('DAY_IN_SECONDS', 86400);
class AuditDatabase {
    public bool $allowLock = true;
    public bool $locked = false;
    public function prepare($sql, $value) { return str_replace('%s', "'" . $value . "'", $sql); }
    public function get_var($sql) {
        if (str_contains($sql, 'GET_LOCK')) { $this->locked = $this->allowLock; return $this->allowLock ? 1 : 0; }
        if (str_contains($sql, 'RELEASE_LOCK')) { $this->locked = false; return 1; }
        throw new RuntimeException('Unexpected query');
    }
}
$wpdb = new AuditDatabase();
function wp_cache_delete($key, $group) {
    // Persistent object caches store transients here as authoritative data.
    if ($group === 'transient') unset($GLOBALS['transients'][$key]);
}
class WP_User {
    public string $user_login = 'fixture';
    public string $user_email = 'fixture@example.invalid';
    public function __construct(public int $ID) {}
}
class WP_Error { public function __construct(public $code, public $message, public $data = []) {} }
class WP_REST_Request {
    public function __construct(private array $params = [], private array $headers = []) {}
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function get_header($key) { return $this->headers[strtolower($key)] ?? null; }
}
function add_action($hook, $callback, ...$args) { $GLOBALS['hooks'][$hook][] = $callback; if ($hook === 'rest_api_init') $callback(); }
function add_filter(...$args) {}
function remove_filter(...$args) {}
function register_rest_route($namespace, $path, $route) { $GLOBALS['routes'][$path] = $route; }
function __return_true() { return true; }
function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; return true; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_post_time(...$args) { return time() - 5; }
function get_user_meta($id, $key, $single) { return $GLOBALS['user_meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) { $GLOBALS['user_meta'][$id][$key] = $value; }
function delete_user_meta($id, $key) { unset($GLOBALS['user_meta'][$id][$key]); }
function get_users($query) {
    foreach ($GLOBALS['user_meta'] as $id => $data) {
        if (($data[$query['meta_key']] ?? null) === $query['meta_value']) return [new WP_User($id)];
    }
    return [];
}
function rest_ensure_response($v) { return $v; }
function current_user_can(...$args) { return false; }
function wp_authenticate($username, $password) { return $username === 'fixture' && $password === 'fixture-password' ? new WP_User(1) : new WP_Error('invalid', 'Invalid'); }
function wp_insert_post($data, $errors) {
    $id = count($GLOBALS['posts']) + 100;
    $GLOBALS['posts'][$id] = (object) array_merge($data, ['ID' => $id, 'post_date' => date('Y-m-d H:i:s')]);
    $GLOBALS['meta'][$id] = $data['meta_input'];
    return $id;
}
function get_posts($query) {
    $result = [];
    foreach ($GLOBALS['posts'] as $id => $post) {
        if ($post->post_type !== $query['post_type'] || $post->post_status !== $query['post_status']) continue;
        foreach ($query['meta_query'] ?? [] as $condition) {
            if (!is_array($condition)) continue;
            if ((string) get_post_meta($id, $condition['key'], true) !== (string) $condition['value']) continue 2;
        }
        $result[] = $post;
    }
    return array_slice($result, 0, $query['numberposts']);
}
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function request($path, $params = [], $token = '') {
    $route = $GLOBALS['routes'][$path];
    $request = new WP_REST_Request($params, $token ? ['authorization' => 'Bearer ' . $token] : []);
    $permission = ($route['permission_callback'])($request);
    return $permission === true ? ($route['callback'])($request) : $permission;
}
require __DIR__ . '/../docker/wp-content/mu-plugins/nexus-auth.php';
require __DIR__ . '/../docker/wp-content/mu-plugins/nexus-orders.php';
check(true, 'auth and orders load together');
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
for ($i = 0; $i < 5; $i++) nexus_check_rate_limit('test', 5, 60);
check(!nexus_check_rate_limit('test', 5, 60), 'sixth request blocked');
$_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.2';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.3';
check(!nexus_check_rate_limit('test', 5, 60), 'forged proxy headers do not bypass limiter');
$tokens = [1 => str_repeat('a', 64), 2 => str_repeat('b', 64)];
foreach ($tokens as $id => $token) $user_meta[$id] = ['nexus_auth_token' => hash('sha256', $token), 'nexus_auth_token_created' => time()];
$posts[10] = (object) ['ID' => 10, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Fixture'];
$posts[11] = (object) ['ID' => 11, 'post_type' => 'product', 'post_status' => 'draft', 'post_title' => 'Draft'];
$meta[10] = ['price' => 299]; $meta[11] = ['price' => 399];
$params = ['product_id' => 10, 'phone' => '000-000-0000'];
check(is_wp_error(request('/orders', $params)), 'anonymous creation rejected');
check(is_wp_error(request('/orders/track', ['query' => '0000000000'])), 'anonymous tracking rejected');
check(is_wp_error(request('/orders', $params, str_repeat('c', 64))), 'invalid token rejected');
$order = request('/orders', $params, $tokens[1]);
check(is_array($order) && preg_match('/^NX-[A-F0-9]{32}$/', $order['order_code']), 'authenticated creation uses random 128-bit order code');
$own = request('/orders/track', ['query' => '0000000000'], $tokens[1]);
check(is_array($own) && count($own) === 1, 'owner can track normalized phone');
check(!isset($own[0]['customer_phone']), 'tracking omits contact information');
check(is_wp_error(request('/orders/track', ['query' => '0000000000'], $tokens[2])), 'other account cannot track phone');
check(is_wp_error(request('/orders/track', ['query' => $order['order_code']], $tokens[2])), 'other account cannot track order code');
$draft = request('/orders', ['product_id' => 11, 'phone' => '0000000000'], $tokens[1]);
check(is_wp_error($draft) && $draft->code === 'invalid_product', 'draft rejected');
$recent = request('/orders/recent');
check(is_array($recent) && !preg_match('/000/', json_encode(array_column($recent, 'user'))), 'public feed contains no phone digits');
$legacy_id = wp_insert_post(['post_type' => 'nexus_order', 'post_status' => 'publish', 'meta_input' => ['phone' => '0000000001', 'order_code' => 'NX-ABCDEF']], true);
check(is_wp_error(request('/orders/track', ['query' => 'NX-ABCDEF'], $tokens[1])), 'unowned legacy orders remain private');
$user_meta[1]['nexus_auth_token_created'] = time() - 31 * DAY_IN_SECONDS;
check(is_wp_error(request('/orders', $params, $tokens[1])), 'expired token rejected');
$user_meta[1] = ['nexus_auth_token' => hash('sha256', $tokens[1])];
check(is_wp_error(request('/orders', $params, $tokens[1])), 'token with missing creation time rejected');
request('/logout', [], $tokens[2]);
check(is_wp_error(request('/orders', $params, $tokens[2])), 'logout revokes token');
check(!empty($GLOBALS['hooks']['profile_update']), 'profile password changes have a revocation hook');
$user_meta[1] = ['nexus_auth_token' => hash('sha256', $tokens[1]), 'nexus_auth_token_created' => time()];
foreach ($GLOBALS['hooks']['profile_update'] as $hook) $hook(1);
check(is_wp_error(request('/orders', $params, $tokens[1])), 'profile update revokes existing token');
$login = request('/login', ['username' => 'fixture', 'password' => 'fixture-password']);
check(isset($login['token']) && hash('sha256', $login['token']) === $user_meta[1]['nexus_auth_token'], 'login persists a digest rather than the bearer token');
check(!is_wp_error(request('/me', [], $login['token'])), 'issued token authenticates');
foreach ($GLOBALS['hooks']['after_password_reset'] as $hook) $hook(new WP_User(1));
check(is_wp_error(request('/me', [], $login['token'])), 'password reset revokes issued token');
$wpdb->allowLock = false;
check(!nexus_check_rate_limit('lock_failure', 5, 60), 'limiter fails closed when shared lock is unavailable');
