<?php
/*
Plugin Name: 微信机器人高级版
Plugin URI: http://blog.wpjam.com/
Description: 微信机器人的主要功能就是能够将你的公众账号和你的 WordPress 博客联系起来，搜索和用户发送信息匹配的文章，并自动回复用户，让你使用微信进行营销事半功倍。
Version: 5.6
Requires at least: 5.6
Tested up to: 5.6
Requires PHP: 7.2
Author: Denis
Author URI: http://blog.wpjam.com/
*/

if (version_compare(PHP_VERSION, '7.2.0') < 0) {
	include plugin_dir_path(__FILE__).'php5/weixin-robot-advanced.php';
}else{
	function weixin_loaded(){
		if(defined('WEIXIN_ROBOT_PLUGIN_DIR')){
			return;
		}

		define('WEIXIN_ROBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
		define('WEIXIN_ROBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
		define('WEIXIN_ROBOT_PLUGIN_FILE',  __FILE__);
		define('WEIXIN_ROBOT_PLUGIN_TEMP_URL', WP_CONTENT_URL.'/uploads/weixin/');
		define('WEIXIN_ROBOT_PLUGIN_TEMP_DIR', WP_CONTENT_DIR.'/uploads/weixin/');
	
		include WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-setting.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'includes/trait-weixin.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-user.php';
	
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-utils.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-hooks.php';

		if(is_admin()){
			include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-admin.php';	
		}

		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-oauth.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-qrcodes.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-jssdk.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-templates.php';
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-crons.php';

		do_action('weixin_loaded');
	}

	if(did_action('wpjam_loaded')){
		weixin_loaded();
	}else{
		add_action('wpjam_loaded', 'weixin_loaded');
	}
}


