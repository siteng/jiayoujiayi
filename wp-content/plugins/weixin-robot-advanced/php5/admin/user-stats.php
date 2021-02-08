<?php
add_filter('weixin_users_stats_tabs', 'weixin_robot_users_stats_tabs', 1);
function weixin_robot_users_stats_tabs($tabs){
	$tabs = array();
	if(WEIXIN_TYPE >= 3) {
		$tabs['subscribe']	= array('title'=>'用户增长', 	'function'=>'weixin_robot_user_subscribe_stats_page');
		$tabs['summary']	= array('title'=>'用户属性', 	'function'=>'weixin_robot_user_summary_page');
		if(function_exists('weixin_robot_insert_pageview')){
			$tabs['device']	= array('title'=>'手机设备', 	'function'=>'weixin_robot_user_devices_page');
		}
		$tabs['activity']	= array('title'=>'活跃度',	'function'=>'weixin_robot_user_activity_page');
		$tabs['loyalty']	= array('title'=>'忠诚度',	'function'=>'weixin_robot_user_loyalty_page');
		$tabs['hot']		= array('title'=>'影响力',	'function'=>'weixin_robot_user_hot_stats_page');
		if(WEIXIN_TYPE == 4){
			$tabs['vendor']	= array('title'=>'订阅渠道',	'function'=>'weixin_robot_user_vendor_stats_page');
		}
		$tabs['masssend']	= array('title'=>'群发统计',	'function'=>'weixin_robot_masssend_stats_page');
	}else{
		$tabs['subscribe']	= array('title'=>'用户增长', 	'function'=>'weixin_robot_user_subscribe_stats_page');
		$tabs['masssend']	= array('title'=>'群发统计',	'function'=>'weixin_robot_masssend_stats_page');
	}
	return $tabs;
}

add_action('weixin_users_stats_page_load', 'weixin_robot_users_stats_page_load');
function weixin_robot_users_stats_page_load(){
	global $weixin_list_table, $current_tab;

	if($current_tab == 'masssend'){
		$columns	= array(
			'CreateTime'	=> '时间',
			'Status'		=> '状态',
			'TotalCount'	=> '所有',
			'FilterCount'	=> '过滤之后',
			'SentCount'		=> '发送成功',
			'SentRate'		=> '成功率',
			'ErrorCount'	=> '发送失败',
		);

		$style = '
		.tablenav{display:none;}
		th.column-CreateTime{width:120px;}
		th.column-Status{width:100px;}';

		$weixin_list_table = wpjam_list_table( array(
			'plural'		=> 'weixin-masssend-stats',
			'singular' 		=> 'weixin-masssend-stats',
			'columns'		=> $columns,
			'item_callback'	=> 'weixin_robot_masssendstats_item',
			'style'			=> $style,
		) );
	}
}

function weixin_robot_get_user_subscribe_counts($start_timestamp, $end_timestamp){
	global $wpdb;
	$where 	= "CreateTime > {$start_timestamp} AND CreateTime < {$end_timestamp}";
	$sql 	= "SELECT Event as label, count(*) as count  FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType = 'event' AND (Event = 'subscribe' OR Event = 'unsubscribe') GROUP BY Event ORDER BY count DESC;";
	$counts = $wpdb->get_results($sql,OBJECT_K);

	$subscribe		= isset($counts['subscribe'])?$counts['subscribe']->count:0;
	$unsubscribe	= isset($counts['unsubscribe'])?$counts['unsubscribe']->count:0;
	$netuser		= $subscribe - $unsubscribe;

	$percent		= ($subscribe)?round($unsubscribe/$subscribe, 4)*100:0;

	return array('subscribe'=>$subscribe, 'unsubscribe'=>$unsubscribe, 'netuser'=>$netuser, 'percent'=>$percent.'%');
}

