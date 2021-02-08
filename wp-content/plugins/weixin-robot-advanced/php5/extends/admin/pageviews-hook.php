<?php
// 微信群发统计
add_filter('wpjam_pages', 'weixin_robot_pageview_admin_pages');
function weixin_robot_pageview_admin_pages($wpjam_pages){

	$base_menu	= 'weixin-robot';
	$subs		= array();
	if(WEIXIN_TYPE >= 3){
		$subs[$base_menu.'-pageviews']		= array('menu_title' => '微网站浏览',		'function'=>'tab');
	}
	$subs[$base_menu.'-pageviews-stats']	= array('menu_title' => '微网站统计分析',	'function'=>'tab');

	foreach ($subs as $menu_slug => $sub) {
		$subs[$menu_slug]['capability']	= 'view_weixin';
	}

	$wpjam_pages[$base_menu.'-pageviews'] = array(
		'menu_title'	=> '微网站统计',
		'icon'			=> 'dashicons-chart-bar',
		'capability'	=> 'view_weixin',
		'position'		=> '2.1.4',
		'subs'			=> $subs,
		'function'		=> 'tab',
	);
	
	return $wpjam_pages;
}

// if(WEIXIN_TYPE >= 3){
// 	add_filter('weixin_sub_pages', 'weixin_robot_pageviews_sub_page');
// 	function weixin_robot_pageviews_sub_page($subs){
// 		$subs['weixin-robot-pageviews']		= array('menu_title' => '网站浏览',	'capability'=>'view_weixin',	'function'=>'tab', 'tabs'=>weixin_robot_get_page_tabs());
// 		return $subs;
// 	}
// }

// add_filter('weixin_stats_sub_pages', 'weixin_robot_pageviews_stats_sub_page');
// function weixin_robot_pageviews_stats_sub_page($subs){
// 	$subs['weixin-robot-pageviews-stats']		= array('menu_title' => '微网站统计分析',	'capability'=>'view_weixin',	'function'=>'tab', 'tabs'=>weixin_robot_get_page_tabs());
// 	return $subs;
// }

add_filter('weixin_users_columns', 'weixin_robot_users_pageviews_columns');
function weixin_robot_users_pageviews_columns($columns){
	$columns['ip']		= '地址（IP）';
	$columns['os']		= '系统';
	$columns['device']	= '设备';
	return $columns;
}

add_action('weixin-robot-user_page_load', 'weixin_robot_pageviews_page_load');
function weixin_robot_pageviews_page_load(){
	global $weixin_list_table, $current_tab;
	
	if($current_tab == 'list-view' || $current_tab == 'views' || $current_tab == 'list-share' || $current_tab == 'shares'){
		$columns	= array();

		$columns['time']	= '时间';

		if(empty($_GET['openid'])){
			$columns['username']	= '用户';
		}

		$columns['sub_type']		= '类型';
		$columns['url']				= '链接';
		$columns['refer']			= '推荐人';
		$columns['address']			= '地址(IP)';
		$columns['network_type']	= '网络';
		$columns['isp']				= 'ISP';
		$columns['device']			= '设备';

		$per_page	= array(
			'label'		=> '项',
			'default'	=> 100, 
			'option'	=> 'weixin_pageviews_per_page' 
		);

		$style = '
		th.column-time{width:64px;}
		th.column-sub_type{width:42px;}
		th.column-address{width:70px;}
		th.column-network_type{width:28px;}
		th.column-isp{width:28px;}
		th.column-url{width:30%;}
		';

		$weixin_list_table = wpjam_list_table( array(
			'plural'			=> 'weixin-pageviews',
			'singular' 			=> 'weixin-pageview',
			'columns'			=> $columns,
			'item_callback'		=> 'weixin_robot_pageview_item',
			'per_page'			=> $per_page,
			'views'				=> 'weixin_robot_pageviews_views',
			'style'				=> $style
		) );
	}
}

function weixin_robot_pageviews_tabs($tabs){
	if(WEIXIN_TYPE >= 3){
		$tabs['list-view']	= array('title'=>'浏览记录', 'function'=>'weixin_robot_view_list_page');
		$tabs['list-share']	= array('title'=>'分享记录', 'function'=>'weixin_robot_view_list_page');
		//$tabs['basic']		= array('title'=>'汇总统计', 'function'=>'weixin_robot_view_basic_stats_page');
	}
	return $tabs;
}

