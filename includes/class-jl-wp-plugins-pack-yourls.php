<?php
if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_Yourls {
    const CLICK_HOOK = 'jl_yourls_refresh_clicks';

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CLICK_HOOK);
    }

    public function __construct() {
        if (!JL_WP_Plugins_Pack_Site_Utilities::enabled('yourls') || JL_WP_Plugins_Pack_Site_Utilities::original_active('yourls-link-creator/yourls-link-creator.php')) {
            return;
        }
        require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/jl-yourls-compat.php';
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_ajax_jl_yourls_create', [$this, 'create_from_admin']);
        add_action('save_post', [$this, 'maybe_create_on_save'], 30, 3);
        add_action('transition_post_status', [$this, 'maybe_create_on_publish'], 30, 3);
        add_filter('get_shortlink', [$this, 'shortlink'], 10, 3);
        add_action('init', [$this, 'schedule_click_refresh']);
        add_action(self::CLICK_HOOK, [$this, 'refresh_clicks']);
    }

    private function allowed($post_id) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        return in_array(get_post_type($post_id), (array) $s['yourls_types'], true) && get_post_status($post_id) === 'publish';
    }

    public function enqueue_admin($hook) {
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('jl-yourls-admin', plugins_url('assets/js/jl-yourls-admin.js', JL_WP_PLUGINS_PACK_PLUGIN_FILE), [], JL_WP_PLUGINS_PACK_VERSION, true);
            wp_add_inline_script('jl-yourls-admin', 'window.jlYourlsAjaxUrl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';', 'before');
        }
    }

    public function meta_boxes() {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        foreach ((array) $s['yourls_types'] as $type) {
            if (post_type_exists($type)) {
                add_meta_box('jl_yourls_link', 'JL YOURLS Link', [$this, 'render_meta_box'], $type, 'side');
            }
        }
    }

    public function render_meta_box($post) {
        if (!$this->allowed($post->ID)) {
            echo '<p>Publish this item before creating a short link.</p>';
            return;
        }
        $link = get_post_meta($post->ID, '_yourls_url', true);
        if ($link) {
            echo '<p><a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link) . '</a></p>';
            echo '<p>Recorded clicks: ' . esc_html((string) absint(get_post_meta($post->ID, '_yourls_clicks', true))) . '</p>';
            return;
        }
        echo '<p><label>Optional keyword <input type="text" class="widefat jl-yourls-keyword" maxlength="200"></label></p>';
        echo '<p><button type="button" class="button jl-yourls-create" data-post-id="' . esc_attr((string) $post->ID) . '" data-nonce="' . esc_attr(wp_create_nonce('jl_yourls_create_' . $post->ID)) . '">Create short link</button></p><p class="jl-yourls-result" role="status"></p>';
    }

    public function create_from_admin() {
        $id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$id || !current_user_can('edit_post', $id)) {
            wp_send_json_error(['message' => 'You cannot create a short link for this item.'], 403);
        }
        check_ajax_referer('jl_yourls_create_' . $id, 'nonce');
        $keyword = isset($_POST['keyword']) && is_string($_POST['keyword']) ? sanitize_title(wp_unslash($_POST['keyword'])) : '';
        $result = $this->create($id, $keyword);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }
        wp_send_json_success(['url' => $result]);
    }

    public function maybe_create_on_save($id, $post, $update) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        if (empty($s['yourls_auto']) || wp_is_post_revision($id) || wp_is_post_autosave($id) || !$this->allowed($id) || get_post_meta($id, '_yourls_url', true)) {
            return;
        }
        $this->create($id);
    }

    public function maybe_create_on_publish($new_status, $old_status, $post) {
        if ($new_status === 'publish' && $old_status !== 'publish' && !empty(JL_WP_Plugins_Pack_Site_Utilities::settings()['yourls_auto'])) {
            $this->create($post->ID);
        }
    }

    public function schedule_click_refresh() {
        if (!wp_next_scheduled(self::CLICK_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CLICK_HOOK);
        }
    }

    public function refresh_clicks() {
        global $wpdb;
        $cursor = absint(get_option('jl_yourls_click_cursor', 0));
        $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id > %d ORDER BY post_id ASC LIMIT %d", '_yourls_url', $cursor, 5));
        if (!$ids) {
            update_option('jl_yourls_click_cursor', 0, false);
            return;
        }
        foreach ($ids as $id) {
            $id = absint($id);
            $url = get_post_meta($id, '_yourls_url', true);
            if (!$url) {
                continue;
            }
            $data = $this->api('url-stats', ['shorturl' => $url], 5);
            if (!is_wp_error($data) && isset($data['link']['clicks'])) {
                update_post_meta($id, '_yourls_clicks', absint($data['link']['clicks']));
            }
        }
        update_option('jl_yourls_click_cursor', absint(end($ids)), false);
    }

    public function shortlink($shortlink, $id, $context) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        if (!$id && is_singular()) {
            $id = get_queried_object_id();
        }
        if (empty($s['yourls_shortlink']) || !$id) {
            return $shortlink;
        }
        $custom = get_post_meta(absint($id), '_yourls_url', true);
        return $custom ? esc_url_raw($custom) : $shortlink;
    }

    public function create($id, $keyword = '') {
        if (!$this->allowed($id)) {
            return new WP_Error('jl_yourls_status', 'The item must be published and its post type enabled.');
        }
        $existing = get_post_meta($id, '_yourls_url', true);
        if ($existing) {
            return $existing;
        }
        $data = $this->api('shorturl', ['url' => get_permalink($id), 'title' => get_the_title($id)] + ($keyword !== '' ? ['keyword' => $keyword] : []), 15);
        if (is_wp_error($data)) {
            return $data;
        }
        $url = is_array($data) && !empty($data['shorturl']) ? esc_url_raw($data['shorturl']) : '';
        if (!$url || !in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return new WP_Error('jl_yourls_response', 'YOURLS did not return a valid short URL.');
        }
        update_post_meta($id, '_yourls_url', $url);
        update_post_meta($id, '_yourls_clicks', 0);
        if ($keyword !== '') {
            update_post_meta($id, '_yourls_keyword', $keyword);
        }
        return $url;
    }

    private function api($action, $parameters, $timeout) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $base = untrailingslashit(esc_url_raw($s['yourls_url']));
        if (!$base || wp_parse_url($base, PHP_URL_SCHEME) !== 'https' || empty($s['yourls_key'])) {
            return new WP_Error('jl_yourls_config', 'Set an HTTPS YOURLS URL and API signature first.');
        }
        $body = array_merge(['signature' => $s['yourls_key'], 'action' => $action, 'format' => 'json'], $parameters);
        $response = wp_safe_remote_post($base . '/yourls-api.php', ['timeout' => $timeout, 'redirection' => 0, 'body' => $body]);
        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('jl_yourls_response', 'YOURLS returned an unsuccessful HTTP response.');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) && (!isset($data['status']) || $data['status'] === 'success') ? $data : new WP_Error('jl_yourls_response', 'YOURLS did not accept the request.');
    }
}
