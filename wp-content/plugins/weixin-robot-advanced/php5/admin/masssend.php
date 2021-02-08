<?php
function weixin_robot_masssend_tabs($tabs){
	if(!empty($_GET['openid'])){
		return array(
			'advanced'	=> '高级群发',
			'custom'	=> '客服群发',
			'template'	=> '模板消息',
		);
	}else{
		return array(
			'advanced'	=> '高级群发',
			'cron'		=> '定时群发作业',
			'template'	=> '模板消息测试',
		);
	}
}

function weixin_robot_masssend_advanced_page(){
	global $wpdb, $current_admin_url;
	
	$type		= isset($_GET['type'])?$_GET['type']:'all';
	$msgtype	= isset($_GET['msgtype'])?$_GET['msgtype']:'mpnews';
	$msgtype	= ($msgtype == 'news')?'mpnews':$msgtype;
	$content	= isset($_GET['content'])?$_GET['content']:'';

	$nonce_action	= 'weixin-masssend-advanced';

	if($type == 'ids'){	// 用户列表页面选择了一些用户来群发
		$ids	= isset($_GET['ids'])?$_GET['ids']:'';

		if(!$ids){
			wp_die('至少要选择一个用户来群发');
		}
	}else{

	}

	$form_fields = array();

	if($type != 'ids'){

		// $type_options 			= array('all'=>'全部', 'tag'=>'按照分组', 'tag'=>'按照标签', 'preview'=>'预览');
		$type_options 			= array('all'=>'全部', 'tag'=>'按照标签', 'preview'=>'预览');
		$form_fields['type']	= array('title'=>'群发对象', 'type'=>'radio', 'value'=>'', 'options'=>$type_options);
		$form_fields['preview']	= array('title'=>'预览微信号', 'type'=>'text');
		
		// $tag_lists				= $wpdb->get_col("SELECT name FROM {$wpdb->weixin_user_tags} WHERE count > 0 ORDER BY count DESC;");
		// $form_fields['tag']		= array('title'=>'输入标签',	'type'=>'text',		'value'=>'',	'options'=>$tag_lists,	'list'=>'tag_list','description'=>'如果要发送给未打标签用户，请留空！');	

		if($weixin_user_tags	= weixin_robot_get_tags()){
			$tag_options = array();
			foreach ($weixin_user_tags as $current_tagid => $weixin_user_tag) {
				if($current_tagid == 1){
					continue;
				}
				$tag_options[$current_tagid] = $weixin_user_tag['name'];
			}

			$form_fields['tag']	= array('title'=>'选择标签',	'type'=>'select',	'value'=>'',	'options'=>$tag_options);
		}
	}

	$msgtype_options	= array( 'mpnews'=>'图文', 'image'=>'图片', 'voice'=>'语音', 'text'=>'文本', 'wxcard'=>'卡券');

	$content_descriptions				= weixin_robot_get_reply_descriptions();
	$content_descriptions['articls']	= $content_descriptions['img'];
	$content_descriptions['mpnews']		= $content_descriptions['news'].'<br /><strong>如果公众号支持留言和原创功能，请到微信公众号后台群发，这里群发不支持留言和原创功能！</strong>';

	$form_fields['msgtype']	= array('title'=>'群发类型',	'type'=>'radio',	'value'=>$msgtype,	'options'=> $msgtype_options);
	$form_fields['content']	= array('title'=>'群发内容',	'type'=>'textarea',	'value'=>$content,	'class'=>'large-text code',	'description'=>$content_descriptions[$msgtype]);
	
	if($type != 'ids'){
		$form_fields['time']	= array('title'=>'发送时间',	'type'=>'datetime',	'value'=>date('Y-m-d H:i:s', current_time('timestamp')+3600),	'description'=>'如果要定时发送，请输入未来要发送的时间，留空立即发送！');	
	}

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'masssend_weixin');

		if($type != 'ids'){
			$type		= $data['type'];
			$tag		= $data['tag'];
			$time		= $data['time'];
			$towxname	= $data['preview'];

			$time		= strtotime($time.' +0800');
			
			if($time <= time()){
				$time	= '';
			}
		}

		$msgtype		= $data['msgtype']; 
		$content		= $data['content'];
		$send_content	= '';

		if($msgtype == 'text'){
			$send_content = addslashes_gpc($content);
		}elseif($msgtype == 'mpnews' || $msgtype == 'image' || $msgtype == 'voice'){
			$send_content = trim($content);
		}elseif($msgtype == 'wxcard'){
			$send_content = array('card_id'=>trim($content));
		}

		if($send_content){
			if($type == 'all'){
				if($time){
					wp_schedule_single_event($time,'weixin_send_future_mass_message',array('all', $msgtype, $send_content));
					wpjam_admin_add_error('定时群发作业已经被设置！<a href="'.admin_url('admin.php?page=weixin-robot-masssend&tab=cron').'">点击查看</a>');
				}else{
					$response = weixin_robot_sendall_mass_message('all', $msgtype, $send_content);
					if(is_wp_error($response)){
						wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');
					}else{
						wpjam_admin_add_error('群发成功');
						wpjam_admin_add_error('群发类型：全部');
					}
				}
			}elseif($type == 'tag'){
				if($time){
					wp_schedule_single_event($time,'weixin_send_future_mass_message',array($tag, $msgtype, $send_content));
					wpjam_admin_add_error('定时群发作业已经被设置！<a href="'.admin_url('admin.php?page=weixin-robot-masssend&tab=cron').'">点击查看</a>！');
				}else{
					$response = weixin_robot_sendall_mass_message($tag, $msgtype, $send_content);
					if(is_wp_error($response)){
						wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');
					}else{
						wpjam_admin_add_error('群发成功');
						wpjam_admin_add_error('群发类型：按分组群发');
						wpjam_admin_add_error('群发分组：'.$tag_options[$tag]);
					}
				}
			// }elseif($type == 'tag'){
			// 	$weixin_openids	= weixin_robot_get_tag_users($tag);
			// 	while(count($weixin_openids) > 10000){
			// 		$send_weixin_openids	= array_slice($weixin_openids, 0, 10000);
			// 		weixin_robot_send_mass_message($send_weixin_openids, $msgtype, $send_content);
			// 		$weixin_openids 	= array_slice($weixin_openids, 10000);
			// 	}
				
			// 	$response = weixin_robot_send_mass_message($weixin_openids, $msgtype, $send_content);
			// 	if(is_wp_error($response)){
			// 		wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');
			// 	}else{
			// 		wpjam_admin_add_error('群发成功');
			// 		wpjam_admin_add_error('群发类型：按标签群发');
			// 		wpjam_admin_add_error('群发标签：'.$tag);
			// 	}
			}elseif($type == 'ids'){
				$weixin_openids	= $ids;  
				$response = weixin_robot_send_mass_message($weixin_openids, $msgtype, $send_content);
				if(is_wp_error($response)){
					wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');
				}else{
					wpjam_admin_add_error('群发成功');
					wpjam_admin_add_error('群发类型：按照 IDs');
				}
			}elseif($type == 'preview'){
				$response = weixin_robot_preview_mass_message($towxname, $msgtype, $send_content);
				if(is_wp_error($response)){
					wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');
				}else{
					wpjam_admin_add_error('预览发送成功');
				}
			}
			wpjam_admin_add_error('发送类型：'.$msgtype_options[$msgtype]);
			wpjam_admin_add_error('群发内容：'.'<code>'.$content.'</code>');
		}
		foreach ($form_fields as $key => $form_field) {
			$form_fields[$key]['value']	= $data[$key];
		}
		$form_fields['content']['description']	= $content_descriptions[$msgtype];
	}
	
	$ids_str = '';
	if($type == 'ids'){
		echo '<h2>群发以下用户</h2>';

		echo '<p><strong>群发以下用户</strong>：</p><p>';
		foreach ($ids as $weixin_openid) {
			$ids_str		.= '&ids[]='.$weixin_openid;
			$weixin_user	= weixin_robot_get_user_detail(weixin_robot_get_user($weixin_openid));
			echo '<img src="'.$weixin_user['headimgurl'].'" alt="'.$weixin_user['nickname'].'" width="32" />';
		}
		echo '</p>';
		$form_url	= $current_admin_url.'&type=ids'.$ids_str;
	}else{
		echo '<h2>高级群发</h2>';
		$form_url	= $current_admin_url;
	}
	?>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, '群发消息'); ?>

	<script type="text/javascript">
	jQuery(function($){
		var content_descriptions	= $.parseJSON('<?php echo wpjam_json_encode($content_descriptions);?>');

		$('#tr_msgtype input[type=radio]').change(function(){
			var selected = $('#tr_msgtype input[type=radio]:checked').val();
			$('#tr_content p').html(content_descriptions[selected]);
		});
		<?php if($type == 'all'){ ?>
		$('#tr_tag').hide();
		$('#tr_preview').hide();
		<?php }elseif($type == 'tag'){ ?>
		$('#tr_preview').hide();
		$('#tr_tag').show();
		<?php }elseif($type == 'preview') { ?>
		$('#tr_tag').hide();
		$('#tr_preview').show();
		$('#tr_time').hide();
		<?php } ?>
		$('#tr_type input[type=radio]').change(function(){
			var selected = $('#tr_type input[type=radio]:checked').val();
			if(selected == 'all'){
				$('#tr_tag').hide();
				$('#tr_time').show();
				$('#tr_preview').hide();
			}else if(selected == 'tag'){
				$('#tr_tag').show();
				$('#tr_time').show();
				$('#tr_preview').hide();
			}else if(selected == 'preview'){
				$('#tr_tag').hide();
				$('#tr_time').hide();
				$('#tr_preview').show();
			}
		});
	});
	</script> 
	<?php
}

