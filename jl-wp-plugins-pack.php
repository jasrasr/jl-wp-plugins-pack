<?php
/**
 * Plugin Name: JL WP Plugins Pack
 * Description: Content utilities for WordPress, including excerpts, hashtag linking, and GitHub PowerShell script draft generation.
 * Version: 1.2.0
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

define('JL_WP_PLUGINS_PACK_VERSION', '1.2.0');
define('JL_WP_PLUGINS_PACK_PLUGIN_FILE', __FILE__);
define('JL_WP_PLUGINS_PACK_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack.php';
require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-github-powershell-drafts.php';

register_activation_hook(__FILE__, ['JL_GitHub_PowerShell_Drafts', 'activate']);
register_deactivation_hook(__FILE__, ['JL_GitHub_PowerShell_Drafts', 'deactivate']);

add_action('plugins_loaded', static function () {
    new JL_WP_Plugins_Pack();
    new JL_GitHub_PowerShell_Drafts();
});
