<?php
class WEIXIN_Admin{
	protected static $sub_pages		= [];

	public static function add_sub_page($sub_slug, $args=[]){
		self::$sub_pages[$sub_slug]	= $args;
	}

	public static function add_menu_pages(){
		if(WPJAM_Verify::verify()){
			$subs	= [];

			if(weixin_get_appid()){
				$subs['weixin']	= [
					'menu_title'	=> '数据预览',
					'function'		=> 'dashboard',
					'page_file'		=> WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-stats.php',
				];

				if(weixin_get_type() >= 2) {
					$subs['weixin-menu']	= [
						'menu_title'	=> '自定义菜单',
						'function'		=> 'tab',
						'page_file'		=> WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-menu.php',
					];
				}

				if(weixin_has_feature('weixin_reply')){
					$subs['weixin-replies']	= [
						'menu_title'	=> '自定义回复',
						'function'		=> 'tab',
						'page_file'		=> WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-replies.php'
					];
				}

				if(weixin_get_type() >= 3) {
					$subs['weixin-material']	= [
						'menu_title'	=> '素材管理',
						'function'		=> 'tab',
						'page_file'		=> WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-material.php'
					];

					$subs['weixin-users']		= [
						'menu_title' 	=> '用户管理',
						'function'		=> 'tab',
						'page_file'		=> WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-users.php',
					];
				}

				$subs	+= self::$sub_pages;
				$subs	= apply_filters('weixin_sub_pages', $subs);

				$subs['weixin-stats']	= [
					'menu_title'	=> '数据统计',
					'function'		=> 'tab',
					'page_file'		=> WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-stats.php',
				];

				$subs['weixin-extends']	= [
					'menu_title'	=> '扩展管理',
					'function'		=> 'tab',
					'capability'	=> 'manage_options',
					'load_callback'	=> ['WEIXIN_Admin', 'load_extends_page'],
				];

				$subs['weixin-setting']	= [
					'menu_title'	=> '公众号设置',
					'function'		=> 'option',
					'option_name'	=> 'weixin-robot',
					'load_callback'	=> ['WEIXIN_Admin', 'load_option_page']
				];
			}else{
				$subs['weixin'] = [
					'menu_title'	=> '微信公众号',
					'function'		=> 'option',
					'option_name'	=> 'weixin-robot',
					'capability'	=> 'manage_options',
					'load_callback'	=> ['WEIXIN_Admin', 'load_option_page']
				];
			}

			if(is_multisite() && current_user_can('manage_sites')){
				$subs['weixin-settings']	= [
					'menu_title'	=> '所有公众号',
					'function'		=> 'list',
					'capability'	=> 'manage_sites',
					'load_callback'	=> ['WEIXIN_Admin', 'load_settings_page']
				];
			}

			wpjam_add_menu_page('weixin', [
				'menu_title'	=> '微信公众号',
				'icon'			=> 'dashicons-weixin',
				'position'		=> '3.91',
				'subs'			=> $subs
			]);
		}else{
			wpjam_add_menu_page('weixin', [
				'menu_title'	=> '微信公众号',
				'icon'			=> 'dashicons-weixin',
				'position'		=> '3.91',
				'function'		=> 'form',
				'form_name'		=> 'verify_wpjam',
				'load_callback'	=> ['WPJAM_Verify', 'page_action']
			]);
		}

		wp_add_inline_style('list-tables', "\n".implode("\n", [
			'#adminmenu div.dashicons-weixin{background-repeat: no-repeat; background-position: center; background-size: 20px auto; background-image: url('.WEIXIN_ROBOT_PLUGIN_URL.'static/icon.svg) !important;}',
			'#adminmenu .wp-has-current-submenu div.dashicons-weixin{background-image: url('.WEIXIN_ROBOT_PLUGIN_URL.'static/icon-active.svg) !important;}'
		])."\n");
	}