// 订阅统计
function weixin_robot_user_subscribe_stats_page() {

	global $wpdb,  $wpjam_stats_labels;

	?>

	<h2>每日订阅统计</h2>

	<?php
	
	extract($wpjam_stats_labels);

	wpjam_stats_header(array('show_date_type'=>true));

	$stats_data = apply_filters('weixin_user_subscribe_stats_data', false);
	if ($stats_data !== false) {
		if (is_string($stats_data)) {
			echo $stats_data;
			return;
		}

		$subscribe_count = $stats_data['subscribe_count'];
		$unsubscribe_count = $stats_data['unsubscribe_count'];
		$netuser = $stats_data['netuser'];
		$unsubscribe_rate = $stats_data['unsubscribe_rate'];
		$counts_array = $stats_data['counts_array'];
	}
	else {

		$counts	= weixin_robot_get_user_subscribe_counts($wpjam_start_timestamp, $wpjam_end_timestamp);
		$subscribe_count	= $counts['subscribe'];
		$unsubscribe_count	= $counts['unsubscribe'];
		$netuser			= $counts['netuser'];
		$unsubscribe_rate	= ($subscribe_count)?round($unsubscribe_count*100/$subscribe_count,2):0;

		$where	= "MsgType ='event' AND CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";

		$sum 	= array();
		$sum[]	= "SUM(case when Event='subscribe' then 1 else 0 end) as subscribe";
		$sum[]	= "SUM(case when Event='unsubscribe' then 1 else 0 end) as unsubscribe";
		$sum[] 	= "SUM(case when Event='subscribe' then 1 when Event='unsubscribe' then -1 else 0 end ) as netuser";
		$sum	= implode(', ', $sum);
		$sql	= "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total, {$sum} FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType = 'event' GROUP BY day ORDER BY day DESC";
		$sql 	= "SELECT *, CONCAT(ROUND(unsubscribe/subscribe * 100,2),'%')  as percent FROM ({$sql}) aaa;";

		$counts_array	= $wpdb->get_results($sql, OBJECT_K);
	}


	echo '从 '.$wpjam_start_date.' 到 '.$wpjam_end_date.' 这段时间内，共有 <span class="green">'.$subscribe_count.'</span> 人订阅，<span class="red">'.$unsubscribe_count.'</span> 人取消订阅，取消率 <span class="red">'.$unsubscribe_rate.'%</span>，净增长 <span class="green">'.$netuser.'</span> 人。';

	$types 	= array('subscribe'=>'用户订阅', 'unsubscribe'=>'取消订阅', 'percent'=>'取消率%', 'netuser'=>'净增长');
	
	wpjam_line_chart($counts_array, $types);
}

