<?php
class WEIXIN_Hook{
	public static function register_api($json){
		if(strpos($json, 'weixin.') !== false){
			$template	= WEIXIN_ROBOT_PLUGIN_DIR.'api/'.$json.'.php';

			if(file_exists($template)){
				$args	= ['template'=>$template];

				if($json == 'weixin.access_token'){
					$args['grant']	= true;
					$args['quota']	= 100;
				}

				wpjam_register_api($json, $args);
			}
		}
	}

	public static function module($action) {
		if(in_array($action, ['reply','jssdk'])){
			include WEIXIN_ROBOT_PLUGIN_DIR.'template/'.$action.'.php';
		}else{
			if(!is_weixin()){
				wp_die('请在微信中访问');
			}

			if(weixin_get_type() == 4){
				weixin_oauth_request();
			}

			$openid	= weixin_get_current_openid();

			if(is_wp_error($openid)){
				wp_die('未登录');
			}

			$weixin_user	= WEIXIN_User::get($openid);

			if(empty($weixin_user) || ($weixin_user['last_update'] < (time() - DAY_IN_SECONDS))){
				$user_info	= weixin()->get_user_info($openid);

				if($user_info && !is_wp_error($user_info)){
					$weixin_user	= WEIXIN_User::sync($user_info);
				}
			}

			if($action == 'oauth'){
				$redirect_url	= $_GET['redirect_url'] ?? '';

				if(empty($redirect_url)){
					wp_die('未传递跳转链接');
				}

				$access_token	= WEIXIN_User::generate_access_token($openid);
				$redirect_url	= add_query_arg(compact('access_token'), $redirect_url);

				wp_redirect($redirect_url);
			}else{
				if($weixin_user && $weixin_user['subscribe']){
					if($template = apply_filters('weixin_template', '', $action)){
						if(is_file($template)){
							return $template;
						}
					}
				}else{
					wp_die('未关注');
				}
			}
		}

		exit;
	}

	public static function filter_current_user($current_user){
		if(!weixin_get_setting()){
			return $current_user;
		}

		$openid	= weixin_get_current_openid();

		if(is_wp_error($openid)){
			return $openid;
		}
		
		$weixin_user	= WEIXIN_User::parse_for_json($openid);

		$user_id		= $weixin_user['user_id'] ?? 0;
		$wp_user 		= $user_id ? get_userdata($user_id) : null;

		if(!$user_id || !$wp_user){
			$weixin_user['user_id']		= 0;
			$weixin_user['user_email']	= $openid.'@'.weixin_get_appid().'.weixin';
		}

		return $weixin_user;
	}
}

if(weixin_doing_reply()){
	// 优化微信自定义回复
	remove_action('set_comment_cookies', 'wp_set_comment_cookies', 10, 3);
	remove_action('sanitize_comment_cookies', 'sanitize_comment_cookies');

	remove_filter('determine_current_user', 'wp_validate_auth_cookie');
	remove_filter('determine_current_user', 'wp_validate_logged_in_cookie', 20);

	remove_action('init', 'wp_widgets_init', 1);
	remove_action('init', 'maybe_add_existing_user_to_blog');
	remove_action('init', 'check_theme_switched', 99);

	remove_action('plugins_loaded', '_wp_customize_include');
	
	remove_action('wp_loaded', '_custom_header_background_just_in_time');
}

add_filter('init', function (){
	add_action('wpjam_api',	['WEIXIN_Hook', 'register_api'], 1);

	if(is_weixin()){
		add_filter('wpjam_current_user',	['WEIXIN_Hook', 'filter_current_user']);
	}

	add_rewrite_tag('%weixin%', '([^/]+)', "module=weixin&action=");
	add_permastruct('weixin', 'weixin/%weixin%', ['with_front'=>false, 'paged'=>false, 'feed'=>false]);

	if(function_exists('wpjam_register_route_module')){
		wpjam_register_route_module('weixin', ['callback'=>['WEIXIN_Hook', 'module']]);
	}

	if(did_action('wpjam_debug_loaded')){
		wpjam_register_debug_type('weixin', [
			'name'		=> '微信公众号插件警告',
			'callback'	=> function($args){
				return strpos($args['caller'], 'weixin') !== false || strpos($args['caller'], 'WEIXIN') !== false || strpos($args['file'], 'weixin-robot') !== false;
			}
		]);
	}

	foreach(weixin_get_extends() as $weixin_extend => $value){
		if($value){
			$weixin_extend_file	= WEIXIN_ROBOT_PLUGIN_DIR.'extends/'.$weixin_extend;
			
			if(is_file($weixin_extend_file)){
				include($weixin_extend_file);
			}
		}
	}
});