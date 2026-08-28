<?php
/**
 * Scheduled AI-assisted blog post generation for JL WP Plugins Pack.
 */
if (!defined('ABSPATH')) { exit; }

class JL_WP_Plugins_Pack_AI_Posts {
    const NONCE_ACTION='jl_wp_plugins_pack_ai_post_settings', NONCE_NAME='jl_wp_plugins_pack_ai_post_nonce', OPTION_NAME='jl_wp_plugins_pack_ai_post_settings', LOG_OPTION='jl_wp_plugins_pack_ai_post_log', CRON_HOOK='jl_wp_plugins_pack_generate_ai_post';

    public function __construct() {
        add_filter('cron_schedules', [$this,'add_cron_schedules']);
        add_action('admin_menu', [$this,'add_tools_page']);
        add_action(self::CRON_HOOK, [$this,'run_scheduled_ai_post']);
        $this->sync_cron();
    }

    public static function activate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        $s = wp_parse_args(is_array(get_option(self::OPTION_NAME, [])) ? get_option(self::OPTION_NAME, []) : [], self::defaults());
        if (!empty($s['enabled'])) { wp_schedule_event(time()+HOUR_IN_SECONDS, self::recurrence($s['recurrence']), self::CRON_HOOK); }
    }

    public static function deactivate() { wp_clear_scheduled_hook(self::CRON_HOOK); }

    public function add_cron_schedules($s) {
        if (!isset($s['weekly'])) { $s['weekly']=['interval'=>WEEK_IN_SECONDS,'display'=>__('Once Weekly','jl-wp-plugins-pack')]; }
        return $s;
    }

    public function add_tools_page() { add_management_page('JL AI Posts','JL AI Posts','manage_options','jl-wp-plugins-pack-ai-posts',[$this,'render_page']); }

    public function render_page() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to access this page.','jl-wp-plugins-pack')); }
        $notice=null;
        if (isset($_POST['jl_save_ai_post_settings'])) { check_admin_referer(self::NONCE_ACTION,self::NONCE_NAME); $this->save($_POST); $notice=['success','Scheduled AI post settings saved.']; }
        if (isset($_POST['jl_run_ai_post_now'])) { check_admin_referer(self::NONCE_ACTION,self::NONCE_NAME); $r=$this->run_scheduled_ai_post(true); $notice=[empty($r['success'])?'error':'success', empty($r['success'])?$r['message']:'Generated "'.$r['title'].'" as '.$r['status'].'.', !empty($r['post_id'])?get_edit_post_link((int)$r['post_id']):'']; }
        $s=$this->settings(); $log=$this->log(); $next=wp_next_scheduled(self::CRON_HOOK);
        $authors=get_users(['capability'=>'edit_posts','orderby'=>'display_name','order'=>'ASC']); $cats=get_categories(['hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
        echo '<div class="wrap"><h1>JL AI Posts</h1>';
        if ($notice) { echo '<div class="notice notice-'.esc_attr($notice[0]).' is-dismissible"><p>'.esc_html($notice[1]).(!empty($notice[2])?' <a href="'.esc_url($notice[2]).'">Open post</a>':'').'</p></div>'; }
        echo '<p>Generate one AI-assisted blog post on a schedule. The selected author controls whether posts can publish or must remain pending/draft.</p><div class="notice notice-info inline"><p><strong>Safer default:</strong> start with a Contributor-style AI user. Tiny robot intern, not robot site owner.</p></div><form method="post">';
        wp_nonce_field(self::NONCE_ACTION,self::NONCE_NAME);
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th>Enable Schedule</th><td><label><input type="checkbox" name="ai_enabled" value="1" '.checked(!empty($s['enabled']),true,false).' /> Generate automatically using WP-Cron.</label></td></tr>';
        echo '<tr><th><label for="ai_recurrence">Frequency</label></th><td><select name="ai_recurrence" id="ai_recurrence">'.$this->options(['weekly'=>'Weekly','daily'=>'Daily','twicedaily'=>'Twice Daily','hourly'=>'Hourly'],$s['recurrence']).'</select></td></tr>';
        echo '<tr><th><label for="ai_author_id">AI Author</label></th><td><select name="ai_author_id" id="ai_author_id"><option value="0">Select an author</option>'; foreach($authors as $a){ echo '<option value="'.esc_attr((string)$a->ID).'" '.selected((int)$s['author_id'],(int)$a->ID,false).'>'.esc_html($a->display_name.' ('.implode(', ',(array)$a->roles).')').'</option>'; } echo '</select><p class="description">Only users with edit_posts are shown.</p></td></tr>';
        echo '<tr><th><label for="ai_status_policy">Post Status Policy</label></th><td><select name="ai_status_policy" id="ai_status_policy">'.$this->options(['draft'=>'Always save as Draft','pending'=>'Always save as Pending Review','role'=>'Role-based: publish only if author can publish','publish'=>'Publish if allowed, otherwise Pending Review'],$s['status_policy']).'</select><p class="description">The plugin will not publish as an author that lacks publish_posts.</p></td></tr>';
        echo '<tr><th><label for="ai_openai_api_key">OpenAI API Key</label></th><td><input type="password" name="ai_openai_api_key" id="ai_openai_api_key" value="" class="regular-text" autocomplete="off" /><p class="description">Leave blank to keep the existing key. '.($this->has_key()?'A key is stored. ':'').($this->has_const_key()?'The JL_WP_PLUGINS_PACK_OPENAI_API_KEY constant overrides it.':'').'</p></td></tr>';
        echo '<tr><th><label for="ai_model">OpenAI Model</label></th><td><input type="text" name="ai_model" id="ai_model" value="'.esc_attr($s['model']).'" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="ai_max_words">Target Length</label></th><td><input type="number" name="ai_max_words" id="ai_max_words" value="'.esc_attr((string)$s['max_words']).'" min="300" max="2500" /> words</td></tr>';
        echo '<tr><th><label for="ai_category_id">Category</label></th><td><select name="ai_category_id" id="ai_category_id"><option value="0">Default WordPress category</option>'; foreach($cats as $c){ echo '<option value="'.esc_attr((string)$c->term_id).'" '.selected((int)$s['category_id'],(int)$c->term_id,false).'>'.esc_html($c->name).'</option>'; } echo '</select></td></tr>';
        echo '<tr><th><label for="ai_tag_names">Base Tags</label></th><td><input type="text" name="ai_tag_names" id="ai_tag_names" value="'.esc_attr($s['tag_names']).'" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="ai_topic_seed">Topic Instructions</label></th><td><textarea name="ai_topic_seed" id="ai_topic_seed" rows="5" class="large-text">'.esc_textarea($s['topic_seed']).'</textarea></td></tr>';
        echo '<tr><th><label for="ai_system_prompt">System Prompt</label></th><td><textarea name="ai_system_prompt" id="ai_system_prompt" rows="5" class="large-text">'.esc_textarea($s['system_prompt']).'</textarea></td></tr>';
        echo '<tr><th>Disclosure Footer</th><td><label><input type="checkbox" name="ai_include_disclosure" value="1" '.checked(!empty($s['include_disclosure']),true,false).' /> Append disclosure.</label><textarea name="ai_disclosure_text" rows="2" class="large-text">'.esc_textarea($s['disclosure_text']).'</textarea></td></tr>';
        echo '</table><p><button type="submit" name="jl_save_ai_post_settings" class="button button-primary">Save AI Post Settings</button></p></form><form method="post">';
        wp_nonce_field(self::NONCE_ACTION,self::NONCE_NAME);
        echo '<p><button type="submit" name="jl_run_ai_post_now" class="button">Generate One AI Post Now</button></p></form>';
        echo '<h2>AI Post Status</h2><p><strong>Next scheduled run:</strong> '.($next?esc_html(wp_date('Y-m-d H:i:s',$next)):esc_html__('Not scheduled','jl-wp-plugins-pack')).'</p><p><strong>Last run:</strong> '.(!empty($log['last_run'])?esc_html($log['last_run']):'Never').'</p><p><strong>Last result:</strong> '.(!empty($log['last_message'])?esc_html($log['last_message']):'No result yet').'</p></div>';
    }

    public function run_scheduled_ai_post($manual=false) {
        $s=$this->settings(); if (empty($s['enabled']) && !$manual) { return $this->record(false,'Scheduled AI posts are disabled.'); }
        $key=$this->api_key($s); if ($key==='') { return $this->record(false,'Missing OpenAI API key.'); }
        $author=(int)$s['author_id']; if ($author<=0 || !get_userdata($author) || !user_can($author,'edit_posts')) { return $this->record(false,'The selected AI author must be a WordPress user with edit_posts permission.'); }
        $gen=$this->generate($s,$key); if (is_wp_error($gen)) { return $this->record(false,$gen->get_error_message()); }
        $pid=$this->insert($gen,$s,$author); if (is_wp_error($pid)) { return $this->record(false,$pid->get_error_message()); }
        $status=get_post_status($pid); return $this->record(true,'Created AI post '.$pid.' as '.$status.'.',['post_id'=>(int)$pid,'title'=>get_the_title($pid),'status'=>$status]);
    }

    private function sync_cron($s=null) {
        $s=$s?:$this->settings(); $event=wp_get_scheduled_event(self::CRON_HOOK);
        if (empty($s['enabled'])) { if ($event) { wp_clear_scheduled_hook(self::CRON_HOOK); } return; }
        $r=self::recurrence($s['recurrence']); if (!$event || $event->schedule!==$r) { wp_clear_scheduled_hook(self::CRON_HOOK); wp_schedule_event(time()+HOUR_IN_SECONDS,$r,self::CRON_HOOK); }
    }

    private function generate($s,$key) {
        $prompt=sprintf("Today is %s. Write one original blog post for the site \"%s\".\n\nTopic guidance:\n%s\n\nRequirements:\n- Target length: about %d words.\n- Practical, plain-English technical writing.\n- Do not invent personal events, customer names, prices, dates, or product facts.\n- Avoid medical, legal, financial, exploit, credential-theft, malware, or privacy-invasive instructions.\n- Return only valid JSON with keys: title, excerpt, content, tags.\n- content must be WordPress-safe HTML.",wp_date('F j, Y'),get_bloginfo('name'),$s['topic_seed'],(int)$s['max_words']);
        $res=wp_remote_post('https://api.openai.com/v1/chat/completions',['timeout'=>90,'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],'body'=>wp_json_encode(['model'=>$s['model'],'messages'=>[['role'=>'system','content'=>$s['system_prompt']],['role'=>'user','content'=>$prompt]]])]);
        if (is_wp_error($res)) { return $res; } $code=(int)wp_remote_retrieve_response_code($res); $body=json_decode((string)wp_remote_retrieve_body($res),true);
        if ($code<200 || $code>=300) { return new WP_Error('jl_ai_openai_error','OpenAI request failed. '.(is_array($body)&&!empty($body['error']['message'])?sanitize_text_field((string)$body['error']['message']):'')); }
        $text=is_array($body)&&!empty($body['choices'][0]['message']['content'])?(string)$body['choices'][0]['message']['content']:''; return $this->parse($text);
    }

    private function parse($text) {
        $text=trim((string)$text); $text=preg_replace('/^```(?:json)?\s*/i','',$text); $text=preg_replace('/\s*```$/','',(string)$text); $a=strpos($text,'{'); $b=strrpos($text,'}');
        if ($a===false || $b===false || $b<=$a) { return new WP_Error('jl_ai_json_missing','AI response did not contain a JSON object.'); }
        $d=json_decode(substr($text,$a,$b-$a+1),true); if (!is_array($d)) { return new WP_Error('jl_ai_json_invalid','AI response JSON could not be decoded.'); }
        $title=!empty($d['title'])?sanitize_text_field(wp_strip_all_tags((string)$d['title'])):''; $content=!empty($d['content'])?wp_kses_post((string)$d['content']):''; if ($title===''||$content==='') { return new WP_Error('jl_ai_missing_fields','AI response must include title and content.'); }
        $tags=[]; if (!empty($d['tags'])&&is_array($d['tags'])) { foreach($d['tags'] as $t){ $t=sanitize_text_field(wp_strip_all_tags((string)$t)); if($t!==''){$tags[]=$t;} } }
        return ['title'=>$title,'excerpt'=>!empty($d['excerpt'])?sanitize_text_field(wp_strip_all_tags((string)$d['excerpt'])):'','content'=>$content,'tags'=>array_values(array_unique($tags))];
    }

    private function insert($g,$s,$author) {
        $content=$g['content']; if (!empty($s['include_disclosure'])&&!empty($s['disclosure_text'])) { $content.="\n\n<p><em>".esc_html($s['disclosure_text'])."</em></p>"; }
        $post=['post_type'=>'post','post_status'=>$this->status($s,$author),'post_author'=>$author,'post_title'=>$g['title'],'post_content'=>$content,'post_excerpt'=>$g['excerpt']]; if (!empty($s['category_id'])) { $post['post_category']=[(int)$s['category_id']]; }
        $pid=wp_insert_post($post,true); if (is_wp_error($pid)) { return $pid; } $tags=array_values(array_unique(array_merge($this->tags($s['tag_names']),$g['tags']))); if ($tags) { wp_set_post_tags((int)$pid,$tags,false); }
        update_post_meta((int)$pid,'_jl_wp_plugins_pack_ai_generated','1'); update_post_meta((int)$pid,'_jl_wp_plugins_pack_ai_model',sanitize_text_field($s['model'])); return (int)$pid;
    }

    private function status($s,$author) { if ($s['status_policy']==='draft') return 'draft'; if ($s['status_policy']==='pending') return 'pending'; if ($s['status_policy']==='publish') return user_can($author,'publish_posts')?'publish':'pending'; return user_can($author,'publish_posts')?'publish':'pending'; }
    private function save($in) { $old=$this->settings(); $s=self::defaults(); $s['enabled']=!empty($in['ai_enabled']); $s['recurrence']=isset($in['ai_recurrence'])?self::recurrence(wp_unslash($in['ai_recurrence'])):'weekly'; $s['author_id']=isset($in['ai_author_id'])?absint(wp_unslash($in['ai_author_id'])):0; $s['status_policy']=isset($in['ai_status_policy'])?sanitize_key(wp_unslash($in['ai_status_policy'])):'draft'; if(!in_array($s['status_policy'],['draft','pending','role','publish'],true)){$s['status_policy']='draft';} $s['model']=isset($in['ai_model'])?sanitize_text_field(wp_unslash($in['ai_model'])):'gpt-5.5'; $s['max_words']=isset($in['ai_max_words'])?max(300,min(2500,absint(wp_unslash($in['ai_max_words'])))):900; $s['category_id']=isset($in['ai_category_id'])?absint(wp_unslash($in['ai_category_id'])):0; $s['tag_names']=isset($in['ai_tag_names'])?sanitize_text_field(wp_unslash($in['ai_tag_names'])):''; $s['topic_seed']=isset($in['ai_topic_seed'])?sanitize_textarea_field(wp_unslash($in['ai_topic_seed'])):$s['topic_seed']; $s['system_prompt']=isset($in['ai_system_prompt'])?sanitize_textarea_field(wp_unslash($in['ai_system_prompt'])):$s['system_prompt']; $s['include_disclosure']=!empty($in['ai_include_disclosure']); $s['disclosure_text']=isset($in['ai_disclosure_text'])?sanitize_textarea_field(wp_unslash($in['ai_disclosure_text'])):$s['disclosure_text']; $new=isset($in['ai_openai_api_key'])?trim((string)wp_unslash($in['ai_openai_api_key'])):''; $s['openai_api_key']=$new!==''?sanitize_text_field($new):(!empty($old['openai_api_key'])?$old['openai_api_key']:''); update_option(self::OPTION_NAME,$s,false); $this->sync_cron($s); }
    private function settings(){ $o=get_option(self::OPTION_NAME,[]); return wp_parse_args(is_array($o)?$o:[],self::defaults()); }
    private static function defaults(){ return ['enabled'=>false,'recurrence'=>'weekly','author_id'=>0,'status_policy'=>'draft','openai_api_key'=>'','model'=>'gpt-5.5','max_words'=>900,'category_id'=>0,'tag_names'=>'AI, Automation','topic_seed'=>'Write practical posts for Jason Lamb about PowerShell, WordPress, GitHub, IT automation, AI-assisted coding, small web tools, and useful technology projects.','system_prompt'=>'You write practical, factual blog posts for Jason Lamb. Do not invent personal anecdotes, private details, product facts, dates, or claims. Prefer drafts that a human can quickly review.','include_disclosure'=>true,'disclosure_text'=>'This post was drafted with AI assistance and should be reviewed before relying on it.']; }
    private static function recurrence($r){ $r=sanitize_key((string)$r); return in_array($r,['hourly','twicedaily','daily','weekly'],true)?$r:'weekly'; }
    private function record($ok,$msg,$x=[]){ $log=['last_run'=>wp_date('Y-m-d H:i:s'),'last_success'=>(bool)$ok,'last_message'=>sanitize_text_field((string)$msg),'last_post_id'=>!empty($x['post_id'])?(int)$x['post_id']:0]; update_option(self::LOG_OPTION,$log,false); return array_merge(['success'=>(bool)$ok,'message'=>(string)$msg],$x); }
    private function log(){ $l=get_option(self::LOG_OPTION,[]); return is_array($l)?$l:[]; }
    private function has_const_key(){ return defined('JL_WP_PLUGINS_PACK_OPENAI_API_KEY') && trim((string)constant('JL_WP_PLUGINS_PACK_OPENAI_API_KEY'))!==''; }
    private function has_key(){ $s=$this->settings(); return !empty($s['openai_api_key']); }
    private function api_key($s){ return $this->has_const_key()?trim((string)constant('JL_WP_PLUGINS_PACK_OPENAI_API_KEY')):(!empty($s['openai_api_key'])?trim((string)$s['openai_api_key']):''); }
    private function tags($csv){ $out=[]; foreach(explode(',',(string)$csv) as $t){ $t=sanitize_text_field(trim($t)); if($t!==''){$out[]=$t;} } return array_values(array_unique($out)); }
    private function options($map,$sel){ $h=''; foreach($map as $k=>$v){ $h.='<option value="'.esc_attr($k).'" '.selected($sel,$k,false).'>'.esc_html($v).'</option>'; } return $h; }
}