// 订阅渠道
function weixin_robot_user_vendor_stats_page() {
	global $wpdb, $current_admin_url, $wpjam_stats_labels;
	extract($wpjam_stats_labels);
	wpjam_stats_header(array('show_date_type'=>true,'show_compare'=>true));
	?>
	<h2>用户订阅渠道统计分析</h2>

	<?php

	if(weixin_robot_get_setting('weixin_es')){
		$blog_id = get_current_blog_id();
		weixin_robot_user_vendor_stats_es_page($blog_id);
		return;
	}

	$weixin_qrcodes = $wpdb->get_results("SELECT concat('qrscene_',wwqr.scene) as new_scene, wwqr.* FROM $wpdb->weixin_qrcodes wwqr;");

	$qrcode_types = array();
	foreach ($weixin_qrcodes as $weixin_qrcode) {
		$qrcode_types[$weixin_qrcode->new_scene] = $weixin_qrcode->name;
	}
	$qrcode_types['']	= '直接订阅';

	$qrcode_type = isset($_GET['type'])?$_GET['type']:'';

	if($qrcode_type && $wpjam_compare){

		if($qrcode_type == 'other'){
			$qrcode_type = '';
		}

		$where 		= "MsgType ='event' AND Event ='subscribe' AND EventKey='{$qrcode_type}' AND CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";
		$where_2	= "MsgType ='event' AND Event ='subscribe' AND EventKey='{$qrcode_type}' AND CreateTime > {$wpjam_start_timestamp_2} AND CreateTime < {$wpjam_end_timestamp_2}";

		if($qrcode_type == ''){
			$qrcode_type = 'other';
		}

		$count		= $wpdb->get_var("SELECT count(id) as count FROM {$wpdb->weixin_messages} WHERE {$where}");
		$count_2	= $wpdb->get_var("SELECT count(id) as count FROM {$wpdb->weixin_messages} WHERE {$where_2}");

		$counts = array();
		$counts[] = array(
			'label'=>$compare_label, 
			'count'=>$count
		);

		$counts[] = array(
			'label'=>$compare_label_2, 
			'count'=>$count_2
		);

		wpjam_donut_chart($counts, array('chart_width'=>280));

	}else{

		$where = "MsgType ='event' AND Event ='subscribe' AND CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";
		$sql = "SELECT count(*) as count, LOWER(EventKey) as label  FROM {$wpdb->weixin_messages} WHERE  {$where} GROUP BY EventKey ORDER BY count DESC;";
		$counts = $wpdb->get_results($sql);
		
		$total = $wpdb->get_var("SELECT count(id) FROM {$wpdb->weixin_messages} WHERE {$where}");
		$total_link	= $current_admin_url.'#daily-chart';

		wpjam_donut_chart($counts, array('total'=>$total,'show_link'=>true,'labels'=>$qrcode_types,'chart_width'=>280));
	}
	?>

	<div class="clear"></div>

	<?php
	if($qrcode_type){
		if($wpjam_compare){
			$time_diff = strtotime($wpjam_start_date) - strtotime($wpjam_start_date_2);

			$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as data FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY day ORDER BY day DESC;";

			$sql_2 = "SELECT FROM_UNIXTIME(CreateTime+{$time_diff}, '{$wpjam_date_format}') as day, count(id) as data2 FROM {$wpdb->weixin_messages} WHERE {$where_2} GROUP BY day ORDER BY day DESC;";
			
			$counts_array = $wpdb->get_results($sql,OBJECT_K);

			$counts_array_2 = $wpdb->get_results($sql_2,OBJECT_K);

			$new_counts_array = array();

			foreach ($counts_array as $day => $counts) {
				$new_counts_array[$day]['data'] = $counts->data;
			}

			foreach ($counts_array_2 as $day => $counts) {
				$new_counts_array[$day]['data2'] = $counts->data2;
			}

			$labels = array(
				'data'	=> $compare_label,
				'data2'	=> $compare_label_2
			);

			wpjam_line_chart($new_counts_array, $labels);

		}else{
			if($qrcode_type == 'other') {
				$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as {$qrcode_type} FROM {$wpdb->weixin_messages} WHERE EventKey = '' AND {$where} GROUP BY day ORDER BY day DESC;";
			}else{
				$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as {$qrcode_type} FROM {$wpdb->weixin_messages} WHERE EventKey = '{$qrcode_type}' AND {$where} GROUP BY day ORDER BY day DESC;";
			}
			$counts_array = $wpdb->get_results($sql,OBJECT_K);
			wpjam_line_chart($counts_array, array($qrcode_type=>$qrcode_types[$qrcode_type]));
		}
		
	}else{
		$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY day ORDER BY day DESC;";

		$counts_array = $wpdb->get_results($sql,OBJECT_K);

		wpjam_line_chart($counts_array, array('total'=>'所有'));
	}
}

