<?php
define('ABSPATH', __DIR__ . '/');
$options_store = ['jl_wp_plugins_pack_poll_votes' => ['lunch' => [0 => 1]]];
function add_shortcode() {}
function add_action() {}
function shortcode_atts($defaults, $atts) { return array_merge($defaults, $atts); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim($value); }
function get_option($key, $default = false) { global $options_store; return $options_store[$key] ?? $default; }
function update_option($key, $value) { global $options_store; $options_store[$key] = $value; return true; }
function absint($value) { return abs((int) $value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return '/wp-admin/' . $path; }
function wp_json_encode($value) { return json_encode($value); }
function wp_nonce_field() {}
function current_user_can($capability) { return $capability === 'manage_options'; }
function submit_button($label) { echo '<button>' . esc_html($label) . '</button>'; }

require_once __DIR__ . '/../includes/class-jl-wp-plugins-pack-baby-poll.php';
$poll = new JL_WP_Plugins_Pack_Baby_Poll();

ob_start();
$poll->admin_page();
$before = ob_get_clean();
if (strpos($before, 'Choice 1 (label unavailable) votes') === false) {
    throw new RuntimeException('Legacy votes should remain editable before the shortcode is viewed.');
}

$poll->render(['id' => 'lunch', 'question' => 'What should we eat?', 'options' => 'Pizza|Tacos|Burgers']);
ob_start();
$poll->admin_page();
$after = ob_get_clean();
foreach (['Pizza', 'Tacos', 'Burgers'] as $index => $label) {
    if (strpos($after, $label . ' votes <input type="number" min="0" name="counts[' . $index . ']"') === false) {
        throw new RuntimeException('Missing named vote field for ' . $label);
    }
}
if ($options_store['jl_wp_plugins_pack_poll_votes']['lunch'] !== [0 => 1]) {
    throw new RuntimeException('Rendering the shortcode changed vote totals.');
}
echo "Poll admin regression passed.\n";
