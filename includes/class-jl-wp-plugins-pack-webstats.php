<?php

if (!defined('ABSPATH')) {
    exit;
}

final class JL_WP_Plugins_Pack_Webstats {
    private const OPTION = 'jl_wp_plugins_pack_webstats_enabled';
    private const GROUP = 'jl_wp_plugins_pack_webstats';
    private const TRACKER_URL = 'https://jasr.me/github/webstats/assets/js/tracker.js';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_tracker']);
    }

    public function add_settings_page(): void {
        add_submenu_page(JL_WP_Plugins_Pack::MENU_SLUG, 'JL Webstats', 'JL Webstats', 'manage_options', self::GROUP, [$this, 'render_settings']);
    }

    public function register_settings(): void {
        register_setting(self::GROUP, self::OPTION, [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
    }

    public function enqueue_tracker(): void {
        if (!get_option(self::OPTION, false) || current_user_can('manage_options')) {
            return;
        }

        $tracker_url = defined('JASR_WEBSTATS_TRACKER_URL') ? JASR_WEBSTATS_TRACKER_URL : self::TRACKER_URL;
        wp_enqueue_script('jasr-webstats', $tracker_url, [], '1.0.0', true);
    }

    public function render_settings(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>JL Webstats</h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPTION); ?>" value="0">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>" value="1" <?php checked((bool) get_option(self::OPTION, false)); ?>>
                    Track public page views and clicks
                </label>
                <p>Add this site's origin to the Webstats collector before enabling tracking.</p>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