// 用户属性
function weixin_robot_user_summary_page(){
	global $wpdb, $plugin_page, $current_admin_url, $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	$type = 'all';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$type = $_POST['type'];
	}

	?>

	<h2>用户属性</h2>
	<?php if(WEIXIN_TYPE >= 3) { ?>
	<form action="<?php echo $current_admin_url ?>" method="post" style="margin-top:15px;">
		<input name="type" type="radio" id="type" onclick="javascript: submit();" value="all" <?php checked("all", $type);?>>所有用户 
		<input name="type" type="radio" id="type" onclick="javascript: submit();" value="subscribe" <?php checked("subscribe", $type);?>>订阅用户 
		<input name="type" type="radio" id="type" onclick="javascript: submit();" value="unsubscribe" <?php checked("unsubscribe", $type);?>>取消订阅 
	</form>
	<?php } ?>
	<?php 

	$where = "subscribe_time !=''";
	

	if($type == 'all'){
		$subscribe_tab	= array(
			'name'		=>'用户订阅',
			'counts_sql'=> "SELECT count(openid) as count, subscribe as label FROM {$wpdb->weixin_users} WHERE {$where} GROUP BY subscribe ORDER BY count DESC;",
			'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where}",
			'labels'	=> array('0'=>'取消订阅', '1'=>'订阅')
		);
	}elseif($type == 'subscribe'){
		$where .= " AND subscribe = 1";
	}else{
		$where .= " AND subscribe = 0";
	}

	$sex_tab	= array(
		'name'		=>'用户性别',
		'counts_sql'=> "SELECT count(openid) as count, sex as label FROM {$wpdb->weixin_users} WHERE {$where} GROUP BY sex ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where}",
		'labels'	=> array('0'=>'未知', '1'=>'男', '2'=>'女'),
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

	$language_tab	= array(
		'name'		=>'用户语言',
		'counts_sql'=> "SELECT count(openid) as count, language as label FROM {$wpdb->weixin_users} WHERE {$where} GROUP BY language ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where}",
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

	$country_tab	= array(
		'name'		=>'国家和地区',
		'counts_sql'=> "SELECT count(openid) as count, country as label FROM {$wpdb->weixin_users} WHERE {$where} AND country != '' GROUP BY country ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND country != ''",
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

	$province_tab	= array(
		'name'		=>'省份',
		'counts_sql'=> "SELECT count(openid) as count, province as label FROM {$wpdb->weixin_users} WHERE {$where} AND country = '中国' AND province !='' GROUP BY province ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND country = '中国' AND province !='' ",
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

	$city_tab	= array(
		'name'		=>'城市',
		'counts_sql'=> "SELECT count(openid) as count, city as label FROM {$wpdb->weixin_users} WHERE {$where} AND country = '中国' AND city !='' GROUP BY city ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND country = '中国' AND city !=''",
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

	$tabs = array();

	
	if($type == 'all'){
		$tabs['subscribe'] = $subscribe_tab;
	}
	$tabs = array_merge($tabs, array('sex'=>$sex_tab,'language'=>$language_tab,'country'=>$country_tab,'province'=>$province_tab,'city'=>$city_tab));

	wpjam_sub_summary($tabs);
}

// 手机设备
function weixin_robot_user_devices_page(){
	global $wpdb, $plugin_page, $current_admin_url, $wpjam_stats_labels;
	extract($wpjam_stats_labels);
	?>
	<h2>手机设备</h2>
	<?php 

	$where = "subscribe_time !=''";

	$os_tab	= array(
		'name'		=>'操作系统',
		'counts_sql'=> "SELECT count(openid) as count, os as label FROM {$wpdb->weixin_users} WHERE {$where} AND os != '' GROUP BY os ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND os != ''",
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

	$ios_tab	= array(
		'name'		=>'iOS 版本',
		'counts_sql'=> "SELECT count(openid) as count, os_ver as label FROM {$wpdb->weixin_users} WHERE {$where} AND os = 'iOS' AND os_ver !='' GROUP BY os_ver ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND os = 'iOS' AND os_ver !='' "
	);

	$android_tab	= array(
		'name'		=>'安卓版本',
		'counts_sql'=> "SELECT count(openid) as count, os_ver as label FROM {$wpdb->weixin_users} WHERE {$where} AND os = 'Android' AND os_ver !='' GROUP BY os_ver ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND os = 'Android' AND os_ver !='' "
	);

	$weixin_tab	= array(
		'name'		=>'微信版本',
		'counts_sql'=> "SELECT count(openid) as count, weixin_ver as label FROM {$wpdb->weixin_users} WHERE {$where} AND weixin_ver != '' GROUP BY weixin_ver ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND weixin_ver != ''"
	);

	$brand_tab	= array(
		'name'		=>'手机品牌',
		'counts_sql'=> "SELECT count(openid) as count, brand as label FROM {$wpdb->weixin_users} wut LEFT JOIN $wpdb->devices wdt ON trim(wut.device) = wdt.device WHERE {$where} AND wut.device != '' GROUP BY brand ORDER BY count DESC;",
		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND device != ''",
		'link'		=> admin_url('admin.php?page=weixin-robot-users')
	);

//	$devices = wpjam_get_devices();
//
//	$device_tab	= array(
//		'name'		=>'手机型号',
//		'counts_sql'=> "SELECT count(openid) as count, (case when device = 'iPhone' AND screen_width > 0 then concat(device,'_',screen_width,'x',screen_height) else device end ) as label FROM {$wpdb->weixin_users} WHERE {$where} AND device != '' GROUP BY label ORDER BY count DESC;",
//		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND device != ''",
//		'labels'	=> $devices,
//		'link'		=> admin_url('admin.php?page=weixin-robot-users')
//	);

//	$size_tab	= array(
//		'name'		=>'屏幕尺寸',
//		'counts_sql'=> "SELECT count(openid) as count, size as label FROM (SELECT openid, (case when device = 'iPhone' AND screen_width > 0 then concat(device,'_',screen_width,'x',screen_height) else device end ) as device FROM {$wpdb->weixin_users} WHERE {$where} AND device != '') wut LEFT JOIN $wpdb->devices wdt ON trim(wut.device) = wdt.device GROUP BY size ORDER BY count DESC;",
//		'total_sql'	=> "SELECT count(openid) FROM {$wpdb->weixin_users} WHERE {$where} AND device != ''",
//		'labels'	=> $devices,
//		'link'		=> admin_url('admin.php?page=weixin-robot-users')
//	);

	$tabs = array();

	if(function_exists('weixin_robot_insert_pageview')){
		$tabs = array_merge($tabs, array('os'=>$os_tab,'ios'=>$ios_tab,'android'=>$android_tab,'weixin'=>$weixin_tab,'brand'=>$brand_tab,'device'=>$device_tab,'size'=>$size_tab));
	}
	
	wpjam_sub_summary($tabs);
}

function weixin_robot_get_subscribe_count(){
	global $wpdb;
	return $wpdb->get_var("SELECT count(*) FROM  $wpdb->weixin_users WHERE subscribe = 1;");
}

// 微信用户活跃度
function weixin_robot_user_activity_page(){ ?>
	<h2>用户活跃度</h2>
	<?php
	$times = array(
		1	=> '1天内',
		3	=> '3天内',
		7	=> '7天内',
		15	=> '15天内',
		30	=> '1个月',
		//'90'	=> '3个月',
		//'365'	=> '1年',
	);

	$now				= time();
	$subscribe_count	= weixin_robot_get_subscribe_count();

	$counts_array		= array();

	foreach ($times as $key => $value) {
		$start		= $now - (DAY_IN_SECONDS*$key);
		$activity	= weixin_robot_user_sub_activity($start);
		$percent	= round($activity / $subscribe_count, 4)*100;
		$counts_array[$value]	= array('users'=>$activity, 'percent'=>$percent.'%');
	}

	$labels = array('users'=>'活跃用户', 'percent'=>'所占比率%');

	wpjam_bar_chart($counts_array, $labels, array('day_label'=>'时长'));
}

function weixin_robot_user_sub_activity($start){
	global $wpdb;
	return count($wpdb->get_results("SELECT FromUserName, COUNT( * ) AS count FROM  {$wpdb->weixin_messages} WHERE CreateTime > {$start} AND FromUserName !=''  GROUP BY FromUserName "));
}

function weixin_robot_user_loyalty_page(){ ?>
	<h2>用户忠诚度</h2>
	<?php
	$times = array(
		30		=> '1个月内',
		90		=> '1-3个月',
		180		=> '3-6个月',
		365		=> '6个月-1年',
		366		=> '1年以上'
		//'90'	=> '3个月',
		//'365'	=> '1年'
	);

	$now				= time();
	$subscribe_count	= weixin_robot_get_subscribe_count();

	$counts_array		= array();

	$pre_start = $now;
	foreach ($times as $key => $value) {
		$start		= ($key>365)?0:$now - (DAY_IN_SECONDS*$key);
		$loyalty	= weixin_robot_user_sub_loyalty($pre_start,$start);
		$percent	= round($loyalty / $subscribe_count, 4)*100;

		$counts_array[$value]	= array('users'=>$loyalty, 'percent'=>$percent.'%');
		$pre_start	= $start;
	}

	$labels = array('users'=>'用户',	'percent'=>'所占比率%');

	wpjam_bar_chart($counts_array, $labels, array('day_label'=>'关注时长'));
}

function weixin_robot_user_sub_loyalty($pre, $start){
	global $wpdb;
	return $wpdb->get_var("SELECT COUNT( * ) AS count FROM {$wpdb->weixin_users} WHERE subscribe = 1 AND subscribe_time < {$pre} AND subscribe_time > {$start}");
}

// 活跃用户
function weixin_robot_user_hot_stats_page(){
	global $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	$tabs = array('messages'	=> '最多互动');

	if(function_exists('weixin_robot_insert_pageview')){
		$tabs['views']	= '最多浏览';
		$tabs['shares']	= '最多分享';
		$tabs['refers']	= '最多推荐';
	}
	?>
	<h2>影响力</h2>

	<?php wpjam_stats_header(); ?>

	<h2 class="nav-tab-wrapper">
    <?php foreach ($tabs as $key => $name) { ?>
        <a class="nav-tab" href="javascript:;" id="tab-title-<?php echo $key;?>"><?php echo $name;?></a>   
    <?php }?>
    </h2>

    <?php foreach ($tabs as $key => $name) { ?>
    <div id="tab-<?php echo $key;?>" class="div-tab" style="margin-top:1em;">
    	<?php weixin_robot_user_sub_hot_stats_page($key)?>
    </div>
    <?php }
}

function weixin_robot_user_sub_hot_stats_page($tab='messages'){
	global $wpdb, $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	if($tab == 'messages'){
		$types = weixin_robot_get_message_types();

		$where = " CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";

		$sum = array();
		foreach (array_keys($types) as $message_type){
			$sum[] = "SUM(case when MsgType='{$message_type}' then 1 else 0 end) as {$message_type}";
		}
		$sum = implode(', ', $sum);

		$sql = "SELECT COUNT( * ) AS total, FromUserName, {$sum} FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY FromUserName ORDER BY total DESC LIMIT 0,150 ";
	}elseif($tab == 'views'){
		$types =  weixin_robot_get_source_types();

		$where = " time > {$wpjam_start_timestamp} AND time < {$wpjam_end_timestamp} AND weixin_openid != ''";

		$sum = array();
		foreach (array_keys($types) as $type){
			$sum[] = "SUM(case when sub_type='{$type}' then 1 else 0 end) as {$type}";
		}
		$sum = implode(', ', $sum);

		$sql = "SELECT COUNT( * ) AS total, weixin_openid, {$sum} FROM {$wpdb->weixin_pageviews} WHERE {$where} AND type = 'View' GROUP BY weixin_openid ORDER BY total DESC LIMIT 0,150 ";
	}elseif($tab == 'shares'){
		$types =  weixin_robot_get_share_types();

		$where = " time > {$wpjam_start_timestamp} AND time < {$wpjam_end_timestamp} AND weixin_openid != ''";

		$sum = array();
		foreach (array_keys($types) as $type){
			$sum[] = "SUM(case when sub_type='{$type}' then 1 else 0 end) as {$type}";
		}
		$sum = implode(', ', $sum);

		$sql = "SELECT COUNT( * ) AS total, weixin_openid, {$sum} FROM {$wpdb->weixin_pageviews} WHERE {$where} AND type = 'Share' GROUP BY weixin_openid ORDER BY total DESC LIMIT 0,150 ";
	}elseif($tab == 'refers'){
		$types =  weixin_robot_get_source_types();

		$where = " time > {$wpjam_start_timestamp} AND time < {$wpjam_end_timestamp} AND weixin_openid != ''";

		$sum = array();
		foreach (array_keys($types) as $type){
			$sum[] = "SUM(case when sub_type='{$type}' then 1 else 0 end) as {$type}";
		}
		$sum = implode(', ', $sum);

		$sql = "SELECT COUNT( * ) AS total, refer, {$sum} FROM {$wpdb->weixin_pageviews} WHERE {$where} AND type = 'View' AND refer !='' GROUP BY refer ORDER BY total DESC LIMIT 0,150 ";
	}
	
	$counts = $wpdb->get_results($sql);
	$types = array('total'=>'所有') + $types;

	if($counts){
	?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th>排名</th>
				<th>用户</th>
				<?php foreach ($types as $key=>$value) {?>
				<th><?php echo $value;?></th>
				<?php }?>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<th>排名</th>
				<th>用户</th>
				<?php foreach ($types as $key=>$value) {?>
				<th><?php echo $value;?></th>
				<?php }?>
			</tr>
		</tfoot>

		<tbody>
		<?php  $i=1;?>
		<?php foreach ($counts as $count) { 
			if($i > 100 ) break;
			$alternate = empty($alternate)?'alternate':'';

			if($tab == 'messages') {
				$weixin_openid = $count->FromUserName;
			}elseif($tab == 'views') {
				$weixin_openid = $count->weixin_openid;
			}elseif($tab == 'shares') {
				$weixin_openid = $count->weixin_openid;
			}elseif($tab == 'refers'){
				$weixin_openid = $count->refer;
			}
			
			$weixin_user = weixin_robot_get_user($weixin_openid);
			$weixin_user = weixin_robot_get_user_detail($weixin_user, array('tab'=>$tab));
			if($weixin_user){ 
			?>
			<tr class="<?php echo $alternate;?>">
				<td><?php echo $i; $i++;?></td>
				<td><?php echo $weixin_user['username'];?></td>
				<?php foreach ($types as $key=>$value) {?>
				<td><?php if($count->$key){ ?>
				<a href="<?php echo $weixin_user['link'].'&tab='.$tab;?>"><?php echo $count->$key;?></a>
				<?php } else { ?>
				<?php echo $count->$key;?>
				<?php }?></td>
				<?php }?>
			</tr>
			<?php }?>
		<?php } ?>
		</tbody>
	</table>
	<?php
	} else{
		echo '<p>暂无数据</p>';
	}
}

// 群发统计
function weixin_robot_masssend_stats_page(){
	global $wpdb, $wpjam_stats_labels, $weixin_list_table;
	extract($wpjam_stats_labels);
	?>
	<h2>群发粉丝统计</h2>
	<?php

	wpjam_stats_header();

	$where = "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";
	$weixin_masssendjobs = $wpdb->get_results("SELECT * FROM {$wpdb->weixin_messages} WHERE {$where} AND Event = 'MASSSENDJOBFINISH' AND Content !='' ORDER BY CreateTime DESC", ARRAY_A);

	$weixin_list_table->prepare_items($weixin_masssendjobs);
	$weixin_list_table->display();
	?>
	
	<?php /*<p>*状态为send success时，也有可能因用户拒收公众号的消息、系统错误等原因造成少量用户接收失败。</p>
	<p>*err(num)是审核失败的具体原因，可能的情况如下：<br />
	<ul>
	<li>err(10001), //涉嫌广告 </li>
	<li>err(20001), //涉嫌政治 </li>
	<li>err(20004), //涉嫌社会 </li>
	<li>err(20002), //涉嫌色情 </li>
	<li>err(20006), //涉嫌违法犯罪 </li>
	<li>err(20008), //涉嫌欺诈 </li>
	<li>err(20013), //涉嫌版权 </li>
	<li>err(22000), //涉嫌互推(互相宣传)</li> 
	<li>err(21000), //涉嫌其他</li>
	</ul>
	</p>*/?>
	<?php
}

function weixin_robot_masssendstats_item($item){
	$item['CreateTime']	= get_date_from_gmt(date('Y-m-d H:i:s',$item['CreateTime']));
	$count_list			= maybe_unserialize($item['Content']);
	if($count_list){
		$item['Status']		= isset($count_list['Status'])?$count_list['Status']:'';
		$item['TotalCount']	= $count_list['TotalCount'];
		$item['FilterCount']= $count_list['FilterCount'];
		$item['SentCount']	= $count_list['SentCount'];
		$item['SentRate']	= round($count_list['SentCount']*100/$count_list['TotalCount'],2).'%';
		$item['ErrorCount']	= $count_list['ErrorCount'];
	}else{
		$item['Status']		= '';
		$item['TotalCount']	= '';
		$item['FilterCount']= '';
		$item['SentCount']	= '';
		$item['SentRate']	= '';
		$item['ErrorCount']	= '';
	}
	return $item;
}







