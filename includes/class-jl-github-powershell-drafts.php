<?php
/**
 * GitHub PowerShell draft generator.
 *
 * Watches jasrasr/powershell for new .ps1 files and creates WordPress drafts
 * containing header metadata plus a link to the current GitHub source file.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JL_GitHub_PowerShell_Drafts {
    const OWNER  = 'jasrasr';
    const REPO   = 'powershell';
    const BRANCH = 'main';

    const NONCE_ACTION = 'jl_github_ps_drafts';
    const NONCE_NAME   = 'jl_github_ps_drafts_nonce';

    const OPTION_SETTINGS = 'jl_github_ps_draft_settings';
    const OPTION_BASELINE = 'jl_github_ps_draft_baseline';
    const OPTION_STATUS   = 'jl_github_ps_draft_status';

    const CRON_HOOK       = 'jl_github_ps_draft_scan';
    const WEEKLY_SCHEDULE = 'jl_github_ps_weekly';
    const SCAN_LOCK       = 'jl_github_ps_scan_lock';

    const MAX_FILES_PER_SCAN = 20;

    public function __construct() {
        add_filter('cron_schedules', [__CLASS__, 'add_cron_schedules']);
        add_action('admin_menu', [$this, 'add_tools_page']);
        add_action(self::CRON_HOOK, [$this, 'run_scan']);
        add_action('init', [$this, 'ensure_schedule']);
    }

    public static function activate() {
        add_filter('cron_schedules', [__CLASS__, 'add_cron_schedules']);

        $settings = wp_parse_args(
            get_option(self::OPTION_SETTINGS, []),
            self::default_settings()
        );

        update_option(self::OPTION_SETTINGS, $settings, false);

        if (!empty($settings['enabled']) && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(
                time() + (5 * MINUTE_IN_SECONDS),
                $settings['frequency'],
                self::CRON_HOOK
            );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        delete_transient(self::SCAN_LOCK);
    }

    public static function add_cron_schedules($schedules) {
        $schedules[self::WEEKLY_SCHEDULE] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => 'Once Weekly',
        ];

        return $schedules;
    }

    private static function default_settings() {
        return [
            'enabled'   => 1,
            'frequency' => self::WEEKLY_SCHEDULE,
        ];
    }

    public function add_tools_page() {
        add_management_page(
            'JL GitHub PowerShell Drafts',
            'JL GitHub Drafts',
            'manage_options',
            'jl-github-powershell-drafts',
            [$this, 'render_page']
        );
    }

    public function ensure_schedule() {
        $settings = $this->get_settings();

        if (empty($settings['enabled'])) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            return;
        }

        $event = wp_get_scheduled_event(self::CRON_HOOK);

        if (!$event || $event->schedule !== $settings['frequency']) {
            $this->reschedule($settings);
        }
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'jl-wp-plugins-pack'));
        }

        $result = null;
        $notice = '';

        if (
            isset($_SERVER['REQUEST_METHOD']) &&
            sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) === 'POST'
        ) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

            if (isset($_POST['jl_save_github_ps_settings'])) {
                $settings = $this->sanitize_settings($_POST);
                update_option(self::OPTION_SETTINGS, $settings, false);
                $this->reschedule($settings);
                $notice = 'Settings saved.';
            }

            if (isset($_POST['jl_run_github_ps_scan'])) {
                $settings = $this->sanitize_settings($_POST);
                update_option(self::OPTION_SETTINGS, $settings, false);
                $this->reschedule($settings);
                $result = $this->run_scan(true);
            }

            if (isset($_POST['jl_reset_github_ps_baseline'])) {
                delete_option(self::OPTION_BASELINE);
                $notice = 'Baseline reset. The next scan will record the current scripts and create no drafts.';
            }
        }

        $settings = $this->get_settings();
        $status   = get_option(self::OPTION_STATUS, []);
        $baseline = get_option(self::OPTION_BASELINE, []);
        $count    = is_array($baseline) ? count($baseline) : 0;
        $next     = wp_next_scheduled(self::CRON_HOOK);
        ?>
        <div class="wrap">
            <h1>JL GitHub PowerShell Drafts</h1>

            <p>
                Monitors
                <a href="https://github.com/<?php echo esc_attr(self::OWNER . '/' . self::REPO); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html(self::OWNER . '/' . self::REPO); ?>
                </a>
                for new <code>.ps1</code> files.
            </p>

            <div class="notice notice-info inline">
                <p>
                    <strong>Safe first scan:</strong>
                    existing scripts are recorded as a baseline and no drafts are created.
                    Only scripts added later create drafts.
                </p>
            </div>

            <p>
                Drafts include your standard script-header fields and a link to the current GitHub file.
                The script itself is not copied into WordPress and is never executed.
            </p>

            <?php if ($notice !== '') : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Scheduled scans</th>
                        <td>
                            <label>
                                <input type="checkbox" name="github_ps_enabled" value="1" <?php checked(!empty($settings['enabled'])); ?> />
                                Enable automatic WP-Cron checks
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="github_ps_frequency">Frequency</label></th>
                        <td>
                            <select name="github_ps_frequency" id="github_ps_frequency">
                                <option value="<?php echo esc_attr(self::WEEKLY_SCHEDULE); ?>" <?php selected($settings['frequency'], self::WEEKLY_SCHEDULE); ?>>Weekly</option>
                                <option value="daily" <?php selected($settings['frequency'], 'daily'); ?>>Daily</option>
                            </select>
                            <p class="description">WP-Cron is traffic-driven, so the run time is approximate.</p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="jl_save_github_ps_settings" class="button button-primary">Save Settings</button>
                    <button type="submit" name="jl_run_github_ps_scan" class="button">Save and Run Check Now</button>
                    <button
                        type="submit"
                        name="jl_reset_github_ps_baseline"
                        class="button"
                        onclick="return confirm('Reset the baseline? The next scan will create no drafts.');"
                    >Reset Baseline</button>
                </p>
            </form>

            <h2>Status</h2>
            <table class="widefat striped" style="max-width: 850px;">
                <tbody>
                    <tr><th style="width: 220px;">Repository</th><td><?php echo esc_html(self::OWNER . '/' . self::REPO . ' (' . self::BRANCH . ')'); ?></td></tr>
                    <tr><th>Baseline scripts</th><td><?php echo esc_html((string) $count); ?></td></tr>
                    <tr>
                        <th>Next scheduled scan</th>
                        <td><?php echo $next ? esc_html(wp_date('Y-m-d H:i:s T', $next)) : 'Not scheduled'; ?></td>
                    </tr>
                    <tr>
                        <th>Last run</th>
                        <td><?php echo !empty($status['timestamp']) ? esc_html(wp_date('Y-m-d H:i:s T', (int) $status['timestamp'])) : 'Never'; ?></td>
                    </tr>
                    <tr><th>Last result</th><td><?php echo esc_html(isset($status['message']) ? $status['message'] : 'No scan has run yet.'); ?></td></tr>
                </tbody>
            </table>

            <?php if (is_array($result)) : ?>
                <h2>Current Scan Result</h2>
                <p><?php echo esc_html($result['message']); ?></p>

                <?php if (!empty($result['items'])) : ?>
                    <table class="widefat striped">
                        <thead><tr><th>Action</th><th>Script</th><th>WordPress post</th></tr></thead>
                        <tbody>
                            <?php foreach ($result['items'] as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html($item['action']); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($item['github_url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($item['path']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['post_id'])) : ?>
                                            <a href="<?php echo esc_url(get_edit_post_link((int) $item['post_id'])); ?>">Open post</a>
                                        <?php else : ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function run_scan($manual = false) {
        $settings = $this->get_settings();

        if (!$manual && empty($settings['enabled'])) {
            return $this->record_status(true, 'Scheduled scan skipped because the feature is disabled.', []);
        }

        if (get_transient(self::SCAN_LOCK)) {
            return $this->record_status(false, 'A GitHub script scan is already running.', []);
        }

        set_transient(self::SCAN_LOCK, 1, 10 * MINUTE_IN_SECONDS);

        try {
            $scripts = $this->fetch_script_tree();

            if (is_wp_error($scripts)) {
                return $this->record_status(false, $scripts->get_error_message(), []);
            }

            $current = [];

            foreach ($scripts as $script) {
                $current[$script['path']] = $script['sha'];
            }

            $baseline = get_option(self::OPTION_BASELINE, false);

            if ($baseline === false || !is_array($baseline)) {
                update_option(self::OPTION_BASELINE, $current, false);

                return $this->record_status(
                    true,
                    sprintf('Baseline created with %d existing PowerShell scripts. No drafts were created.', count($current)),
                    []
                );
            }

            $next       = [];
            $items      = [];
            $created    = 0;
            $refreshed  = 0;
            $skipped    = 0;
            $errors     = 0;
            $processed  = 0;

            foreach ($scripts as $script) {
                $path    = $script['path'];
                $sha     = $script['sha'];
                $old_sha = isset($baseline[$path]) ? (string) $baseline[$path] : '';
                $is_new  = !array_key_exists($path, $baseline);
                $changed = !$is_new && $old_sha !== $sha;

                if (!$is_new && !$changed) {
                    $next[$path] = $sha;
                    continue;
                }

                if ($processed >= self::MAX_FILES_PER_SCAN) {
                    if (!$is_new) {
                        $next[$path] = $old_sha;
                    }
                    continue;
                }

                $processed++;
                $sync = $this->sync_script($path, $sha, $is_new);

                if (is_wp_error($sync)) {
                    $errors++;

                    if (!$is_new) {
                        $next[$path] = $old_sha;
                    }

                    $items[] = [
                        'action'     => 'Error: ' . $sync->get_error_message(),
                        'path'       => $path,
                        'github_url' => $this->github_file_url($path),
                        'post_id'    => 0,
                    ];
                    continue;
                }

                $next[$path] = $sha;
                $items[]     = $sync;

                if ($sync['action'] === 'Draft created') {
                    $created++;
                } elseif ($sync['action'] === 'Draft refreshed') {
                    $refreshed++;
                } else {
                    $skipped++;
                }
            }

            update_option(self::OPTION_BASELINE, $next, false);

            return $this->record_status(
                $errors === 0,
                sprintf(
                    'Scan complete: %d draft(s) created, %d refreshed, %d skipped, %d error(s).',
                    $created,
                    $refreshed,
                    $skipped,
                    $errors
                ),
                $items
            );
        } finally {
            delete_transient(self::SCAN_LOCK);
        }
    }

    private function sync_script($path, $sha, $is_new) {
        $source_key = strtolower(self::OWNER . '/' . self::REPO . '@' . self::BRANCH . ':' . $path);
        $post_id    = $this->find_post($source_key);
        $github_url = $this->github_file_url($path);

        if (!$is_new && $post_id <= 0) {
            return [
                'action'     => 'Existing script changed; no linked draft',
                'path'       => $path,
                'github_url' => $github_url,
                'post_id'    => 0,
            ];
        }

        $source = $this->fetch_script_content($path);

        if (is_wp_error($source)) {
            return $source;
        }

        $header = $this->parse_header($source, $path);
        $post   = $this->build_post($path, $header, $github_url);

        if ($post_id > 0) {
            $existing = get_post($post_id);

            if (!$existing instanceof WP_Post) {
                return new WP_Error('missing_linked_post', 'The linked WordPress post could not be loaded.');
            }

            if (!in_array($existing->post_status, ['draft', 'pending'], true)) {
                update_post_meta($post_id, '_jl_github_script_sha', $sha);

                return [
                    'action'     => 'Skipped; post is already ' . $existing->post_status,
                    'path'       => $path,
                    'github_url' => $github_url,
                    'post_id'    => $post_id,
                ];
            }

            $post['ID'] = $post_id;
            $updated    = wp_update_post($post, true);

            if (is_wp_error($updated)) {
                return $updated;
            }

            $this->save_meta($post_id, $path, $sha, $github_url, $source_key, $header);

            return [
                'action'     => 'Draft refreshed',
                'path'       => $path,
                'github_url' => $github_url,
                'post_id'    => $post_id,
            ];
        }

        $author_id = get_current_user_id();

        if ($author_id <= 0) {
            $admins = get_users([
                'role__in' => ['administrator'],
                'number'   => 1,
                'orderby'  => 'ID',
                'order'    => 'ASC',
                'fields'   => 'ID',
            ]);

            if (!empty($admins)) {
                $author_id = (int) $admins[0];
            }
        }

        if ($author_id > 0) {
            $post['post_author'] = $author_id;
        }

        $post_id = wp_insert_post($post, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->save_meta($post_id, $path, $sha, $github_url, $source_key, $header);

        return [
            'action'     => 'Draft created',
            'path'       => $path,
            'github_url' => $github_url,
            'post_id'    => (int) $post_id,
        ];
    }

    private function fetch_script_tree() {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/git/trees/%s?recursive=1',
            self::OWNER,
            self::REPO,
            self::BRANCH
        );

        $response = $this->github_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        if (!empty($response['truncated'])) {
            return new WP_Error('truncated_tree', 'GitHub returned a truncated repository tree.');
        }

        $scripts = [];

        foreach (isset($response['tree']) && is_array($response['tree']) ? $response['tree'] : [] as $entry) {
            if (
                empty($entry['path']) ||
                empty($entry['sha']) ||
                empty($entry['type']) ||
                $entry['type'] !== 'blob' ||
                strtolower(pathinfo($entry['path'], PATHINFO_EXTENSION)) !== 'ps1'
            ) {
                continue;
            }

            $scripts[] = [
                'path' => (string) $entry['path'],
                'sha'  => (string) $entry['sha'],
            ];
        }

        usort($scripts, static function ($a, $b) {
            return strcasecmp($a['path'], $b['path']);
        });

        return $scripts;
    }

    private function fetch_script_content($path) {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            self::OWNER,
            self::REPO,
            $this->encode_path($path),
            self::BRANCH
        );

        $response = $this->github_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        if (
            empty($response['encoding']) ||
            $response['encoding'] !== 'base64' ||
            !isset($response['content'])
        ) {
            return new WP_Error('invalid_file_response', 'GitHub did not return base64 file content.');
        }

        $decoded = base64_decode((string) $response['content'], true);

        return $decoded === false
            ? new WP_Error('decode_failed', 'The GitHub file could not be decoded.')
            : $decoded;
    }

    private function github_get($url) {
        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Accept'               => 'application/vnd.github+json',
                'User-Agent'           => 'JL-WP-Plugins-Pack/' . JL_WP_PLUGINS_PACK_VERSION,
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('github_request_failed', 'GitHub request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !is_array($body)) {
            $message = is_array($body) && !empty($body['message'])
                ? sanitize_text_field($body['message'])
                : 'Unexpected GitHub response.';

            return new WP_Error('github_api_error', sprintf('GitHub API error (%d): %s', $code, $message));
        }

        return $body;
    }

    private function parse_header($content, $path) {
        $header = [
            'filename'      => basename($path),
            'revision'      => '',
            'description'   => '',
            'author'        => '',
            'created_date'  => '',
            'modified_date' => '',
            'changelog'     => [],
        ];

        $map = [
            'filename'      => 'filename',
            'revision'      => 'revision',
            'description'   => 'description',
            'author'        => 'author',
            'created date'  => 'created_date',
            'modified date' => 'modified_date',
        ];

        $collect = false;
        $lines   = preg_split('/\r\n|\r|\n/', (string) $content);

        foreach (array_slice($lines, 0, 120) as $line) {
            if (preg_match('/^\s*#\s*Changelog\s*:\s*$/i', $line)) {
                $collect = true;
                continue;
            }

            if ($collect) {
                if (preg_match('/^\s*#\s*(.+?)\s*$/', $line, $match)) {
                    $change = trim($match[1]);

                    if ($change !== '') {
                        $header['changelog'][] = sanitize_text_field($change);
                    }

                    if (count($header['changelog']) >= 10) {
                        break;
                    }

                    continue;
                }

                if (trim($line) !== '') {
                    break;
                }

                continue;
            }

            if (!preg_match('/^\s*#\s*([^:]+?)\s*:\s*(.*?)\s*$/', $line, $match)) {
                continue;
            }

            $label = strtolower(trim($match[1]));
            $value = sanitize_text_field(trim($match[2]));

            if (isset($map[$label]) && $value !== '') {
                $header[$map[$label]] = $value;
            }
        }

        return $header;
    }

    private function build_post($path, $header, $github_url) {
        $title = pathinfo(
            !empty($header['filename']) ? $header['filename'] : $path,
            PATHINFO_FILENAME
        );

        $description = !empty($header['description'])
            ? $header['description']
            : 'PowerShell script published in the GitHub repository.';

        $content  = '<p>' . esc_html($description) . '</p>';
        $content .= '<p>This draft was generated from GitHub. The script body is not copied into WordPress, so the link below always opens the current source file.</p>';
        $content .= '<p><a href="' . esc_url($github_url) . '" target="_blank" rel="noopener noreferrer"><strong>View the latest script on GitHub</strong></a></p>';
        $content .= '<h2>Script details</h2><ul>';

        foreach ([
            'filename'      => 'Filename',
            'revision'      => 'Revision',
            'author'        => 'Author',
            'created_date'  => 'Created date',
            'modified_date' => 'Modified date',
        ] as $key => $label) {
            if (!empty($header[$key])) {
                $content .= '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($header[$key]) . '</li>';
            }
        }

        $content .= '</ul>';

        if (!empty($header['changelog'])) {
            $content .= '<h2>Header changelog</h2><ul>';

            foreach ($header['changelog'] as $change) {
                $content .= '<li>' . esc_html($change) . '</li>';
            }

            $content .= '</ul>';
        }

        $content .= '<p><em>Source: ' . esc_html(self::OWNER . '/' . self::REPO . ', ' . self::BRANCH . ':' . $path) . '. Last synchronized ' . esc_html(wp_date('Y-m-d H:i:s T')) . '.</em></p>';

        return [
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => sanitize_text_field($title),
            'post_excerpt' => sanitize_text_field($description),
            'post_content' => wp_kses_post($content),
        ];
    }

    private function find_post($source_key) {
        $ids = get_posts([
            'post_type'              => 'post',
            'post_status'            => ['draft', 'pending', 'future', 'publish', 'private', 'trash'],
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'meta_key'               => '_jl_github_script_source_key',
            'meta_value'             => $source_key,
            'no_found_rows'          => true,
            'suppress_filters'       => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return !empty($ids) ? (int) $ids[0] : 0;
    }

    private function save_meta($post_id, $path, $sha, $github_url, $source_key, $header) {
        update_post_meta($post_id, '_jl_github_script_source_key', $source_key);
        update_post_meta($post_id, '_jl_github_script_path', $path);
        update_post_meta($post_id, '_jl_github_script_sha', $sha);
        update_post_meta($post_id, '_jl_github_script_url', esc_url_raw($github_url));
        update_post_meta($post_id, '_jl_github_script_revision', $header['revision']);
        update_post_meta($post_id, '_jl_github_script_modified_date', $header['modified_date']);
        update_post_meta($post_id, '_jl_github_script_last_synced', time());
    }

    private function get_settings() {
        $settings = wp_parse_args(
            get_option(self::OPTION_SETTINGS, []),
            self::default_settings()
        );

        if (!in_array($settings['frequency'], [self::WEEKLY_SCHEDULE, 'daily'], true)) {
            $settings['frequency'] = self::WEEKLY_SCHEDULE;
        }

        return $settings;
    }

    private function sanitize_settings($input) {
        $frequency = isset($input['github_ps_frequency'])
            ? sanitize_key(wp_unslash($input['github_ps_frequency']))
            : self::WEEKLY_SCHEDULE;

        if (!in_array($frequency, [self::WEEKLY_SCHEDULE, 'daily'], true)) {
            $frequency = self::WEEKLY_SCHEDULE;
        }

        return [
            'enabled'   => isset($input['github_ps_enabled']) ? 1 : 0,
            'frequency' => $frequency,
        ];
    }

    private function reschedule($settings) {
        wp_clear_scheduled_hook(self::CRON_HOOK);

        if (!empty($settings['enabled'])) {
            wp_schedule_event(
                time() + (5 * MINUTE_IN_SECONDS),
                $settings['frequency'],
                self::CRON_HOOK
            );
        }
    }

    private function record_status($success, $message, $items) {
        $result = [
            'success'   => (bool) $success,
            'message'   => (string) $message,
            'items'     => $items,
            'timestamp' => time(),
        ];

        update_option(self::OPTION_STATUS, [
            'success'   => $result['success'],
            'message'   => sanitize_text_field($result['message']),
            'timestamp' => $result['timestamp'],
        ], false);

        return $result;
    }

    private function github_file_url($path) {
        return sprintf(
            'https://github.com/%s/%s/blob/%s/%s',
            self::OWNER,
            self::REPO,
            self::BRANCH,
            $this->encode_path($path)
        );
    }

    private function encode_path($path) {
        return implode('/', array_map('rawurlencode', explode('/', (string) $path)));
    }
}
