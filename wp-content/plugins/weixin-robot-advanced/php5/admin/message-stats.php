<?php
function weixin_robot_messages_stats_tabs($tabs){
	return array(
		'stats'		=> array('title'=>'消息预览',	'function'=>'weixin_robot_message_overview_page'),
		'message'	=> array('title'=>'消息统计',	'function'=>'weixin_robot_message_stats_page'),
		'event'		=> array('title'=>'事件统计',	'function'=>'weixin_robot_message_stats_page'),
		'text'		=> array('title'=>'文本统计',	'function'=>'weixin_robot_message_stats_page'),
		'summary'	=> array('title'=>'文本汇总',	'function'=>'weixin_robot_message_summary_page')
	);
}

function weixin_robot_message_overview_page(){

	global $wpdb, $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	echo '<h2>消息统计分析预览</h2>';

	wpjam_stats_header(array('show_date_type'=>true,'show_compare'=>true));

	$counts_array	= $wpdb->get_results("SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as cnt, count(DISTINCT FromUserName) as user, (COUNT(id)/COUNT(DISTINCT FromUserName)) as avg FROM {$wpdb->weixin_messages} WHERE CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp} GROUP BY day ORDER BY day DESC;", OBJECT_K);

	wpjam_line_chart($counts_array, array(
		'cnt'	=>'消息发送次数', 
		'user'	=>'消息发送人数', 
		'avg'	=>'人均发送次数#'
	));
}

