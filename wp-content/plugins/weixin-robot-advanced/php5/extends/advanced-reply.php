<?php
/*
Plugin Name: 高级回复
Plugin URI: 
Description: 发送 n r 等关键字获取博客最新或者随机文章。
Version: 1.0
Author URI: http://blog.wpjam.com/
*/

// 定义高级回复的关键字
add_filter('weixin_builtin_reply', 'wpjam_weixin_advanced_builtin_reply');
function wpjam_weixin_advanced_builtin_reply($weixin_builtin_replies){
    $weixin_builtin_replies[weixin_robot_get_setting('new')] 		= array('type'=>'full',	'reply'=>'最新日志',			'function'=>'weixin_robot_new_posts_reply');
	$weixin_builtin_replies[weixin_robot_get_setting('rand')] 		= array('type'=>'full',	'reply'=>'随机日志',			'function'=>'weixin_robot_rand_posts_reply');
	$weixin_builtin_replies[weixin_robot_get_setting('hot')] 		= array('type'=>'full',	'reply'=>'最热日志',			'function'=>'weixin_robot_hot_posts_reply');
	$weixin_builtin_replies[weixin_robot_get_setting('comment')] 	= array('type'=>'full',	'reply'=>'留言最多日志',		'function'=>'weixin_robot_comment_posts_reply');
	$weixin_builtin_replies[weixin_robot_get_setting('hot-7')] 		= array('type'=>'full',	'reply'=>'一周内最热日志',		'function'=>'weixin_robot_hot_7_posts_reply');
	$weixin_builtin_replies[weixin_robot_get_setting('comment-7')]	= array('type'=>'full',	'reply'=>'一周内留言最多日志',	'function'=>'weixin_robot_comment_7_posts_reply');
    return $weixin_builtin_replies;
}

//设置时间为最近7天
function weixin_robot_posts_where_7( $where = '' ) {
	return $where . " AND post_date > '" . date('Y-m-d', strtotime('-7 days')) . "'";
}

//设置时间为最近30天
function weixin_robot_posts_where_30( $where = '' ) {
	return $where . " AND post_date > '" . date('Y-m-d', strtotime('-60 days')) . "'";
}

// 高级回复
function weixin_robot_advanced_reply($keyword){
	global $wechatObj;
	weixin_robot_post_query_reply();
	$wechatObj->set_response('advanced');
}

//按照时间排序
function weixin_robot_new_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_new_query');
	weixin_robot_advanced_reply($keyword);
}
function weixin_robot_new_query($weixin_query_array){
	unset($weixin_query_array['s']);
	return $weixin_query_array;
}
//随机排序
function weixin_robot_rand_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_rand_query');
	weixin_robot_advanced_reply($keyword);
}
function weixin_robot_rand_query($weixin_query_array){
	unset($weixin_query_array['s']);
	$weixin_query_array['orderby']		= 'rand';
	return $weixin_query_array;
}
//按照浏览排序
function weixin_robot_hot_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_hot_query');
	weixin_robot_advanced_reply($keyword);
}
function weixin_robot_hot_query($weixin_query_array){
	unset($weixin_query_array['s']);
	$weixin_query_array['meta_key']		= 'views';
	$weixin_query_array['orderby']		= 'meta_value_num';
	return $weixin_query_array;
}
//按照留言数排序
function weixin_robot_comment_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_comment_query');
	weixin_robot_advanced_reply($keyword);
}
function weixin_robot_comment_query($weixin_query_array){
	unset($weixin_query_array['s']);
	$weixin_query_array['orderby']		= 'comment_count';
	return $weixin_query_array;
}
//7天内最热
function weixin_robot_hot_7_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_hot_query');
	add_filter('posts_where', 'weixin_robot_posts_where_7' );
	weixin_robot_advanced_reply($keyword);
}
//7天内留言最多 
function weixin_robot_comment_7_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_comment_query');
	add_filter('posts_where', 'weixin_robot_posts_where_7' );
	weixin_robot_advanced_reply($keyword);
}
//30天内最热
function weixin_robot_hot_30_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_hot_query');
	add_filter('posts_where', 'weixin_robot_posts_where_30' );
	weixin_robot_advanced_reply($keyword);
}
//30天内留言最多
function weixin_robot_comment_30_posts_reply($keyword){
	add_filter('weixin_query','weixin_robot_comment_query');
	add_filter('posts_where', 'weixin_robot_posts_where_30' );
	weixin_robot_advanced_reply($keyword);
}