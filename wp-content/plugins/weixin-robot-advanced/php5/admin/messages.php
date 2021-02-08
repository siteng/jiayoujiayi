<?php
add_action('weixin-robot-user_page_load', 'weixin_robot_messages_page_load');
function weixin_robot_messages_page_load(){
	global $weixin_list_table, $plugin_page, $current_tab;

	if($plugin_page == 'weixin-robot-messages' || ( $plugin_page == 'weixin-robot-user' && $current_tab == 'messages')){
		$columns	= array(
			'cb'	=> 'checkbox'
		);

		if(empty($_GET['openid'])) {
			$columns['username']	= '用户';
			$columns['address']	= '地址（微信）';
		}

		$columns['MsgType']		= '类型';
		$columns['Content']		= '内容';
		$columns['Response']	= '回复类型';
		$columns['CreateTime']	= '时间';

		if(isset($_GET['openid'])) {
			$columns['operation']	= '操作';
		}

		$per_page	= array(
			'label'		=> '项',
			'default'	=> 100, 
			'option'	=> 'weixin_messages_per_page' 
		);

		$style = '
		th.column-MsgType{width:60px;}
		th.column-Response{width:80px;}
		th.column-CreateTime{width:74px;}
		th.column-username{width:140px;}
		th.column-address{width:90px;}
		/*td.column-username{white-space:pre; word-spacing:normal;}*
		';

		$weixin_list_table = wpjam_list_table( array(
			'plural'			=> 'weixin-messages',
			'singular' 			=> 'weixin-message',
			'columns'			=> $columns,
			'actions_column'	=> 'username',
			'item_callback'		=> 'weixin_robot_message_item',
			'per_page'			=> $per_page,
			'views'				=> 'weixin_robot_messages_views',
			'bulk_actions'		=> array('delete' => '删除'),
			'style'				=> $style,
		) );
	}
}

function weixin_robot_messages_page() {
	global $wpdb, $weixin_list_table;

	$action = $weixin_list_table->current_action();
	$type	= isset($_REQUEST['type'])?$_REQUEST['type']:'';

	if($action == 'delete'){

		if( !current_user_can( 'manage_options' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

		if(!empty($_GET['id'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['id']);

			if($wpdb->delete($wpdb->weixin_messages, array('id' => $_GET['id']))){
				wpjam_admin_add_error('删除成功');
			}else{
				wpjam_admin_add_error('删除失败：'.$wpdb->last_error,'error');
			}
		}elseif (isset($_GET['ids'])) {

			check_admin_referer('bulk-'.$weixin_list_table->get_plural());

			$error = false;
			foreach ($_GET['ids'] as $id) {
				if(!$wpdb->delete($wpdb->weixin_messages, array('id' => $id))){
					$error = true;
					wpjam_admin_add_error($id.'删除失败：'.$wpdb->last_error,'error');
				}
			}
			if($error == false){
				wpjam_admin_add_error('删除成功');
			}
		}
	}

	if(!empty($_GET['openid'])){
		echo '<h2>消息记录</h2>';
	}else{
		echo '<h1>消息管理</h1>';
	}

	$Response =  isset($_REQUEST['Response'])?$_REQUEST['Response']:'';

	$where = '';

	if(!empty($_GET['openid'])){
		$where .= " AND FromUserName = '{$_GET['openid']}'";	
	}

	if(isset($_GET['s'])){
		$where .= " AND Content like '%{$_GET['s']}%'";	
	}

	$limit				= $weixin_list_table->get_limit();

	if($type){
		if($type == 'manual'){
			$where	.= " AND ( Response ='not-found' OR Response ='too-long' ) AND Event != 'MASSSENDJOBFINISH'";
			$weixin_messages	= $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_messages} WHERE 1=1 {$where} AND CreateTime > ".WEIXIN_CUSTOM_SEND_LIMIT." ORDER BY CreateTime DESC LIMIT {$limit} ", ARRAY_A);
		}else{
			$where .= " AND MsgType = '{$type}'";
			if($type == 'event'){
				$where .= " AND Event != 'MASSSENDJOBFINISH'";//去掉群发
			}
			$weixin_messages	= $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_messages} WHERE 1=1 {$where} ORDER BY CreateTime DESC LIMIT {$limit} ", ARRAY_A);
		}
	}else{
		$weixin_messages	= $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_messages} WHERE 1=1 {$where} AND MsgType != 'manual' ORDER BY CreateTime DESC LIMIT {$limit} ", ARRAY_A);
	}

	$total				= $wpdb->get_var("SELECT FOUND_ROWS();");

	$weixin_list_table->prepare_items($weixin_messages, $total);
	$weixin_list_table->display();	
}

function weixin_robot_message_item($item){
	global $current_admin_url, $weixin_list_table;

	$response_types = weixin_robot_get_response_types();
	$msg_types		= weixin_robot_get_message_types();

	$msg_types['manual'] = '需要人工回复';

	$MsgType 		= $item['MsgType']; 
	$item['MsgType']= ($MsgType)?$msg_types[$MsgType]:'';
	$Response		= $item['Response'];
	$weixin_openid	= $item['FromUserName'];
	$weixin_user	= weixin_robot_get_user($weixin_openid);
	$weixin_user	= weixin_robot_get_user_detail($weixin_user, array('tab'=>'messages')); 

	if(empty($_GET['openid'])) {
		if($weixin_user){
			$item['username']	= $weixin_user['username'].'（'.$weixin_user['sex'].'）';
			$item['address']	= $weixin_user['address'];
		}else{
			$item['username']	= '';
			$item['address']	= '';
		}
	}

	$item['name']	= $item['FromUserName'];

	if($MsgType == 'text'){
		$item['Content']	= wp_strip_all_tags($item['Content']); 
	}elseif($MsgType == 'link'){
		$item['Content']	= '<a href="'.$item['Url'].'" target="_blank">'.$item['Title'].'</a>';
	}elseif($MsgType == 'image'){
		if(WEIXIN_TYPE >=3 && $item['CreateTime'] > WEIXIN_CUSTOM_SEND_LIMIT){
			$item['Content']	= '<a href="'.weixin_robot_get_media($item['MediaId']).'" target="_blank" title="'.$item['MediaId'].'"><img src="'.weixin_robot_get_media($item['MediaId']).'" alt="'.$item['MediaId'].'" width="100px;"></a>';
			$item['Content']	.= '<br /><a href="'.weixin_robot_get_media($item['MediaId']).'">下载图片</href>';
		}else{
			$item['Content']	.= '图片已过期，不可下载';
		}
		if(isset($_GET['debug'])) $item['Content']	.=  '<br />MediaId：'.$item['MediaId'];
	}elseif($MsgType == 'location'){
		$location = maybe_unserialize($item['Content']);
		if(is_array($location)){
			$item['Content'] = '<img src="http://st.map.qq.com/api?size=300*150&center='.$location['Location_Y'].','.$location['Location_X'].'&zoom=15&markers='.$location['Location_Y'].','.$location['Location_X'].'" />';
			if(isset($location['Label'])) $item['Content'] .= '<br />'.$location['Label'];
		}
	}elseif($MsgType == 'voice'){
		if($item['Content']){
			$item['Content']	= '语音识别成：'.$item['Content'];
		}
		if(WEIXIN_TYPE >=3 && $item['CreateTime'] > WEIXIN_CUSTOM_SEND_LIMIT){
			$item['Content']	= $item['Content'].'<br /><a href="'.weixin_robot_get_media_download_url($item['MediaId']).'">下载语音</href>';
		}
		if(isset($_GET['debug'])) $item['Content']	.= '<br />MediaId：'.$item['MediaId'];
	}elseif($MsgType == 'video' || $MsgType == 'shortvideo'){
		if(WEIXIN_TYPE >=3 && $item['CreateTime'] > WEIXIN_CUSTOM_SEND_LIMIT){
			$item['Content']	= '<a href="'.weixin_robot_get_media_download_url($item['MediaId']).'" target="_blank" title="'.$item['MediaId'].'"><img src="'.weixin_robot_get_media($item['Url']).'" alt="'.$item['Url'].'" width="100px;"><br >点击下载视频</a>';
		}else{
			$item['Content']	.= '视频已过期，不可下载';
		}
	}elseif($MsgType == 'event'){
		$Event = strtolower($item['Event']);
		if($Event == 'click'){
			$item['Content']	= '['.$item['Event'].'] '.$item['EventKey']; 
		}elseif($Event == 'view'){
			$item['Content']	= '['.$item['Event'].'] '.'<a href="'.$item['EventKey'].'">'.$item['EventKey'].'</a>'; 
		}elseif($Event == 'location'){
			// $location = maybe_unserialize($item['Content']);
			// if(is_array($location)){
			// 	$item['Content'] = '<img src="http://st.map.qq.com/api?size=300*150&center='.$location['Location_Y'].','.$location['Location_X'].'&zoom=15&markers='.$location['Location_Y'].','.$location['Location_X'].'" />';
			// }
			$item['Content']	= '['.$item['Event'].'] ';
		}elseif ($Event == 'templatesendjobfinish') {
			$item['Content']	= '['.$item['Event'].'] '.$item['EventKey'];
		}elseif ($Event == 'masssendjobfinish') {
			$count_array		= maybe_unserialize($item['Content']);
			if(is_array($count_array)){
				$item['Content']	= '['.$item['Event'].'] '.$item['EventKey'].'<br />'.'所有：'.$count_array['TotalCount'].'过滤之后：'.$count_array['FilterCount'].'发送成功：'.$count_array['SentCount'].'发送失败：'.$count_array['ErrorCount'];
			}
		}elseif($Event == 'scancode_push' || $Event == 'scancode_waitmsg'){
			$item['Content']	= '['.$item['Event'].'] '.$item['Title'].'<br />'.$item['Content'];
		}elseif($Event == 'location_select'){
			$location = maybe_unserialize($item['Content']);
			if(is_array($location)){
				$item['Content'] = '<img src="http://st.map.qq.com/api?size=300*150&center='.$location['Location_Y'].','.$location['Location_X'].'&zoom=15&markers='.$location['Location_Y'].','.$location['Location_X'].'" />';
				if(isset($location['Label'])) $item['Content'] .= '<br />'.$location['Label'];
			}
		}else{
			$item['Content']	= '['.$item['Event'].'] '.$item['EventKey'];
		}
		
	}

	if(is_numeric($Response) ){
		$item['Response'] = '人工回复';
		$weixin_reply_message = weixin_robot_get_message($Response);
		if($weixin_reply_message){
			$item['Content']	.= '<br /><span style="background-color:yellow; padding:2px; ">人工回复：'.$weixin_reply_message['Content'].'</span>';
		}
	}elseif(isset($response_types[$Response])){
		$item['Response'] = $response_types[$Response];	
	}

	$row_actions	= array();
	if($item['CreateTime'] > WEIXIN_CUSTOM_SEND_LIMIT){
		if(is_numeric($Response)){
			$row_actions['reply']	= '已经回复';
		}elseif($weixin_user['subscribe']){
			$row_actions['reply']	= '<a href="'.admin_url('admin.php?page=weixin-robot-masssend&tab=custom&openid='.$weixin_user['openid'].'&reply_id='.$item['id'].'&TB_iframe=true&width=780&height=420').'" title="回复客服消息" class="thickbox" >回复</a>';
		}
	}else{
		if(isset($_GET['openid'])){
			$row_actions['delete']		= '<a href="'.$current_admin_url.'&openid='.$_GET['openid'].'&action=delete&id='.$item['id'].'">删除</a>';
		}else{
			$row_actions['delete']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['id'],'delete-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">删除</a>';
		}
	}

	$item['row_actions']	= $row_actions;

	$item['CreateTime']	= get_date_from_gmt(date('Y-m-d H:i:s',$item['CreateTime']));

	if(isset($_GET['openid'])){
		$row_action_values = array();
		foreach ($row_actions as $class => $row_action) {
			$row_action_values[] = '<span class="'.$class.'">'.$row_action.'</span>';
		}
		$item['operation'] = implode(' | ', $row_action_values);
	}

	return $item;
}

function wpjam_qqface_convert_html($text){	
	$qqface_maps = array("/::)", "/::~", "/::B", "/::|", "/:8-)", "/::<", "/::$", "/::X", "/::Z", "/::'(", "/::-|", "/::@", "/::P", "/::D", "/::O", "/::(", "/::+", "/:--b", "/::Q", "/::T", "/:,@P", "/:,@-D", "/::d", "/:,@o", "/::g", "/:|-)", "/::!", "/::L", "/::>", "/::,@", "/:,@f", "/::-S", "/:?", "/:,@x", "/:,@@", "/::8", "/:,@!", "/:!!!", "/:xx", "/:bye", "/:wipe", "/:dig", "/:handclap", "/:&-(", "/:B-)", "/:<@", "/:@>", "/::-O", "/:>-|", "/:P-(", "/::'|", "/:X-)", "/::*", "/:@x", "/:8*", "/:pd", "/:<W>", "/:beer", "/:basketb", "/:oo", "/:coffee", "/:eat", "/:pig", "/:rose", "/:fade", "/:showlove", "/:heart", "/:break", "/:cake", "/:li", "/:bome", "/:kn", "/:footb", "/:ladybug", "/:shit", "/:moon", "/:sun", "/:gift", "/:hug", "/:strong", "/:weak", "/:share", "/:v", "/:@)", "/:jj", "/:@@", "/:bad", "/:lvu", "/:no", "/:ok", "/:love", "/:<L>", "/:jump", "/:shake", "/:<O>", "/:circle", "/:kotow", "/:turn", "/:skip", "/:oY");

	return str_replace( $qqface_maps,
		array_map("wpjam_qqface_add_img_label", array_keys($qqface_maps) ),
		$text
	);
}

function wpjam_qqface_add_img_label($v){
	return '<img src="https://res.wx.qq.com/mpres/htmledition/images/icon/emotion/'.$v.'.gif" width="24" height="24">';
}

function weixin_robot_messages_views($views){
	global $current_admin_url;

	$msg_types	= weixin_robot_get_message_types();
	$msg_types['manual'] = '需要人工回复';
	$type		= isset($_GET['type']) ? $_GET['type'] : '';

	$views	= array();

	$class = empty($type) ? 'class="current"':'';
	$views['all'] = '<a href="'.$current_admin_url.'" '.$class.'>全部</a>';

	foreach ($msg_types as $key => $value) {
		$class = ($type == $key) ? 'class="current"':'';
		$views[$key] = '<a href="'.$current_admin_url.'&type='.$key.'" '.$class.'>'.$value.'</a>';
	}

	return $views;
}

function weixin_robot_get_message($id){
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM {$wpdb->weixin_messages} WHERE id=$id", ARRAY_A);
}

add_action('wp_ajax_weixin_reply', 'weixin_robot_reply_message_action_callback');
add_action('wp_ajax_nopriv_weixin_reply', 'weixin_robot_reply_message_action_callback');
function weixin_robot_reply_message_action_callback(){
	check_ajax_referer( 'weixin_ajax_nonce' );

	$weixin_openid	= $_POST['weixin_openid'];
	$reply_id		= $_POST['reply_id'];
	$reply_type		= $_POST['reply_type'];
	$content		= $_POST['content'];


	if(empty($weixin_openid) || empty($reply_id) || empty($content)) return;

	$data = array(
		'MsgType'		=> 'manual',
		'FromUserName'	=> $weixin_openid,
		'CreateTime'	=> current_time('timestamp',true),
		'Content'		=> $content,
	);

	global $wpdb;

	$insert_id = $wpdb->insert($wpdb->weixin_messages,$data); 
	$wpdb->update($wpdb->weixin_messages, array('Response'=>$wpdb->insert_id),array('id'=>$reply_id));
	$response = weixin_robot_send_user($weixin_openid, $content, $reply_type);

	if(is_wp_error($response)){
		echo 0;
	}else{
		echo $response;
	}
}

function weixin_robot_get_message_types($type=''){
	if($type == 'event' || $type == 'card-event'){
		return array(
			'click'				=> '点击菜单',
			'view'				=> '跳转URL',

			'subscribe'			=> '用户订阅', 
			'unsubscribe'		=> '取消订阅',

			'scancode_push'		=> '扫码推事件',
			'scancode_waitmsg'	=> '扫码带提示',
			'pic_sysphoto'		=> '系统拍照发图',
			'pic_photo_or_album'=> '拍照或者相册发图',
			'pic_weixin'		=> '微信相册发图器',
			'location_select'	=> '地理位置选择器',
			'location'			=> '获取用户地理位置',
			'scan'				=> '扫描带参数二维码',

			'user_get_card'		=> '领取卡券',
			'user_del_card'		=> '删除卡券',
			'user_consume_card'	=> '核销卡券',
			'card_pass_check'	=> '卡券通过审核',
			'card_not_pass_check'	=> '卡券未通过审核',
			'user_view_card'	=> '进入会员卡',
			'user_enter_session_from_card'	=> '从卡券进入公众号会话',
			'card_sku_remind'	=> '卡券库存报警',
			'submit_membercard_user_info'	=> '接收会员信息',

			'wificonnected'		=> 'Wi-Fi连网成功',
			'shakearoundusershake'	=> '摇一摇',
			'poi_check_notify'	=> '门店审核',
			
			'masssendjobfinish'		=> '群发信息',
			'templatesendjobfinish'	=> '收到模板消息',

			'kf_create_session'	=> '多客服接入会话',
			'kf_close_session'	=> '多客服关闭会话',
			'kf_switch_session'	=> '多客服转接会话',

			'qualification_verify_success'	=> '资质认证成功',
			'qualification_verify_fail'		=> '资质认证失败',
			'naming_verify_success'			=> '名称认证成功',	
			'naming_verify_fail'			=> '名称认证失败',
			'annual_renew'					=> '年审通知',
			'verify_expired'				=> '认证过期失效通知',	
		);
	}elseif($type == 'text'){
		return weixin_robot_get_response_types();
	}elseif($type == 'menu'){
		$message_types	= array();
		// global $wpdb;
		// $buttons_list	= $wpdb->get_col("SELECT button FROM {$wpdb->weixin_menus}");
		// foreach ($buttons_list as $buttons) {
		// $buttons = json_decode($buttons,true);
		
		$weixin_menu = weixin_robot_get_local_menu();

			if($buttons = $weixin_menu['button']){
				foreach($buttons as $button){
					if(empty($button['sub_button'])){
						if($button['type']	== 'view'){
							$message_types[$button['url']]	= $button['name'];
						}else{
							$message_types[$button['key']]	= $button['name'];	
						}
					}else{
						foreach ($button['sub_button'] as $sub_button) {
							if($sub_button['type']	== 'view'){
								$message_types[$sub_button['url']]	= $sub_button['name'];
							}else{
								$message_types[$sub_button['key']]	= $sub_button['name'];	
							}
						}
					}
				}
			}
		// }
		
		return $message_types;
	}else{
		return array(
			'text'			=>'文本消息', 
			'event'			=>'事件消息',  
			'location'		=>'位置消息', 
			'image'			=>'图片消息', 
			'link'			=>'链接消息', 
			'voice'			=>'语音消息',
			'video'			=>'视频消息',
			'shortvideo'	=>'小视频'
		);
	}

	return $message_types;
}

function weixin_robot_get_response_types(){
	$response_types = array(
		'advanced'		=> '高级回复',

		'welcome'		=> '欢迎语',

		'subscribe'		=> '订阅',
		'unsubscribe'	=> '取消订阅',
		'scan'			=> '扫描带参数二维码',
		
		'tag'			=> '标签最新日志',
		'cat'			=> '分类最新日志',
		'taxonomy'		=> '自定义分类最新日志',
		
		'custom-text'	=> '自定义文本回复',
		'custom-img'	=> '文章图文回复',
		'custom-img2'	=> '自定义图文回复',
		'custom-image'	=> '自定义图片回复',
		'custom-voice'	=> '自定义音频回复',
		'custom-music'	=> '自定义音乐回复',
		'custom-video'	=> '自定义视频回复',
		
		'query'			=> '搜索查询回复',
		'too-long'		=> '关键字太长',
		'not-found'		=> '没有匹配内容',

		'voice'			=> '语音自动回复',
		'loction'		=> '位置自动回复',
		'link'			=> '链接自动回复',
		'image'			=> '图片自动回复',
		'video'			=> '视频自动回复',

		'enter-reply'	=> '进入微信回复',
		'3rd'			=> '第三方回复',
		'view'			=> '打开网页',
		'scancode_push'		=> '扫码推事件',
		'scancode_waitmsg'	=> '扫码带提示',
		'pic_sysphoto'		=> '系统拍照发图',
		'pic_photo_or_album'=> '拍照或者相册发图',
		'pic_weixin'		=> '微信相册发图器',
		'location_select'	=> '地理位置选择器',
		'templatesendjobfinish'	=> '收到模板消息',
		
		'checkin'		=> '回复签到',
		'credit'		=> '回复积分',
		'top-credits'	=> '积分排行榜',
    	'top-checkin'	=> '签到排行榜',
		
		'dkf'			=> '转到多客服'
	);

	return apply_filters('weixin_response_types',$response_types);
}