function weixin_robot_message_stats_page() {
	global $wpdb, $current_admin_url, $current_tab, $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	$message_types	= weixin_robot_get_message_types($current_tab);

	if($current_tab == 'event'){
		$title		= '事件消息统计分析';
		$field		= 'LOWER(Event)';
		$where_base	= "MsgType = 'event' AND ";
	}elseif ($current_tab == 'text') {
		$title		= '文本消息统计分析';
		$field		= 'LOWER(Response)';
		$where_base	= "MsgType = 'text' AND ";
		if(!empty($_GET['s'])){
			$where_base	.= "Content = '".trim($_GET['s'])."' AND ";
		}
	}elseif($current_tab == 'menu'){
		$weixin_menu = weixin_robot_get_local_menu();
		if(!$weixin_menu) return;

		$title		= '菜单点击统计分析';
		$field		= 'EventKey';
		$where_base	= "MsgType = 'event' AND Event in('CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin') AND EventKey !='' AND ";
	}elseif($current_tab == 'subscribe'){
		$title		= '订阅统计分析';
		$field		= 'LOWER(EventKey)';
		$where_base	= "MsgType = 'event' AND (Event = 'subscribe' OR Event = 'unsubscribe') AND ";
	}elseif($current_tab == 'wifi-shop'){
		$title		= 'Wi-Fi连接门店统计分析';
		$field		= 'LOWER(EventKey)';
		$where_base	= "MsgType = 'event' AND Event = 'WifiConnected' AND EventKey!='' AND EventKey!='0' AND ";
	}elseif($current_tab == 'card-event'){
		$title		= '卡券事件统计分析';
		$field		= 'LOWER(Event)';
		$where_base	= "MsgType = 'event' AND Event in('card_not_pass_check', 'card_pass_check', 'user_get_card', 'user_del_card', 'user_view_card', 'user_enter_session_from_card', 'user_consume_card') AND ";
	}else{
		$title		= '消息统计分析';
		$field		= 'LOWER(MsgType)';
		$where_base	= "MsgType !='manual' AND ";
	}

	$message_type 	=  isset($_GET['type'])?$_GET['type']:'';

	if($message_type){
		$where_base .= " {$field} ='{$message_type}' AND ";
	}

	if($message_type && $wpjam_compare){
		$title = $message_types[$message_type].'消息对比';
	}

	echo '<h2>'.$title.'</h2>';
	if($current_tab == 'menu'){
		echo '<p>下面的名称，如果是默认菜单的按钮，则显示名称，如果是个性化菜单独有的按钮，则显示key。</p>';
	}

	wpjam_stats_header(array('show_date_type'=>true,'show_compare'=>true));

	$where 	= $where_base . "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";

	if($message_type && $wpjam_compare){

		$where_2	= $where_base . "CreateTime > {$wpjam_start_timestamp_2} AND CreateTime < {$wpjam_end_timestamp_2}";
		
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
		$sql = "SELECT count(id) as count, {$field} as label FROM {$wpdb->weixin_messages} WHERE  {$where} GROUP BY {$field} ORDER BY count DESC;";

		$counts = $wpdb->get_results($sql);
		$total 	= $wpdb->get_var("SELECT count(*) FROM {$wpdb->weixin_messages} WHERE {$where}");

		$new_counts = $new_message_types = array();
		foreach ($counts as $count) {
			// if(( ($current_tab == 'text' || $current_tab == 'custom-menu') && isset($message_types[$count->label])) || ($current_tab != 'text' && $current_tab != 'custom-menu')){
				$new_message_types[$count->label] = isset($message_types[$count->label])?$message_types[$count->label]:$count->label;
			// }
		}

		if(empty($_GET['s'])){
			wpjam_donut_chart($counts, array('total'=>$total, 'labels'=>$new_message_types, 'show_link'=>true,'chart_width'=>280));
		}
	}
	?>

	<div class="clear"></div>

	<?php

	if($message_type){
		if($wpjam_compare){
			$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as data FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY day ORDER BY day DESC;";

			$time_diff = strtotime($wpjam_start_date) - strtotime($wpjam_start_date_2);

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

			krsort($new_counts_array);

			$labels = array(
				'data'	=> $compare_label,
				'data2'	=> $compare_label_2
			);

			wpjam_line_chart($new_counts_array, $labels);

		}else{
			$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as `{$message_type}` FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY day ORDER BY day DESC;";
			$counts_array = $wpdb->get_results($sql,OBJECT_K);
			$message_type_label = isset($message_types[$message_type])?$message_types[$message_type]:$message_type;
			wpjam_line_chart($counts_array, array($message_type=>$message_type_label));
		}
	}else{
		if(empty($_GET['s'])){
			$sum = array();
			foreach (array_keys($message_types) as $message_type){
				$sum[] = "SUM(case when {$field}='{$message_type}' then 1 else 0 end) as `{$message_type}`";
			}
			$sum = implode(', ', $sum);
			$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total, {$sum} FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY day ORDER BY day DESC;";

			$counts_array = $wpdb->get_results($sql,OBJECT_K);
			$new_message_types = array('total'=>'所有#')+$new_message_types;
			wpjam_line_chart($counts_array, $new_message_types);
		}else{
			$sql = "SELECT FROM_UNIXTIME(CreateTime, '{$wpjam_date_format}') as day, count(id) as total FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY day ORDER BY day DESC;";
			$counts_array = $wpdb->get_results($sql,OBJECT_K);
			wpjam_line_chart($counts_array, array('total'=>$_GET['s']));
		}
		
	}
}