	public static function load_settings_page(){
		wpjam_register_list_table('weixin-settings', [
			'title'			=> '公众号',
			'singular'		=> 'weixin-setting',
			'plural'		=> 'weixin-settings',
			'model'		 	=> 'WEIXIN_Setting',
			'primary_key'	=> 'appid',
			'capability'	=> 'manage_sites'
		]);
	}

	public static function load_extends_page(){
		wpjam_register_plugin_page_tab('extends',		[
			'title'			=> '扩展管理',	
			'function'		=> 'option',	
			'option_name'	=> 'weixin_'.weixin_get_appid().'_extends',	
			'load_callback'	=> ['WEIXIN_Admin', 'load_extends_option_page']
		]);

		wpjam_register_plugin_page_tab('clear_quota',	[
			'title'			=> '接口清零',
			'function'		=> 'form',
			'form_name'		=> 'weixin_clear_quota',
			'load_callback'	=> ['WEIXIN_Admin', 'load_clear_quota_form_page']
		]);

		wpjam_register_plugin_page_tab('check_callback',[
			'title'			=>'网络检测',
			'function'		=>['WEIXIN_Admin', 'check_callback_page']
		]);

		wpjam_register_plugin_page_tab('ip',			[
			'title'			=>'微信IP',	
			'page_title'	=>'微信IP列表',	
			'function'		=>['WEIXIN_Admin', 'callback_ip_page']
		]);
	}

	public static function load_extends_option_page(){
		$fields		= [];
		$extend_dir	= WEIXIN_ROBOT_PLUGIN_DIR.'/extends';
		
		if($extends = weixin_get_extends()){	 // 已激活的优先
			foreach ($extends as $extend_file => $value) {
				if($value){
					if(is_file($extend_dir.'/'.$extend_file) && $data = get_plugin_data($extend_dir.'/'.$extend_file)){
						$fields[$extend_file] = ['title'=>$data['Name'],	'type'=>'checkbox',	'description'=>$data['Description']];
					}
				}
			}
		}

		if($extend_handle = opendir($extend_dir)){   
			while (($extend_file = readdir($extend_handle)) !== false) {
				if ($extend_file!="." && $extend_file!=".." && is_file($extend_dir.'/'.$extend_file) && empty($extends[$extend_file])) {
					if(pathinfo($extend_file, PATHINFO_EXTENSION) == 'php'){
						if(($data = get_plugin_data($extend_dir.'/'.$extend_file)) && $data['Name']){
							$fields[$extend_file] = ['title'=>$data['Name'],	'type'=>'checkbox',	'description'=>$data['Description']];
						}
					}
				}
			}
			closedir($extend_handle);   
		}

		wpjam_register_option('weixin_'.weixin_get_appid().'_extends', ['fields'=>$fields]);
	}

	public static function load_clear_quota_form_page(){
		$summary	= '开发者可以登录微信公众平台，在帐号后台开发者中心接口权限模板查看帐号各接口当前的日调用上限和实时调用量，对于认证帐号可以对实时调用量清零，说明如下：

		1、由于指标计算方法或统计时间差异，实时调用量数据可能会出现误差，一般在1%以内。
		2、每个帐号每月共10次清零操作机会，清零生效一次即用掉一次机会（10次包括了平台上的清零和调用接口API的清零）。
		3、每个有接口调用限额的接口都可以进行清零操作。
		';

		wpjam_register_page_action('weixin_clear_quota', [
			'submit_text'	=> 'API调用次数清零',
			'summary'		=> $summary,
			'direct'		=> true, 
			'confirm'		=> true,
			'callback'		=> function(){
				$response	= weixin()->clear_quota();

				return is_wp_error($response) ? $response : true;
			}
		]);
	}
	
