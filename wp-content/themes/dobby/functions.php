<?php

define( 'DOBBY_VERSION', '1.0' );

require_once( get_template_directory() . '/inc/core.php');
require_once( get_template_directory() . '/inc/smtp.php');
require_once( get_template_directory() . '/inc/global.php');
require_once( get_template_directory() . '/inc/images.php');
require_once( get_template_directory() . '/inc/widget.php');
require_once( get_template_directory() . '/inc/single.php');
require_once( get_template_directory() . '/inc/comments.php');
require_once( get_template_directory() . '/inc/navwalker.php');
require_once( get_template_directory() . '/inc/shortcode.php');


//移除前台adminbar的样式
add_action( 'wp_enqueue_scripts', 'fanly_remove_block_library_css', 100 );
function fanly_remove_block_library_css() {
	wp_dequeue_style( 'wp-block-library' );
}
//网站运行时间函数
function time2string($second){
	$day = floor($second/(3600*24));
	$second = $second%(3600*24);
	$hour = floor($second/3600);
//	$second = $second%3600;
//	$minute = floor($second/60);
//	$second = $second%60;
	return $day.'天'.$hour.'小时';
}
global $wpdb;
$wpdb->query("DELETE FROM jiayi_options WHERE option_name = ' core_updater.lock '");