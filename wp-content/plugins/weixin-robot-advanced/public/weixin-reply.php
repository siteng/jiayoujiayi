<?php
if(class_exists('WEIXIN_ReplySetting')){
	return;
}

include WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-reply-setting.php';
include WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-message.php';

if(!is_admin()){
	include WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-reply.php';
}

function weixin_register_reply($keyword, $args){
	WEIXIN_ReplySetting::register_builtin_reply($keyword, $args);
}

function weixin_register_query($name, $callback){
	WEIXIN_ReplySetting::register_query($name, $callback);
}

function weixin_register_response_type($name, $title){
	WEIXIN_Message::register_response_type($name, $title);
}

foreach([
	'[voice]', 
	'[location]', 
	'[image]', 
	'[link]', 
	'[video]', 
	'[shortvideo]',
	'[emotion]'
] as $keyword){
	weixin_register_reply($keyword,	['type'=>'full',	'reply'=>'默认回复',	'method'=>'default_reply']);
}

foreach([
	'[view]', 
	'[view_miniprogram]',
	'[scancode_push]', 
	'[scancode_waitmsg]', 
	'[location_select]', 
	'[pic_sysphoto]', 
	'[pic_photo_or_album]',
	'[pic_weixin]',
	'[templatesendjobfinish]',

	'[kf_create_session]',
	'[kf_close_session]',
	'[kf_switch_session]',
	
	'[user_get_card]', 
	'[user_del_card]', 
	'[card_pass_check]', 
	'[card_not_pass_check]', 
	'[user_view_card]', 
	'[user_enter_session_from_card]', 
	'[card_sku_remind]', 
	'[user_consume_card]',
	'[submit_membercard_user_info]',

	'[masssendjobfinish]',
	'[templatesendjobfinish]',

	'[poi_check_notify]',
	'[wificonnected]',
	'[shakearoundusershake]'

] as $keyword){
	weixin_register_reply($keyword,		['type'=>'full',	'reply'=>'']);
}

weixin_register_reply('subscribe',		['type'=>'full',	'reply'=>'用户订阅',			'method'=>'subscribe_reply']);
weixin_register_reply('unsubscribe',	['type'=>'full',	'reply'=>'用户订阅',			'method'=>'unsubscribe_reply']);
weixin_register_reply('scan',			['type'=>'full',	'reply'=>'扫描带参数二维码',	'method'=>'scan_reply']);

if(weixin_get_type() == 4){
	weixin_register_reply('event-location',	['type'=>'full',	'reply'=>'获取用户地理位置',	'method'=>'location_event_reply']);
}

foreach([
	'[qualification_verify_success]',
	'[qualification_verify_fail]',
	'[naming_verify_success]',
	'[naming_verify_fail]',
	'[annual_renew]',
	'[verify_expired]'
] as $keyword){
	weixin_register_reply($keyword,		['type'=>'full',	'reply'=>'微信认证回复',	'method'=>'verify_reply']);
}

// 定义高级回复的关键字
if(weixin_has_feature('weixin_search')){
	$setting	= weixin_get_setting();

	if(!empty($setting['new'])){
		weixin_register_reply($setting['new'],		['type'=>'full',	'reply'=>'最新文章',		'callback'=>['WEIXIN_Advanced','new_posts_reply']]);
	}

	if(!empty($setting['rand'])){
		weixin_register_reply($setting['rand'],		['type'=>'full',	'reply'=>'随机文章',		'callback'=>['WEIXIN_Advanced','rand_posts_reply']]);
	}

	if(!empty($setting['hot'])){
		weixin_register_reply($setting['hot'],		['type'=>'full',	'reply'=>'最热文章',		'callback'=>['WEIXIN_Advanced','hot_posts_reply']]);
	}

	if(!empty($setting['comment'])){
		weixin_register_reply($setting['comment'],	['type'=>'full',	'reply'=>'评论最多文章',	'callback'=>['WEIXIN_Advanced','comment_posts_reply']]);
	}

	if(!empty($setting['hot-7'])){
		weixin_register_reply($setting['hot-7'],	['type'=>'full',	'reply'=>'一周最热文章',	'callback'=>['WEIXIN_Advanced','hot_7_posts_reply']]);
	}

	if(!empty($setting['comment-7'])){
		weixin_register_reply($setting['comment-7'],['type'=>'full',	'reply'=>'一周评论文章',	'callback'=>['WEIXIN_Advanced','comment_7_posts_reply']]);
	}
};

do_action('weixin_reply_loaded');