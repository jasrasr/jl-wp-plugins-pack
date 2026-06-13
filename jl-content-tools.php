<?php
/**
 * Plugin Name: JL Content Tools
 * Description: Content utilities for WordPress, including bulk excerpt generation, automatic excerpts, and hashtag linking.
 * Version: 1.1.1
 * Author: Jason Lamb
 * Primary Branch: main
 * GitHub Plugin URI: https://github.com/jasrasr/jl-wp-plugins-pack
 * Update URI: https://github.com/jasrasr/jl-wp-plugins-pack
 * Plugin URI: https://jasonlamb.me
 * License: GPL-2.0-or-later
 * Text Domain: jl-content-tools
 */

if (!defined('ABSPATH')) {
    exit;
}

define('JL_CONTENT_TOOLS_VERSION', '1.1.1');
define('JL_CONTENT_TOOLS_PLUGIN_FILE', __FILE__);
define('JL_CONTENT_TOOLS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once JL_CONTENT_TOOLS_PLUGIN_DIR . 'includes/class-jl-content-tools.php';

add_action('plugins_loaded', static function () {
    new JL_Content_Tools();
});