function weixin_robot_masssend_custom_page(){
	global $wpdb,  $current_admin_url;

	if(empty($_GET['openid'])){
		$weixin_openids	= weixin_robot_get_can_send_users();
		$capability		= 'masssend_weixin';
	}else{
		$weixin_openid = $_GET['openid'];
		if(!weixin_robot_can_send_user($weixin_openid)){
			wp_die('48小时没有互动过，无法发送消息！');
		}
		$capability		= 'edit_weixin';
	}

	$type	= 'text';

	$content	= '';

	$type_options 		= array('text'=>'文本', 'news'=>'素材图文', 'img'=>'文章图文','img2'=>'自定义图文',	'wxcard'=>'卡券');
	if(!weixin_robot_get_setting('weixin_search'))	unset($type_options['img']);

	$content_descriptions	= weixin_robot_get_reply_descriptions();

	$form_fields	= array(
		'type'		=> array('title'=>'类型',	'type'=>'radio',	'value'=>$type,		'options'=> $type_options ),
		'content'	=> array('title'=>'内容',	'type'=>'textarea',	'value'=>$content,	'rows'=>'8',	'class'=>'large-text code',	'description'=>$content_descriptions[$type])
	);

	if(weixin_robot_get_setting('weixin_dkf')){
		if($weixin_kf_list 	= weixin_robot_customservice_get_online_kf_list()){
			$weixin_kf_options	= array(''=>' ');
			foreach ($weixin_kf_list as $weixin_kf_account) {
				$weixin_kf_options[$weixin_kf_account['kf_account']] = $weixin_kf_account['kf_nick'];
			}
			$form_fields['kf_account'] = array('title'=>'以客服账号回复',	'type'=>'select',	'options'=>$weixin_kf_options);
		}
	}

	$nonce_action = 'weixin-masssend-custom';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data = wpjam_get_form_post($form_fields, $nonce_action, $capability);

		$type		= $data['type'];
		$content	= $data['content'];

		if($type == 'wxcard'){
			$raw_items 	= explode("\n", $content);
			$card_id	= isset($raw_items[0])?$raw_items[0]:'';
			$outer_id	= isset($raw_items[1])?$raw_items[1]:'';
			$code		= isset($raw_items[2])?$raw_items[2]:'';
			$openid		= isset($raw_items[3])?$raw_items[3]:'';

			$card_ext	= weixin_robot_generate_card_ext(compact('card_id','outer_id','code','openid'));

			$content	= compact('card_id','card_ext');
		}

		$kf_account	= isset($data['kf_account'])?$data['kf_account']:'';

		if(empty($_GET['openid'])){
			foreach ($weixin_openids as $weixin_openid) {
				if($content){	
					$response = weixin_robot_send_user($weixin_openid, $content, $type);
					if(is_wp_error($response)){
						wpjam_admin_add_error($weixin_openid.' '.$response->get_error_code().'：'. $response->get_error_message(), 'error');
					}
				}	
			}
		}else{
			if(isset($_GET['reply_id'])){
				$message_data = array(
					'MsgType'		=> 'manual',
					'FromUserName'	=> $weixin_openid,
					'CreateTime'	=> current_time('timestamp',true),
					'Content'		=> $content,
				);

				$wpdb->insert($wpdb->weixin_messages, $message_data); 
				$wpdb->update($wpdb->weixin_messages, array('Response'=>$wpdb->insert_id), array('id'=>trim($_GET['reply_id'])));
			}

			$response = weixin_robot_send_user($weixin_openid, $content, $type, $kf_account);

			if(is_wp_error($response)){
				wpjam_admin_add_error($weixin_openid.' '.$response->get_error_code().'：'. $response->get_error_message(), 'error');
			}

			if($kf_account){
				$response	= weixin_robot_customservice_create_kf_session($kf_account, $weixin_openid); 

				if(is_wp_error($response)){
					wpjam_admin_add_error($weixin_openid.' '.$response->get_error_code().'：'. $response->get_error_message(), 'error');
				}
			}
		}

		if(!is_wp_error($response)){
			wpjam_admin_add_error('发送成功');
		}

		foreach ($form_fields as $key => $form_field) {
			$form_fields[$key]['value']	= $data[$key];
		}
		$form_fields['content']['description']	= $content_descriptions[$type];
	}

	$form_url = $current_admin_url;

	if(empty($_GET['openid'])){
		echo '<h2>使用客服接口群发</h2>';
		echo '<p>使用客服接口进行群发可能会违反微信公众号规定而被封号，请注意使用！</p>';
	}else{
		echo '<h2>发送消息</h2>';
		$form_url	=$form_url.'&openid='.$_GET['openid'];
		if(!empty($_GET['reply_id'])){
			$form_url	=$form_url.'&reply_id='.$_GET['reply_id'];
		}

		$weixin_user	= weixin_robot_get_user_detail(weixin_robot_get_user($weixin_openid));
		echo '<p style="height:32px; line-height:32px;">'.
		'<img style="float:left; margin-right:10px;" src="'.$weixin_user['headimgurl'].'" alt="'.$weixin_user['nickname'].'" width="32" aligin="left" /> '.
		$weixin_user['nickname'].'（'.$weixin_user['sex'].'）'.
		'</p>';
	}
	?>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, '发送信息'); ?>

	<?php if(empty($_GET['openid'])){ ?><p>* 消息将群发到<?php echo count($weixin_openids); ?>用户</p><?php } ?>

	<script type="text/javascript">
	jQuery(function($){
		var content_descriptions	= $.parseJSON('<?php echo json_encode($content_descriptions);?>');

		$('#tr_type input[type=radio]').change(function(){
			var selected = $('#tr_type input[type=radio]:checked').val();
			$('#tr_content p').html(content_descriptions[selected]);
		});
	});
	</script> 
	<?php
}