	public static function load_option_page(){
		$sections		= [];

		$weixin_fields	= [
			'weixin_app_id'		=> ['title'=>'AppID(应用ID)',	'type'=>'text',		'required',	'class'=>'all-options'],
			'weixin_app_secret'	=> ['title'=>'Secret(应用密钥)',	'type'=>'password',	'required'],
			'weixin_type'		=> ['title'=>'公众号类型',		'type'=>'select',	'options'=>['-1'=>' ','1'=>'订阅号','2'=>'服务号','3'=>'认证订阅号','4'=>'认证服务号']],
			'weixin_reply'		=> ['title'=>'自定义回复',		'type'=>'checkbox',	'class'=>'show-if-key',	'description'=>'开启自定义回复功能，在公众号后台自定义公众号关键字回复。']
		];

		if(!current_user_can('manage_options')){
			unset($weixin_fields['weixin_app_secret']);
		}

		$sections['weixin']	= ['title'=>'微信设置',	'fields'=>$weixin_fields];

		if(weixin_get_appid()){
			if(is_multisite()){
				$sections['weixin']['fields']['weixin_app_id']['type']	= 'view';
			}

			$reply_fields	= [
				'weixin_url'			=> ['title'=>'URL(服务器地址)',	'type'=>'view',		'value'=>home_url('/weixin/reply/')],
				'weixin_message_mode'	=> ['title'=>'消息加解密方式',	'type'=>'view',		'value'=>'请在微信公众号后台选用<strong>安全模式</strong>。'],
				'weixin_token'			=> ['title'=>'Token(令牌)',		'type'=>'text',		'class'=>'all-options'],
				'weixin_encodingAESKey'	=> ['title'=>'EncodingAESKey',	'type'=>'text',		'description'=>'请输入兼容或者安全模式下的消息加解密密钥'],
				'weixin_keyword_length'	=> ['title'=>'搜索关键字最大字节',	'type'=>'number',	'class'=>'small-text',	'description'=>'一个汉字算两个，一个英文单词算两个，空格不算，搜索多个关键字可以用空格分开！',	'min'=>8,	'max'=>20,	'step'=>2,	'value'=>10],
				'weixin_search'			=> ['title'=>'博客文章搜索回复',	'type'=>'checkbox',	'description'=>'开启<strong>博客文章搜索</strong>，在自定义回复和内置回复没有相关的关键字，微信机器人会去搜索博客文章。'],
				'weixin_text_search'	=> ['title'=>'文章搜索文本回复',	'type'=>'checkbox',	'show_if'=>['key'=>'weixin_search', 'value'=>1],	'description'=>'文章搜索结果使用文本而非图文的方式回复。'],
				'weixin_search_url'		=> ['title'=>'图文链接地址',		'type'=>'checkbox',	'show_if'=>['key'=>'weixin_search', 'value'=>1],	'description'=>'搜索结果多余一篇文章跳转搜索结果页面或者分类/标签列表页。']	
			];

			$sections['reply']	= ['title'=>'回复设置',	'fields'=>$reply_fields,	'show_if'=>['key'=>'weixin_reply', 'value'=>1]];

			$sections	= apply_filters('weixin_setting', $sections);

			// $site_fields = [
			// 	// 'weixin_content_wrap'		=> ['title'=>'开启文章图片预览',		'type'=>'text',		'class'=>'all-options',	'description'=>'输入文章内容所在DIV的class或者ID，留空则不启用该功能'],
			// 	// 'weixin_hide_option_menu'	=> ['title'=>'全局隐藏右上角菜单',	'type'=>'checkbox',	'description'=>'全局隐藏微网站右上角按钮']
			// ];
		}else{
			unset($sections['weixin']['fields']['weixin_reply']);
		}
		

		wpjam_register_option('weixin-robot', [
			'sections'			=> $sections,
			'sanitize_callback'	=> ['WEIXIN_Admin', 'sanitize_option'], 
			'ajax'				=> false
		]);
	}

