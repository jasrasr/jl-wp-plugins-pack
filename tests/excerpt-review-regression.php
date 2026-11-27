<?php
define('ABSPATH', __DIR__ . '/');
$posts = [
    1 => (object) ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'First', 'post_content' => 'First post content for excerpt generation.', 'post_excerpt' => ''],
    2 => (object) ['ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Second', 'post_content' => 'Second post content for excerpt generation.', 'post_excerpt' => ''],
];
$writes = [];
class Excerpt_Test_DB {
    public $posts = 'wp_posts';
    public function prepare($sql, ...$args) { return $args; }
    public function get_results($args) {
        global $posts;
        [$type, $limit] = $args;
        return array_slice(array_values(array_filter($posts, static function ($post) use ($type) {
            return $post->post_type === $type && $post->post_status === 'publish' && $post->post_excerpt === '';
        })), 0, $limit);
    }
}
$wpdb = new Excerpt_Test_DB();
function add_action() {}
function add_filter() {}
function current_user_can($capability, $id = null) { return $capability === 'manage_options' || ($capability === 'edit_post' && $id !== 99); }
function wp_die($message) { throw new RuntimeException($message); }
function check_admin_referer() {}
function wp_nonce_field() {}
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function absint($value) { return abs((int) $value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function selected() {}
function get_edit_post_link($id) { return '/edit/' . $id; }
function get_the_title($id) { global $posts; return $posts[$id]->post_title; }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function strip_shortcodes($value) { return $value; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function get_bloginfo($field) { return 'UTF-8'; }
function wp_trim_words($value, $count, $more) { return implode(' ', array_slice(explode(' ', $value), 0, $count)); }
function is_wp_error($value) { return false; }
function wp_update_post($data, $error = false) {
    global $posts, $writes;
    $posts[$data['ID']]->post_excerpt = $data['post_excerpt'];
    $writes[] = $data;
    return $data['ID'];
}
require_once __DIR__ . '/../includes/class-jl-wp-plugins-pack.php';
$pack = new JL_WP_Plugins_Pack();
function render_excerpt_page($pack, $post) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $post;
    ob_start();
    $pack->render_page();
    return ob_get_clean();
}
function assert_excerpt($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$html = render_excerpt_page($pack, ['jl_generate_excerpt_preview' => '1', 'post_type' => 'post']);
assert_excerpt(strpos($html, 'Approve Selected') !== false && strpos($html, 'Regenerate All') !== false, 'Review actions are missing.');
assert_excerpt(strpos($html, 'name="items[1][excerpt]"') !== false, 'Editable row is missing.');
assert_excerpt(count($writes) === 0, 'Preview wrote to posts.');
$hash1 = hash('sha256', $posts[1]->post_content);
$hash2 = hash('sha256', $posts[2]->post_content);
$rows = [
    1 => ['excerpt' => 'Edited first excerpt.', 'content_hash' => $hash1],
    2 => ['excerpt' => 'Edited second excerpt.', 'content_hash' => $hash2],
];
$html = render_excerpt_page($pack, ['jl_excerpt_action' => 'approve_selected', 'post_type' => 'post', 'items' => $rows, 'selected' => [1 => '1']]);
assert_excerpt($posts[1]->post_excerpt === 'Edited first excerpt.' && $posts[2]->post_excerpt === '', 'Selected approval saved the wrong rows.');
assert_excerpt(strpos($html, 'name="items[2][excerpt]"') !== false && strpos($html, 'name="items[1][excerpt]"') === false, 'Unapproved row was not retained.');
$posts[2]->post_content = 'Revised second post content with enough words to test a shorter regenerated excerpt after changing the length control.';
$html = render_excerpt_page($pack, ['jl_excerpt_action' => 'approve:2', 'post_type' => 'post', 'items' => [2 => $rows[2]]]);
assert_excerpt($posts[2]->post_excerpt === '' && strpos($html, 'changed since the preview') !== false, 'Stale preview was approved.');
$html = render_excerpt_page($pack, ['jl_excerpt_action' => 'regenerate:2', 'post_type' => 'post', 'word_count' => '10', 'items' => [2 => $rows[2]]]);
assert_excerpt(strpos($html, 'Revised second post content with enough words to test a.') !== false && strpos($html, 'a shorter regenerated') === false && $posts[2]->post_excerpt === '', 'Regeneration did not use current content and length.');
$html = render_excerpt_page($pack, ['jl_excerpt_action' => 'approve_all', 'post_type' => 'post', 'items' => [2 => ['excerpt' => 'Approved after regeneration.', 'content_hash' => hash('sha256', $posts[2]->post_content)]]]);
assert_excerpt($posts[2]->post_excerpt === 'Approved after regeneration.' && count($writes) === 2, 'Approve all failed.');
$html = render_excerpt_page($pack, ['jl_excerpt_action' => 'approve_all', 'post_type' => 'post', 'items' => [2 => $rows[2]]]);
assert_excerpt(count($writes) === 2, 'Existing excerpt was overwritten.');
echo "Excerpt review regression passed.\n";
