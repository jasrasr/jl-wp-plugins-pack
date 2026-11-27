<?php
define('ABSPATH', __DIR__ . '/');
define('JL_WP_PLUGINS_PACK_PLUGIN_DIR', dirname(__DIR__) . '/');
define('JL_WP_PLUGINS_PACK_PLUGIN_FILE', dirname(__DIR__) . '/jl-wp-plugins-pack.php');
define('JL_WP_PLUGINS_PACK_VERSION', '1.5.5');

$options = [];
$post_meta = [];
$posts = [7 => (object) ['ID' => 7, 'post_type' => 'post', 'post_status' => 'publish'], 8 => (object) ['ID' => 8, 'post_type' => 'page', 'post_status' => 'publish']];
$hooks = [];
$requests = [];
$query_vars = [];
$is_404 = false;

function add_action($name, $callback, $priority = 10, $args = 1) { global $hooks; $hooks[$name][] = $callback; }
function add_filter($name, $callback, $priority = 10, $args = 1) { global $hooks; $hooks[$name][] = $callback; }
function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
function update_option($key, $value, $autoload = null) { global $options; $options[$key] = $value; return true; }
function get_site_option($key, $default = false) { return $default; }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function get_post_type($id) { $post = get_post($id); return $post ? $post->post_type : false; }
function get_post_status($id) { $post = get_post($id); return $post ? $post->post_status : false; }
function get_post_meta($id, $key, $single = false) { global $post_meta; return $post_meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $post_meta; $post_meta[$id][$key] = $value; return true; }
function get_permalink($id) { return 'https://example.com/post/' . $id . '/'; }
function get_the_title($id) { return 'Title ' . $id; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function esc_url_raw($value) { return $value; }
function esc_url($value) { return $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_parse_url($value, $part = -1) { return parse_url($value, $part); }
function untrailingslashit($value) { return rtrim($value, '/'); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function is_admin() { return false; }
function is_front_page() { return !empty($GLOBALS['front_page']); }
function is_404() { global $is_404; return $is_404; }
function set_query_var($key, $value) { global $query_vars; $query_vars[$key] = $value; }
function status_header($code) { $GLOBALS['status_code'] = $code; }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function wp_safe_remote_post($url, $args) { global $requests; $requests[] = [$url, $args]; return ['response' => ['code' => 200], 'body' => '{"status":"success","shorturl":"https://go.example/abc"}']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
class WP_Error { private $message; public function __construct($code, $message) { $this->message = $message; } public function get_error_message() { return $this->message; } }

class Fake_Query {
    private $data;
    public function __construct($data = []) { $this->data = $data; }
    public function get($key) { return $this->data[$key] ?? null; }
    public function set($key, $value) { $this->data[$key] = $value; }
    public function is_search() { return !empty($this->data['search']); }
    public function is_feed() { return false; }
    public function is_home() { return false; }
    public function is_category() { return false; }
    public function is_tag() { return false; }
    public function is_author() { return false; }
    public function is_date() { return false; }
    public function is_post_type_archive() { return false; }
    public function is_tax() { return false; }
    public function is_archive() { return false; }
    public function is_main_query() { return !array_key_exists('main', $this->data) || $this->data['main']; }
}

class Fake_DB {
    public $prefix = 'wp_';
    public $postmeta = 'wp_postmeta';
    public function prepare($sql, ...$args) { return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $sql), $args); }
    public function get_var($sql) { return 'wp_whp_posts_visibility'; }
    public function get_results($sql, $format) {
        if (strpos($sql, 'whp_posts_visibility') !== false) return [['post_id' => 7, 'condition' => 'hide_on_search']];
        return [['post_id' => 8, 'meta_key' => '_whp_hide_on_categories', 'meta_value' => '1']];
    }
}
$wpdb = new Fake_DB();
define('ARRAY_A', 'ARRAY_A');

require_once dirname(__DIR__) . '/includes/class-jl-wp-plugins-pack-site-utilities.php';
require_once dirname(__DIR__) . '/includes/class-jl-wp-plugins-pack-visibility.php';
require_once dirname(__DIR__) . '/includes/class-jl-wp-plugins-pack-konami.php';
require_once dirname(__DIR__) . '/includes/class-jl-wp-plugins-pack-yourls.php';
require_once dirname(__DIR__) . '/includes/class-jl-wp-plugins-pack-custom-404.php';

function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }

expect(!JL_WP_Plugins_Pack_Site_Utilities::enabled('konami'), 'New features must start disabled.');
$options['active_plugins'] = ['wp-konami-code/wp-konami-code.php'];
$options[JL_WP_Plugins_Pack_Site_Utilities::OPTION] = ['konami_enabled' => true];
new JL_WP_Plugins_Pack_Konami();
expect(empty($hooks['wp_enqueue_scripts']), 'Konami must pause while its original is active.');
$options['active_plugins'] = [];
new JL_WP_Plugins_Pack_Konami();
expect(!empty($hooks['wp_enqueue_scripts']), 'Konami did not register after the original was disabled.');

$options[JL_WP_Plugins_Pack_Site_Utilities::OPTION] = ['visibility_enabled' => true];
$options[JL_WP_Plugins_Pack_Site_Utilities::VISIBILITY_OPTION] = [7 => ['search' => true], 8 => []];
$visibility = new JL_WP_Plugins_Pack_Visibility();
$query = new Fake_Query(['search' => true, 'post__in' => [7, 8]]);
$visibility->filter_query($query);
expect($query->get('post__in') === [8], 'Search exclusion did not remove an included post.');
$options[JL_WP_Plugins_Pack_Site_Utilities::VISIBILITY_OPTION][8] = ['hide_on_frontpage' => true];
$GLOBALS['front_page'] = true;
$static_home = new Fake_Query(['main' => true]);
$visibility->filter_query($static_home);
expect(!$static_home->get('post__not_in'), 'A static home page must not exclude its own page.');
$GLOBALS['front_page'] = false;

$import = new ReflectionMethod($visibility = new JL_WP_Plugins_Pack_Site_Utilities(), 'import_hide_posts');
$message = $import->invoke($visibility);
$rules = JL_WP_Plugins_Pack_Site_Utilities::visibility_rules();
expect(!empty($rules[7]['search']) && !empty($rules[7]['hide_on_search']) && !empty($rules[8]['hide_on_categories']), 'Import did not merge old table and postmeta rules with existing search rules.');
expect(strpos($message, 'Hide Posts rules copied') !== false, 'Import result missing.');
$options['whp_data_migrated'] = true;
$options[JL_WP_Plugins_Pack_Site_Utilities::VISIBILITY_OPTION] = [];
$import->invoke($visibility);
$rules = JL_WP_Plugins_Pack_Site_Utilities::visibility_rules();
expect(!empty($rules[7]['hide_on_search']) && empty($rules[8]['hide_on_categories']), 'Stale legacy postmeta was imported after table migration.');

$options[JL_WP_Plugins_Pack_Site_Utilities::OPTION] = ['yourls_enabled' => true, 'yourls_url' => 'https://go.example', 'yourls_key' => 'secret', 'yourls_types' => ['post']];
$yourls = new JL_WP_Plugins_Pack_Yourls();
$link = $yourls->create(7, 'custom');
expect($link === 'https://go.example/abc' && get_post_meta(7, '_yourls_url', true) === $link, 'YOURLS link was not saved.');
expect(count($requests) === 1 && $requests[0][1]['body']['signature'] === 'secret' && $requests[0][1]['body']['keyword'] === 'custom', 'YOURLS API request was wrong.');
expect($yourls->create(7) === $link && count($requests) === 1, 'Existing short link was created twice.');
expect(get_yourls_shortlink(7) === $link && strpos(yourls_display_box(7, false), $link) !== false, 'Legacy YOURLS template tags no longer work.');

$options[JL_WP_Plugins_Pack_Site_Utilities::OPTION] = ['not_found_enabled' => true, 'not_found_page' => 8];
$custom_404 = new JL_WP_Plugins_Pack_Custom_404();
$is_404 = true;
expect($custom_404->template('/theme/404.php') === JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'templates/jl-custom-404.php', 'Custom 404 template was not selected.');
expect($GLOBALS['status_code'] === 404 && $query_vars['jl_custom_404_page_id'] === 8, 'Custom 404 lost its HTTP status or page selection.');

echo "Site utilities regression passed.\n";
