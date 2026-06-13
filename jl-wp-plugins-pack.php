<?php
/**
 * Plugin Name: JL WP Plugins Pack
 * Description: Content utilities for WordPress, including bulk excerpt generation, automatic excerpts, and hashtag linking.
 * Version: 1.1.4
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

define('JL_WP_PLUGINS_PACK_VERSION', '1.1.4');
define('JL_WP_PLUGINS_PACK_PLUGIN_FILE', __FILE__);
define('JL_WP_PLUGINS_PACK_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once JL_WP_PLUGINS_PACK_PLUGIN_DIR . 'includes/class-jl-wp-plugins-pack.php';

add_action('plugins_loaded', static function () {
    new JL_WP_Plugins_Pack();
});

