<?php
include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-reply.php';

add_filter('wpjam_weixin_dashboard_widgets', function (){
	$end 	= current_time('timestamp',true);
	$start	= $end - (DAY_IN_SECONDS);

	$args 	= compact('start', 'end');

	return [
		'weixin-overview'	=>['title'=>'数据预览',			'callback'=>'weixin_overview_dashboard_widget_callback',	'args'=>$args],
		'weixin-keyword'	=>['title'=>'24小时热门关键字',	'callback'=>'weixin_keyword_dashboard_widget_callback',		'args'=>$args,	'context'=>'side'],
	];
});

add_action('wpjam_weixin_stats_tabs', function($tabs){
	$tabs	= [
		'subscribe'	=> ['title'=>'用户增长', 	'function'=>'weixin_user_subscribe_stats_page'],
		'masssend'	=> ['title'=>'群发统计', 	'function'=>'list',	'list_table_name'=>'weixin_masssend'],
		'stats'		=> ['title'=>'消息预览',	'function'=>'weixin_message_overview_page'],
		'message'	=> ['title'=>'消息统计',	'function'=>'weixin_message_stats_page'],
		'event'		=> ['title'=>'事件统计',	'function'=>'weixin_message_stats_page'],
		'menu'		=> ['title'=>'菜单统计',	'function'=>'weixin_message_stats_page'],
		'text'		=> ['title'=>'文本统计',	'function'=>'weixin_message_stats_page'],
		'summary'	=> ['title'=>'文本汇总',	'function'=>'weixin_message_summary_page'],
	];

	if(weixin_get_type() < 3) {
		unset($tabs['masssend']);
	}

	return $tabs;
});

add_filter('wpjam_weixin_masssend_list_table', function(){
	WEIXIN_AdminMessage::set_tab('masssend');

	return [
		'title'		=> '群发记录',
		'singular'	=> 'weixin-masssend',
		'plural'	=> 'weixin-masssends',
		'model'		=> 'WEIXIN_AdminMessage',
		'actions'	=> [],
	];
});

add_action('wpjam_weixin_masssends_extra_tablenav', function($which){
	if($which == 'top'){
		wpjam_stats_header();

		global $wpjam_stats_labels;

		extract($wpjam_stats_labels);
	}
});

function weixin_get_message_counts($start, $end){
	$total	= WEIXIN_AdminMessage::Query()->where('appid', weixin_get_appid())->where_gt('CreateTime', $start)->where_lt('CreateTime', $end)->get_var("count(id) as total");
	$people	= WEIXIN_AdminMessage::Query()->where('appid', weixin_get_appid())->where_gt('CreateTime', $start)->where_lt('CreateTime', $end)->get_var("count(DISTINCT FromUserName) as people");
	
	$avg	= ($people)?round($total/$people,4):0;

	return compact('total', 'people', 'avg');
}

function weixin_get_expected_count($today_count, $yesterday_count, $yesterday_compare_count='', $asc=true){

	if($yesterday_compare_count){
		$expected_count = round($today_count/$yesterday_compare_count*$yesterday_count);
	}else{
		$expected_count	= $today_count;
	}

	if(floatval($expected_count) >= floatval($yesterday_count)){
		if($asc){
			$expected_count	.= '<span class="green">&uarr;</span>';
		}else{
			$expected_count	.= '<span class="red">&uarr;</span>';
		}
	}else{
		if($asc){
			$expected_count	.= '<span class="red">&darr;</span>';
		}else{
			$expected_count	.= '<span class="green">&darr;</span>';
		}
	}

	return $expected_count;
}