	public static function sanitize_option($value){
		$weixin			= new WEIXIN($value['weixin_app_id'], $value['weixin_app_secret']);
		$access_token	= $weixin->get_access_token($force=true);

		if(is_wp_error($access_token)){
			$errcode	= $access_token->get_error_code();

			if($errcode == '40164'){
				$errmsg	= '未把服务器IP填入微信公众号IP白名单，请仔细检查后重试。';
			}elseif($errcode == '40125' || $errcode == '40001'){
				$errmsg	= '公众号ID或者密钥错误，请到公众号后台获取重新输入。';
			}else{
				$errmsg	= '公众号ID或者密钥错误，或者未把服务器IP填入微信公众号IP白名单，请仔细检查后重新输入。';
			}

			return new WP_Error($errcode, $errmsg);
		}

		if(isset($value['api_disabled'])){
			unset($value['api_disabled']);
		}

		if(is_multisite()){
			if($weixin_setting = WEIXIN_Setting::get($value['weixin_app_id'])){
				if($weixin_setting['blog_id'] != get_current_blog_id()){
					return new WP_Error('weixin_binded', '该公众号已经绑定其他站点。');
				}

				$result	= WEIXIN_Setting::update($value['weixin_app_id'], [
					'secret'	=> $value['weixin_app_secret']
				]);
			}else{
				$result	= WEIXIN_Setting::insert([
					'appid'		=> $value['weixin_app_id'],
					'secret'	=> $value['weixin_app_secret'],
					'blog_id'	=> get_current_blog_id()
				]);
			}

			if(is_wp_error($result)){
				return $result;
			}
		}

		if(weixin_get_appid() == $value['weixin_app_id']){
			self::activation();
		}

		return $value;
	}

	public static function check_callback_page(){
		echo wpautop('该接口实现微信对服务器的域名解析，然后对所有IP进行一次ping操作，得到丢包率和耗时。');

		$callback = weixin()->check_callback();
		
		if(is_wp_error($callback)){
			echo wpautop($callback->get_error_message());
		}else{
			echo wpjam_print_r($callback);
		}
	}

	public static function callback_ip_page(){
		$callback_ip	= weixin()->get_callback_ip();
		$api_domain_ip	= weixin()->get_api_domain_ip();

		if(is_wp_error($callback_ip) || is_wp_error($api_domain_ip)){
			if(is_wp_error($callback_ip)){
				echo wpautop($callback_ip->get_error_message());
			}

			if(is_wp_error($api_domain_ip)){
				echo wpautop($api_domain_ip->get_error_message());
			}
		}else{
			echo '<table class="widefat striped" style="max-width:600px;">
			<thead>
				<tr><th>微信服务器IP地址</th><th>微信API接口IP地址</th></tr>
			</thead>
			<tbody>
				<tr><td>'.wpautop(implode("\n", $callback_ip)).'</td><td>'.wpautop(implode("\n", $api_domain_ip)).'</td></tr>
			</tbody>

			</table>';
		}
	}

	public static function activation(){
		flush_rewrite_rules();

		include_once WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-message.php';
		include_once WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-reply-setting.php';

		WEIXIN_Setting::create_table();
		WEIXIN_Message::create_table();
		WEIXIN_ReplySetting::create_table();

		if($weixin_appid = weixin_get_current_appid()){
			WEIXIN_User::create_table();

			global $wpdb;

			$table	= WEIXIN_User::get_table();

			if($wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'user_id'") != 'user_id'){
				$wpdb->query("ALTER TABLE $table ADD COLUMN user_id BIGINT(20) NOT NULL");	// 添加 user_id 字段
			}

			do_action('weixin_activation', $weixin_appid);
		}
	}
}

function weixin_add_sub_page($sub_slug, $args=[]){
	WEIXIN_Admin::add_sub_page($sub_slug, $args);
}

function weixin_activation(){
	WEIXIN_Admin::activation();
}

add_action('wpjam_admin_init',	['WEIXIN_Admin', 'add_menu_pages']);

register_activation_hook(WEIXIN_ROBOT_PLUGIN_FILE, ['WEIXIN_Admin', 'activation']);