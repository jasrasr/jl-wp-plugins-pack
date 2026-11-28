<?php
if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_Visibility {
    private $hidden_single = false;
    const LEGACY_CONDITIONS = [
        'hide_on_frontpage', 'hide_on_blog_page', 'hide_on_archive', 'hide_on_cpt_archive',
        'hide_on_categories', 'hide_on_tags', 'hide_on_authors', 'hide_on_date',
        'hide_on_search', 'hide_in_rss_feed', 'hide_on_store', 'hide_on_product_category',
        'hide_on_recent_posts', 'hide_on_post_navigation', 'hide_on_rest_api',
        'hide_on_single_post_page', 'hide_on_sitemap', 'hide_on_xml_sitemap',
        'hide_on_yoast_sitemap', 'hide_on_yoast_breadcrumbs', 'hide_on_yoast_internal_links',
    ];

    private function active() {
        return JL_WP_Plugins_Pack_Site_Utilities::enabled('visibility')
            && !JL_WP_Plugins_Pack_Site_Utilities::original_active('search-exclude/search-exclude.php')
            && !JL_WP_Plugins_Pack_Site_Utilities::original_active('whp-hide-posts/whp-hide-posts.php');
    }

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
        add_action('save_post', [$this, 'save_post'], 30, 2);
        if (!$this->active()) {
            return;
        }
        add_action('pre_get_posts', [$this, 'filter_query'], 99);
        add_action('template_redirect', [$this, 'hide_single'], 1);
        add_filter('redirect_canonical', [$this, 'stop_hidden_redirect']);
        add_filter('widget_posts_args', [$this, 'filter_recent']);
        add_filter('get_next_post_where', [$this, 'filter_navigation']);
        add_filter('get_previous_post_where', [$this, 'filter_navigation']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'filter_sitemap'], 10, 2);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'filter_yoast_sitemap']);
        add_filter('wpseo_breadcrumb_links', [$this, 'filter_yoast_breadcrumbs']);
        add_filter('wpseo_link_suggestions_indexables', [$this, 'filter_yoast_links'], 10, 3);
        add_action('init', [$this, 'register_rest_filters'], 20);
        add_filter('woocommerce_rest_product_object_query', [$this, 'filter_rest_product'], 10, 2);
        add_filter('woocommerce_rest_product_query', [$this, 'filter_rest_product'], 10, 2);
    }

    public function meta_boxes() {
        foreach (get_post_types(['public' => true, 'show_ui' => true], 'names') as $type) {
            add_meta_box('jl_post_visibility', 'JL Post Visibility', [$this, 'render_meta_box'], $type, 'side');
        }
    }

    public function render_meta_box($post) {
        $rules = JL_WP_Plugins_Pack_Site_Utilities::visibility_rules();
        $selected = isset($rules[$post->ID]) && is_array($rules[$post->ID]) ? $rules[$post->ID] : [];
        wp_nonce_field('jl_post_visibility_' . $post->ID, 'jl_post_visibility_nonce');
        echo '<p>These settings take effect when Post Visibility is enabled and the original plugins are inactive.</p>';
        $labels = [
            'search' => 'Hide from WordPress search (Search Exclude)',
            'hide_on_frontpage' => 'Front page',
            'hide_on_blog_page' => 'Blog posts page',
            'hide_on_archive' => 'All archives',
            'hide_on_cpt_archive' => 'Custom post type archive',
            'hide_on_categories' => 'Category archives',
            'hide_on_tags' => 'Tag archives',
            'hide_on_authors' => 'Author archives',
            'hide_on_date' => 'Date archives',
            'hide_on_search' => 'WordPress search (Hide Posts)',
            'hide_in_rss_feed' => 'RSS feeds',
            'hide_on_store' => 'WooCommerce shop',
            'hide_on_product_category' => 'WooCommerce product categories',
            'hide_on_recent_posts' => 'Recent Posts widget',
            'hide_on_post_navigation' => 'Previous/next post links',
            'hide_on_rest_api' => 'WordPress REST collections',
            'hide_on_single_post_page' => 'Direct page view (return 404)',
            'hide_on_sitemap' => 'Core and Yoast sitemaps',
            'hide_on_xml_sitemap' => 'WordPress XML sitemap',
            'hide_on_yoast_sitemap' => 'Yoast XML sitemap',
            'hide_on_yoast_breadcrumbs' => 'Yoast breadcrumbs',
            'hide_on_yoast_internal_links' => 'Yoast link suggestions',
        ];
        foreach ($labels as $key => $label) {
            echo '<p><label><input type="checkbox" name="jl_visibility[' . esc_attr($key) . ']" value="1" ' . checked(!empty($selected[$key]), true, false) . '> ' . esc_html($label) . '</label></p>';
        }
    }

    public function save_post($id, $post) {
        if (!isset($_POST['jl_post_visibility_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['jl_post_visibility_nonce'])), 'jl_post_visibility_' . $id)) {
            return;
        }
        if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post', $id)) {
            return;
        }
        $input = isset($_POST['jl_visibility']) && is_array($_POST['jl_visibility']) ? map_deep(wp_unslash($_POST['jl_visibility']), 'sanitize_text_field') : [];
        $selected = [];
        foreach (array_merge(['search'], self::LEGACY_CONDITIONS) as $key) {
            if (!empty($input[$key])) {
                $selected[$key] = true;
            }
        }
        $rules = JL_WP_Plugins_Pack_Site_Utilities::visibility_rules();
        if ($selected) {
            $rules[$id] = $selected;
        } else {
            unset($rules[$id]);
        }
        update_option(JL_WP_Plugins_Pack_Site_Utilities::VISIBILITY_OPTION, $rules, false);
    }

    private function ids_for($conditions, $post_type = '') {
        $rules = JL_WP_Plugins_Pack_Site_Utilities::visibility_rules();
        $ids = [];
        foreach ($rules as $id => $flags) {
            if (!is_array($flags) || !array_intersect($conditions, array_keys(array_filter($flags)))) {
                continue;
            }
            $id = absint($id);
            if ($id && (!$post_type || get_post_type($id) === $post_type)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function exclude_ids($query, $ids) {
        if (!$ids) {
            return;
        }
        $included = array_map('absint', (array) $query->get('post__in'));
        if ($included) {
            $remaining = array_values(array_diff($included, $ids));
            $query->set('post__in', $remaining ? $remaining : [0]);
        } else {
            $excluded = array_map('absint', (array) $query->get('post__not_in'));
            $query->set('post__not_in', array_values(array_unique(array_merge($excluded, $ids))));
        }
    }

    private function filter_search_groups($query) {
        $groups = JL_WP_Plugins_Pack_Site_Utilities::search_groups();
        $ids = [];
        foreach (isset($groups['entries']) && is_array($groups['entries']) ? $groups['entries'] : [] as $type => $rule) {
            if (!is_array($rule) || !is_string($type) || !post_type_exists($type)) {
                continue;
            }
            $ids = array_merge($ids, !empty($rule['all']) ? get_posts(['post_type' => $type, 'posts_per_page' => -1, 'fields' => 'ids']) : (array) ($rule['ids'] ?? []));
        }
        $this->exclude_ids($query, array_values(array_unique(array_map('absint', $ids))));
        $clauses = [];
        foreach (isset($groups['taxonomies']) && is_array($groups['taxonomies']) ? $groups['taxonomies'] : [] as $taxonomy => $rule) {
            if (!is_array($rule) || !is_string($taxonomy) || !taxonomy_exists($taxonomy)) {
                continue;
            }
            if (!empty($rule['all'])) {
                $clauses[] = ['taxonomy' => $taxonomy, 'operator' => 'NOT EXISTS'];
            } elseif (!empty($rule['ids'])) {
                $clauses[] = ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $rule['ids'], 'operator' => 'NOT IN'];
            }
        }
        if ($clauses) {
            $existing = $query->get('tax_query');
            $query->set('tax_query', $existing ? array_merge(['relation' => 'AND', $existing], $clauses) : array_merge(['relation' => 'AND'], $clauses));
        }
        $author = isset($groups['author']) && is_array($groups['author']) ? $groups['author'] : [];
        if (!empty($author['all'])) {
            $query->set('post__in', [0]);
        } elseif (!empty($author['ids'])) {
            $query->set('author__not_in', array_values(array_unique(array_merge((array) $query->get('author__not_in'), $author['ids']))));
        }
    }

    public function filter_query($query) {
        if (is_admin()) {
            return;
        }
        $conditions = [];
        if ($query->is_search()) {
            $conditions = ['search', 'hide_on_search'];
            $this->filter_search_groups($query);
        } elseif ($query->is_feed()) {
            $conditions = ['hide_in_rss_feed'];
        } elseif ($query->is_home()) {
            $conditions = ['hide_on_blog_page'];
            if ($query->is_main_query() && is_front_page()) {
                $conditions[] = 'hide_on_frontpage';
            }
        } elseif ($query->is_category()) {
            $conditions = ['hide_on_categories', 'hide_on_archive'];
        } elseif ($query->is_tag()) {
            $conditions = ['hide_on_tags', 'hide_on_archive'];
        } elseif ($query->is_author()) {
            $conditions = ['hide_on_authors', 'hide_on_archive'];
        } elseif ($query->is_date()) {
            $conditions = ['hide_on_date', 'hide_on_archive'];
        } elseif ($query->is_post_type_archive()) {
            $conditions = ['hide_on_cpt_archive', 'hide_on_archive'];
            if (function_exists('is_shop') && is_shop()) {
                $conditions[] = 'hide_on_store';
            }
        } elseif ($query->is_tax()) {
            $conditions = ['hide_on_archive'];
            if (function_exists('is_product_category') && is_product_category()) {
                $conditions[] = 'hide_on_product_category';
            }
        } elseif ($query->is_archive()) {
            $conditions = ['hide_on_archive'];
        }
        if (is_front_page() && !$query->is_main_query()) {
            $conditions[] = 'hide_on_frontpage';
        }
        $this->exclude_ids($query, $this->ids_for($conditions));
    }

    public function hide_single() {
        if (!is_singular()) {
            return;
        }
        $id = get_queried_object_id();
        if (in_array($id, $this->ids_for(['hide_on_single_post_page']), true)) {
            global $wp_query;
            $this->hidden_single = true;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }

    public function stop_hidden_redirect($url) {
        return $this->hidden_single ? false : $url;
    }

    public function filter_recent($args) {
        $ids = $this->ids_for(['hide_on_recent_posts'], 'post');
        $args['post__not_in'] = array_values(array_unique(array_merge(isset($args['post__not_in']) ? (array) $args['post__not_in'] : [], $ids)));
        return $args;
    }

    public function filter_navigation($where) {
        $ids = $this->ids_for(['hide_on_post_navigation'], 'post');
        return $ids ? $where . ' AND p.ID NOT IN (' . implode(',', array_map('absint', $ids)) . ')' : $where;
    }

    public function register_rest_filters() {
        foreach (get_post_types(['show_in_rest' => true], 'names') as $type) {
            add_filter('rest_' . $type . '_query', [$this, 'filter_rest'], 10, 2);
        }
    }

    public function filter_rest($args, $request) {
        if (is_object($request) && method_exists($request, 'get_param') && $request->get_param('context') === 'edit') {
            return $args;
        }
        $type = isset($args['post_type']) && is_string($args['post_type']) ? $args['post_type'] : '';
        $ids = $this->ids_for(['hide_on_rest_api'], $type);
        $args['post__not_in'] = array_values(array_unique(array_merge(isset($args['post__not_in']) ? (array) $args['post__not_in'] : [], $ids)));
        return $args;
    }

    public function filter_rest_product($args, $request = null) {
        if (is_object($request) && method_exists($request, 'get_param') && $request->get_param('context') === 'edit') {
            return $args;
        }
        $ids = $this->ids_for(['hide_on_rest_api'], 'product');
        $args['post__not_in'] = array_values(array_unique(array_merge(isset($args['post__not_in']) ? (array) $args['post__not_in'] : [], $ids)));
        return $args;
    }

    public function filter_sitemap($args, $post_type) {
        $ids = $this->ids_for(['hide_on_sitemap', 'hide_on_xml_sitemap'], $post_type);
        $args['post__not_in'] = array_values(array_unique(array_merge(isset($args['post__not_in']) ? (array) $args['post__not_in'] : [], $ids)));
        return $args;
    }

    public function filter_yoast_sitemap($excluded) {
        return array_values(array_unique(array_merge((array) $excluded, $this->ids_for(['hide_on_sitemap', 'hide_on_yoast_sitemap']))));
    }

    public function filter_yoast_breadcrumbs($links) {
        if (!is_array($links)) {
            return $links;
        }
        $hidden = $this->ids_for(['hide_on_yoast_breadcrumbs']);
        foreach ($links as $key => $link) {
            if (is_array($link) && !empty($link['id']) && in_array(absint($link['id']), $hidden, true)) {
                unset($links[$key]);
            }
        }
        return $links;
    }

    public function filter_yoast_links($suggestions, $object_id, $object_type) {
        if ($object_type !== 'post' || !is_array($suggestions)) {
            return $suggestions;
        }
        $hidden = $this->ids_for(['hide_on_yoast_internal_links']);
        return array_values(array_filter($suggestions, static function ($suggestion) use ($hidden) {
            return !is_object($suggestion) || !isset($suggestion->object_id) || !in_array(absint($suggestion->object_id), $hidden, true);
        }));
    }
}
