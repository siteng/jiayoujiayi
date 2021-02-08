<?php
/*
Plugin Name: 微信机器人高级版
Plugin URI: http://blog.wpjam.com/
Description: 微信机器人的主要功能就是能够将你的公众账号和你的 WordPress 博客联系起来，搜索和用户发送信息匹配的日志，并自动回复用户，让你使用微信进行营销事半功倍。
Version: 4.6.8
Author: Denis
Author URI: http://blog.wpjam.com/
*/
define('WEIXIN_ROBOT_PLUGIN_URL', plugins_url('', __FILE__));
define('WEIXIN_ROBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEIXIN_ROBOT_PLUGIN_FILE',  __FILE__);
define('WEIXIN_ROBOT_PLUGIN_TEMP_URL', WP_CONTENT_URL.'/uploads/weixin/');
define('WEIXIN_ROBOT_PLUGIN_TEMP_DIR', WP_CONTENT_DIR.'/uploads/weixin/');
define('WEIXIN_CUSTOM_SEND_LIMIT', current_time('timestamp')-48*3600);

// 定义数据库表名
global $wpdb;

$wpdb->weixin_menus			= $wpdb->base_prefix . 'weixin_menus';
$wpdb->weixin_custom_replies= $wpdb->prefix . 'weixin_custom_replies';
$wpdb->weixin_messages		= $wpdb->prefix . 'weixin_messages';
$wpdb->weixin_qrcodes		= $wpdb->prefix . 'weixin_qrcodes';
$wpdb->weixin_subscribes	= $wpdb->prefix . 'weixin_subscribes';
$wpdb->weixin_users			= $wpdb->prefix . 'weixin_users';
// $wpdb->weixin_crm_users		= $wpdb->prefix . 'weixin_crm_users';

include(WEIXIN_ROBOT_PLUGIN_DIR.'api/api.php');				// 基本接口
include(WEIXIN_ROBOT_PLUGIN_DIR.'api/jssdk.php');			// 微信页面统计接口

include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/reply.php');			// 微信被动回复

include(WEIXIN_ROBOT_PLUGIN_DIR.'user/user.php');			// 微信用户管理
// include(WEIXIN_ROBOT_PLUGIN_DIR.'user/crm.php');			// 微信CRM用户管理

if(WEIXIN_TYPE >= 3){
	include(WEIXIN_ROBOT_PLUGIN_DIR.'user/advanced.php');	// 微信用户高级接口
	include(WEIXIN_ROBOT_PLUGIN_DIR.'user/tag.php');		// 微信用户标签管理
	// include(WEIXIN_ROBOT_PLUGIN_DIR.'user/group.php');	// 微信用户分组管理
	include(WEIXIN_ROBOT_PLUGIN_DIR.'api/material.php');	// 素材管理
	include(WEIXIN_ROBOT_PLUGIN_DIR.'api/send.php');		// 高级群发和客服回复
	include(WEIXIN_ROBOT_PLUGIN_DIR.'api/customservice.php');	// 客服账户接口
}

if(WEIXIN_TYPE == 4){
	include(WEIXIN_ROBOT_PLUGIN_DIR.'api/qrcode.php');		// 带参数二维码
	include(WEIXIN_ROBOT_PLUGIN_DIR.'user/oauth.php');		// 微信 OAuth 2.0 接口
}

weixin_robot_include_extends();								// 扩展

if(is_admin()){
	include(WEIXIN_ROBOT_PLUGIN_DIR.'admin/admin.php');		// 插件后台
	return;
}

add_action('wp_loaded', 'weixin_robot_loaded', 11);
function weixin_robot_loaded(){

	if(defined('DOING_AJAX') && DOING_AJAX) {
		return;
	}

	if(defined('DOING_WEIXIN_REPLY') && DOING_WEIXIN_REPLY) {
		weixin_robot_make_reply();				// 被动响应微信用户消息
		return;
	}

	if(is_weixin()){
		if(WEIXIN_TYPE == 4 && !isset($_GET['debug'])){
			// if(weixin_robot_get_setting('weixin_oauth20')){
			// 	weixin_robot_make_oauth_request();	// 发起 OAuth 请求
			// 	weixin_robot_make_redirect();		// 微信活动跳转，用于支持第三方活动
			// }
		}else{
			weixin_robot_set_query_cookie();	// 订阅号就保存用户 query_id 到 cookie 里
		}
	}
}

if((isset($_GET['signature']) && isset($_GET["timestamp"]) && isset($_GET["nonce"])) || (isset($_GET['debug']) && isset($_GET['weixin'])) ) {

	define('DOING_WEIXIN_REPLY', true);

	/*下面代码优化微信自定义回复的效率*/
	
	function get_currentuserinfo(){}	//	无需获取当前用户
	function is_user_logged_in(){ return false;}	// 没登陆
	// 	remove_all_filters('determine_current_user');
	
	remove_action('plugins_loaded', 'wp_maybe_load_widgets', 0);
	remove_action('plugins_loaded', 'wp_maybe_load_embeds',  0);
	remove_action('plugins_loaded', '_wp_customize_include' );
	remove_action('plugins_loaded', 'weixin_robot_set_get_mp_stats_cron' );
	remove_action('plugins_loaded', 'weixin_robot_set_get_user_list_cron' );
	remove_action('plugins_loaded', 'weixin_robot_set_get_wifi_cron' );
	

	remove_action('sanitize_comment_cookies',   'sanitize_comment_cookies');

	remove_action('init', 'smilies_init', 5);	
	remove_action('init', 'wp_widgets_init', 1);
	remove_action('init', 'kses_init');
	remove_action('init', 'ms_subdomain_constants');
	remove_action('init', 'check_theme_switched',99);
	remove_action('init', 'maybe_add_existing_user_to_blog');
	remove_action('init', 'wpjam_redirect_to_mapped_domain');
	remove_action('init', 'wpjam_feed_post_views_init', 4);

	remove_action('set_current_user', 'kses_init');

	remove_action('wp_loaded', '_custom_header_background_just_in_time' );
	remove_filter('wpjam_html_replace', 'wpjam_qiniu_html_replace');

//	add_filter( 'locale', 'wpjam_locale' );

	add_filter('do_parse_request', '__return_false');

	if(!weixin_robot_get_setting('weixin_search')){
		remove_action( 'init', 'create_initial_post_types', 0 );
		remove_action( 'init', 'create_initial_taxonomies', 0 );
		remove_action( 'init', 'wpjam_post_type_init', 11 );
		remove_action( 'init', 'wpjam_taxonomy_init', 11 );

		// wp_templating_constants(  );

		// require_once( ABSPATH . WPINC . '/locale.php' );
		// $GLOBALS['wp_locale'] = new WP_Locale();

		// if ( TEMPLATEPATH !== STYLESHEETPATH && file_exists( STYLESHEETPATH . '/functions.php' ) )
		// 	include( STYLESHEETPATH . '/functions.php' );
		// if ( file_exists( TEMPLATEPATH . '/functions.php' ) )
		// 	include( TEMPLATEPATH . '/functions.php' );

		// add_action('plugins_loaded', 'weixin_robot_loaded', 11);
		
	}
}

