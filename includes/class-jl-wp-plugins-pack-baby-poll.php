<?php
/**
 * Baby gender guess poll for JL WP Plugins Pack.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JL_WP_Plugins_Pack_Baby_Poll {
    const OPTION_NAME = 'jl_wp_plugins_pack_baby_poll_votes';
    const COOKIE_NAME = 'jl_baby_poll_voted';
    const NONCE_ACTION = 'jl_wp_plugins_pack_baby_poll_vote';

    public function __construct() {
        add_shortcode('jl_baby_gender_poll', [$this, 'render_shortcode']);
        add_action('admin_post_jl_baby_poll_vote', [$this, 'handle_vote']);
        add_action('admin_post_nopriv_jl_baby_poll_vote', [$this, 'handle_vote']);
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_jl_baby_poll_reset', [$this, 'handle_reset']);
    }

    private function votes() {
        $votes = get_option(self::OPTION_NAME, []);
        $votes = is_array($votes) ? $votes : [];
        return [
            'boy' => isset($votes['boy']) ? absint($votes['boy']) : 0,
            'girl' => isset($votes['girl']) ? absint($votes['girl']) : 0,
        ];
    }

    private function visitor_has_voted() {
        return isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] === '1';
    }

    public function render_shortcode($attributes = []) {
        $attributes = shortcode_atts([
            'question' => 'Will the baby be a boy or a girl?',
        ], $attributes, 'jl_baby_gender_poll');

        $question = sanitize_text_field($attributes['question']);
        $votes = $this->votes();
        $total = $votes['boy'] + $votes['girl'];
        $has_voted = $this->visitor_has_voted();
        $instance_id = wp_unique_id('jl-baby-poll-');

        ob_start();
        ?>
        <section class="jl-baby-poll" aria-labelledby="<?php echo esc_attr($instance_id); ?>-question">
            <style>
                .jl-baby-poll{max-width:520px;margin:1.5rem auto;padding:1.5rem;border:1px solid #d8dee8;border-radius:14px;background:#fff;color:#1f2937;box-shadow:0 5px 20px rgba(15,23,42,.06);font:inherit}
                .jl-baby-poll h2{margin:0 0 1rem;font-size:1.35rem;line-height:1.3;color:inherit}
                .jl-baby-poll__choices{display:flex;gap:.75rem;flex-wrap:wrap}
                .jl-baby-poll__button{flex:1;min-width:130px;padding:.85rem 1rem;border:0;border-radius:9px;background:#2257a5;color:#fff;font:inherit;font-weight:700;cursor:pointer}
                .jl-baby-poll__button:hover,.jl-baby-poll__button:focus{background:#163e7c}
                .jl-baby-poll__button:focus-visible{outline:3px solid #f59e0b;outline-offset:2px}
                .jl-baby-poll__results{margin-top:1.25rem}
                .jl-baby-poll__row{margin:.8rem 0}
                .jl-baby-poll__label{display:flex;justify-content:space-between;gap:1rem;margin-bottom:.3rem;font-weight:600}
                .jl-baby-poll__track{height:12px;overflow:hidden;border-radius:999px;background:#e5e7eb}
                .jl-baby-poll__bar{height:100%;border-radius:999px;background:#4b8bd8}
                .jl-baby-poll__row--girl .jl-baby-poll__bar{background:#e879a9}
                .jl-baby-poll__total,.jl-baby-poll__message{margin:.8rem 0 0;color:#5b6472}
            </style>
            <h2 id="<?php echo esc_attr($instance_id); ?>-question"><?php echo esc_html($question); ?></h2>
            <?php if (!$has_voted) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="jl_baby_poll_vote">
                    <?php wp_nonce_field(self::NONCE_ACTION, '_jl_baby_poll_nonce'); ?>
                    <div class="jl-baby-poll__choices">
                        <button class="jl-baby-poll__button" type="submit" name="jl_baby_poll_choice" value="boy">Boy</button>
                        <button class="jl-baby-poll__button" type="submit" name="jl_baby_poll_choice" value="girl">Girl</button>
                    </div>
                </form>
            <?php else : ?>
                <p class="jl-baby-poll__message" role="status">Thanks for your guess!</p>
            <?php endif; ?>

            <?php if ($has_voted || $total === 0) : ?>
                <div class="jl-baby-poll__results" aria-label="Poll results">
                    <?php foreach (['boy' => 'Boy', 'girl' => 'Girl'] as $key => $label) :
                        $percentage = $total ? (int) round(($votes[$key] / $total) * 100) : 0;
                    ?>
                        <div class="jl-baby-poll__row jl-baby-poll__row--<?php echo esc_attr($key); ?>">
                            <div class="jl-baby-poll__label">
                                <span><?php echo esc_html($label); ?></span>
                                <span><?php echo esc_html((string) $percentage); ?>% (<?php echo esc_html((string) $votes[$key]); ?>)</span>
                            </div>
                            <div class="jl-baby-poll__track" role="progressbar" aria-label="<?php echo esc_attr($label); ?> votes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $percentage); ?>">
                                <div class="jl-baby-poll__bar" style="width:<?php echo esc_attr((string) $percentage); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p class="jl-baby-poll__total">Based on <?php echo esc_html((string) $total); ?> <?php echo esc_html(_n('guess', 'guesses', $total, 'jl-wp-plugins-pack')); ?>.</p>
                </div>
            <?php else : ?>
                <p class="jl-baby-poll__message">Cast your guess to see the results.</p>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_vote() {
        $nonce = isset($_POST['_jl_baby_poll_nonce']) ? sanitize_text_field(wp_unslash($_POST['_jl_baby_poll_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_die(esc_html__('This poll form expired. Refresh the page and try again.', 'jl-wp-plugins-pack'), '', ['response' => 403]);
        }

        if ($this->visitor_has_voted()) {
            $this->redirect_back();
        }

        $choice = isset($_POST['jl_baby_poll_choice']) ? sanitize_key(wp_unslash($_POST['jl_baby_poll_choice'])) : '';
        if (!in_array($choice, ['boy', 'girl'], true)) {
            wp_die(esc_html__('Choose Boy or Girl.', 'jl-wp-plugins-pack'), '', ['response' => 400]);
        }

        $votes = $this->votes();
        $votes[$choice]++;
        update_option(self::OPTION_NAME, $votes, false);

        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, '1', [
                'expires' => time() + YEAR_IN_SECONDS,
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[self::COOKIE_NAME] = '1';
        }

        $this->redirect_back();
    }

    public function add_admin_page() {
        add_management_page(
            'JL Baby Gender Poll',
            'JL Baby Gender Poll',
            'manage_options',
            'jl-baby-gender-poll',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $votes = $this->votes();
        $total = $votes['boy'] + $votes['girl'];
        ?>
        <div class="wrap">
            <h1>JL Baby Gender Poll</h1>
            <p><?php echo esc_html(sprintf(__('Boy: %1$d · Girl: %2$d · Total: %3$d', 'jl-wp-plugins-pack'), $votes['boy'], $votes['girl'], $total)); ?></p>
            <p>Place <code>[jl_baby_gender_poll]</code> on a page or post. Customize the prompt with <code>[jl_baby_gender_poll question="Will Baby Lamb be a boy or a girl?"]</code>.</p>
            <p>Results appear after a visitor votes. Only aggregate totals are saved; the plugin does not store voter names, email addresses, IP addresses, or user-agent strings. A one-year first-party cookie discourages repeat votes in the same browser.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Reset all poll votes?', 'jl-wp-plugins-pack')); ?>');">
                <input type="hidden" name="action" value="jl_baby_poll_reset">
                <?php wp_nonce_field('jl_baby_poll_reset', '_jl_baby_poll_reset_nonce'); ?>
                <?php submit_button(__('Reset all votes', 'jl-wp-plugins-pack'), 'delete'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_reset() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to reset this poll.', 'jl-wp-plugins-pack'), '', ['response' => 403]);
        }
        check_admin_referer('jl_baby_poll_reset', '_jl_baby_poll_reset_nonce');
        delete_option(self::OPTION_NAME);
        wp_safe_redirect(add_query_arg('page', 'jl-baby-gender-poll', admin_url('tools.php')));
        exit;
    }

    private function redirect_back() {
        $referer = wp_get_referer();
        wp_safe_redirect($referer ? $referer : home_url('/'));
        exit;
    }
}