function weixin_overview_dashboard_widget_callback($dashboard, $meta_box){
	global $wpjam_stats_labels;

	$today						= date('Y-m-d',current_time('timestamp'));
	$today_start_timestamp		= strtotime(get_gmt_from_date($today.' 00:00:00'));
	$today_end_timestamp		= current_time('timestamp',true);

	$yesterday					= date('Y-m-d',current_time('timestamp')-DAY_IN_SECONDS);
	$yesterday_start_timestamp	= strtotime(get_gmt_from_date($yesterday.' 00:00:00'));
	$yesterday_end_timestamp	= strtotime(get_gmt_from_date($yesterday.' 23:59:59'));

	$yesterday_end_timestamp_c	= current_time('timestamp',true)-DAY_IN_SECONDS;

	$today_counts 				= weixin_get_user_subscribe_counts($today_start_timestamp, $today_end_timestamp);
	$yesterday_counts 			= weixin_get_user_subscribe_counts($yesterday_start_timestamp, $yesterday_end_timestamp);
	$yesterday_compare_counts	= weixin_get_user_subscribe_counts($yesterday_start_timestamp, $yesterday_end_timestamp_c);
	
	?>
	<h3>用户订阅</h3>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th>时间</th>
				<th>用户订阅</th>	
				<th>取消订阅</th>	
				<th>取消率%</th>	
				<th>净增长</th>	
			</tr>
		</thead>
		<tbody>
			<tr class="alternate">
				<td>今日</td>
				<td><?php echo $today_counts['subscribe'];?></td>
				<td><?php echo $today_counts['unsubscribe'];?></td>
				<td><?php echo $today_counts['percent'];?></td>
				<td><?php echo $today_counts['netuser'];?></td>
			</tr>
			<tr class="">
				<td>昨日</td>
				<td><?php echo $yesterday_counts['subscribe'];?></td>
				<td><?php echo $yesterday_counts['unsubscribe'];?></td>
				<td><?php echo $yesterday_counts['percent'];?></td>
				<td><?php echo $yesterday_counts['netuser'];?></td>
			</tr>
			<tr class="alternate" style="font-weight:bold;">
				<td>预计今日</td>
				<td><?php echo $expected_subscribe = weixin_get_expected_count($today_counts['subscribe'], $yesterday_counts['subscribe'], $yesterday_compare_counts['subscribe']); ?></td>
				<td><?php echo $expected_unsubscribe = weixin_get_expected_count($today_counts['unsubscribe'], $yesterday_counts['unsubscribe'], $yesterday_compare_counts['unsubscribe'], false); ?></td>
				<td><?php echo weixin_get_expected_count($today_counts['percent'], $yesterday_counts['percent'],'',false); ?></td>
				<td><?php echo weixin_get_expected_count(intval($expected_subscribe) - intval($expected_unsubscribe), $yesterday_counts['netuser']); ?></td>
			</tr>
		</tbody>
	</table>

	<p><a href="<?php echo admin_url('admin.php?page=weixin-stats&tab=subscribe');?>">详细用户订阅数据...</a></p>
	<hr />
	<?php

	$today_counts 				= weixin_get_message_counts($today_start_timestamp, $today_end_timestamp);
	$yesterday_counts 			= weixin_get_message_counts($yesterday_start_timestamp, $yesterday_end_timestamp);
	$yesterday_compare_counts	= weixin_get_message_counts($yesterday_start_timestamp, $yesterday_end_timestamp_c);
	?>
	<h3>消息统计</h3>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th>时间</th>
				<th>消息发送次数</th>	
				<th>消息发送人数</th>	
				<th>人均发送次数</th>	
			</tr>
		</thead>
		<tbody>
			<tr class="alternate">
				<td>今日</td>
				<td><?php echo $today_counts['total']; ?>
				<td><?php echo $today_counts['people']; ?>
				<td><?php echo $today_counts['avg']; ?>
			</tr>
			<tr class="">
				<td>昨日</td>
				<td><?php echo $yesterday_counts['total']; ?>
				<td><?php echo $yesterday_counts['people']; ?>
				<td><?php echo $yesterday_counts['avg']; ?>
			</tr>
			<tr class="alternate" style="font-weight:bold;">
				<td>预计今日</td>
				<td><?php echo weixin_get_expected_count($today_counts['total'], $yesterday_counts['total'], $yesterday_compare_counts['total']); ?>
				<td><?php echo weixin_get_expected_count($today_counts['people'], $yesterday_counts['people'], $yesterday_compare_counts['people']); ?>
				<td><?php echo weixin_get_expected_count($today_counts['avg'], $yesterday_counts['avg']); ?>
			</tr>
		</tbody>
	</table>

	<p><a href="<?php echo admin_url('admin.php?page=weixin-stats&tab=stats');?>">详细消息统计...</a></p>
	<?php
}

