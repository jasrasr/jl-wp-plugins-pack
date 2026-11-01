<?php
if (!defined('ABSPATH')) { exit; }

class JL_WP_Plugins_Pack_Baby_Poll {
    const OPTION = 'jl_wp_plugins_pack_poll_votes';
    const COOKIE = 'jl_poll_voted_';
    const NONCE = 'jl_wp_plugins_pack_poll_vote';

    public function __construct() {
        add_shortcode('jl_poll', [$this, 'render']);
        add_shortcode('jl_baby_gender_poll', [$this, 'render_baby_alias']);
        add_action('admin_post_jl_poll_vote', [$this, 'vote']);
        add_action('admin_post_nopriv_jl_poll_vote', [$this, 'vote']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_jl_poll_reset', [$this, 'reset']);
    }

    public function render_baby_alias($atts = []) {
        $atts = shortcode_atts(['id'=>'baby-gender','question'=>'Will the baby be a boy or a girl?','options'=>'Boy|Girl'], $atts, 'jl_baby_gender_poll');
        return $this->render($atts);
    }

    private function config($atts) {
        $atts = shortcode_atts(['id'=>'poll','question'=>'What do you think?','options'=>'Yes|No'], $atts, 'jl_poll');
        $id = sanitize_key($atts['id']);
        $options = array_values(array_filter(array_map('trim', explode('|', sanitize_text_field($atts['options'])))));
        $options = array_slice(array_unique($options), 0, 8);
        if (!$id) $id='poll';
        if (count($options)<2) $options=['Yes','No'];
        return [$id, sanitize_text_field($atts['question']), $options];
    }

    public function render($atts = []) {
        [$id,$question,$options]=$this->config($atts);
        $all=get_option(self::OPTION, []);
        $votes=is_array($all[$id]??null)?$all[$id]:[];
        $total=array_sum(array_map('absint',$votes));
        $cookie=self::COOKIE.substr(hash('sha256',$id),0,16);
        $voted=isset($_COOKIE[$cookie]);
        ob_start(); ?>
        <section class="jl-poll"><h2><?php echo esc_html($question); ?></h2>
        <?php if(!$voted): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="jl_poll_vote"><input type="hidden" name="poll_id" value="<?php echo esc_attr($id); ?>">
        <input type="hidden" name="poll_options" value="<?php echo esc_attr(wp_json_encode($options)); ?>">
        <?php wp_nonce_field(self::NONCE.$id,'_jl_poll_nonce'); foreach($options as $i=>$label): ?>
        <button name="poll_choice" value="<?php echo esc_attr((string)$i); ?>"><?php echo esc_html($label); ?></button>
        <?php endforeach; ?></form><?php else: ?><p>Thanks for voting!</p><?php endif;
        if($voted || !$total): foreach($options as $i=>$label): $count=absint($votes[$i]??0); $pct=$total?(int)round(100*$count/$total):0; ?>
        <p><?php echo esc_html($label); ?>: <?php echo esc_html((string)$count); ?> (<?php echo esc_html((string)$pct); ?>%)</p>
        <?php endforeach; ?><small><?php echo esc_html((string)$total); ?> votes</small><?php else: ?><p>Vote to see results.</p><?php endif; ?></section>
        <?php return ob_get_clean();
    }

    public function vote() {
        $id=isset($_POST['poll_id'])?sanitize_key(wp_unslash($_POST['poll_id'])):'';
        $nonce=isset($_POST['_jl_poll_nonce'])?sanitize_text_field(wp_unslash($_POST['_jl_poll_nonce'])):'';
        $opts=isset($_POST['poll_options'])?json_decode(wp_unslash($_POST['poll_options']),true):[];
        $choice=isset($_POST['poll_choice'])?absint($_POST['poll_choice']):-1;
        if(!$id || !wp_verify_nonce($nonce,self::NONCE.$id) || !is_array($opts) || count($opts)<2 || $choice<0 || $choice>=count($opts)) wp_die('Invalid poll submission.','',['response'=>400]);
        $cookie=self::COOKIE.substr(hash('sha256',$id),0,16);
        if(!isset($_COOKIE[$cookie])) {
            $all=get_option(self::OPTION,[]); if(!is_array($all))$all=[];
            if(!isset($all[$id])||!is_array($all[$id]))$all[$id]=[];
            $all[$id][$choice]=absint($all[$id][$choice]??0)+1; update_option(self::OPTION,$all,false);
            setcookie($cookie,'1',['expires'=>time()+YEAR_IN_SECONDS,'path'=>COOKIEPATH?:'/','domain'=>COOKIE_DOMAIN,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        }
        wp_safe_redirect(wp_get_referer()?:home_url('/')); exit;
    }

    public function admin_menu(){ add_management_page('JL Polls','JL Polls','manage_options','jl-polls',[$this,'admin_page']); }
    public function admin_page(){ if(!current_user_can('manage_options'))return; $all=get_option(self::OPTION,[]); echo '<div class="wrap"><h1>JL Polls</h1><p>Poll totals are stored in WordPress options. Use [jl_poll id="lunch" question="What should we eat?" options="Pizza|Tacos|Burgers"]. IDs keep poll results separate. [jl_baby_gender_poll] remains available.</p><pre>'.esc_html(print_r($all,true)).'</pre><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="jl_poll_reset">'; wp_nonce_field('jl_poll_reset','_jl_poll_reset_nonce'); submit_button('Reset all poll totals','delete'); echo '</form></div>'; }
    public function reset(){ if(!current_user_can('manage_options'))wp_die('Forbidden','',['response'=>403]); check_admin_referer('jl_poll_reset','_jl_poll_reset_nonce'); delete_option(self::OPTION); wp_safe_redirect(admin_url('tools.php?page=jl-polls')); exit; }
}