function weixin_robot_pageviews_stats_tabs($tabs){
	$tabs['source']			= array('title'=>'浏览统计', 'function'=>'weixin_robot_sub_view_stats_page');
	
	if(WEIXIN_TYPE >= 3){
		$tabs['share']		= array('title'=>'分享统计', 'function'=>'weixin_robot_sub_view_stats_page');
	}
	
	$tabs['hot-view']		= array('title'=>'热门浏览', 'function'=>'weixin_robot_hot_view_stats_page');
	
	if(WEIXIN_TYPE >= 3){
		$tabs['hot-share']	= array('title'=>'热门分享', 'function'=>'weixin_robot_hot_view_stats_page');
	}
	
	$tabs['region']			= array('title'=>'地区分布', 'function'=>'weixin_robot_view_region_stats_page');
	$tabs['os']				= array('title'=>'操作系统', 'function'=>'weixin_robot_view_os_stats_page');
	$tabs['device']			= array('title'=>'手机型号', 'function'=>'weixin_robot_view_device_stats_page');
	return $tabs;
}

add_filter('weixin-robot-users-stats_tabs','weixin_robot_users_pageviews_stats_tabs');
function weixin_robot_users_pageviews_stats_tabs($tabs){
	if(WEIXIN_TYPE >= 3){
		$tabs['summary']= array('title'=>'用户属性', 	'function'=>'weixin_robot_user_summary_page');
	}
	return $tabs;
}

// add_filter('weixin_users_tabs',	'weixin_robot_pageviews_users_tabs');
// function weixin_robot_pageviews_users_tabs($tabs){
// 	$tabs['views']	= array('title'=>'最多浏览','function'=>'weixin_robot_user_hot_stats_page');
// 	$tabs['shares']	= array('title'=>'最多分享','function'=>'weixin_robot_user_hot_stats_page');
// 	return $tabs;
// }

add_filter('weixin-robot-user_tabs', 	'weixin_robot_pageviews_user_tabs');
function weixin_robot_pageviews_user_tabs($tabs){
	$tabs['views']		= array('title'=>'浏览记录','function'=>'weixin_robot_view_list_page');
	$tabs['shares']		= array('title'=>'分享记录','function'=>'weixin_robot_view_list_page');
	return $tabs;
}

add_filter('weixin_tables', 'weixin_robot_pageviews_tables' );
function weixin_robot_pageviews_tables($weixin_tables){
	$weixin_tables['weixin_robot_pageviews_activation'] = array(
		'weixin_pageviews' => '微信页面和分享统计'
	);
	return $weixin_tables;
}

add_action('weixin_extends_updated', 'weixin_robot_pageviews_activation');
function weixin_robot_pageviews_activation(){
	global $wpdb;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	if($wpdb->get_var("show tables like '{$wpdb->weixin_pageviews}'") != $wpdb->weixin_pageviews) {
		$sql = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_pageviews}` (
		  `id` bigint(20) NOT NULL auto_increment,
		  `weixin_openid` varchar(30) NOT NULL,
		  `time` int(10) NOT NULL,
		  `type` varchar(16) NOT NULL default 'View',
		  `sub_type` varchar(16) NOT NULL,
		  `post_id` bigint(20) NOT NULL,
		  `url` varchar(255) NOT NULL,
		  `refer` varchar(30) NOT NULL,
		  `ip` varchar(23) NOT NULL,
		  `ua` varchar(255) NOT NULL,
		  `country` varchar(32) NOT NULL,
		  `region` varchar(32) NOT NULL,
		  `city` varchar(32) NOT NULL,
		  `isp` varchar(64) NOT NULL,
		  `network_type` varchar(20) NOT NULL,
		  `device` varchar(64) NOT NULL,
		  `screen_width` INT(4) NOT NULL,
		  `screen_height` INT(4) NOT NULL,
		  `retina` INT(1) NOT NULL,
		  `build` varchar(32) NOT NULL,
		  `os` varchar(32) NOT NULL,
		  `os_ver` varchar(8) NOT NULL,
		  `weixin_ver` varchar(8) NOT NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		";
 
		dbDelta($sql);
	}

	$sql = "UPDATE {$wpdb->weixin_pageviews} SET device = trim(device)";
	$wpdb->query($sql);

	$sql = "DESCRIBE " . $wpdb->weixin_pageviews . " 'screen_width'";
	if($wpdb->query($sql) == 0){

		$sql = "ALTER TABLE  " . $wpdb->weixin_pageviews . " ADD  `screen_width` INT(4) NOT NULL AFTER  `device`";
		$wpdb->query($sql);

		$sql = "ALTER TABLE  " . $wpdb->weixin_pageviews . " ADD  `screen_height` INT(4) NOT NULL AFTER  `screen_width`";
		$wpdb->query($sql);

		$sql = "ALTER TABLE  " . $wpdb->weixin_pageviews . " ADD  `retina` INT(1) NOT NULL AFTER  `screen_height`";
		$wpdb->query($sql);
	}

	$sql = "DESCRIBE " . $wpdb->weixin_pageviews . " 'refer'";
	
	if($wpdb->query($sql) == 0){

		$sql = "ALTER TABLE  " . $wpdb->weixin_pageviews . " ADD  `refer` VARCHAR( 30 ) AFTER  `url`";
		$wpdb->query($sql);
	}
}