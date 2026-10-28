<?php
/** Run with: php tests/ask-writing-regression.php */

$test_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jl-ask-writing-' . getmypid() . DIRECTORY_SEPARATOR;
define('ABSPATH', $test_root);
mkdir($test_root);

class Json_Response extends Exception {
    public $data;
    public $status;
    public function __construct($data, $status) {
        $this->data = $data;
        $this->status = $status;
    }
}

$options = ['jl_wp_plugins_pack_ai_post_settings' => ['openai_api_key' => 'test-key']];
$posts = [];
$fail_checkpoint = false;

class Test_Wpdb {
    public $posts = 'wp_posts';
    public $options = 'wp_options';
    public $last_error = '';
    public function prepare($sql, ...$args) {
        return [$sql, count($args) === 1 && is_array($args[0]) ? $args[0] : $args];
    }
    public function get_col($prepared) {
        global $posts;
        $args = $prepared[1];
        $cursor = (int) end($args);
        $types = array_slice($args, 1, -1);
        $ids = array_keys(array_filter($posts, static function ($post) use ($cursor, $types) {
            return $post->ID > $cursor && $post->post_status === 'publish' && in_array($post->post_type, $types, true);
        }));
        sort($ids);
        return array_slice($ids, 0, 5);
    }
    public function query($prepared) {
        global $options;
        [$name, $value] = $prepared[1];
        if (isset($options[$name]) && $options[$name] === $value) { unset($options[$name]); }
    }
}
$wpdb = new Test_Wpdb();

function add_action() {}
function add_shortcode() {}
function current_user_can() { return true; }
function check_ajax_referer() {}
function wp_generate_uuid4() { return uniqid('', true); }
function get_option($name, $default = false) { global $options; return $options[$name] ?? $default; }
function add_option($name, $value) { global $options; if (isset($options[$name])) { return false; } $options[$name] = $value; return true; }
function update_option($name, $value) {
    global $options, $fail_checkpoint;
    if ($name === JL_WP_Plugins_Pack_Ask_Writing::STATE_OPTION && $fail_checkpoint && !empty($value['offset'])) {
        $fail_checkpoint = false;
        return false;
    }
    $options[$name] = $value;
    return true;
}
function delete_option($name) { global $options; unset($options[$name]); }
function wp_cache_delete() {}
function wp_send_json_error($data, $status) { throw new Json_Response($data, $status); }
function wp_send_json_success($data) { throw new Json_Response($data, 200); }
function wp_date($format) { return date($format); }
function wp_count_posts($type) {
    global $posts;
    $count = count(array_filter($posts, static function ($post) use ($type) { return $post->post_type === $type && $post->post_status === 'publish'; }));
    return (object) ['publish' => $count];
}
function post_type_exists() { return true; }
function wp_mkdir_p($path) { return mkdir($path, 0777, true); }
function trailingslashit($path) { return rtrim($path, '/\\') . DIRECTORY_SEPARATOR; }
function absint($value) { return abs((int) $value); }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function get_the_title($post) { return 'Post ' . $post->ID; }
function get_permalink($post) { return 'https://example.test/' . $post->ID; }
function get_post_time() { return '2026-10-28T00:00:00Z'; }
function strip_shortcodes($text) { return $text; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function get_bloginfo() { return 'UTF-8'; }
function is_multisite() { return false; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_remote_post($url, $args) {
    $texts = json_decode($args['body'], true)['input'];
    return ['data' => array_map(static function ($index) { return ['index' => $index, 'embedding' => [1, 0]]; }, array_keys($texts))];
}
function is_wp_error() { return false; }
function wp_remote_retrieve_response_code() { return 200; }
function wp_remote_retrieve_body($response) { return json_encode($response); }

require dirname(__DIR__) . '/includes/class-jl-wp-plugins-pack-ask-writing.php';
$plugin = new JL_WP_Plugins_Pack_Ask_Writing();
$data_dir = $test_root . 'ask-data' . DIRECTORY_SEPARATOR;

function check($condition, $message) { if (!$condition) { throw new Exception($message); } }
function step() {
    global $plugin, $options;
    try { $plugin->ajax_sync_step(); } catch (Json_Response $response) {
        unset($options[JL_WP_Plugins_Pack_Ask_Writing::SYNC_LOCK_OPTION]);
        return $response;
    }
    throw new Exception('No sync response');
}
function seed_posts($count) {
    global $posts, $options, $data_dir;
    $posts = [];
    for ($id = 1; $id <= $count; $id++) {
        $posts[$id] = (object) ['ID' => $id, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'post_content' => 'Use PowerShell<br>for scripting.'];
    }
    unset($options[JL_WP_Plugins_Pack_Ask_Writing::STATE_OPTION], $options[JL_WP_Plugins_Pack_Ask_Writing::LOG_OPTION]);
    foreach ([JL_WP_Plugins_Pack_Ask_Writing::BUILD_FILE, JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE] as $name) {
        if (is_file($data_dir . $name)) { unlink($data_dir . $name); }
    }
}
function indexed_ids() {
    global $data_dir;
    $lines = file($data_dir . JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map(static function ($line) { return json_decode($line, true)['post_id']; }, $lines);
}

try {
    seed_posts(6);
    check(step()->data['done'] === false, 'First batch should remain active');
    $posts[1]->post_status = 'draft';
    check(step()->data['done'] === true, 'Second batch should finish');
    check(indexed_ids() === [1, 2, 3, 4, 5, 6], 'Changing an earlier post must not skip ID 6');

    seed_posts(6);
    $fail_checkpoint = true;
    check(step()->status === 500, 'Simulated checkpoint failure should return an error');
    check(step()->data['done'] === false, 'Retry should restore and commit the first batch');
    check(step()->data['done'] === true, 'Retry should finish the remaining batch');
    check(indexed_ids() === [1, 2, 3, 4, 5, 6], 'Retried append must not duplicate passages');

    seed_posts(2);
    mkdir($data_dir . JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE);
    check(step()->status === 500, 'Blocked final rename should return an error');
    rmdir($data_dir . JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE);
    check(step()->data['done'] === true, 'Retry should publish without appending again');
    check(indexed_ids() === [1, 2], 'Finalization retry must not duplicate passages');

    seed_posts(2);
    mkdir($data_dir . JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE);
    check(step()->status === 500, 'Blocked final rename should preserve its checkpoint');
    rmdir($data_dir . JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE);
    rename($data_dir . JL_WP_Plugins_Pack_Ask_Writing::BUILD_FILE, $data_dir . JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE);
    check(step()->data['done'] === true, 'Retry should recognize a rename completed before interruption');
    check(indexed_ids() === [1, 2], 'Post-rename retry must not duplicate passages');

    $method = new ReflectionMethod($plugin, 'clean_content');
    check($method->invoke($plugin, 'Use PowerShell<br>for scripting.') === "Use PowerShell\nfor scripting.", 'Opening br must preserve a separator');
    check($method->invoke($plugin, 'A<br />B') === "A\nB", 'Self-closing br must preserve a separator');
    echo "Ask Writing regressions passed.\n";
} finally {
    foreach ([JL_WP_Plugins_Pack_Ask_Writing::BUILD_FILE, JL_WP_Plugins_Pack_Ask_Writing::INDEX_FILE, '.htaccess', 'index.php'] as $name) {
        if (is_file($data_dir . $name)) { unlink($data_dir . $name); }
    }
    if (is_dir($data_dir)) { rmdir($data_dir); }
    if (is_dir($test_root . 'ask-data')) { rmdir($test_root . 'ask-data'); }
    if (is_dir($test_root)) { rmdir($test_root); }
}
