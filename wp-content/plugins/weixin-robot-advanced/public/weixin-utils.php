<?php
class WEIXIN_Util{
	private static $current_openid	= null;
	private static $current_appid	= null;
	private static $instances		= [];

	private $appid		= null;
	private $weixin		= null;
	private $settings	= [];
	private $extends	= [];

	private function __construct($appid, $settings=[]){
		$this->appid	= $appid;
		$this->settings	= $settings;

		$secret			= $settings['secret'] ?? $settings['weixin_app_secret'];

		$this->weixin	= new WEIXIN($appid, $secret);
		$this->extends	= get_option('weixin_'.$appid.'_extends') ?: [];
	}

	public function get_weixin(){
		return $this->weixin;
	}

	public function get_extends(){
		return $this->extends;
	}

	public function get_setting($setting_name=''){
		if($setting_name){
			return $this->settings[$setting_name] ?? null;
		}else{
			return $this->settings;
		}
	}

	public function update_setting($setting_name, $setting_value){
		if(is_multisite()){
			$weixin_setting	= WEIXIN_Setting::get($this->appid);

			if(empty($weixin_setting) || empty($weixin_setting['blog_id']) || isset($weixin_setting[$setting_name])){
				return false;
			}

			$this->settings[$setting_name]	= $setting_value;

			return wpjam_update_setting('weixin-robot', $setting_name, $setting_value, $weixin_setting['blog_id']);
		}else{
			$this->settings[$setting_name]	= $setting_value;

			return wpjam_update_setting('weixin-robot', $setting_name, $setting_value);
		}
	}

	public static function get_instance($appid=''){
		$appid	= $appid ?: self::get_current_appid();

		if($appid){
			if(!isset(self::$instances[$appid])){
				if(is_multisite()){
					$weixin_setting	= WEIXIN_Setting::get_setting($appid);
				}else{
					$weixin_setting	= wpjam_get_option('weixin-robot');
				}

				if(empty($weixin_setting)){
					return new WP_Error('empty_weixin_setting', '公众号设置信息为空，请先在后台公众号设置中加入该公众号');
				}

				self::$instances[$appid]	= new self($appid, $weixin_setting);
			}

			return self::$instances[$appid];
		}else{
			return null;
		}
	}

	public static function get_current_appid(){
		if(!is_null(self::$current_appid)){
			return self::$current_appid;
		}

		$appid	= wpjam_get_setting('weixin-robot', 'weixin_app_id');
		$appid	= trim($appid);

		if($appid && !preg_match('/(wx[A-Za-z0-9]{15,17})/', $appid)){
			$appid	= '';
		}

		if($appid && is_multisite()){
			if(!WEIXIN_Setting::get_setting($appid)){
				$appid	= '';
			}
		}

		if($appid){
			self::set_current_appid($appid);
		}

		return $appid;
	}

	public static function set_current_appid($appid){
		if($appid){
			self::$current_appid = $appid;
		}
	}

	public static function get_current_openid(){
		if(!is_weixin()){
			return new WP_Error('illegal_platform', '该函数只能在微信中中调用');
		}

		if(!is_null(self::$current_openid)){
			return self::$current_openid;
		}

		if(weixin_get_type() == 4 && wpjam_is_json_request()){	// 用于 API 接口
			$access_token	= $_GET['access_token'] ?? '';

			if(empty($access_token)){
				if((isset($_GET['debug']) || isset($_GET['debug_openid'])) && isset($_GET['openid'])){
					return $_GET['openid'];	// 用于测试
				}
			}
		}else{	// 用于网页
			if(weixin_get_type() < 4 && isset($_GET['weixin_access_token'])){
				$access_token	= $_GET['weixin_access_token'];
				WEIXIN_User::set_access_token_cookie($access_token);
			}elseif(isset($_COOKIE['weixin_access_token'])){
				$access_token	= $_COOKIE['weixin_access_token'];
			}
		}

		if(empty($access_token)){
			return new WP_Error('illegal_access_token', 'Access Token 为空！');
		}

		$openid =  WEIXIN_User::get_openid_by_access_token($access_token);

		if(!is_wp_error($openid)){
			self::set_current_openid($openid);
		}

		return $openid;
	}

	public static function set_current_openid($openid){
		self::$current_openid	= $openid;
	}

	public static function doing_reply(){
		if(get_option('permalink_structure')){
			if(strpos($_SERVER['REQUEST_URI'], '/weixin/reply') === 0){
				return true;
			}
		}else{
			if(isset($_GET['module']) && $_GET['module'] == 'weixin'){
				return true;
			}
		}

		return false;
	}