// 文本汇总
function weixin_robot_message_summary_page(){

	global $wpdb, $current_admin_url, $wpjam_stats_labels;
	extract($wpjam_stats_labels);

	echo '<h2>文本回复类型统计分析</h2>';

	wpjam_stats_header();
	
	$response_types = weixin_robot_get_response_types();
	
	$response_type = isset($_GET['type'])?$_GET['type']:'';

	// $response_types_string = "'".implode("','", array_keys($response_types))."'";

	$where = "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";
	// $sql = "SELECT COUNT( * ) AS count, Response FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND (MsgType ='text' OR (MsgType = 'event' AND Event!='subscribe' AND Event!='unsubscribe' AND EventKey != '')) GROUP BY Response ORDER BY count DESC";
	//$sql = "SELECT COUNT( * ) AS count, Response FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND MsgType ='text' GROUP BY Response ORDER BY count DESC";
	$sql = "SELECT COUNT( * ) AS count, Response FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' GROUP BY Response ORDER BY count DESC";

	$counts = $wpdb->get_results($sql);

	$new_counts = array();
	foreach ($counts as $count) {
		if(isset($response_types[$count->Response])){
			$new_counts[] = array(
				'label'	=>isset($response_types[$count->Response])?$response_types[$count->Response]:$count->Response,
				'count'	=>$count->count
			);
			$new_response_types[$count->Response] = isset($response_types[$count->Response])?$response_types[$count->Response]:$count->Response;
		}
	}
	
	// $total = $wpdb->get_var("SELECT COUNT( id ) FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND (MsgType ='text' OR (MsgType = 'event' AND Event!='subscribe' AND Event!='unsubscribe' AND EventKey != ''))");
	// $total = $wpdb->get_var("SELECT COUNT( id ) FROM {$wpdb->weixin_messages} WHERE {$where} AND Response in ({$response_types_string}) AND MsgType ='text' ");
	$total = $wpdb->get_var("SELECT COUNT( id ) FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' ");

	wpjam_donut_chart($new_counts, array('total'=>$total, 'show_link'=>true, 'chart_width'=> '280'));
	?>

	<div style="clear:both;"></div>

	<?php

	$filpped_response_types = array_flip($response_types);
	if($response_type){
		echo '<h2 id="detail">'.$response_type.'热门关键字</h2>';
		$where .= " AND Response = '{$filpped_response_types[$response_type]}'";
	}else{
		echo '<h2 id="detail">热门关键字</h2>';
		// $where .= " AND Response in ({$response_types_string})";
	}

	//$sql = "SELECT COUNT( * ) AS count, Response, MsgType, Content FROM ( SELECT Response, MsgType, LOWER(Content) as Content FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' AND Content !='' UNION ALL SELECT Response, MsgType,  LOWER(EventKey) as Content FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType = 'event'  AND Event!='subscribe' AND Event!='unsubscribe' AND EventKey !='' ) as T1 GROUP BY Content ORDER BY count DESC LIMIT 0 , 100";
	$sql = "SELECT COUNT( * ) AS count, Response, MsgType, LOWER(Content) as Content FROM ( SELECT * FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' AND Content !='' ORDER BY CreateTime DESC) abc GROUP BY Content ORDER BY count DESC LIMIT 0, 100";
	
	$weixin_hot_messages = $wpdb->get_results($sql);
	if($weixin_hot_messages){
	?>
	<table class="widefat" cellspacing="0">
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
	foreach ($weixin_hot_messages as $weixin_message) {
		// if(isset($response_types[$weixin_message->Response])){
		$alternate = empty($alternate)?'alternate':'';
		$i++;
	?>
		<tr class="<?php echo $alternate; ?>">
			<td><?php echo $i; ?></td>
			<td><?php echo $weixin_message->count; ?></td>
			<td><?php echo $weixin_message->Content; ?></td>
			<td><?php echo isset($response_types[$weixin_message->Response])?$response_types[$weixin_message->Response]:''; ?></td>
		</tr>
		<?php // } ?>
	<?php } ?>
	</tbody>
	</table>
	<?php
	}
}

function weixin_robot_get_message_counts($start_timestamp, $end_timestamp){
	global $wpdb;
	$where 	= "CreateTime > {$start_timestamp} AND CreateTime < {$end_timestamp}";

	$sql	= "SELECT count(id) as total FROM {$wpdb->weixin_messages} WHERE {$where};";
	$total	= $wpdb->get_var($sql);

	$sql	= "SELECT count(DISTINCT FromUserName) as people FROM {$wpdb->weixin_messages} WHERE {$where}";
	
	$people	= $wpdb->get_var($sql);
	
	$avg	= ($people)?round($total/$people,4):0;

	return compact('total', 'people', 'avg');
}


