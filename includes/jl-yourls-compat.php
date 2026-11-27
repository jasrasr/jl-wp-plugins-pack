<?php
if (!defined('ABSPATH')) {
    exit;
}

// Preserve the original plugin's template tags for themes that call them.
if (!function_exists('get_yourls_shortlink')) {
    function get_yourls_shortlink($post_id = 0, $echo = false) {
        if (!$post_id) {
            global $post;
            $post_id = isset($post->ID) ? absint($post->ID) : 0;
        }
        $url = $post_id ? get_post_meta($post_id, '_yourls_url', true) : '';
        $url = $url ? esc_url($url) : '';
        if ($echo && $url) {
            echo $url;
        }
        return $url;
    }
}

if (!function_exists('yourls_display_box')) {
    function yourls_display_box($post_id = 0, $echo = true) {
        $url = get_yourls_shortlink($post_id);
        if (!$url) {
            return '';
        }
        $html = '<div class="jl-yourls-box"><label>Short URL <input type="url" readonly value="' . esc_attr($url) . '"></label></div>';
        if ($echo) {
            echo $html;
        }
        return $html;
    }
}

if (!function_exists('wp_ozh_yourls_geturl')) {
    function wp_ozh_yourls_geturl($post_id) {
        $url = get_yourls_shortlink(absint($post_id));
        return $url ? $url : get_post_meta(absint($post_id), 'yourls_shorturl', true);
    }
}

if (!function_exists('wp_ozh_yourls_raw_url')) {
    function wp_ozh_yourls_raw_url($echo = false) {
        global $id;
        $url = apply_filters('ozh_yourls_shorturl', wp_ozh_yourls_geturl(absint($id)));
        if ($echo && $url) {
            echo esc_url($url);
        }
        return $url ? esc_url($url) : '';
    }
}

if (!function_exists('wp_ozh_yourls_url')) {
    function wp_ozh_yourls_url() {
        $url = wp_ozh_yourls_raw_url();
        if ($url) {
            echo '<a href="' . esc_url($url) . '" rel="nofollow alternate shorturl shortlink" title="Short URL">' . esc_html($url) . '</a>';
        }
    }
}