function weixin_robot_masssend_template_page(){
	global $wpdb,  $current_admin_url;

	$content	= 'first
loeny提交了新的工单
#0A0A0A

keyword1
08250603
#FF0000

keyword2
XXX公司
#00FF00

keyword3
需重启服务器
#0000FF

remark
请重启10.10.10.10服务器。
#00FFFF';

	$form_fields 	= array(
		'touser'		=> array('title'=>'发送的用户',	'type'=>'text' ),
		'template_id'	=> array('title'=>'模板ID',		'type'=>'text' ),
		'url'			=> array('title'=>'链接',		'type'=>'url' ),
		'topcolor'		=> array('title'=>'顶部颜色',		'type'=>'color',	'value'=>'#FF0000'),
		'content'		=> array('title'=>'内容',		'type'=>'textarea', 'value'=>$content,	'rows'=>'12',	'class'=>'large-text code')
	);

	$nonce_action = 'weixin-masssend-template';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data = wpjam_get_form_post($form_fields, $nonce_action, 'masssend_weixin');

		$touser			= $data['touser'];
		$template_id	= $data['template_id'];
		$url			= $data['url'];
		$topcolor		= $data['topcolor'];
		$content		= $data['content'];

		$send_data = array();

		$items = explode("\n\n", str_replace("\r\n", "\n", $content));
		foreach ($items as $item ) {
			$lines = explode("\n", $item);
			$send_data[$lines[0]]['value']	= urlencode($lines[1]);
			$send_data[$lines[0]]['color']	= urlencode($lines[2]);
		}

		$response = weixin_robot_send_template_message($touser, $template_id, $send_data, $url, $topcolor);

		if(is_wp_error($response)){
			wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');
		}else{
			wpjam_admin_add_error('发送成功');
		}

		foreach ($form_fields as $key => $form_field) {
			$form_fields[$key]['value']	= $data[$key];
		}
	}
	?>

	<h2>模板接口测试</h2>

	<p>这个界面用于测试模板消息接口，模板消息接口真正实现需要通过程序实现。</p>

	<?php wpjam_form($form_fields, $current_admin_url, $nonce_action, '发送信息'); ?>

	<?php
}

