<?php
include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/class.php');		// 微信被动回复类库	
include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/custom.php');	// 自定义回复
include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/builtin.php');	// 内置和函数回复
include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/query.php');		// 搜索回复
include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/messages.php');	// 微信消息和群发统计

function weixin_robot_make_reply(){
	global $wechatObj;
	if(!isset($wechatObj)){
		$wechatObj = new wechatCallback();
		$wechatObj->valid();
		exit;
	}
}

// 如果是在被动响应微信消息，和微信用户界面中，设置 is_home 为 false，
add_action('parse_query','weixin_robot_parse_query');
function weixin_robot_parse_query($query){
	if(isset($_GET['weixin']) || isset($_GET['signature']) || !empty($_GET['weixin_user'])) {
		$query->is_home 	= false;
		$query->is_search 	= false;
		$query->is_weixin 	= true;
	}
}