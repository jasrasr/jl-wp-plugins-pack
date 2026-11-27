<?php
/**
 * Optional replacements for small site plugins. All behavior starts disabled.
 */
if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_Site_Utilities {
    const OPTION = 'jl_wp_plugins_pack_site_utilities';
    const VISIBILITY_OPTION = 'jl_wp_plugins_pack_visibility_rules';
    const SEARCH_GROUP_OPTION = 'jl_wp_plugins_pack_search_groups';
    const MENU_SLUG = 'jl-wp-plugins-pack-site-utilities';

    public static function defaults() {
        return [
            'konami_enabled' => false,
            'konami_page' => 0,
            'konami_url' => '',
            'konami_noindex' => false,
            'konami_hide_search' => false,
            'yourls_enabled' => false,
            'yourls_url' => '',
            'yourls_key' => '',
            'yourls_auto' => false,
            'yourls_shortlink' => false,
            'yourls_types' => ['post', 'page'],
            'not_found_enabled' => false,
            'not_found_page' => 0,
            'not_found_hide_search' => false,
            'not_found_force_error' => false,
            'not_found_disable_guess' => false,
            'visibility_enabled' => false,
        ];
    }

    public static function settings() {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function enabled($feature) {
        $settings = self::settings();
        return !empty($settings[$feature . '_enabled']);
    }

    public static function original_active($plugin_file) {
        if (in_array($plugin_file, (array) get_option('active_plugins', []), true)) {
            return true;
        }
        return function_exists('get_site_option') && array_key_exists($plugin_file, (array) get_site_option('active_sitewide_plugins', []));
    }

    public static function visibility_rules() {
        $rules = get_option(self::VISIBILITY_OPTION, []);
        return is_array($rules) ? $rules : [];
    }

    public static function search_groups() {
        $groups = get_option(self::SEARCH_GROUP_OPTION, []);
        return is_array($groups) ? $groups : [];
    }

    private static function clean_exclusion_group($value) {
        if (!is_array($value)) {
            return ['all' => false, 'ids' => []];
        }
        $ids = isset($value['ids']) && is_array($value['ids']) ? $value['ids'] : [];
        return ['all' => !empty($value['all']), 'ids' => array_values(array_unique(array_filter(array_map('absint', $ids))))];
    }

    private static function clean_search_groups($old) {
        $groups = ['entries' => [], 'taxonomies' => [], 'author' => self::clean_exclusion_group($old['author'] ?? [])];
        foreach (['entries', 'taxonomies'] as $kind) {
            foreach (isset($old[$kind]) && is_array($old[$kind]) ? $old[$kind] : [] as $name => $value) {
                if (is_string($name) && sanitize_key($name) === $name) {
                    $groups[$kind][$name] = self::clean_exclusion_group($value);
                }
            }
        }
        return $groups;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_jl_site_utilities_save', [$this, 'save']);
        add_action('admin_post_jl_site_utilities_import', [$this, 'import']);
    }

    public function add_menu() {
        add_submenu_page(JL_WP_Plugins_Pack::MENU_SLUG, 'JL Site Utilities', 'JL Site Utilities', 'manage_options', self::MENU_SLUG, [$this, 'render']);
    }

    private function redirect($notice) {
        wp_safe_redirect(add_query_arg('jl_utilities_notice', rawurlencode($notice), admin_url('admin.php?page=' . self::MENU_SLUG)));
        exit;
    }

    public function save() {
        if (!current_user_can('manage_options')) {
            wp_die('You cannot manage site utilities.');
        }
        check_admin_referer('jl_site_utilities_save');
        $input = isset($_POST['utilities']) && is_array($_POST['utilities']) ? wp_unslash($_POST['utilities']) : [];
        $old = self::settings();
        $new = $old;
        foreach (['konami', 'yourls', 'not_found', 'visibility'] as $feature) {
            $new[$feature . '_enabled'] = !empty($input[$feature . '_enabled']);
        }
        foreach (['konami_noindex', 'konami_hide_search', 'yourls_auto', 'yourls_shortlink', 'not_found_hide_search', 'not_found_force_error', 'not_found_disable_guess'] as $flag) {
            $new[$flag] = !empty($input[$flag]);
        }
        $new['konami_page'] = isset($input['konami_page']) ? absint($input['konami_page']) : 0;
        $new['not_found_page'] = isset($input['not_found_page']) ? absint($input['not_found_page']) : 0;
        $new['konami_url'] = isset($input['konami_url']) && is_string($input['konami_url']) ? esc_url_raw($input['konami_url']) : '';
        $new['yourls_url'] = isset($input['yourls_url']) && is_string($input['yourls_url']) ? esc_url_raw($input['yourls_url']) : '';
        if (!empty($input['yourls_key']) && is_string($input['yourls_key'])) {
            $new['yourls_key'] = sanitize_text_field($input['yourls_key']);
        }
        if (!empty($input['clear_yourls_key'])) {
            $new['yourls_key'] = '';
        }
        $available_types = get_post_types(['public' => true], 'names');
        $requested_types = isset($input['yourls_types']) && is_array($input['yourls_types']) ? array_map('sanitize_key', array_filter($input['yourls_types'], 'is_string')) : [];
        $new['yourls_types'] = array_values(array_intersect($requested_types, array_values($available_types)));
        update_option(self::OPTION, $new, false);
        if (empty($new['yourls_enabled'])) {
            wp_clear_scheduled_hook(JL_WP_Plugins_Pack_Yourls::CLICK_HOOK);
        }
        $this->redirect('Settings saved.');
    }

    public function import() {
        if (!current_user_can('manage_options')) {
            wp_die('You cannot import site utility settings.');
        }
        check_admin_referer('jl_site_utilities_import');
        $source = isset($_POST['source']) && is_string($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
        $settings = self::settings();
        $message = 'No matching legacy settings were found.';
        if ($source === 'konami') {
            $old = get_option('wp_konami_code_options', false);
            if (is_array($old)) {
                $settings['konami_page'] = absint(isset($old['page']) ? $old['page'] : 0);
                $settings['konami_url'] = !empty($old['url_redirect_activated']) && !empty($old['url_redirect']) && is_string($old['url_redirect']) ? esc_url_raw($old['url_redirect']) : '';
                $settings['konami_noindex'] = !empty($old['visibility_search_engine']);
                $settings['konami_hide_search'] = !empty($old['visibility_internal_search_engine']);
                $message = 'Konami settings copied. Review them before enabling the replacement.';
            }
        } elseif ($source === 'yourls') {
            $old = get_option('yourls_options', false);
            if (is_array($old)) {
                $settings['yourls_url'] = !empty($old['url']) && is_string($old['url']) ? esc_url_raw($old['url']) : '';
                $settings['yourls_key'] = !empty($old['api']) && is_string($old['api']) ? sanitize_text_field($old['api']) : '';
                $settings['yourls_auto'] = !empty($old['sav']) || !empty($old['sch']);
                $settings['yourls_shortlink'] = !empty($old['sht']);
                $legacy_types = !empty($old['typ']) && is_array($old['typ']) ? array_values($old['typ']) : [];
                $settings['yourls_types'] = array_values(array_unique(array_merge(['post', 'page'], array_map('sanitize_key', array_filter($legacy_types, 'is_string')))));
                $message = 'YOURLS settings copied. Existing short links remain in post metadata.';
            }
        } elseif ($source === 'not_found') {
            $old = get_option('404page_settings', false);
            if (is_array($old)) {
                $settings['not_found_page'] = absint(isset($old['page_id']) ? $old['page_id'] : 0);
                $settings['not_found_hide_search'] = !empty($old['hide']);
                $settings['not_found_force_error'] = !empty($old['force_error']);
                $settings['not_found_disable_guess'] = !empty($old['no_url_guessing']);
                $message = 'Custom 404 settings copied. Review them before enabling the replacement.';
                if (!empty($old['http410_if_trashed']) || !empty($old['http410_always'])) {
                    $message .= ' The old plugin has HTTP 410 behavior that this replacement does not provide.';
                }
            }
        } elseif ($source === 'search_exclude') {
            $message = $this->import_search_exclude();
        } elseif ($source === 'hide_posts') {
            $message = $this->import_hide_posts();
        }
        update_option(self::OPTION, $settings, false);
        $this->redirect($message);
    }

    private function import_search_exclude() {
        $modern = get_option('qlse_settings', false);
        $old = get_option('sep_exclude', false);
        $messages = [];
        if (is_array($modern)) {
            update_option(self::SEARCH_GROUP_OPTION, self::clean_search_groups($modern), false);
            $messages[] = 'Search Exclude 2.x post type, taxonomy, and author settings copied.';
        }
        if (is_array($old)) {
            $rules = self::visibility_rules();
            $count = 0;
            foreach ($old as $id) {
                $id = absint($id);
                if ($id && get_post($id)) {
                    $rules[$id]['search'] = true;
                    $count++;
                }
            }
            update_option(self::VISIBILITY_OPTION, $rules, false);
            $messages[] = $count . ' older per-post search exclusions copied.';
        }
        return $messages ? implode(' ', $messages) : 'No matching Search Exclude settings were found.';
    }

    private function import_hide_posts() {
        global $wpdb;
        $rules = self::visibility_rules();
        $count = 0;
        $table = $wpdb->prefix . 'whp_posts_visibility';
        $has_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if ($has_table) {
            $rows = $wpdb->get_results("SELECT post_id, `condition` FROM {$table}", ARRAY_A);
            foreach ((array) $rows as $row) {
                $id = absint($row['post_id']);
                $condition = sanitize_key($row['condition']);
                if ($id && in_array($condition, JL_WP_Plugins_Pack_Visibility::LEGACY_CONDITIONS, true) && get_post($id)) {
                    $rules[$id][$condition] = true;
                    $count++;
                }
            }
        }
        if (!$has_table || !get_option('whp_data_migrated', false)) {
            $legacy = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '_whp_hide_%'", ARRAY_A);
            foreach ((array) $legacy as $row) {
                $id = absint($row['post_id']);
                $condition = sanitize_key(substr($row['meta_key'], 5));
                if ($id && !empty($row['meta_value']) && in_array($condition, JL_WP_Plugins_Pack_Visibility::LEGACY_CONDITIONS, true) && get_post($id)) {
                    $rules[$id][$condition] = true;
                    $count++;
                }
            }
        }
        update_option(self::VISIBILITY_OPTION, $rules, false);
        return $count . ' Hide Posts rules copied. Review affected posts before enabling the replacement.';
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = self::settings();
        $rules = self::visibility_rules();
        echo '<div class="wrap"><h1>JL Site Utilities</h1>';
        echo '<p>Each replacement starts off. Import copies saved settings without changing or deleting the original plugin. Review and test one replacement at a time before deactivating its original.</p>';
        if (isset($_GET['jl_utilities_notice']) && is_string($_GET['jl_utilities_notice'])) {
            echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['jl_utilities_notice']))) . '</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="jl_site_utilities_save">';
        wp_nonce_field('jl_site_utilities_save');
        $this->section('konami', 'Konami Code', $s, 'wp-konami-code/wp-konami-code.php');
        echo '<p>Keyboard: Up Up Down Down Left Right Left Right B A. Touch: swipe the directions, then tap twice.</p>';
        echo '<p><label>Target page '; wp_dropdown_pages(['name' => 'utilities[konami_page]', 'selected' => $s['konami_page'], 'show_option_none' => 'None', 'option_none_value' => 0]); echo '</label></p>';
        echo '<p><label>Or target URL <input class="regular-text" type="url" name="utilities[konami_url]" value="' . esc_attr($s['konami_url']) . '"></label></p>';
        $this->checkbox('konami_noindex', 'Discourage indexing the target page', $s);
        $this->checkbox('konami_hide_search', 'Hide the target page from WordPress search', $s);
        $this->section('yourls', 'YOURLS Link Creator', $s, 'yourls-link-creator/yourls-link-creator.php');
        echo '<p><label>YOURLS site URL <input class="regular-text" type="url" name="utilities[yourls_url]" value="' . esc_attr($s['yourls_url']) . '"></label></p>';
        echo '<p><label>API signature <input class="regular-text" type="password" name="utilities[yourls_key]" value="" autocomplete="new-password" placeholder="' . (!empty($s['yourls_key']) ? 'Stored; leave blank to keep' : 'Enter API signature') . '"></label> <label><input type="checkbox" name="utilities[clear_yourls_key]" value="1"> Clear saved signature</label></p>';
        $this->checkbox('yourls_auto', 'Create a short link when a post is published', $s);
        $this->checkbox('yourls_shortlink', 'Use the YOURLS URL as the WordPress shortlink', $s);
        echo '<p>Post types: ';
        foreach (get_post_types(['public' => true], 'objects') as $type) {
            echo '<label style="margin-right:1em"><input type="checkbox" name="utilities[yourls_types][]" value="' . esc_attr($type->name) . '" ' . checked(in_array($type->name, (array) $s['yourls_types'], true), true, false) . '> ' . esc_html($type->label) . '</label>';
        }
        echo '</p>';
        $this->section('not_found', 'Custom 404 Page', $s, '404page/404page.php');
        echo '<p><label>Page to display '; wp_dropdown_pages(['name' => 'utilities[not_found_page]', 'selected' => $s['not_found_page'], 'show_option_none' => 'None', 'option_none_value' => 0]); echo '</label></p>';
        $this->checkbox('not_found_hide_search', 'Hide the selected page from WordPress search and core sitemaps', $s);
        $this->checkbox('not_found_force_error', 'Return a 404 when someone opens the selected page directly', $s);
        $this->checkbox('not_found_disable_guess', 'Disable WordPress URL guesses for missing pages', $s);
        $this->section('visibility', 'Post Visibility', $s, 'search-exclude/search-exclude.php, whp-hide-posts/whp-hide-posts.php');
        echo '<p>Combines WordPress search exclusions with per-post hiding rules. ' . count($rules) . ' posts currently have copied or saved rules.</p>';
        submit_button('Save site utilities');
        echo '</form><h2>Import existing settings</h2><p>Import while the original plugins and their database data are still present. Imports do not enable replacements.</p>';
        foreach (['konami' => 'WP Konami Code', 'yourls' => 'YOURLS Link Creator', 'not_found' => 'Smart Custom 404', 'search_exclude' => 'Search Exclude', 'hide_posts' => 'Hide Posts'] as $key => $name) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:0 8px 8px 0"><input type="hidden" name="action" value="jl_site_utilities_import"><input type="hidden" name="source" value="' . esc_attr($key) . '">';
            wp_nonce_field('jl_site_utilities_import');
            echo '<button type="submit" class="button">Import ' . esc_html($name) . '</button></form>';
        }
        echo '</div>';
    }

    private function section($key, $title, $settings, $original) {
        echo '<hr><h2>' . esc_html($title) . '</h2>';
        $this->checkbox($key . '_enabled', 'Enable replacement', $settings);
        echo '<p class="description">Original: ' . esc_html($original) . '. The replacement waits while the original is active.</p>';
        foreach (explode(', ', $original) as $file) {
            if (self::original_active($file)) {
                echo '<p class="notice notice-warning inline">' . esc_html($file) . ' is active; this replacement is paused.</p>';
            }
        }
    }

    private function checkbox($key, $label, $settings) {
        echo '<p><label><input type="checkbox" name="utilities[' . esc_attr($key) . ']" value="1" ' . checked(!empty($settings[$key]), true, false) . '> ' . esc_html($label) . '</label></p>';
    }
}