function weixin_robot_masssend_page_load(){
	global $weixin_list_table, $current_tab;

	if($current_tab != 'cron') return;

	$columns		= array(
		'timestamp'		=> '群发时间',
		'tag'			=> '群发对象',
		'msgtype'		=> '群发类型',
		'content'		=> '群发内容',
	);

	$style = '
	th.column-tag{width:140px;}
	th.column-msgtype{width:84px;}
	th.column-timestamp{width:84px;}
	.tablenav{display:none;}
	';

	$weixin_list_table = wpjam_list_table( array(
		'plural'			=> 'crons',
		'singular' 			=> 'cron',
		'columns'			=> $columns,
		'actions_column'	=> 'timestamp',
		'style'				=> $style
	) );
}

function weixin_robot_masssend_cron_page(){
	global $weixin_list_table, $current_admin_url;
	$wp_crons = _get_cron_array();

	$action = $weixin_list_table->current_action();

	if($action == 'delete'){
		if( !current_user_can( 'manage_options' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

	    if(!empty($_GET['sig'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['sig']);

			if(isset($wp_crons[$_GET['timestamp']][$_GET['hook']][$_GET['sig']])){
				$data = $wp_crons[$_GET['timestamp']][$_GET['hook']][$_GET['sig']];

				$args = $data['args'];
				wp_unschedule_event( $_GET['timestamp'], $_GET['hook'], $args );
			}

			wpjam_admin_add_error('删除成功');
		}

		$wp_crons = _get_cron_array();
	}

	$msgtype_options	= array( 'mpnews'=>'图文',	 'image'=>'图片', 'voice'=>'语音', 'text'=>'文本' );
	$weixin_user_tags	= weixin_robot_get_tags();

	?>
	
	<h2>定时群发作业</h2>
	<link rel="stylesheet" type="text/css" href="<?php echo WEIXIN_ROBOT_PLUGIN_URL.'/template/static/news-items.css'?>">

	<?php

	$new_crons	= array();

	foreach ($wp_crons as $timestamp => $wp_cron) {
		foreach ($wp_cron as $hook => $dings) {
			if($hook != 'weixin_send_future_mass_message') continue;
			foreach( $dings as $sig=>$data ) {
				$msgtype	= $msgtype_options[$data['args'][1]];
				$tag		= ($data['args'][0] == 'all')?'所有用户':'标签：'.$weixin_user_tags[$data['args'][0]]['name'];
				$content	= $data['args'][2];

				if($data['args'][1] == 'mpnews'){
					$material	= weixin_robot_get_material($data['args'][2], 'news');
					if(is_wp_error($material)){
						$content = $material->get_error_code().' '.$material->get_error_message();
					}else{
						$content	= '';
						$i 			= 1;
						$count		= count($material);

						foreach ($material as $news_item) {

							$item_div_class	= ($i == 1)? 'big':'small'; 
							$item_a_class	= ($i == $count)?'noborder':''; 
							$item_excerpt	= ($count == 1)?'<p>'.$news_item['digest'].'</p>':'';

							$thumb	= weixin_robot_get_material($news_item['thumb_media_id'], 'thumb');
							$thumb	= is_wp_error($thumb)?'':$thumb;

							$content   .= '
							<a class="'.$item_a_class.'" target="_blank" href="'.$news_item['url'] .'">
							<div class="img_container '.$item_div_class.'" style="background-image:url('.$thumb.');">
								<h3>'.$news_item['title'].'</h3>
							</div>
							'.$item_excerpt.'
							</a>';
							
							$i++;
						}
						$content 	= '<div class="reply_item">'.$content.'</div>';
					}
				}

				$new_cron = array(
					'timestamp'		=> get_date_from_gmt( date('Y-m-d H:i:s', $timestamp) ),
					'tag'			=> $tag,
					'msgtype'		=> $msgtype,
					'content'		=> $content,
					'row_actions'	=> array(
						'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&timestamp='.$timestamp.'&hook='.$hook.'&sig='.$sig, 'delete-'.$weixin_list_table->get_singular().'-'.$sig)).'">删除</a>',
						//'do'		=> '立即执行'
					)
				);

				$new_crons[]	= $new_cron;
			}
		}
	}

	if($new_crons){
		$weixin_list_table->prepare_items($new_crons, count($new_crons));
		$weixin_list_table->display(array('search'=>false));
	}else{
		echo '<p>暂无定时群发作业！</p>';
	}
}
