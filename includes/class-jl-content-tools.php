<?php
/**
 * JL Content Tools
 *
 * Features:
 * - Manual batch generation of missing excerpts from Tools -> JL Content Tools
 * - Automatic excerpt generation when saving posts
 * - Hashtag linking and hashtag-to-tag syncing
 */

if (!defined('ABSPATH')) {
    exit;
}

class JL_Content_Tools {
    const NONCE_ACTION = 'jl_content_tools_excerpt_run';
    const NONCE_NAME   = 'jl_content_tools_excerpt_nonce';

    const DEFAULT_WORD_COUNT    = 35;
    const MAX_HASHTAGS_PER_POST = 25;
    const MIN_HASHTAG_LENGTH    = 2;
    const MAX_HASHTAG_LENGTH    = 60;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_tools_page']);
        add_action('save_post', [$this, 'maybe_generate_excerpt_on_save'], 20, 3);
        add_action('save_post', [$this, 'sync_hashtags_to_tags_on_save'], 30, 3);
        add_filter('the_content', [$this, 'link_hashtags_in_content'], 12);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_hashtag_styles']);
    }

    public function add_tools_page() {
        add_management_page(
            'JL Content Tools',
            'JL Content Tools',
            'manage_options',
            'jl-content-tools',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'jl-content-tools'));
        }

        $result = null;
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

        if ($request_method === 'POST' && isset($_POST['jl_run_excerpt_generator'])) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to run this tool.', 'jl-content-tools'));
            }

            $dry_run    = isset($_POST['dry_run']);
            $word_count = isset($_POST['word_count']) ? max(10, min(100, absint(wp_unslash($_POST['word_count'])))) : self::DEFAULT_WORD_COUNT;
            $batch_size = isset($_POST['batch_size']) ? max(1, min(100, absint(wp_unslash($_POST['batch_size'])))) : 25;
            $post_type  = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';

            if (!in_array($post_type, ['post', 'page'], true)) {
                $post_type = 'post';
            }

            $result = $this->generate_missing_excerpts($dry_run, $word_count, $batch_size, $post_type);
        }

        $selected_post_type  = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $selected_word_count = isset($_POST['word_count']) ? max(10, min(100, absint(wp_unslash($_POST['word_count'])))) : self::DEFAULT_WORD_COUNT;
        $selected_batch_size = isset($_POST['batch_size']) ? max(1, min(100, absint(wp_unslash($_POST['batch_size'])))) : 25;
        $selected_dry_run    = !isset($_POST['jl_run_excerpt_generator']) || isset($_POST['dry_run']);

        ?>
        <div class="wrap">
            <h1>JL Content Tools</h1>

            <h2>Bulk Missing Excerpts</h2>
            <p>This tool generates missing excerpts from existing published post or page content.</p>

            <div class="notice notice-warning inline">
                <p><strong>Recommendation :</strong> Run this in dry-run mode first. Then run one small batch and verify the excerpts before processing everything. WordPress gremlins love confidence.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="post_type">Post Type</label></th>
                        <td>
                            <select name="post_type" id="post_type">
                                <option value="post" <?php selected($selected_post_type, 'post'); ?>>Posts</option>
                                <option value="page" <?php selected($selected_post_type, 'page'); ?>>Pages</option>
                            </select>
                            <p class="description">Start with posts. Pages usually need more manual wording.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="word_count">Excerpt Length</label></th>
                        <td>
                            <input type="number" name="word_count" id="word_count" value="<?php echo esc_attr((string) $selected_word_count); ?>" min="10" max="100" /> words
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="batch_size">Batch Size</label></th>
                        <td>
                            <input type="number" name="batch_size" id="batch_size" value="<?php echo esc_attr((string) $selected_batch_size); ?>" min="1" max="100" /> posts per run
                            <p class="description">Keep this modest on shared hosting.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Dry Run</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dry_run" <?php checked($selected_dry_run); ?> /> Preview only. Do not update posts.
                            </label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="jl_run_excerpt_generator" class="button button-primary">Run Excerpt Generator</button>
                </p>
            </form>

            <hr />

            <h2>Automatic Excerpts</h2>
            <p>Automatic excerpt generation is active for normal blog posts. When a post is saved and the excerpt field is empty, the plugin creates a <?php echo esc_html((string) self::DEFAULT_WORD_COUNT); ?>-word excerpt. Existing manual excerpts are not overwritten.</p>

            <hr />

            <h2>Hashtag Linking</h2>
            <p>Hashtag linking is active for public content. Hashtags like <code>#PowerShell</code> are linked to the matching WordPress tag archive when available.</p>
            <p>When a post is saved, hashtag words are appended as WordPress tags. Existing manual tags are preserved.</p>
            <p><strong>Ignored areas :</strong> existing links, code blocks, preformatted blocks, scripts, styles, and HTML comments.</p>

            <?php if ($result) : ?>
                <hr />
                <h2>Batch Result</h2>
                <p><strong>Mode :</strong> <?php echo esc_html($result['dry_run'] ? 'Dry run' : 'Updated database'); ?></p>
                <p><strong>Items checked :</strong> <?php echo esc_html((string) $result['checked']); ?></p>
                <p><strong>Items updated :</strong> <?php echo esc_html((string) $result['updated']); ?></p>

                <?php if (!empty($result['items'])) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Post ID</th>
                                <th>Title</th>
                                <th>Generated Excerpt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['items'] as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $item['id']); ?></td>
                                    <td><a href="<?php echo esc_url(get_edit_post_link($item['id'])); ?>"><?php echo esc_html($item['title']); ?></a></td>
                                    <td><?php echo esc_html($item['excerpt']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No published <?php echo esc_html($result['post_type']); ?> items with empty excerpts were found in this batch.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function maybe_generate_excerpt_on_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return;
        }

        if (!in_array($post->post_status, ['publish', 'draft', 'pending', 'future'], true)) {
            return;
        }

        if (!empty(trim((string) $post->post_excerpt)) || empty(trim((string) $post->post_content))) {
            return;
        }

        $excerpt = $this->make_excerpt($post->post_content, self::DEFAULT_WORD_COUNT);

        if ($excerpt === '') {
            return;
        }

        remove_action('save_post', [$this, 'maybe_generate_excerpt_on_save'], 20);

        wp_update_post([
            'ID'           => (int) $post_id,
            'post_excerpt' => $excerpt,
        ]);

        add_action('save_post', [$this, 'maybe_generate_excerpt_on_save'], 20, 3);
    }

    public function sync_hashtags_to_tags_on_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return;
        }

        if (!in_array($post->post_status, ['publish', 'draft', 'pending', 'future'], true)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (empty(trim((string) $post->post_content))) {
            return;
        }

        $hashtags = $this->extract_hashtags($post->post_content);

        if (empty($hashtags)) {
            return;
        }

        $new_tag_names = [];

        foreach ($hashtags as $hashtag) {
            $tag_name = $this->format_tag_name($hashtag);

            if ($tag_name !== '') {
                $new_tag_names[] = $tag_name;
            }
        }

        $new_tag_names = array_values(array_unique($new_tag_names));

        if (empty($new_tag_names)) {
            return;
        }

        $existing_tag_names = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);

        if (is_wp_error($existing_tag_names)) {
            $existing_tag_names = [];
        }

        /*
         * Append hashtag-derived tags without removing manually assigned tags.
         * This is intentionally conservative: deleting a hashtag from content does
         * not delete a real WordPress tag that may have been assigned manually.
         */
        $combined_tag_names = array_values(array_unique(array_merge($existing_tag_names, $new_tag_names)));

        wp_set_post_terms($post_id, $combined_tag_names, 'post_tag', false);
    }

    public function link_hashtags_in_content($content) {
        if (is_admin() || is_feed()) {
            return $content;
        }

        if (!is_string($content) || strpos($content, '#') === false) {
            return $content;
        }

        $protected_segments = [];
        $content = $this->protect_html_segments($content, $protected_segments);

        $parts = preg_split('/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return strtr($content, $protected_segments);
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }

            $parts[$index] = $this->link_hashtags_in_text_node($part);
        }

        return strtr(implode('', $parts), $protected_segments);
    }

    public function enqueue_hashtag_styles() {
        wp_register_style('jl-content-tools-hashtags', false, [], JL_CONTENT_TOOLS_VERSION);
        wp_enqueue_style('jl-content-tools-hashtags');

        wp_add_inline_style(
            'jl-content-tools-hashtags',
            '.jl-hashtag-link{display:inline-block;text-decoration:none;font-weight:700;border-bottom:1px dotted currentColor}.jl-hashtag-link:hover{text-decoration:underline}'
        );
    }

    private function generate_missing_excerpts($dry_run, $word_count, $batch_size, $post_type) {
        global $wpdb;

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_excerpt = '' ORDER BY post_date ASC LIMIT %d",
                $post_type,
                $batch_size
            )
        );

        $checked = 0;
        $updated = 0;
        $items   = [];

        foreach ($posts as $post) {
            $checked++;
            $excerpt = $this->make_excerpt($post->post_content, $word_count);

            if ($excerpt === '') {
                continue;
            }

            $items[] = [
                'id'      => (int) $post->ID,
                'title'   => get_the_title((int) $post->ID),
                'excerpt' => $excerpt,
            ];

            if (!$dry_run) {
                $update_result = wp_update_post(
                    [
                        'ID'           => (int) $post->ID,
                        'post_excerpt' => $excerpt,
                    ],
                    true
                );

                if (!is_wp_error($update_result)) {
                    $updated++;
                }
            }
        }

        return [
            'dry_run'   => (bool) $dry_run,
            'checked'   => (int) $checked,
            'updated'   => (int) $updated,
            'items'     => $items,
            'post_type' => $post_type,
        ];
    }

    private function make_excerpt($content, $word_count) {
        $content = strip_shortcodes((string) $content);
        $content = wp_strip_all_tags($content, true);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim((string) $content);

        if ($content === '') {
            return '';
        }

        $excerpt = wp_trim_words($content, $word_count, '');

        return rtrim($excerpt, " \t\n\r\0\x0B.,;:-") . '.';
    }

    private function link_hashtags_in_text_node($text) {
        return preg_replace_callback(
            $this->get_hashtag_regex(),
            function ($matches) {
                $full_match  = $matches[0];
                $hashtag_raw = $matches[1];

                if (!$this->is_valid_hashtag($hashtag_raw)) {
                    return $full_match;
                }

                return sprintf(
                    '<a class="jl-hashtag-link" href="%s" rel="tag">%s</a>',
                    esc_url($this->get_hashtag_url($hashtag_raw)),
                    esc_html($full_match)
                );
            },
            $text
        );
    }

    private function extract_hashtags($content) {
        $protected_segments = [];
        $content = $this->protect_html_segments((string) $content, $protected_segments);
        $content = wp_strip_all_tags($content, true);

        preg_match_all($this->get_hashtag_regex(), $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $hashtags = [];

        foreach ($matches[1] as $hashtag_raw) {
            if (!$this->is_valid_hashtag($hashtag_raw)) {
                continue;
            }

            $hashtags[] = $hashtag_raw;

            if (count($hashtags) >= self::MAX_HASHTAGS_PER_POST) {
                break;
            }
        }

        return array_values(array_unique($hashtags));
    }

    private function get_hashtag_regex() {
        /*
         * Match a hashtag that:
         * - Starts with #
         * - Begins with a Unicode letter
         * - Allows Unicode letters, numbers, underscores, and hyphens
         * - Avoids common false positives like C#, URLs with /#section, and entities
         */
        return '/(?<![\p{L}\p{N}_&\/\.])#([\p{L}][\p{L}\p{N}_-]{1,60})\b/u';
    }

    private function is_valid_hashtag($hashtag_raw) {
        $hashtag_raw = trim((string) $hashtag_raw);

        if ($hashtag_raw === '') {
            return false;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($hashtag_raw) : strlen($hashtag_raw);

        if ($length < self::MIN_HASHTAG_LENGTH || $length > self::MAX_HASHTAG_LENGTH) {
            return false;
        }

        /*
         * Avoid turning color hex values into tags, for example #fff or #ffffff.
         */
        if (preg_match('/^[A-Fa-f0-9]{3}([A-Fa-f0-9]{3})?$/', $hashtag_raw)) {
            return false;
        }

        return sanitize_title($hashtag_raw) !== '';
    }

    private function format_tag_name($hashtag_raw) {
        $hashtag_raw = trim((string) $hashtag_raw);

        if (!$this->is_valid_hashtag($hashtag_raw)) {
            return '';
        }

        $tag_name = str_replace(['_', '-'], ' ', $hashtag_raw);
        $tag_name = preg_replace('/\s+/', ' ', $tag_name);
        $tag_name = trim((string) $tag_name);

        return sanitize_text_field($tag_name);
    }

    private function get_hashtag_url($hashtag_raw) {
        $tag_name = $this->format_tag_name($hashtag_raw);
        $tag_slug = sanitize_title($tag_name);

        if ($tag_slug !== '') {
            $term = get_term_by('slug', $tag_slug, 'post_tag');

            if ($term && !is_wp_error($term)) {
                $tag_link = get_tag_link($term);

                if (!is_wp_error($tag_link)) {
                    return $tag_link;
                }
            }
        }

        /*
         * Fallback for hashtags that have not been synced into real WordPress tags yet.
         */
        return add_query_arg('s', '#' . rawurlencode($hashtag_raw), home_url('/'));
    }

    private function protect_html_segments($content, &$protected_segments) {
        $patterns = [
            '/<a\b[^>]*>.*?<\/a>/is',
            '/<pre\b[^>]*>.*?<\/pre>/is',
            '/<code\b[^>]*>.*?<\/code>/is',
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<!--.*?-->/s',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) use (&$protected_segments) {
                    $key = '%%JL_HASH_PROTECTED_' . count($protected_segments) . '%%';
                    $protected_segments[$key] = $matches[0];

                    return $key;
                },
                $content
            );
        }

        return $content;
    }
}
