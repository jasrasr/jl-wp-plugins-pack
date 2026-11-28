<?php
/**
 * Plugin Name: JL WP Plugins Pack
 * Description: WordPress content utilities, including excerpts, hashtag linking, AI-assisted posts, GitHub PowerShell drafts, semantic search, polls, Webstats, and optional site utilities.
 * Version: 1.5.6
 * Author: Jason Lamb
 * Primary Branch: main
 * GitHub Plugin URI: https://github.com/jasrasr/jl-wp-plugins-pack
 * Update URI: https://github.com/jasrasr/jl-wp-plugins-pack
 * Plugin URI: https://jasonlamb.me
 * License: GPL-2.0-or-later
 * Text Domain: jl-wp-plugins-pack
 */

if (!defined('ABSPATH')) {
    exit;
}

define('JL_WP_PLUGINS_PACK_VERSION', '1.5.6');
define('JL_WP_PLUGINS_PACK_PLUGIN_FILE', __FILE__);
define('JL_WP_PLUGINS_PACK_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-github-powershell-drafts.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-ai-posts.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-ask-writing.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-baby-poll.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-webstats.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-site-utilities.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-konami.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-yourls.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-custom-404.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack-visibility.php';

register_activation_hook(__FILE__, ['JL_GitHub_PowerShell_Drafts', 'activate']);
register_deactivation_hook(__FILE__, ['JL_GitHub_PowerShell_Drafts', 'deactivate']);
register_activation_hook(__FILE__, ['JL_WP_Plugins_Pack_AI_Posts', 'activate']);
register_deactivation_hook(__FILE__, ['JL_WP_Plugins_Pack_AI_Posts', 'deactivate']);
register_deactivation_hook(__FILE__, ['JL_WP_Plugins_Pack_Yourls', 'deactivate']);

add_action('plugins_loaded', static function () {
    new JL_WP_Plugins_Pack();
    new JL_GitHub_PowerShell_Drafts();
    new JL_WP_Plugins_Pack_AI_Posts();
    new JL_WP_Plugins_Pack_Ask_Writing();
    new JL_WP_Plugins_Pack_Baby_Poll();
    new JL_WP_Plugins_Pack_Webstats();
    new JL_WP_Plugins_Pack_Site_Utilities();
    new JL_WP_Plugins_Pack_Konami();
    new JL_WP_Plugins_Pack_Yourls();
    new JL_WP_Plugins_Pack_Custom_404();
    new JL_WP_Plugins_Pack_Visibility();
});