function weixin_keyword_dashboard_widget_callback($dashboard, $meta_box){
	$start	= $meta_box['args']['start'];
	$end	= $meta_box['args']['end'];

	$where = " CreateTime > {$start} AND CreateTime < {$end}";

	$hot_messages	= WEIXIN_AdminMessage::Query()->where('appid', weixin_get_appid())->where_gt('CreateTime', $start)->where_lt('CreateTime', $end)->where('MsgType','text')->where_not('Content','')->group_by('Content')->order_by('count')->order('DESC')->limit(10)->get_results("COUNT( * ) AS count, Response, MsgType, LOWER(Content) as Content");

	$response_types = WEIXIN_AdminMessage::get_response_types();

	$i= 0;
	if($hot_messages){ ?>
	<table class="widefat" cellspacing="0">
		<tbody>
		<?php foreach ($hot_messages as $message) { $alternate = empty($alternate)?'alternate':''; $i++; ?>
			<tr class="<?php echo $alternate; ?>">
				<td style="width:18px;"><?php echo $i; ?></td>
				<td><?php echo $message['Content']; ?></td>
				<td style="width:32px;"><?php echo $message['count']; ?></td>
				<td style="width:98px;"><?php echo ($response_types[$message['Response']])??''; ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	<p><a href="<?php echo admin_url('admin.php?page=weixin-stats&tab=summary');?>">更多热门关键字...</a></p>
	<?php
	}
}

function weixin_get_user_subscribe_counts($start, $end){
	$counts	= WEIXIN_AdminMessage::Query()
			->where('appid', weixin_get_appid())
			->where_gt('CreateTime', $start)
			->where_lt('CreateTime', $end)
			->where('MsgType','event')
			->where_in('Event',['subscribe','unsubscribe'])
			->group_by('Event')
			->order_by('count')
			->get_results("Event as label, count(*) as count");

	if($counts){
		$counts			= wp_list_pluck($counts, 'count', 'label');

		$subscribe		= $counts['subscribe'] ?? 0;
		$unsubscribe	= $counts['unsubscribe'] ?? 0;
	}else{
		$subscribe		= 0;
		$unsubscribe	= 0;
	}
	
	$netuser	= $subscribe - $unsubscribe;
	$percent	= $subscribe ? round($unsubscribe/$subscribe, 4)*100 : 0;

	return compact('subscribe', 'unsubscribe', 'netuser', 'percent');
}

function weixin_user_subscribe_stats_page() {
	global $wpjam_stats_labels;

	wpjam_stats_header(['show_date_type'=>true]);

	extract($wpjam_stats_labels);

	$counts	= weixin_get_user_subscribe_counts($wpjam_start_timestamp, $wpjam_end_timestamp);

	echo '<p>从 '.$wpjam_start_date.' 到 '.$wpjam_end_date.' 这段时间内，共有 <span class="green">'.$counts['subscribe'].'</span> 人订阅，<span class="red">'.$counts['unsubscribe'].'</span> 人取消订阅，取消率 <span class="red">'.$counts['percent'].'%</span>，净增长 <span class="green">'.$counts['netuser'].'</span> 人。</p>';

	$sum 	= [];
	$sum[]	= "SUM(case when Event='subscribe' then 1 else 0 end) as subscribe";
	$sum[]	= "SUM(case when Event='unsubscribe' then 1 else 0 end) as unsubscribe";
	$sum[] 	= "SUM(case when Event='subscribe' then 1 when Event='unsubscribe' then -1 else 0 end ) as netuser";
	$sum	= implode(', ', $sum);

	$counts	= WEIXIN_AdminMessage::Query()
			->where('appid', weixin_get_appid())
			->where_gt('CreateTime', $wpjam_start_timestamp)
			->where_lt('CreateTime', $wpjam_end_timestamp)
			->where('MsgType','event')
			->where_in('Event',['subscribe','unsubscribe'])
			->group_by('day')->order_by('day')
			->get_results("FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total, {$sum}");

	$counts_array	= [];

	foreach ($counts as $count) {
		$count['percent']	= $count['subscribe'] ? round($count['unsubscribe']/$count['subscribe'] * 100, 2) : 0;
		$counts_array[$count['day']]	= $count;
	}

	$types 	= ['subscribe'=>'用户订阅', 'unsubscribe'=>'取消订阅', 'percent'=>'取消率%', 'netuser'=>'净增长'];
	
	wpjam_line_chart($counts_array, $types);
}

function weixin_message_overview_page(){
	wpjam_stats_header(array('show_date_type'=>true));

	global $wpdb, $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	// $counts_array	= $wpdb->get_results("SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as cnt, count(DISTINCT FromUserName) as user, (COUNT(id)/COUNT(DISTINCT FromUserName)) as avg FROM {WEIXIN_Message::get_table()} WHERE CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp} GROUP BY day ORDER BY day DESC;", OBJECT_K);

	$counts_array	= WEIXIN_AdminMessage::Query()->where('appid', weixin_get_appid())->where_gt('CreateTime', $wpjam_start_timestamp)->where_lt('CreateTime', $wpjam_end_timestamp)->group_by('day')->order_by('day')->get_results("FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as cnt, count(DISTINCT FromUserName) as user, (COUNT(id)/COUNT(DISTINCT FromUserName)) as avg");

	$counts_array	= array_combine(array_column($counts_array, 'day'), $counts_array);

	wpjam_line_chart($counts_array, array(
		'cnt'	=>'消息发送次数', 
		'user'	=>'消息发送人数', 
		'avg'	=>'人均发送次数#'
	));
}

function weixin_message_stats_page() {
	global $wpdb, $current_admin_url, $current_tab, $wpjam_stats_labels;

	$message_types	= WEIXIN_AdminMessage::get_message_types($current_tab);
	$message_query	= WEIXIN_AdminMessage::Query()->where('appid', weixin_get_appid());

	if($current_tab == 'event'){
		$field		= 'LOWER(Event)';

		$message_query->where('MsgType', 'event');
		// $where_base	= "MsgType = 'event' AND ";
	}elseif ($current_tab == 'text') {
		$field		= 'LOWER(Response)';

		$message_query->where('MsgType', 'text');

		// $where_base	= "MsgType = 'text' AND ";
		if(!empty($_GET['s'])){
			$message_query->where('Content', trim($_GET['s']));
			// $where_base	.= "Content = '".trim($_GET['s'])."' AND ";
		}
	}elseif($current_tab == 'menu'){
		$weixin_menu	= get_option('weixin_'.weixin_get_appid().'_menus', []);
		$menu			= $weixin_menu && $weixin_menu['menu'] ? $weixin_menu['menu'] : [];

		if(!$menu ){
			return;
		}

		$message_types	= [];
		
		if($buttons = $menu['button']){
			foreach($buttons as $button){
				if(empty($button['sub_button'])){
					if($button['type']	== 'view'){
						$message_types[$button['url']]	= $button['name'];
					}elseif(isset($button['key'])){
						$message_types[$button['key']]	= $button['name'];	
					}
				}else{
					foreach ($button['sub_button'] as $sub_button) {
						if($sub_button['type']	== 'view'){
							$message_types[$sub_button['url']]	= $sub_button['name'];
						}elseif($sub_button['type']	== 'miniprogram'){
							// 
						}else{
							$message_types[$sub_button['key']]	= $sub_button['name'];	
						}
					}
				}
			}
		}

		$field		= 'EventKey';

		$message_query->where('MsgType', 'event')->where_in('Event',['CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin'])->where_not('EventKey', '');

		// $where_base	= "MsgType = 'event' AND Event in('CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin') AND EventKey !='' AND ";
	}elseif($current_tab == 'subscribe'){
		$field		= 'LOWER(EventKey)';
		$message_query->where('MsgType', 'event')->where_in('Event',['subscribe','unsubscribe']);

		// $where_base	= "MsgType = 'event' AND (Event = 'subscribe' OR Event = 'unsubscribe') AND ";
	}elseif($current_tab == 'wifi-shop'){
		$field		= 'LOWER(EventKey)';

		$message_query->where('MsgType', 'event')->where('Event', 'WifiConnected')->where_not('EventKey', '')->where_not('EventKey', '');

		// $where_base	= "MsgType = 'event' AND Event = 'WifiConnected' AND EventKey!='' AND EventKey!='0' AND ";
	}elseif($current_tab == 'card-event'){
		$title		= '卡券事件统计分析';
		$field		= 'LOWER(Event)';

		$message_query->where('MsgType', 'event')->where_in('Event', ['card_not_pass_check', 'card_pass_check', 'user_get_card', 'user_del_card', 'user_view_card', 'user_enter_session_from_card', 'user_consume_card']);

		// $where_base	= "MsgType = 'event' AND Event in('card_not_pass_check', 'card_pass_check', 'user_get_card', 'user_del_card', 'user_view_card', 'user_enter_session_from_card', 'user_consume_card') AND ";
	}else{
		$field		= 'LOWER(MsgType)';

		$message_query->where_not('MsgType', 'manual');
		// $where_base	= "MsgType !='manual' AND ";
	}

	$message_type 	=  isset($_GET['type'])?$_GET['type']:'';

	if($message_type){
		$message_query->where($field, $message_type);
	}

	if($current_tab == 'menu'){
		echo '<p>下面的名称，如果是默认菜单的按钮，则显示名称，如果是个性化菜单独有的按钮，则显示key。</p>';
	}

	wpjam_stats_header(array('show_date_type'=>true));

	extract($wpjam_stats_labels);

	$wheres	= $message_query->where_gt('CreateTime', $wpjam_start_timestamp)->where_lt('CreateTime', $wpjam_end_timestamp)->get_wheres();

	$counts = WEIXIN_AdminMessage::Query()->where_fragment($wheres)->group_by($field)->order_by('count')->get_results("count(id) as count, {$field} as label");
	$labels	= wp_array_slice_assoc($message_types, array_column($counts, 'label'));
	$total 	= WEIXIN_AdminMessage::Query()->where_fragment($wheres)->get_var('count(*)');

	if(empty($_GET['s'])){
		// wpjam_donut_chart($counts, array('total'=>$total, 'labels'=>$new_message_types, 'show_link'=>true,'chart_width'=>280));
		wpjam_donut_chart($counts, ['total'=>$total, 'labels'=>$labels, 'show_link'=>true,'chart_width'=>280]);
	}

	?>

	<div class="clear"></div>

	<?php

	if($message_type){
		$counts_array	= WEIXIN_AdminMessage::Query()->where_fragment($wheres)->group_by('day')->order_by('day')->get_results("FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as `{$message_type}`");

		$counts_array	= array_combine(array_column($counts_array, 'day'), $counts_array);

		$message_type_label = $message_types[$message_type]??$message_type;

		wpjam_line_chart($counts_array, [$message_type=>$message_type_label]);
	}else{
		if(empty($_GET['s'])){
			$sum = array();
			foreach (array_keys($message_types) as $message_type){
				$sum[] = "SUM(case when {$field}='{$message_type}' then 1 else 0 end) as `{$message_type}`";
			}
			$sum = implode(', ', $sum);
		
			$counts_array	= WEIXIN_AdminMessage::Query()->where_fragment($wheres)->group_by('day')->order_by('day')->get_results("FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total, {$sum}");

			$counts_array	= array_combine(array_column($counts_array, 'day'), $counts_array);

			$labels = ['total'=>'所有#']+$labels;
			wpjam_line_chart($counts_array, $labels);
		}else{
			$counts_array	= WEIXIN_AdminMessage::Query()->where_fragment($wheres)->group_by('day')->order_by('day')->get_results("FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total");

			$counts_array	= array_combine(array_column($counts_array, 'day'), $counts_array);

			wpjam_line_chart($counts_array, array('total'=>$_GET['s']));
		}
		
	}
}

function weixin_message_summary_page(){

	global $wpdb, $current_admin_url, $wpjam_stats_labels;

	wpjam_stats_header();

	extract($wpjam_stats_labels);
	
	$response_types = WEIXIN_AdminMessage::get_response_types();
	
	$response_type = $_GET['type']??null;

	// $response_types_string = "'".implode("','", array_keys($response_types))."'";

	$wheres	= WEIXIN_AdminMessage::Query()->where('appid', weixin_get_appid())->where_gt('CreateTime', $wpjam_start_timestamp)->where_lt('CreateTime', $wpjam_end_timestamp)->where('MsgType', 'text')->where_not('Response', '')->get_wheres();

	$counts_array	= WEIXIN_AdminMessage::Query()->where_fragment($wheres)->group_by('Response')->order_by('count')->get_results("COUNT( * ) AS count, Response as label");

	// wpjam_print_r($counts_array);

	// $counts_array	= array_combine(array_column($counts_array, 'Response'), $counts_array);

	// $where = "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";
	// $sql = "SELECT COUNT( * ) AS count, Response FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND (MsgType ='text' OR (MsgType = 'event' AND Event!='subscribe' AND Event!='unsubscribe' AND EventKey != '')) GROUP BY Response ORDER BY count DESC";
	//$sql = "SELECT COUNT( * ) AS count, Response FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND MsgType ='text' GROUP BY Response ORDER BY count DESC";
	// $sql = "SELECT COUNT( * ) AS count, Response FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' GROUP BY Response ORDER BY count DESC";

	// $counts = $wpdb->get_results($sql);

	// $new_counts = array();
	// foreach ($counts as $count) {
	// 	if(isset($response_types[$count->Response])){
	// 		$new_counts[] = array(
	// 			'label'	=>isset($response_types[$count->Response])?$response_types[$count->Response]:$count->Response,
	// 			'count'	=>$count->count
	// 		);
	// 		$new_response_types[$count->Response] = isset($response_types[$count->Response])?$response_types[$count->Response]:$count->Response;
	// 	}
	// }
	
	// $total = $wpdb->get_var("SELECT COUNT( id ) FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND (MsgType ='text' OR (MsgType = 'event' AND Event!='subscribe' AND Event!='unsubscribe' AND EventKey != ''))");
	// $total = $wpdb->get_var("SELECT COUNT( id ) FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND MsgType ='text' ");
	// $total = $wpdb->get_var("SELECT COUNT( id ) FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' ");

	// wpjam_donut_chart($new_counts, array('total'=>$total, 'show_link'=>true, 'chart_width'=> '280'));
	wpjam_donut_chart($counts_array, array('labels'=>$response_types, 'show_link'=>true, 'chart_width'=> '280'));
	?>

	<div style="clear:both;"></div>

	<?php

	// $filpped_response_types = array_flip($response_types);

	//$sql = "SELECT COUNT( * ) AS count, Response, MsgType, Content FROM ( SELECT Response, MsgType, LOWER(Content) as Content FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' AND Content !='' UNION ALL SELECT Response, MsgType,  LOWER(EventKey) as Content FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType = 'event'  AND Event!='subscribe' AND Event!='unsubscribe' AND EventKey !='' ) as T1 GROUP BY Content ORDER BY count DESC LIMIT 0 , 100";
	// $sql = "SELECT COUNT( * ) AS count, Response, MsgType, LOWER(Content) as Content FROM ( SELECT * FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' AND Content !='' ORDER BY CreateTime DESC) abc GROUP BY Content ORDER BY count DESC LIMIT 0, 100";
	
	// $weixin_hot_messages = $wpdb->get_results($sql);

	$weixin_hot_messages	= WEIXIN_AdminMessage::Query()->where_fragment($wheres)->where('Response', $response_type)->where_not('Content', '')->group_by('Content')->order_by('count')->limit(100)->get_results("COUNT( * ) AS count, Response, MsgType, LOWER(Content) as Content");

	if($weixin_hot_messages){
	?>
	<table class="widefat striped" cellspacing="0">
	<thead>
		<tr>
			<th style="width:42px">排名</th>
			<th style="width:42px">数量</th>
			<th>关键词</th>
			<th style="width:91px">回复类型</th>
		</tr>
	</thead>
	<tbody>
	<?php
	$i = 0;
	foreach ($weixin_hot_messages as $weixin_message) { $i++; ?>
		<tr>
			<td><?php echo $i; ?></td>
			<td><?php echo $weixin_message['count']; ?></td>
			<td><?php echo wp_strip_all_tags($weixin_message['Content']); ?></td>
			<td><?php echo $response_types[$weixin_message['Response']]??$weixin_message['Response']; ?></td>
		</tr>
	<?php } ?>
	</tbody>
	</table>
	<?php
	}
}
