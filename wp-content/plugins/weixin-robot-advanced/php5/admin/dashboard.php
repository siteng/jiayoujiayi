<?php

function weixin_robot_stats_dashboard_widgets($wpjam_dashboard_wdigets){
	$end 	= current_time('timestamp',true);
	$start	= $end - (DAY_IN_SECONDS);

	$args 	= array ('start' => $start, 'end'=>$end);

	$wpjam_dashboard_wdigets['weixin-robot-overview']	= array('title'=>'数据预览',		'args'=>$args);
	$wpjam_dashboard_wdigets['weixin-robot-keyword']	= array('title'=>'24小时内热门关键字',	'args'=>$args);
	// if(WEIXIN_TYPE >=3){
	// 	$wpjam_dashboard_wdigets['weixin-robot-user']		= array('title'=>'活跃用户',			'args'=>$args);	
	// }
	// if(function_exists('weixin_robot_insert_pageview')){
	// 	$wpjam_dashboard_wdigets['weixin-robot-pageviews']	= array('title'=>'24小时内热门浏览网页','args'=>$args);
	// }

	return $wpjam_dashboard_wdigets;
}

function weixin_robot_get_expected_count($today_count, $yesterday_count, $yesterday_compare_count='', $asc=true){

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

function weixin_robot_overview_dashboard_widget_callback($dashboard, $meta_box){

	global $wpdb,  $wpjam_stats_labels;

	$today						= date('Y-m-d',current_time('timestamp'));
	$today_start_timestamp		= strtotime(get_gmt_from_date($today.' 00:00:00'));
	$today_end_timestamp		= current_time('timestamp',true);

	$yesterday					= date('Y-m-d',current_time('timestamp')-DAY_IN_SECONDS);
	$yesterday_start_timestamp	= strtotime(get_gmt_from_date($yesterday.' 00:00:00'));
	$yesterday_end_timestamp	= strtotime(get_gmt_from_date($yesterday.' 23:59:59'));

	$yesterday_end_timestamp_c	= current_time('timestamp',true)-DAY_IN_SECONDS;

	$today_counts 				= weixin_robot_get_user_subscribe_counts($today_start_timestamp, $today_end_timestamp);
	$yesterday_counts 			= weixin_robot_get_user_subscribe_counts($yesterday_start_timestamp, $yesterday_end_timestamp);
	$yesterday_compare_counts	= weixin_robot_get_user_subscribe_counts($yesterday_start_timestamp, $yesterday_end_timestamp_c);
	
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
				<td><?php echo $expected_subscribe = weixin_robot_get_expected_count($today_counts['subscribe'], $yesterday_counts['subscribe'], $yesterday_compare_counts['subscribe']); ?></td>
				<td><?php echo $expected_unsubscribe = weixin_robot_get_expected_count($today_counts['unsubscribe'], $yesterday_counts['unsubscribe'], $yesterday_compare_counts['unsubscribe'], false); ?></td>
				<td><?php echo weixin_robot_get_expected_count($today_counts['percent'], $yesterday_counts['percent'],'',false); ?></td>
				<td><?php echo weixin_robot_get_expected_count($expected_subscribe - $expected_unsubscribe, $yesterday_counts['netuser']); ?></td>
			</tr>
		</tbody>
	</table>

	<p><a href="<?php echo admin_url('admin.php?page=weixin-robot-users-stats&tab=subscribe');?>">详细用户订阅数据...</a></p>
	<hr />
	<?php
	if(WEIXIN_TYPE >= 3) {

		$weixin_custom_menus = get_option('weixin-custom-menus');

		if($weixin_custom_menus){

			$menu_keys_labels	= array('total'=>'所有');
			$menu_keys			= array();

			foreach($weixin_custom_menus as $weixin_custom_menu){
				if( !empty($weixin_custom_menu['key']) ){
					$menu_keys_labels[$weixin_custom_menu['key']]	= $weixin_custom_menu['name'];
				}	
			}

			$today_counts 				= weixin_robot_get_menu_counts($today_start_timestamp, $today_end_timestamp);
			$yesterday_counts 			= weixin_robot_get_menu_counts($yesterday_start_timestamp, $yesterday_end_timestamp);
			$yesterday_compare_counts	= weixin_robot_get_menu_counts($yesterday_start_timestamp, $yesterday_end_timestamp_c);

			$i = 0;
			foreach ($today_counts as $key => $value) {
				$menu_keys[]	= $key;
				$i++;
				if($i > 3) break;
			}

			?>
			<h3>自定义菜单点击</h3>
			<table class="widefat" cellspacing="0">
				<thead>
					<tr>
						<th>时间</th>
						<?php foreach ($menu_keys as $key) { ?>
						<th><?php echo isset($menu_keys_labels[$key])?$menu_keys_labels[$key]:$key;?></th>
						<?php } ?>	
					</tr>
				</thead>
				<tbody>
					<tr class="alternate">
						<td>今日</td>
						<?php foreach ($menu_keys as $key) { ?>
						<td><?php echo $today_counts[$key];?></td>
						<?php } ?>
					</tr>
					<tr class="">
						<td>昨日</td>
						<?php foreach ($menu_keys as $key) { ?>
						<td><?php echo isset($yesterday_counts[$key])?$yesterday_counts[$key]:0;?></td>
						<?php } ?>
					</tr>
					<tr class="alternate" style="font-weight:bold;">
						<td>预计今日</td>
						<?php foreach ($menu_keys as $key) { ?>
						<td><?php 
						if(isset($yesterday_counts[$key]) && isset($yesterday_compare_counts[$key])) { 
							echo weixin_robot_get_expected_count($today_counts[$key], $yesterday_counts[$key], $yesterday_compare_counts[$key]);
						}else{ 
							echo $today_counts[$key]; 
						}
						?></td>
						<?php } ?>
					</tr>
				</tbody>
			</table>

			<p><a href="<?php echo admin_url('admin.php?page=weixin-robot-custom-menu-stats');?>">详细自定义菜单点击数据...</a></p>
		<?php } ?>
	<?php
	}

	$today_counts 				= weixin_robot_get_message_counts($today_start_timestamp, $today_end_timestamp);
	$yesterday_counts 			= weixin_robot_get_message_counts($yesterday_start_timestamp, $yesterday_end_timestamp);
	$yesterday_compare_counts	= weixin_robot_get_message_counts($yesterday_start_timestamp, $yesterday_end_timestamp_c);
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
				<td><?php echo weixin_robot_get_expected_count($today_counts['total'], $yesterday_counts['total'], $yesterday_compare_counts['total']); ?>
				<td><?php echo weixin_robot_get_expected_count($today_counts['people'], $yesterday_counts['people'], $yesterday_compare_counts['people']); ?>
				<td><?php echo weixin_robot_get_expected_count($today_counts['avg'], $yesterday_counts['avg']); ?>
			</tr>
		</tbody>
	</table>

	<p><a href="<?php echo admin_url('admin.php?page=weixin-robot-messages-stats&tab=stats');?>">详细消息统计...</a></p>
	<?php
	
}

function weixin_robot_pageviews_dashboard_widget_callback($dashboard, $meta_box){
	global $wpdb;

	$start	= $meta_box['args']['start'];
	$end	= $meta_box['args']['end'];

	$where = "url != '' AND time > {$start} AND time < {$end}";
	
	$sql = "SELECT COUNT( * ) AS total, url, post_id FROM {$wpdb->weixin_pageviews} WHERE {$where} AND type ='View' GROUP BY url ORDER BY total DESC LIMIT 0,10 ";

	$counts = $wpdb->get_results($sql);
	$i= 0;
	if($counts){ ?>
	<table class="widefat" cellspacing="0">
		<tbody>
		<?php foreach ($counts as $count) { $alternate = empty($alternate)?'alternate':''; $i++;?>
			<tr class="<?php echo $alternate;?>">
				<td style="width:18px;"><?php echo $i; ?></td>
				<td>
				<?php if(WEIXIN_TYPE >= 3){ ?>
					<?php if($count->post_id) { ?>
						<a href="<?php echo admin_url('admin.php?page=weixin-robot-pageviews&tab=list-view&url='.urlencode($count->url));?>"><?php echo get_post($count->post_id)->post_title; ?></a> | <a href="<?php echo $count->url; ?>" target="_blank">访问</a>
					<?php } else { ?>
						<a href="<?php echo admin_url('admin.php?page=weixin-robot-pageviews&tab=list-view&url='.urlencode($count->url));?>"><?php echo urldecode($count->url); ?></a> | <a href="<?php echo $count->url; ?>" target="_blank">访问</a>
					<?php } ?>
				<?php }else{ ?>
					<?php if($count->post_id) { ?>
						<?php echo get_post($count->post_id)->post_title; ?> | <a href="<?php echo $count->url; ?>" target="_blank">访问</a>
					<?php } else { ?>
						<?php echo urldecode($count->url); ?> | <a href="<?php echo $count->url; ?>" target="_blank">访问</a>
					<?php } ?>
				<?php } ?>
					
				</td>
				<td style="width:32px"><?php echo $count->total; ?></td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
	<p><a href="<?php echo admin_url('admin.php?page=weixin-robot-pageviews-stats&tab=hot-view');?>">更多热门浏览网页...</a></p>
	<?php
	}
}

function weixin_robot_user_dashboard_widget_callback($dashboard, $meta_box){
	global $wpdb;

	$start	= $meta_box['args']['start'];
	$end	= $meta_box['args']['end'];

	$where = " CreateTime > {$start} AND CreateTime < {$end}";

	$sql = "SELECT COUNT( * ) AS total, FromUserName FROM {$wpdb->weixin_messages} WHERE {$where} GROUP BY FromUserName ORDER BY total DESC LIMIT 0,15 ";

	$counts = $wpdb->get_results($sql);

	$i=0;

	if($counts){ ?>
	<style type="text/css">
	td img.weixin-avatar {
	vertical-align: top;
	margin-right: 10px;
	float: left;
	}
	</style>
	<table class="widefat" cellspacing="0">
		<tbody>
		<?php foreach ($counts as $count) { 
			$weixin_openid = $count->FromUserName; 
			$weixin_user = weixin_robot_get_user($weixin_openid);
			if($weixin_user && $weixin_user['subscribe']){ 
				$alternate = empty($alternate)?'alternate':'';
				$weixin_user = weixin_robot_get_user_detail($weixin_user);
				$i++; 

				if($i > 10 ) break;
		?>
		<tr class="<?php echo $alternate;?>">
			<td><?php echo $i; ?></td>
			<td><?php echo $weixin_user['username'];?></td>
			<td><?php echo $count->total;?></td>
		</tr>
		<?php } ?>
		<?php } ?>
		<tr class="<?php $alternate = empty($alternate)?'alternate':''; echo $alternate;?>"><td colspan="3"><a href="<?php echo admin_url('admin.php?page=weixin-robot-users&tab=messages');?>">详细列表...</a></td></tr>
		</tbody>
	</table>
	<?php
	}
}

function weixin_robot_keyword_dashboard_widget_callback($dashboard, $meta_box){

	global $wpdb;

	$start	= $meta_box['args']['start'];
	$end	= $meta_box['args']['end'];

	$where = " CreateTime > {$start} AND CreateTime < {$end}";
	
	$sql = "SELECT COUNT( * ) AS count, Response, MsgType, LOWER(Content) as Content FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' AND Content !='' GROUP BY Content ORDER BY count DESC LIMIT 0 , 10";

	// $sql = "SELECT COUNT( * ) AS count, Response, MsgType, LOWER(Content) as Content FROM ( SELECT * FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType ='text' AND Content !='' ORDER BY CreateTime DESC) abc GROUP BY Content ORDER BY count DESC LIMIT 0, 10";

	$weixin_hot_messages = $wpdb->get_results($sql);

	$response_types = weixin_robot_get_response_types();

	$i= 0;
	if($weixin_hot_messages){ ?>
	<table class="widefat" cellspacing="0">
		<tbody>
		<?php foreach ($weixin_hot_messages as $weixin_message) { $alternate = empty($alternate)?'alternate':''; $i++; ?>
			<tr class="<?php echo $alternate; ?>">
				<td style="width:18px;"><?php echo $i; ?></td>
				<td><?php echo $weixin_message->Content; ?></td>
				<td style="width:32px;"><?php echo $weixin_message->count; ?></td>
				<td style="width:98px;"><?php echo isset($response_types[$weixin_message->Response])?$response_types[$weixin_message->Response]:''; ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	<p><a href="<?php echo admin_url('admin.php?page=weixin-robot-messages-stats&tab=summary');?>">更多热门关键字...</a></p>
	<?php
	}
}

