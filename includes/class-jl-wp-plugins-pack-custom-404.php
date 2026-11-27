<?php
if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_Custom_404 {
    private $forced = false;

    public function __construct() {
        if (!JL_WP_Plugins_Pack_Site_Utilities::enabled('not_found') || JL_WP_Plugins_Pack_Site_Utilities::original_active('404page/404page.php')) {
            return;
        }
        add_filter('template_include', [$this, 'template'], 99);
        add_action('template_redirect', [$this, 'force_direct_404'], 0);
        add_filter('redirect_canonical', [$this, 'stop_forced_redirect']);
        add_action('pre_get_posts', [$this, 'exclude_search']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'exclude_sitemap'], 10, 2);
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        if (!empty($s['not_found_disable_guess'])) {
            add_filter('do_redirect_guess_404_permalink', '__return_false');
        }
    }

    public function selected_page() {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $id = absint($s['not_found_page']);
        return $id && get_post_type($id) === 'page' && get_post_status($id) === 'publish' ? $id : 0;
    }

    public function template($current) {
        if (!is_404() || !$this->selected_page()) {
            return $current;
        }
        set_query_var('jl_custom_404_page_id', $this->selected_page());
        status_header(404);
        nocache_headers();
        return JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'templates/jl-custom-404.php';
    }

    public function force_direct_404() {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $id = $this->selected_page();
        if (empty($s['not_found_force_error']) || !$id || !is_page($id) || is_404()) {
            return;
        }
        global $wp_query;
        $this->forced = true;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    public function stop_forced_redirect($url) {
        return $this->forced ? false : $url;
    }

    public function exclude_search($query) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $id = $this->selected_page();
        if ($id && !empty($s['not_found_hide_search']) && !is_admin() && $query->is_search()) {
            $query->set('post__not_in', array_values(array_unique(array_merge(array_map('absint', (array) $query->get('post__not_in')), [$id]))));
        }
    }

    public function exclude_sitemap($args, $post_type) {
        $s = JL_WP_Plugins_Pack_Site_Utilities::settings();
        $id = $this->selected_page();
        if ($post_type === 'page' && $id && !empty($s['not_found_hide_search'])) {
            $args['post__not_in'] = array_values(array_unique(array_merge(isset($args['post__not_in']) ? (array) $args['post__not_in'] : [], [$id])));
        }
        return $args;
    }
}
