<?php
/**
 * Scheduled AI-assisted blog post generation for JL WP Plugins Pack.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_AI_Posts {
    const NONCE_ACTION = 'jl_wp_plugins_pack_ai_post_settings';
    const NONCE_NAME   = 'jl_wp_plugins_pack_ai_post_nonce';
    const OPTION_NAME  = 'jl_wp_plugins_pack_ai_post_settings';
    const LOG_OPTION   = 'jl_wp_plugins_pack_ai_post_log';
    const CRON_HOOK    = 'jl_wp_plugins_pack_generate_ai_post';

    public function __construct() {
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('admin_menu', [$this, 'add_tools_page']);
        add_action(self::CRON_HOOK, [$this, 'run_scheduled_ai_post']);

        $this->maybe_sync_cron();
    }

    public static function activate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);

        $settings = self::get_default_settings_static();
        $stored   = get_option(self::OPTION_NAME, []);

        if (is_array($stored)) {
            $settings = wp_parse_args($stored, $settings);
        }

        if (!empty($settings['enabled'])) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, self::sanitize_recurrence_static($settings['recurrence']), self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function add_cron_schedules($schedules) {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', 'jl-wp-plugins-pack'),
            ];
        }

        return $schedules;
    }

    public function add_tools_page() {
        add_management_page(
            'JL AI Posts',
            'JL AI Posts',
            'manage_options',
            'jl-wp-plugins-pack-ai-posts',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'jl-wp-plugins-pack'));
        }

        $notice = null;
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

        if ($request_method === 'POST' && isset($_POST['jl_save_ai_post_settings'])) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

            $settings = $this->sanitize_settings($_POST, $this->get_settings());
            update_option(self::OPTION_NAME, $settings, false);
            $this->maybe_sync_cron($settings);

            $notice = [
                'type'    => 'success',
                'message' => __('Scheduled AI post settings saved.', 'jl-wp-plugins-pack'),
            ];
        }

        if ($request_method === 'POST' && isset($_POST['jl_run_ai_post_now'])) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

            $manual_result = $this->run_scheduled_ai_post(true);

            if (!empty($manual_result['success'])) {
                $notice = [
                    'type'    => 'success',
                    'message' => sprintf(
                        /* translators: 1: post title, 2: post status */
                        __('Generated "%1$s" as %2$s.', 'jl-wp-plugins-pack'),
                        $manual_result['title'],
                        $manual_result['status']
                    ),
                    'link'    => get_edit_post_link((int) $manual_result['post_id']),
                ];
            } else {
                $notice = [
                    'type'    => 'error',
                    'message' => !empty($manual_result['message']) ? $manual_result['message'] : __('AI post generation failed.', 'jl-wp-plugins-pack'),
                ];
            }
        }

        $settings = $this->get_settings();
        $log      = $this->get_log();
        $next_run = wp_next_scheduled(self::CRON_HOOK);
        $authors  = get_users([
            'capability' => 'edit_posts',
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ]);
        $categories = get_categories([
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        ?>
        <div class="wrap">
            <h1>JL AI Posts</h1>

            <?php if ($notice) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p>
                        <?php echo esc_html($notice['message']); ?>
                        <?php if (!empty($notice['link'])) : ?>
                            <a href="<?php echo esc_url($notice['link']); ?>"><?php esc_html_e('Open post', 'jl-wp-plugins-pack'); ?></a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <p>Generate one AI-assisted blog post on a schedule. The selected author's WordPress capabilities control whether the post may publish or must stay pending/draft.</p>

            <div class="notice notice-info inline">
                <p><strong>Safer default :</strong> Use a Contributor-style AI author first. It can create pending posts without letting the robot grab the admin hammer.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Enable Schedule</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_enabled" value="1" <?php checked(!empty($settings['enabled'])); ?> />
                                Generate posts automatically using WP-Cron.
                            </label>
                            <p class="description">WP-Cron runs when WordPress receives visits. For exact timing, trigger wp-cron.php from your host cron.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_recurrence">Frequency</label></th>
                        <td>
                            <select name="ai_recurrence" id="ai_recurrence">
                                <option value="daily" <?php selected($settings['recurrence'], 'daily'); ?>>Daily</option>
                                <option value="weekly" <?php selected($settings['recurrence'], 'weekly'); ?>>Weekly</option>
                                <option value="twicedaily" <?php selected($settings['recurrence'], 'twicedaily'); ?>>Twice Daily</option>
                                <option value="hourly" <?php selected($settings['recurrence'], 'hourly'); ?>>Hourly</option>
                            </select>
                            <p class="description">Weekly is the least annoying setting. Hourly is how blogs become spam cannons.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_author_id">AI Author</label></th>
                        <td>
                            <select name="ai_author_id" id="ai_author_id">
                                <option value="0" <?php selected((int) $settings['author_id'], 0); ?>>Select an author</option>
                                <?php foreach ($authors as $author) : ?>
                                    <option value="<?php echo esc_attr((string) $author->ID); ?>" <?php selected((int) $settings['author_id'], (int) $author->ID); ?>>
                                        <?php echo esc_html($author->display_name . ' (' . implode(', ', (array) $author->roles) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Only users with <code>edit_posts</code> are shown.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_status_policy">Post Status Policy</label></th>
                        <td>
                            <select name="ai_status_policy" id="ai_status_policy">
                                <option value="draft" <?php selected($settings['status_policy'], 'draft'); ?>>Always save as Draft</option>
                                <option value="pending" <?php selected($settings['status_policy'], 'pending'); ?>>Always save as Pending Review</option>
                                <option value="role" <?php selected($settings['status_policy'], 'role'); ?>>Role-based: publish only if the author can publish</option>
                                <option value="publish" <?php selected($settings['status_policy'], 'publish'); ?>>Publish if allowed, otherwise Pending Review</option>
                            </select>
                            <p class="description">The plugin will not publish under an author that lacks <code>publish_posts</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" name="ai_openai_api_key" id="ai_openai_api_key" value="" class="regular-text" autocomplete="off" />
                            <p class="description">
                                Leave blank to keep the existing stored key.
                                <?php if ($this->has_stored_api_key()) : ?>
                                    A key is currently stored.
                                <?php endif; ?>
                                <?php if ($this->has_constant_api_key()) : ?>
                                    The <code>JL_WP_PLUGINS_PACK_OPENAI_API_KEY</code> constant is defined and will override the stored key.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_model">OpenAI Model</label></th>
                        <td>
                            <input type="text" name="ai_model" id="ai_model" value="<?php echo esc_attr($settings['model']); ?>" class="regular-text" />
                            <p class="description">Example: <code>gpt-5.5</code>. Change this if your OpenAI project uses a different model name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_max_words">Target Length</label></th>
                        <td>
                            <input type="number" name="ai_max_words" id="ai_max_words" value="<?php echo esc_attr((string) $settings['max_words']); ?>" min="300" max="2500" /> words
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_category_id">Category</label></th>
                        <td>
                            <select name="ai_category_id" id="ai_category_id">
                                <option value="0" <?php selected((int) $settings['category_id'], 0); ?>>Default WordPress category</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected((int) $settings['category_id'], (int) $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_tag_names">Base Tags</label></th>
                        <td>
                            <input type="text" name="ai_tag_names" id="ai_tag_names" value="<?php echo esc_attr($settings['tag_names']); ?>" class="regular-text" />
                            <p class="description">Comma-separated. AI-generated tags are appended.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_topic_seed">Topic Instructions</label></th>
                        <td>
                            <textarea name="ai_topic_seed" id="ai_topic_seed" rows="5" class="large-text"><?php echo esc_textarea($settings['topic_seed']); ?></textarea>
                            <p class="description">Tell it what your blog should cover. Good inputs: PowerShell, WordPress, GitHub, IT automation, AI coding, small web tools, home tech projects.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_system_prompt">System Prompt</label></th>
                        <td>
                            <textarea name="ai_system_prompt" id="ai_system_prompt" rows="6" class="large-text"><?php echo esc_textarea($settings['system_prompt']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Disclosure Footer</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_include_disclosure" value="1" <?php checked(!empty($settings['include_disclosure'])); ?> />
                                Append a disclosure to generated posts.
                            </label>
                            <textarea name="ai_disclosure_text" rows="2" class="large-text"><?php echo esc_textarea($settings['disclosure_text']); ?></textarea>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="jl_save_ai_post_settings" class="button button-primary">Save AI Post Settings</button>
                </p>
            </form>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <p>
                    <button type="submit" name="jl_run_ai_post_now" class="button">Generate One AI Post Now</button>
                </p>
            </form>

            <h2>AI Post Status</h2>
            <p><strong>Next scheduled run :</strong> <?php echo $next_run ? esc_html(wp_date('Y-m-d H:i:s', $next_run)) : esc_html__('Not scheduled', 'jl-wp-plugins-pack'); ?></p>
            <p><strong>Last run :</strong> <?php echo !empty($log['last_run']) ? esc_html($log['last_run']) : esc_html__('Never', 'jl-wp-plugins-pack'); ?></p>
            <p><strong>Last result :</strong> <?php echo !empty($log['last_message']) ? esc_html($log['last_message']) : esc_html__('No result yet', 'jl-wp-plugins-pack'); ?></p>
            <?php if (!empty($log['last_post_id'])) : ?>
                <p><strong>Last post :</strong> <a href="<?php echo esc_url(get_edit_post_link((int) $log['last_post_id'])); ?>"><?php echo esc_html((string) $log['last_post_id']); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function run_scheduled_ai_post($manual = false) {
        $settings = $this->get_settings();

        if (empty($settings['enabled']) && !$manual) {
            return $this->record_result(false, __('Scheduled AI posts are disabled.', 'jl-wp-plugins-pack'));
        }

        $api_key = $this->get_api_key($settings);

        if ($api_key === '') {
            return $this->record_result(false, __('Missing OpenAI API key.', 'jl-wp-plugins-pack'));
        }

        $author_id = (int) $settings['author_id'];

        if ($author_id <= 0 || !get_userdata($author_id) || !user_can($author_id, 'edit_posts')) {
            return $this->record_result(false, __('The selected AI author must be a WordPress user with edit_posts permission.', 'jl-wp-plugins-pack'));
        }

        $generated = $this->request_ai_blog_post($settings, $api_key);

        if (is_wp_error($generated)) {
            return $this->record_result(false, $generated->get_error_message());
        }

        $post_id = $this->insert_ai_blog_post($generated, $settings, $author_id);

        if (is_wp_error($post_id)) {
            return $this->record_result(false, $post_id->get_error_message());
        }

        $status = get_post_status($post_id);
        $title  = get_the_title($post_id);
        $message = sprintf(
            /* translators: 1: post ID, 2: post status */
            __('Created AI post %1$d as %2$s.', 'jl-wp-plugins-pack'),
            (int) $post_id,
            $status
        );

        return $this->record_result(true, $message, [
            'post_id' => (int) $post_id,
            'title'   => $title,
            'status'  => $status,
        ]);
    }

    private function maybe_sync_cron($settings = null) {
        if ($settings === null) {
            $settings = $this->get_settings();
        }

        $event = wp_get_scheduled_event(self::CRON_HOOK);

        if (empty($settings['enabled'])) {
            if ($event) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
            }

            return;
        }

        $recurrence = self::sanitize_recurrence_static($settings['recurrence']);

        if (!$event || $event->schedule !== $recurrence) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_schedule_event(time() + HOUR_IN_SECONDS, $recurrence, self::CRON_HOOK);
        }
    }

    private function request_ai_blog_post($settings, $api_key) {
        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'timeout' => 90,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'    => $settings['model'],
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => $settings['system_prompt'],
                        ],
                        [
                            'role'    => 'user',
                            'content' => $this->build_user_prompt($settings),
                        ],
                    ],
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $message = __('OpenAI request failed.', 'jl-wp-plugins-pack');

            if (is_array($body) && !empty($body['error']['message'])) {
                $message .= ' ' . sanitize_text_field((string) $body['error']['message']);
            }

            return new WP_Error('jl_ai_openai_error', $message);
        }

        $content = '';

        if (is_array($body) && !empty($body['choices'][0]['message']['content'])) {
            $content = (string) $body['choices'][0]['message']['content'];
        }

        if ($content === '') {
            return new WP_Error('jl_ai_empty_response', __('OpenAI returned an empty response.', 'jl-wp-plugins-pack'));
        }

        return $this->parse_ai_json($content);
    }

    private function build_user_prompt($settings) {
        return sprintf(
            "Today is %s. Write one original blog post for the site \"%s\".\n\nTopic guidance:\n%s\n\nRequirements:\n- Target length: about %d words.\n- Use practical, plain-English technical writing.\n- Do not invent personal events, employment claims, customer names, prices, dates, or product facts.\n- Avoid medical, legal, financial, exploit, credential-theft, malware, or privacy-invasive instructions.\n- Return only valid JSON with these keys: title, excerpt, content, tags.\n- title: plain text, no HTML.\n- excerpt: 1 short plain-text summary sentence.\n- content: WordPress-safe HTML using paragraphs, headings, lists, and code blocks only when useful.\n- tags: an array of short tag names.",
            wp_date('F j, Y'),
            get_bloginfo('name'),
            $settings['topic_seed'],
            (int) $settings['max_words']
        );
    }

    private function parse_ai_json($content) {
        $content = trim((string) $content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', (string) $content);

        $start = strpos($content, '{');
        $end   = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return new WP_Error('jl_ai_json_missing', __('AI response did not contain a JSON object.', 'jl-wp-plugins-pack'));
        }

        $data = json_decode(substr($content, $start, $end - $start + 1), true);

        if (!is_array($data)) {
            return new WP_Error('jl_ai_json_invalid', __('AI response JSON could not be decoded.', 'jl-wp-plugins-pack'));
        }

        $title   = !empty($data['title']) ? sanitize_text_field(wp_strip_all_tags((string) $data['title'])) : '';
        $excerpt = !empty($data['excerpt']) ? sanitize_text_field(wp_strip_all_tags((string) $data['excerpt'])) : '';
        $body    = !empty($data['content']) ? wp_kses_post((string) $data['content']) : '';

        if ($title === '' || $body === '') {
            return new WP_Error('jl_ai_missing_fields', __('AI response must include a title and content.', 'jl-wp-plugins-pack'));
        }

        $tags = [];

        if (!empty($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                $tag = sanitize_text_field(wp_strip_all_tags((string) $tag));

                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return [
            'title'   => $title,
            'excerpt' => $excerpt,
            'content' => $body,
            'tags'    => array_values(array_unique($tags)),
        ];
    }

    private function insert_ai_blog_post($generated, $settings, $author_id) {
        $status = $this->resolve_post_status($settings, $author_id);
        $content = $generated['content'];

        if (!empty($settings['include_disclosure']) && !empty($settings['disclosure_text'])) {
            $content .= "\n\n<p><em>" . esc_html($settings['disclosure_text']) . "</em></p>";
        }

        $postarr = [
            'post_type'    => 'post',
            'post_status'  => $status,
            'post_author'  => (int) $author_id,
            'post_title'   => $generated['title'],
            'post_content' => $content,
            'post_excerpt' => $generated['excerpt'],
        ];

        if (!empty($settings['category_id'])) {
            $postarr['post_category'] = [(int) $settings['category_id']];
        }

        $post_id = wp_insert_post($postarr, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $tag_names = array_values(array_unique(array_merge($this->parse_tag_names($settings['tag_names']), $generated['tags'])));

        if (!empty($tag_names)) {
            wp_set_post_tags((int) $post_id, $tag_names, false);
        }

        update_post_meta((int) $post_id, '_jl_wp_plugins_pack_ai_generated', '1');
        update_post_meta((int) $post_id, '_jl_wp_plugins_pack_ai_model', sanitize_text_field($settings['model']));
        update_post_meta((int) $post_id, '_jl_wp_plugins_pack_ai_status_policy', sanitize_text_field($settings['status_policy']));

        return (int) $post_id;
    }

    private function resolve_post_status($settings, $author_id) {
        if ($settings['status_policy'] === 'draft') {
            return 'draft';
        }

        if ($settings['status_policy'] === 'pending') {
            return 'pending';
        }

        if ($settings['status_policy'] === 'publish') {
            return user_can($author_id, 'publish_posts') ? 'publish' : 'pending';
        }

        if (user_can($author_id, 'publish_posts')) {
            return 'publish';
        }

        if (user_can($author_id, 'edit_posts')) {
            return 'pending';
        }

        return 'draft';
    }

    private function record_result($success, $message, $extra = []) {
        $log = [
            'last_run'     => wp_date('Y-m-d H:i:s'),
            'last_success' => (bool) $success,
            'last_message' => sanitize_text_field((string) $message),
            'last_post_id' => !empty($extra['post_id']) ? (int) $extra['post_id'] : 0,
        ];

        update_option(self::LOG_OPTION, $log, false);

        return array_merge(
            [
                'success' => (bool) $success,
                'message' => (string) $message,
            ],
            $extra
        );
    }

    private function get_settings() {
        $stored = get_option(self::OPTION_NAME, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, self::get_default_settings_static());
    }

    private static function get_default_settings_static() {
        return [
            'enabled'            => false,
            'recurrence'         => 'weekly',
            'author_id'          => 0,
            'status_policy'      => 'draft',
            'openai_api_key'     => '',
            'model'              => 'gpt-5.5',
            'max_words'          => 900,
            'category_id'        => 0,
            'tag_names'          => 'AI, Automation',
            'topic_seed'         => 'Write practical posts for Jason Lamb about PowerShell, WordPress, GitHub, IT automation, AI-assisted coding, small web tools, and useful technology projects.',
            'system_prompt'      => 'You write practical, factual blog posts for Jason Lamb. Keep the tone helpful, technically accurate, and plain-English. Do not invent personal anecdotes, private details, product facts, dates, or claims. Prefer drafts that a human can quickly review.',
            'include_disclosure' => true,
            'disclosure_text'    => 'This post was drafted with AI assistance and should be reviewed before relying on it.',
        ];
    }

    private function sanitize_settings($input, $existing) {
        $settings = self::get_default_settings_static();

        $settings['enabled']            = !empty($input['ai_enabled']);
        $settings['recurrence']         = isset($input['ai_recurrence']) ? self::sanitize_recurrence_static(wp_unslash($input['ai_recurrence'])) : 'weekly';
        $settings['author_id']          = isset($input['ai_author_id']) ? absint(wp_unslash($input['ai_author_id'])) : 0;
        $settings['status_policy']      = isset($input['ai_status_policy']) ? sanitize_key(wp_unslash($input['ai_status_policy'])) : 'draft';
        $settings['model']              = isset($input['ai_model']) ? sanitize_text_field(wp_unslash($input['ai_model'])) : 'gpt-5.5';
        $settings['max_words']          = isset($input['ai_max_words']) ? max(300, min(2500, absint(wp_unslash($input['ai_max_words'])))) : 900;
        $settings['category_id']        = isset($input['ai_category_id']) ? absint(wp_unslash($input['ai_category_id'])) : 0;
        $settings['tag_names']          = isset($input['ai_tag_names']) ? sanitize_text_field(wp_unslash($input['ai_tag_names'])) : '';
        $settings['topic_seed']         = isset($input['ai_topic_seed']) ? sanitize_textarea_field(wp_unslash($input['ai_topic_seed'])) : $settings['topic_seed'];
        $settings['system_prompt']      = isset($input['ai_system_prompt']) ? sanitize_textarea_field(wp_unslash($input['ai_system_prompt'])) : $settings['system_prompt'];
        $settings['include_disclosure'] = !empty($input['ai_include_disclosure']);
        $settings['disclosure_text']    = isset($input['ai_disclosure_text']) ? sanitize_textarea_field(wp_unslash($input['ai_disclosure_text'])) : $settings['disclosure_text'];

        if (!in_array($settings['status_policy'], ['draft', 'pending', 'role', 'publish'], true)) {
            $settings['status_policy'] = 'draft';
        }

        $existing_key = is_array($existing) && !empty($existing['openai_api_key']) ? (string) $existing['openai_api_key'] : '';
        $new_key = isset($input['ai_openai_api_key']) ? trim((string) wp_unslash($input['ai_openai_api_key'])) : '';

        $settings['openai_api_key'] = $new_key !== '' ? sanitize_text_field($new_key) : $existing_key;

        return $settings;
    }

    private static function sanitize_recurrence_static($recurrence) {
        $recurrence = sanitize_key((string) $recurrence);

        return in_array($recurrence, ['hourly', 'twicedaily', 'daily', 'weekly'], true) ? $recurrence : 'weekly';
    }

    private function get_log() {
        $log = get_option(self::LOG_OPTION, []);

        return is_array($log) ? $log : [];
    }

    private function has_stored_api_key() {
        $settings = $this->get_settings();

        return !empty($settings['openai_api_key']);
    }

    private function has_constant_api_key() {
        return defined('JL_WP_PLUGINS_PACK_OPENAI_API_KEY') && trim((string) constant('JL_WP_PLUGINS_PACK_OPENAI_API_KEY')) !== '';
    }

    private function get_api_key($settings) {
        if ($this->has_constant_api_key()) {
            return trim((string) constant('JL_WP_PLUGINS_PACK_OPENAI_API_KEY'));
        }

        return !empty($settings['openai_api_key']) ? trim((string) $settings['openai_api_key']) : '';
    }

    private function parse_tag_names($tag_names) {
        $raw_tags = explode(',', (string) $tag_names);
        $tags = [];

        foreach ($raw_tags as $tag) {
            $tag = sanitize_text_field(trim($tag));

            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }
}
