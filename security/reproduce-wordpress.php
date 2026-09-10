<?php
// Executes original plugin callbacks with synthetic WordPress fixtures.
// No actual WordPress database, accounts, or remote requests are used.
$routes = []; $posts = []; $meta = []; $evidence = [];
$retest = in_array('--retest', $argv, true);
class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param($name) { return $this->params[$name] ?? null; }
    public function get_header($name) { return null; }
}
class WP_Error {
    public function __construct(public $code, public $message, public $data = []) {}
}
function add_action($hook, $callback) { if ($hook === 'rest_api_init') $callback(); }
function register_rest_route($namespace, $path, $definition) { $GLOBALS['routes'][$path] = $definition; }
function __return_true() { return true; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function is_wp_error($value) { return $value instanceof WP_Error; }
// Synthetic transient store supports the concurrently added rate limiter.
function get_transient($key) { return $GLOBALS['audit_transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['audit_transients'][$key] = $value; return true; }
function wp_insert_post($value, $errors) {
    $id = count($GLOBALS['posts']) + 100;
    $GLOBALS['posts'][$id] = (object) array_merge($value, ['ID' => $id, 'post_date' => date('Y-m-d H:i:s')]);
    $GLOBALS['meta'][$id] = $value['meta_input'];
    return $id;
}
function get_posts($args) {
    $matches = [];
    foreach ($GLOBALS['posts'] as $id => $post) {
        if ($post->post_type !== $args['post_type'] || $post->post_status !== $args['post_status']) continue;
        if (isset($args['meta_query'][0])) {
            $q = $args['meta_query'][0];
            if (($GLOBALS['meta'][$id][$q['key']] ?? null) !== $q['value']) continue;
        }
        $matches[] = $post;
    }
    return array_slice($matches, 0, $args['numberposts']);
}
function get_post_time($format, $gmt, $post) { return time() - 10; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function call_route($path, $params = []) {
    $route = $GLOBALS['routes'][$path];
    $request = new WP_REST_Request($params);
    check(($route['permission_callback'])($request) === true, 'Anonymous permission denied');
    return ($route['callback'])($request);
}
if (in_array('--baseline', $argv, true)) {
    // Immutable Git blob observed before concurrent edits; never user-supplied code.
    $original = shell_exec('git show 6895621e3d664dcd5921d6b2fcb29e801c114a18');
    check(is_string($original) && str_starts_with($original, '<?php'), 'Baseline Git object is unavailable');
    eval(substr($original, 5));
} else {
    require __DIR__ . '/../docker/wp-content/mu-plugins/nexus-orders.php';
}
$posts[10] = (object) ['ID' => 10, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Synthetic product'];
$meta[10] = ['price' => 299];
$posts[11] = (object) ['ID' => 11, 'post_type' => 'product', 'post_status' => 'draft', 'post_title' => 'Synthetic unpublished product'];
$meta[11] = ['price' => 399];
$created = call_route('/orders', ['product_id' => 10, 'phone' => '000000000']);
check(is_array($created) && isset($created['order_code']), 'Anonymous order did not succeed');
$evidence[] = ['finding' => 'WP-AUTH', 'reproduced' => true, 'anonymous_order' => $created];
$draft = call_route('/orders', ['product_id' => 11, 'phone' => '0000000000']);
if ($retest) {
    check($draft instanceof WP_Error && $draft->code === 'invalid_product', 'Draft purchase protection failed');
    $evidence[] = ['finding' => 'WP-DRAFT', 'status' => 'fixed in callback', 'code' => $draft->code];
} else {
    check(is_array($draft) && $draft['product_title'] === 'Synthetic unpublished product', 'Draft purchase did not succeed');
    $evidence[] = ['finding' => 'WP-DRAFT', 'reproduced' => true, 'anonymous_draft_order' => $draft];
}
$tracked = call_route('/orders/track', ['query' => '000000000']);
check(is_array($tracked) && count($tracked) === 1, 'Anonymous phone lookup failed');
$evidence[] = ['finding' => 'WP-TRACK', 'reproduced' => true, 'anonymous_phone_lookup' => $tracked];
$recent = call_route('/orders/recent');
if ($retest) {
    check(!in_array('000000000', array_column($recent, 'user'), true), 'Recent feed still exposes full nine-digit phone');
    $evidence[] = ['finding' => 'WP-PHONE-RECENT', 'status' => 'full-phone exposure fixed in callback', 'recent' => $recent];
    check($tracked[0]['customer_phone'] === '000000000', 'Expected tracking phone exposure not reproduced');
    $evidence[] = ['finding' => 'WP-PHONE-TRACK', 'status' => 'still exposed in tracking callback', 'customer_phone' => $tracked[0]['customer_phone']];
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    for ($i = 0; $i < 5; $i++) check(nexus_check_rate_limit('audit_fixture', 5, 60), 'Limiter blocked too early');
    check(!nexus_check_rate_limit('audit_fixture', 5, 60), 'Limiter did not block sixth request');
    $_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.11';
    check(nexus_check_rate_limit('audit_fixture', 5, 60), 'CF header did not bypass limiter');
    unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.12';
    check(nexus_check_rate_limit('audit_fixture', 5, 60), 'Forwarded header did not bypass limiter');
    $evidence[] = ['finding' => 'WP-RATE-LIMIT', 'sixth_request_blocked' => true, 'same_remote_addr_cf_header_bypass' => true, 'same_remote_addr_xff_header_bypass' => true];
} else {
    check(in_array('000000000', array_column($recent, 'user'), true), 'Nine-digit phone was masked');
    $evidence[] = ['finding' => 'WP-PHONE', 'reproduced' => true, 'recent' => $recent];
}
$invalid = call_route('/orders', ['product_id' => 10, 'phone' => 'invalid']);
check($invalid instanceof WP_Error && $invalid->code === 'invalid_phone', 'Invalid phone control failed');
$evidence[] = ['control' => 'Invalid phone rejected', 'code' => $invalid->code];
echo json_encode(['mode' => in_array('--baseline', $argv, true) ? 'pinned baseline PHP callbacks; synthetic WordPress functions; no database' : 'current PHP callbacks; synthetic WordPress functions; no database', 'evidence' => $evidence], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
