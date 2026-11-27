<?php
/**
 * JL WP Plugins Pack
 *
 * Features:
 * - Manual batch generation of missing excerpts from JL WP Plugins Pack -> Bulk Excerpts
 * - Automatic excerpt generation when saving posts
 * - Hashtag linking and hashtag-to-tag syncing
 */

if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack {
    const MENU_SLUG = 'jl-wp-plugins-pack';
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
        add_menu_page(
            'JL WP Plugins Pack',
            'JL WP Plugins Pack',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
        add_submenu_page(
            self::MENU_SLUG,
            'JL WP Plugins Pack',
            'Bulk Excerpts',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'jl-wp-plugins-pack'));
        }

        $items = null;
        $message = '';
        $errors = [];
        $request_method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';
        $selected_post_type  = isset($_POST['post_type']) && is_string($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $selected_word_count = isset($_POST['word_count']) && is_scalar($_POST['word_count']) ? max(10, min(100, absint(wp_unslash($_POST['word_count'])))) : self::DEFAULT_WORD_COUNT;
        $selected_batch_size = isset($_POST['batch_size']) && is_scalar($_POST['batch_size']) ? max(1, min(100, absint(wp_unslash($_POST['batch_size'])))) : 25;
        if (!in_array($selected_post_type, ['post', 'page'], true)) {
            $selected_post_type = 'post';
        }

        if ($request_method === 'POST' && (isset($_POST['jl_generate_excerpt_preview']) || isset($_POST['jl_excerpt_action']))) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);
            if (isset($_POST['jl_generate_excerpt_preview'])) {
                $items = $this->generate_missing_excerpts($selected_word_count, $selected_batch_size, $selected_post_type);
                $message = 'Preview generated. Review the excerpts below, then approve the ones you want to save.';
            } else {
                $items = $this->read_review_items($selected_post_type, $errors);
                $action = is_string($_POST['jl_excerpt_action']) ? sanitize_text_field(wp_unslash($_POST['jl_excerpt_action'])) : '';
                $targets = [];
                if (in_array($action, ['approve_all', 'regenerate_all'], true)) {
                    $targets = array_keys($items);
                } elseif (in_array($action, ['approve_selected', 'regenerate_selected'], true)) {
                    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : [];
                    $targets = array_intersect(array_keys($items), array_map('absint', array_keys($selected)));
                } elseif (preg_match('/^(approve|regenerate):([1-9][0-9]*)$/', $action, $matches) && isset($items[(int) $matches[2]])) {
                    $targets = [(int) $matches[2]];
                }

                if (!$targets) {
                    $errors[] = 'Select at least one excerpt to approve or regenerate.';
                } else {
                    $approved = 0;
                    $regenerated = 0;
                    foreach ($targets as $id) {
                        $post = get_post($id);
                        if (!$post || $post->post_status !== 'publish' || $post->post_type !== $selected_post_type || trim((string) $post->post_excerpt) !== '' || !current_user_can('edit_post', $id)) {
                            unset($items[$id]);
                            $errors[] = 'Post ' . $id . ' is no longer eligible for a missing excerpt.';
                            continue;
                        }
                        if (strpos($action, 'regenerate') === 0) {
                            $excerpt = $this->make_excerpt($post->post_content, $selected_word_count);
                            if ($excerpt === '') {
                                $errors[] = 'Post ' . $id . ' has no usable content for an excerpt.';
                                continue;
                            }
                            $items[$id]['excerpt'] = $excerpt;
                            $items[$id]['content_hash'] = hash('sha256', (string) $post->post_content);
                            $regenerated++;
                        } else {
                            if ($items[$id]['content_hash'] !== hash('sha256', (string) $post->post_content)) {
                                $errors[] = 'Post ' . $id . ' changed since the preview. Regenerate it before approving.';
                                continue;
                            }
                            if ($items[$id]['excerpt'] === '') {
                                $errors[] = 'Post ' . $id . ' needs a nonempty excerpt.';
                                continue;
                            }
                            $saved = wp_update_post(['ID' => $id, 'post_excerpt' => $items[$id]['excerpt']], true);
                            if (is_wp_error($saved) || !$saved) {
                                $errors[] = 'Post ' . $id . ' could not be updated.';
                                continue;
                            }
                            unset($items[$id]);
                            $approved++;
                        }
                    }
                    if ($approved) {
                        $message = $approved . ' excerpt' . ($approved === 1 ? '' : 's') . ' approved and saved.';
                    } elseif ($regenerated) {
                        $message = $regenerated . ' excerpt' . ($regenerated === 1 ? '' : 's') . ' regenerated. Review before approving.';
                    }
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>JL WP Plugins Pack</h1>

            <h2>Bulk Missing Excerpts</h2>
            <p>Generate a preview from published posts or pages with empty excerpts. Edit or regenerate each suggestion, then approve individual excerpts, selected excerpts, or the whole batch.</p>
            <?php if ($message !== '') : ?><div class="notice notice-success inline"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
            <?php foreach ($errors as $error) : ?><div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div><?php endforeach; ?>

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
                </table>

                <p>
                    <button type="submit" name="jl_generate_excerpt_preview" value="1" class="button button-primary">Generate Preview</button>
                </p>
            </form>

            <?php if ($items !== null) : ?>
                <h2>Review Excerpts</h2>
                <?php if ($items) : ?>
                    <p>Edits and selections stay in this form until you submit an action. To make a different suggestion, change the length below and regenerate. Generating a new preview starts a new batch.</p>
                    <form method="post">
                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                        <input type="hidden" name="post_type" value="<?php echo esc_attr($selected_post_type); ?>" />
                        <input type="hidden" name="batch_size" value="<?php echo esc_attr((string) $selected_batch_size); ?>" />
                        <p><label for="jl-review-word-count">Regeneration length</label> <input type="number" name="word_count" id="jl-review-word-count" value="<?php echo esc_attr((string) $selected_word_count); ?>" min="10" max="100" /> words</p>
                        <table class="widefat striped">
                            <thead><tr>
                                <th scope="col"><label><input type="checkbox" id="jl-select-all-excerpts" /> Select all</label></th>
                                <th scope="col">Post</th><th scope="col">Excerpt</th><th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($items as $item) : ?>
                                    <tr>
                                        <td><input type="checkbox" class="jl-excerpt-select" name="selected[<?php echo esc_attr((string) $item['id']); ?>]" value="1" aria-label="Select excerpt for <?php echo esc_attr($item['title']); ?>" /></td>
                                        <td><a href="<?php echo esc_url(get_edit_post_link($item['id'])); ?>"><?php echo esc_html($item['title']); ?></a><br /><small>ID <?php echo esc_html((string) $item['id']); ?></small></td>
                                        <td>
                                            <label class="screen-reader-text" for="jl-excerpt-<?php echo esc_attr((string) $item['id']); ?>">Excerpt for <?php echo esc_html($item['title']); ?></label>
                                            <textarea id="jl-excerpt-<?php echo esc_attr((string) $item['id']); ?>" name="items[<?php echo esc_attr((string) $item['id']); ?>][excerpt]" rows="4" class="large-text"><?php echo esc_textarea($item['excerpt']); ?></textarea>
                                            <input type="hidden" name="items[<?php echo esc_attr((string) $item['id']); ?>][content_hash]" value="<?php echo esc_attr($item['content_hash']); ?>" />
                                        </td>
                                        <td>
                                            <button type="submit" name="jl_excerpt_action" value="approve:<?php echo esc_attr((string) $item['id']); ?>" class="button button-primary">Approve</button>
                                            <button type="submit" name="jl_excerpt_action" value="regenerate:<?php echo esc_attr((string) $item['id']); ?>" class="button">Regenerate</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="submit" name="jl_excerpt_action" value="approve_selected" class="button button-primary">Approve Selected</button>
                            <button type="submit" name="jl_excerpt_action" value="approve_all" class="button button-primary">Approve All</button>
                            <button type="submit" name="jl_excerpt_action" value="regenerate_selected" class="button">Regenerate Selected</button>
                            <button type="submit" name="jl_excerpt_action" value="regenerate_all" class="button">Regenerate All</button>
                        </p>
                    </form>
                    <script>
                    document.getElementById('jl-select-all-excerpts').addEventListener('change', function () {
                        document.querySelectorAll('.jl-excerpt-select').forEach(function (box) { box.checked = this.checked; }, this);
                    });
                    </script>
                <?php else : ?>
                    <p>No excerpts remain in this preview. Generate another batch to continue.</p>
                <?php endif; ?>
            <?php endif; ?>

            <hr />

            <h2>Automatic Excerpts</h2>
            <p>Automatic excerpt generation is active for normal blog posts. When a post is saved and the excerpt field is empty, the plugin creates a <?php echo esc_html((string) self::DEFAULT_WORD_COUNT); ?>-word excerpt. Existing manual excerpts are not overwritten.</p>

            <hr />

            <h2>Hashtag Linking</h2>
            <p>Hashtag linking is active for public content. Hashtags like <code>#PowerShell</code> are linked to the matching WordPress tag archive when available.</p>
            <p>When a post is saved, hashtag words are appended as WordPress tags. Existing manual tags are preserved.</p>
            <p><strong>Ignored areas :</strong> existing links, code blocks, preformatted blocks, scripts, styles, and HTML comments.</p>

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
        wp_register_style('jl-wp-plugins-pack-hashtags', false, [], JL_WP_PLUGINS_PACK_VERSION);
        wp_enqueue_style('jl-wp-plugins-pack-hashtags');

        wp_add_inline_style(
            'jl-wp-plugins-pack-hashtags',
            '.jl-hashtag-link{display:inline-block;text-decoration:none;font-weight:700;border-bottom:1px dotted currentColor}.jl-hashtag-link:hover{text-decoration:underline}'
        );
    }

    private function generate_missing_excerpts($word_count, $batch_size, $post_type) {
        global $wpdb;

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_excerpt = '' ORDER BY post_date ASC LIMIT %d",
                $post_type,
                $batch_size
            )
        );

        $items = [];

        foreach ($posts as $post) {
            if (!current_user_can('edit_post', $post->ID)) {
                continue;
            }
            $excerpt = $this->make_excerpt($post->post_content, $word_count);

            if ($excerpt === '') {
                continue;
            }

            $items[(int) $post->ID] = [
                'id'      => (int) $post->ID,
                'title'   => get_the_title((int) $post->ID),
                'excerpt' => $excerpt,
                'content_hash' => hash('sha256', (string) $post->post_content),
            ];
        }

        return $items;
    }

    private function read_review_items($post_type, &$errors) {
        $submitted = isset($_POST['items']) && is_array($_POST['items']) ? wp_unslash($_POST['items']) : [];
        $items = [];
        foreach (array_slice($submitted, 0, 100, true) as $key => $row) {
            $id = absint($key);
            if (!$id || !is_array($row)) {
                continue;
            }
            $post = get_post($id);
            if (!$post || $post->post_type !== $post_type || $post->post_status !== 'publish' || trim((string) $post->post_excerpt) !== '' || !current_user_can('edit_post', $id)) {
                $errors[] = 'Post ' . $id . ' is no longer eligible for a missing excerpt.';
                continue;
            }
            $items[$id] = [
                'id' => $id,
                'title' => get_the_title($id),
                'excerpt' => isset($row['excerpt']) && is_string($row['excerpt']) ? trim(sanitize_textarea_field($row['excerpt'])) : '',
                'content_hash' => isset($row['content_hash']) && is_string($row['content_hash']) ? sanitize_text_field($row['content_hash']) : '',
            ];
        }
        return $items;
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
