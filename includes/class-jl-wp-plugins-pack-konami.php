<?php
if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_Konami {
    public function __construct() {
        if (!JL_WP_Plugins_Pack_Site_Utilities::enabled('konami') || JL_WP_Plugins_Pack_Site_Utilities::original_active('wp-konami-code/wp-konami-code.php')) {
            return;
        }
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_head', [$this, 'noindex']);
        add_action('pre_get_posts', [$this, 'exclude_search']);
    }

    private function target_page() {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $id = absint($s['konami_page']);
        return $id && get_post_type($id) === 'page' && get_post_status($id) === 'publish' ? $id : 0;
    }

    public function enqueue() {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $url = !empty($s['konami_url']) ? esc_url_raw($s['konami_url']) : '';
        if (!$url && $this->target_page()) {
            $url = get_permalink($this->target_page());
        }
        if (!$url || !in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return;
        }
        wp_enqueue_script('jl-konami-code', plugins_url('assets/js/jl-konami-code.js', JL_WP_PLUGINS_PACK_PLUGIN_FILE), [], JL_WP_PLUGINS_PACK_VERSION, true);
        wp_add_inline_script('jl-konami-code', 'window.jlKonamiTarget = ' . wp_json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }

    public function noindex() {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        if (!empty($s['konami_noindex']) && $this->target_page() && is_page($this->target_page())) {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
        }
    }

    public function exclude_search($query) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $id = $this->target_page();
        if ($id && !empty($s['konami_hide_search']) && !is_admin() && $query->is_search()) {
            $excluded = (array) $query->get('post__not_in');
            $query->set('post__not_in', array_values(array_unique(array_merge(array_map('absint', $excluded), [$id]))));
        }
    }
}