	public static function parse_mp_article($mp_url){
		$mp_html	= wpjam_remote_request($mp_url, ['need_json_decode'=>false]);

		if(is_wp_error($mp_html)){
			return $mp_html;
		}

		$content = $content_source_url = '';
		$show_cover_pic = 0;

		$results	= [];

		foreach([
			'title'		=> 'og:title',
			'digest'	=> 'og:description',
			'author'	=> 'og:article:author',
			'thumb_url'	=> 'og:image',
		] as $key => $value){
			if(preg_match_all('/<meta property=\"'.$value.'\" content=\"(.*?)\" \/>/i', $mp_html, $matches)){
				$results[$key]	= str_replace(['&nbsp;','&amp;'], [' ','&'], $matches[1][0]);
			}else{
				$results[$key]	= '';
			}
		}

		if(preg_match_all('/<div class=\"rich_media_content \".*?>[\s\S]{106}([\s\S]*?)[\s\S]{22}<\/div>/i', $mp_html, $matches)){
			$results['content']	= $matches[1][0];
		}

		if(preg_match_all('/var msg_source_url = \'(.*?)\';/i', $mp_html, $matches)){
			$results['content_source_url']	= $matches[1][0];
		}

		return $results;
	}
}

function weixin($appid=''){
	$appid	= $appid ?: weixin_get_current_appid();

	if(empty($appid)){
		trigger_error('empty_appid');
		$wp_error = new WP_Error('empty_appid', '公众号 appid 为空');

		if(wpjam_is_json_request()){
			wpjam_send_json($wp_error);
		}else{
			wp_die($wp_error);
		}
	}

	$instance	= WEIXIN_Util::get_instance($appid);

	if(is_wp_error($instance)){
		if(wpjam_is_json_request()){
			wpjam_send_json($instance);
		}elseif(!wp_doing_cron()){
			wp_die($instance);
		}else{
			return $instance;
		}
	}else{
		return $instance->get_weixin();
	}
}

function weixin_exists($appid, $secret){
	$weixin			= new WEIXIN($appid, $secret);
	$access_token	= $weixin->get_access_token($force=true);
	return !is_wp_error($access_token);
}

// weixin_get_setting() 获取 weixin_get_current_appid() 的小程序设置
// weixin_get_setting($appid) 获取 $appid 的小程序设置
// weixin_get_setting($setting_name) 获取  weixin_get_current_appid() 的小程序 $setting_name 的设置
// weixin_get_setting($setting_name, $appid) 获取 $appid 的小程序 $setting_name 的设置
function weixin_get_setting(...$args){
	$args_num	= count($args);
	$appid		= '';

	if($args_num == 0){
		$setting_name	= '';
	}elseif($args_num == 1){
		$setting_name	= $args[0];

		if($setting_name){
			if(preg_match('/(wx[A-Za-z0-9]{15,17})/', $setting_name)){
				$appid			= $setting_name;
				$setting_name	= '';
			}
		}
	}elseif($args_num == 2){
		$setting_name	= $args[0];
		$appid			= $args[1];
	}

	$instance	= WEIXIN_Util::get_instance($appid);

	if($instance && !is_wp_error($instance)){
		return $instance->get_setting($setting_name);
	}

	return $setting_name ? null : [];
}

function weixin_update_setting($setting_name, $setting_value, $appid=''){
	$instance	= WEIXIN_Util::get_instance($appid);

	if($instance && !is_wp_error($instance)){
		return $instance->update_setting($setting_name, $setting_value);
	}

	return false;
}

function weixin_has_feature($feature, $appid=''){
	return boolval(weixin_get_setting($feature, $appid));
}

function weixin_get_type($appid=''){
	return weixin_get_setting('weixin_type', $appid);
}

function weixin_get_appid(){
	return WEIXIN_Util::get_current_appid();
}

function weixin_get_current_appid(){
	return WEIXIN_Util::get_current_appid();
}

function weixin_get_current_openid(){
	return WEIXIN_Util::get_current_openid();
}

function weixin_set_current_openid($openid){
	WEIXIN_Util::set_current_openid($openid);
}

function weixin_doing_reply(){
	return WEIXIN_Util::doing_reply();
}

function weixin_parse_mp_article($mp_url){
	return WEIXIN_Util::parse_mp_article($mp_url);
}

function weixin_get_extends($appid=''){
	$instance	= WEIXIN_Util::get_instance($appid);

	if($instance && !is_wp_error($instance)){
		return $instance->get_extends();
	}

	return [];
}

