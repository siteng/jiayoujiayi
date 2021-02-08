<?php
wp_cache_add_global_groups('weixin_messages');

class WEIXIN_Message extends WPJAM_Model {
	use WEIXIN_Trait;

	private static $response_types = [
		'subscribe'		=> '订阅',
		'unsubscribe'	=> '取消订阅',
		'scan'			=> '扫描带参数二维码',
		
		'custom-text'	=> '自定义文本回复',
		'custom-img'	=> '文章图文回复',
		'custom-img2'	=> '自定义图文回复',
		'custom-news'	=> '自定义素材图文回复',
		'custom-image'	=> '自定义图片回复',
		'custom-voice'	=> '自定义音频回复',
		'custom-music'	=> '自定义音乐回复',
		'custom-video'	=> '自定义视频回复',

		'empty'			=> '空白字符回复',
		
		'query'			=> '搜索查询回复',
		'too-long'		=> '关键字太长',
		'not-found'		=> '没有匹配内容',

		'3rd'			=> '第三方回复',		
		'dkf'			=> '转到多客服'
	];

	public static function register_response_type($name, $title){
		self::$response_types[$name]	= $title;
	}

	public static function get_response_types(){
		return self::$response_types;
	}

	public static function get_message_types(){
		return [
			'text'			=>'文本消息', 
			'event'			=>'事件消息',  
			'location'		=>'位置消息', 
			'image'			=>'图片消息', 
			'link'			=>'链接消息', 
			'voice'			=>'语音消息',
			'video'			=>'视频消息',
			'shortvideo'	=>'小视频'
		];
	}

	public static function get_user_location($openid){	// 获取用户的最新的地理位置并缓存10分钟。
		$cache_key	= 'location:'.static::get_appid().':'.$openid;
		$location	= self::cache_get($cache_key);
		
		if($location === false){
			$location	= self::Query()->where_not('Content', '')->where('FromUserName',$openid)->where('appid',self::get_appid())->where_gt('CreateTime', time()-HOUR_IN_SECONDS)->where_fragment("MsgType='Location' OR (MsgType ='Event' AND Event='LOCATION')")->order_by('CreateTime')->order('DESC')->get_var('Content');

			$location	= maybe_unserialize($location);
			self::cache_set($cache_key, $location, 600);
		}

		return $location;
	}

	public static function sanitize($message, $response=''){
		$appid	= static::get_appid();

		$data	= [
			'MsgId'			=>	$message['MsgId'] ?? '',
			'MsgType'		=>	$message['MsgType'] ?? '',
			'FromUserName'	=>	$message['FromUserName'] ?? '',
			'CreateTime'	=>	$message['CreateTime'] ?? '',
			'Content'		=> '',
			'Event'			=> '',
			'EventKey'		=> '',
			'Title'			=> '',
			'Url'			=> '',
			'MediaId'		=> '',
			'Response'		=>	$response,
		];

		$openid		= $message['FromUserName'] ?? '';
		$msgType	= isset($message['MsgType']) ? strtolower($message['MsgType']) : '';

		if($msgType == 'text'){
			$data['Content']	= $message['Content'] ? strval($message['Content']) : '';
		}elseif($msgType == 'image'){
			$data['Url']		= $message['PicUrl'];
			$data['MediaId']	= $message['MediaId'];
		}elseif($msgType == 'location'){
			$location	= [
				'Location_X'	=>	$message['Location_X'],
				'Location_Y'	=>	$message['Location_Y'],
				'Scale'			=>	$message['Scale'],
				'Label'			=>	$message['Label']
			];
			$data['Content']	= maybe_serialize($location);

			self::cache_set('location:'.$appid.':'.$openid, $location, 600);// 缓存用户地理位置信息
		}elseif($msgType == 'link'){
			$data['Title']		= $message['Title'];
			$data['Content']	= $message['Description'] ?: '';
			$data['Url']		= $message['Url'];
		}elseif($msgType == 'voice'){
			$data['Url']		= $message['Format'];
			$data['MediaId']	= $message['MediaId'];
			$data['Content']	= !empty($message['Recognition']) ? $message['Recognition'] : '';
		}elseif($msgType == 'video' || $msgType == 'shortvideo'){
			$data['MediaId']	= $message['MediaId'];
			$data['Url']		= $message['ThumbMediaId'];
		}elseif($msgType == 'event'){
			$data['Event']		= $message['Event'];
			$Event 				= strtolower($message['Event']);
			$data['EventKey']	= !empty($message['EventKey']) ? $message['EventKey'] : '';
			if($Event == 'location'){
				$location	= [
					'Location_X'	=>	$message['Latitude'],
					'Location_Y'	=>	$message['Longitude'],
					'Precision'		=>	$message['Precision'],
				];
				$data['Content']	= maybe_serialize($location);
			}elseif ($Event == 'templatesendjobfinish') {
				$data['EventKey']	= $message['Status'];
			}elseif ($Event == 'masssendjobfinish') {
				$data['EventKey']	= $message['Status'];
				$data['MsgId']		= $message['MsgId'] ?? ($message['MsgID'] ?? '');
				// file_put_contents(WP_CONTENT_DIR.'/debug/masssendjobfinish.log',var_export($message,true),FILE_APPEND);
				$data['Content']	= maybe_serialize([
					'Status'		=> $message['Status'],
					'TotalCount'	=> $message['TotalCount'],
					'FilterCount'	=> $message['FilterCount'],
					'SentCount'		=> $message['SentCount'],
					'ErrorCount'	=> $message['ErrorCount']
				]);	
			}elseif($Event == 'scancode_push' || $Event == 'scancode_waitmsg'){
				$ScanCodeInfo 		= $message['ScanCodeInfo'];
				$data['Title']		= (string)$ScanCodeInfo['ScanType'];
				$data['Content']	= (string)$ScanCodeInfo['ScanResult'];
			}elseif($Event == 'location_select'){
				$SendLocationInfo	= $message['SendLocationInfo'];
				$location	= [
					'Location_X'	=>	$message['Location_X'],
					'Location_Y'	=>	$message['Location_Y'],
					'Scale'			=>	$message['Scale'],
					'Label'			=>	$message['Label'],
					'Poiname'		=>	$message['Poiname'],
				];
				$data['content']	= maybe_serialize($location);

				self::cache_set('location:'.$appid.':'.$openid, $location, 600);// 缓存用户地理位置信息
			}elseif($Event == 'pic_sysphoto' || $Event == 'pic_photo_or_album' || $Event == 'pic_weixin'){
				$SendPicsInfo		= $message['SendPicsInfo'];
				$Count 				= (string)$SendPicsInfo['Count'];
				$PicList			= (string)$SendPicsInfo['PicList'];
			}elseif ($Event == 'card_not_pass_check' || $Event == 'card_pass_check') {
				$data['EventKey']	= $message['CardId'];
			}elseif ($Event == 'user_get_card') {
				$data['EventKey']	= $message['CardId'];
				$data['Title']		= $message['UserCardCode'];
				$data['MediaId']	= $message['OuterId'];
				$data['Url']		= $message['IsGiveByFriend'];

				$data['content']	= maybe_serialize([
					'FriendUserName'	=>	$message['FriendUserName'],
					'OldUserCardCode'	=>	$message['OldUserCardCode'],
				]);
			}elseif ($Event == 'user_del_card') {
				$data['EventKey']	= $message['CardId'];
				$data['Title']		= $message['UserCardCode'];
			}elseif ($Event == 'user_view_card') {
				$data['EventKey']	= $message['CardId'];
				$data['Title']		= $message['UserCardCode'];
			}elseif ($Event == 'user_enter_session_from_card') {
				$data['EventKey']	= $message['CardId'];
				$data['Title']		= $message['UserCardCode'];
			}elseif ($Event == 'user_consume_card') {
				$data['EventKey']	= $message['CardId'];
				$data['Title']		= $message['UserCardCode'];
				$data['MediaId']	= $message['ConsumeSource'];

				$data['content']	= maybe_serialize([
					'OutTradeNo'	=>	$message['OutTradeNo'],
					'TransId'		=>	$message['TransId'],
					'LocationName'	=>	$message['LocationName'],
					'StaffOpenId'	=>	$message['StaffOpenId'],
				]);
			}elseif($Event == 'submit_membercard_user_info'){
				$data['EventKey']	= $message['CardId'];
				$data['Title']		= $message['UserCardCode'];
			}elseif ($Event == 'wificonnected') {
				$data['EventKey']	= $message['PlaceId'];
				$data['Title']		= $message['DeviceNo'];
				$data['MediaId']	= $message['ConnectTime'];

				$data['content']	= maybe_serialize([
					'ExpireTime'	=>	$message['ExpireTime'],
					'VendorId'		=>	$message['VendorId'],
				]);
			}elseif ($Event == 'shakearoundusershake') {
				$data['Title']		= maybe_serialize($message['ChosenBeacon']);
				$data['Content']	= maybe_serialize($message['AroundBeacons']);
			}elseif ($Event == 'poi_check_notify') {
				$data['EventKey']	= $message['UniqId'];
				$data['Title']		= $message['PoiId'];
				$data['MediaId']	= $message['Result'];
				$data['Content']	= $message['Msg'];
			}elseif($Event == 'qualification_verify_success' || $Event == 'naming_verify_success' || $Event == 'annual_renew' || $Event == 'verify_expired'){
				$data['Title']		= $message['ExpiredTime'];
			}elseif($Event == 'qualification_verify_fail' || $Event == 'naming_verify_fail'){
				$data['Title']		= $message['FailTime'];
				$data['Content']	= $message['FailReason'];
			}elseif($Event == 'kf_create_session' || $Event == 'kf_close_session'){
				$data['Title']		= $message['KfAccount'];
			}elseif($Event == 'kf_switch_session' || $Event == 'kf_close_session'){
				$data['Title']		= $message['FromKfAccount'];
				$data['Content']	= $message['ToKfAccount'];
			}
		}

		return $data;
	}
	
	public static function insert($data){
		$data['appid']	= $appid = static::get_appid();

		if(!wp_using_ext_object_cache() || count($data) <= 5){
			return parent::insert($data); 
		}

		$messages	= self::cache_get('messages:'.$appid);
		$messages	= ($messages === false) ? [] : $messages;
		
		$messages[]	= $data;

		if(count($messages) < 10){
			return self::cache_set('messages:'.$appid, $messages, 3600);
		}else{
			self::cache_delete('messages:'.$appid);
			parent::insert_multi($messages);
		}

		return true;
	}

	protected static $handler;

	public static function get_table(){
		global $wpdb;
		return $wpdb->base_prefix.'weixin_messages';
	}

	public static function get_handler(){
		if(is_null(static::$handler)){
			static::$handler = new WPJAM_DB(self::get_table(), array(
				'primary_key'		=> 'id',
				'cache'				=> false,
				'cache_group'		=> 'weixin_messages',
				'field_types'		=> ['id'=>'%d','MsgId'=>'%d','CreateTime'=>'%d'],
				'filterable_fields'	=> ['MsgType','Response','FromUserName'],
			));
		}

		return static::$handler;
	}

	public static function create_table(){
		global $wpdb;

		$table = static::get_table();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		if($wpdb->get_var("show tables like '".$table."'") != $table) {
			$sql = "
			CREATE TABLE IF NOT EXISTS ".$table." (
				`id` bigint(20) NOT NULL auto_increment,
				`appid` varchar(32) NOT NULL,
				`MsgId` bigint(20) NOT NULL,
				`FromUserName` varchar(30) NOT NULL,
				`MsgType` varchar(10) NOT NULL,
				`CreateTime` int(10) NOT NULL,
				`Content` longtext NOT NULL,
				`Event` varchar(50) NOT NULL,
				`EventKey` varchar(50) NOT NULL,
				`Title` text NOT NULL,
				`Url` varchar(255) NOT NULL,
				`MediaId` varchar(500) NOT NULL,
				`Response` varchar(255) NOT NULL,
				PRIMARY KEY (`id`)
			) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			";
	 
			dbDelta($sql);

			$wpdb->query("ALTER TABLE `".$table."`
				ADD KEY `MsgType` (`MsgType`),
				ADD KEY `CreateTime` (`CreateTime`),
				ADD KEY `Event` (`Event`);");
		}
	}
}

class WEIXIN_AdminMessage extends WEIXIN_Message{
	private static $tab = '';

	public static function set_tab($tab){
		self::$tab	= $tab;
	}

	private static function get_send_limit(){
		return time()-2*DAY_IN_SECONDS;
	}

	public static function views(){
		$views	= [];

		if(self::$tab == 'messages'){
			$msg_types	= self::get_message_types()+['manual'=>'需要人工回复'];

			$_msg_type	= $_REQUEST['MsgType'] ?? '';
			$class		= empty($_msg_type) ? 'current' : '';

			$views['all']	= wpjam_get_list_table_filter_link([], '全部', $class);

			foreach ($msg_types as $key => $label) {
				$class = $_msg_type == $key ? 'current' : '';

				$views[$key] = wpjam_get_list_table_filter_link(['MsgType'=>$key], $label, $class);
			}
		}

		return $views;
	}

	public static function query_items($limit, $offset){
		if(self::$tab == 'messages'){
			$msg_type	= wpjam_get_data_parameter('MsgType');

			if($msg_type == 'manual'){
				$items	= self::Query()->offset($offset)->limit($limit)->where('appid', weixin_get_appid())->where_in('Response', array('not-found', 'too-long'))->where_gt('CreateTime', self::get_send_limit())->find();
				$total 	= self::Query()->find_total();
			}else{
				self::Query()->where('appid', weixin_get_appid())->where_not('MsgType', 'manual');
				return parent::query_items($limit, $offset);
			}

			if($items){
				$openids 	= array_column($items, 'FromUserName');
				$users		= WEIXIN_User::batch_get_user_info($openids);
			}
		}elseif(self::$tab == 'masssend'){
			if(method_exists('WPJAM_Chart', 'init')){
				WPJAM_Chart::init();

				$wpjam_start_timestamp	= wpjam_get_chart_parameter('start_timestamp');
				$wpjam_end_timestamp	= wpjam_get_chart_parameter('end_timestamp');
			}else{
				global $wpjam_stats_labels;
				extract($wpjam_stats_labels);
			}
			
			$items	= self::Query()->offset($offset)->limit($limit)->where('appid', weixin_get_appid())->where('Event', 'MASSSENDJOBFINISH')->where_gt('CreateTime', $wpjam_start_timestamp)->where_lt('CreateTime', $wpjam_end_timestamp)->find();
			$total 	= self::Query()->find_total();
		}

		return compact('items', 'total');
	}

	public static function item_callback($item){
		if(self::$tab == 'messages'){
			$msg_types['manual'] = '需要人工回复';

			$MsgType	= $item['MsgType']; 

			$Response	= $item['Response'];
			$openid		= $item['FromUserName'];
			$user		= WEIXIN_User::get($openid);

			// if(empty($_GET['openid'])) {
				if($user && ($user	= WEIXIN_User::render_user($user))){
					$item['username']	= wpjam_get_list_table_filter_link(['FromUserName'=>$openid], $user['username']).'（'.$user['sex'].'）';
					// $item['address']	= $user['address'];
				}else{
					$item['username']	= '';
					// $item['address']	= '';
				}
			// }

			$item['name']	= $item['FromUserName'];

			if($MsgType == 'text'){
				$item['Content']	= wp_strip_all_tags($item['Content']); 
			}elseif($MsgType == 'link'){
				$item['Content']	= '<a href="'.$item['Url'].'" target="_blank">'.$item['Title'].'</a>';
			}elseif($MsgType == 'image'){
				if(weixin_get_type() >=3 && $item['CreateTime'] > self::get_send_limit()){
					$item['Content']	= '<a href="'.weixin()->get_media($item['MediaId']).'" target="_blank" title="'.$item['MediaId'].'"><img src="'.weixin()->get_media($item['MediaId']).'" alt="'.$item['MediaId'].'" width="100px;"></a>';
					$item['Content']	.= '<br /><a href="'.weixin()->get_media_download_url($item['MediaId']).'">下载图片</href>';
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
					$item['Content']	= '语音识别成：'.wp_strip_all_tags($item['Content']);
				}
				if(weixin_get_type() >=3 && $item['CreateTime'] > self::get_send_limit()){
					$item['Content']	= $item['Content'].'<br /><a href="'.weixin()->get_media_download_url($item['MediaId']).'">下载语音</href>';
				}
				if(isset($_GET['debug'])) $item['Content']	.= '<br />MediaId：'.$item['MediaId'];
			}elseif($MsgType == 'video' || $MsgType == 'shortvideo'){
				if(weixin_get_type() >=3 && $item['CreateTime'] > self::get_send_limit()){
					$item['Content']	= '<a href="'.weixin()->get_media_download_url($item['MediaId']).'" target="_blank" title="'.$item['MediaId'].'"><img src="'.weixin()->get_media($item['Url']).'" alt="'.$item['Url'].'" width="100px;"><br >点击下载视频</a>';
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
				$reply_message = self::get($Response);
				if($reply_message){
					$item['Content']	.= '<br /><span style="background-color:yellow; padding:2px; ">人工回复：'.$reply_message['Content'].'</span>';
				}
			}elseif(isset($response_types[$Response])){
				$item['Response'] = $response_types[$Response];	
			}

			
			if($item['CreateTime'] > self::get_send_limit()){
				// $row_actions = array();
				if(is_numeric($Response)){
					// $item['row_actions']['reply']	= '已经回复';
					unset($item['row_actions']['reply']);
					unset($item['row_actions']['delete']);
				}elseif(empty($user['subscribe'])){
					unset($item['row_actions']['reply']);
					unset($item['row_actions']['delete']);
					// $row_actions['reply']	= '<a href="'.admin_url('admin.php?page=weixin-masssend&tab=custom&openid='.$user['openid'].'&reply_id='.$item['id'].'&TB_iframe=true&width=780&height=420').'" title="回复客服消息" class="thickbox" >回复</a>';
				}
				// $item['row_actions']	= $row_actions;
			}else{
				unset($item['row_actions']['reply']);
				unset($item['row_actions']['delete']);
			}

			$item['CreateTime']	= get_date_from_gmt(date('Y-m-d H:i:s',$item['CreateTime']));
		}elseif(self::$tab == 'masssend'){
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
		}

		return $item;
	}

	public static function get_message_types($type=''){
		if($type == 'event' || $type == 'card-event'){
			return [
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
				'view_miniprogram'	=> '跳转小程序',
				
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

				'user_get_card'					=> '领取卡券',
				'user_del_card'					=> '删除卡券',
				'user_consume_card'				=> '核销卡券',
				'card_pass_check'				=> '卡券通过审核',
				'card_not_pass_check'			=> '卡券未通过审核',
				'user_view_card'				=> '进入会员卡',
				'user_enter_session_from_card'	=> '从卡券进入公众号会话',
				'card_sku_remind'				=> '卡券库存报警',
				'submit_membercard_user_info'	=> '接收会员信息',

				'wificonnected'			=> 'Wi-Fi连网成功',
				'shakearoundusershake'	=> '摇一摇',
				'poi_check_notify'		=> '门店审核',
			];
		}elseif($type == 'text'){
			return self::get_response_types();
		}else{
			return parent::get_message_types();
		}
	}

	public static function reply(){
		$args_num	= func_num_args();
		$args		= func_get_args();

		if($args_num == 2){
			$id		= $args[0];
			$data	= $args[1];
		}else{
			$id		= 0;
			$data	= $args[0];
		}

		$openid = $data['FromUserName'];

		if(!self::can_send($openid)){
			return new WP_Error('out_of_custom_message_time_limit', '48小时没有互动过，无法发送消息！');
		}

		$type		= $data['type'];
		$content	= $data['content'];
		$kf_account	= isset($data['kf_account'])?$data['kf_account']:'';

		$response = self::send($openid, $content, $type, $kf_account);

		if(is_wp_error($response)){
			return $response;
		}

		if(isset($id)){
			$message_data = [
				'MsgType'		=> 'manual',
				'FromUserName'	=> $openid,
				'CreateTime'	=> time(),
				'Content'		=> $content,
			];

			$insert_id	= self::insert($message_data);
			self::update($id, ['Response'=>$insert_id]);
		}

		if($kf_account){
			$response	= weixin()->create_customservice_kf_session($kf_account, $openid); 

			if(is_wp_error($response)){
				return $response;
			}
		}

		return true;
	}

	public static function send($openid, $content, $type='text', $kf_account=''){
		if(empty($content)) return;

		if($type == 'img'){
			$counter = 0;

			$articles = $article	= array();

			$img_reply_query 	= new WP_Query(array('post__in'=>explode(',', $content),'orderby'=>'post__in','post_type'=>'any'));

			if($img_reply_query->have_posts()){
				while ($img_reply_query->have_posts()) {
					$img_reply_query->the_post();

					$article['title']		= html_entity_decode(get_the_title());
					$article['description']	= html_entity_decode(get_the_excerpt());
					$article['url']			= get_permalink();

					if($counter == 0){
						$article['picurl'] = wpjam_get_post_thumbnail_url('', array(640,320));
					}else{
						$article['picurl'] = wpjam_get_post_thumbnail_url('', array(80,80));
					}
					$counter ++;
					$articles[] = $article;
				}
				$type		= 'news';
				$content	= $articles;
			}
			wp_reset_query();
		}elseif($type == 'img2'){
			$articles = $article	= array();

			$items = explode("\n\n", str_replace("\r\n", "\n", $content));
			foreach ($items as $item ) {
				$lines = explode("\n", $item);
				$article['title']		= isset($lines[0])?$lines[0]:'';
				$article['description']	= isset($lines[1])?$lines[1]:'';
				$article['picurl']		= isset($lines[2])?$lines[2]:'';
				$article['url']			= isset($lines[3])?$lines[3]:'';

				$articles[] = $article;
			}
			$type		= 'news';
			$content	= $articles;
		}elseif($type == 'news'){
			$material	= weixin()->get_material($content, 'news');
			if(is_wp_error($material)){
				return $material;
			}else{
				$articles = $article	= array();
				
				foreach ($material as $news_item) {
					$article['title']		= $news_item['title'];
					$article['description']	= $news_item['digest'];
					$article['picurl']		= $news_item['thumb_url'];
					$article['url']			= $news_item['url'];

					$articles[] = $article;
				}
				$type		= 'news';
				$content	= $articles;
			}
		}elseif($type == 'wxcard'){
			$items 		= explode("\n", $content);
			$card_id	= ($items[0])??'';
			$outer_id	= ($items[1])??'';
			$code		= ($items[2])??'';

			$card_ext	= weixin_robot_generate_card_ext(compact('card_id','outer_id','code','openid'));

			$data	= [
				'touser'	=>$openid,
				'msgtype'	=>'wxcard',
				'wxcard'	=>compact('card_id','card_ext')
			];
		}elseif($type == 'text'){
			$content	= compact('content');
		}

		$data	= [
			'touser'	=> $openid,
			'msgtype'   => $type,
			$type 		=> $content,
		];

		if($kf_account){
			$data['customservice']	= compact('kf_account');
		}

		return weixin()->send_custom_message($data);
	}

	public static function can_send($openid){
		if(self::Query()->where('appid',static::get_appid())->where('FromUserName',$openid)->where_gt('CreateTime', self::get_send_limit())->get_row()){
			return true;
		}else{
			return false;
		}
	}

	public static function get_can_send_users(){
		return self::Query()->where('appid',static::get_appid())->where_gt('CreateTime', time()-HOUR_IN_SECONDS)->group_by('FromUserName')->get_col('FromUserName');
	}

	public static function dummy($id){
		return true;
	}

	public static function bulk_delete($ids){
		return self::delete_multi($ids);
	}

	public static function get_reply_fields(){
		$fields = [
			'FromUserName'	=> ['title'=>'',	'type'=>'hidden'],
			'type'			=> ['title'=>'',	'type'=>'hidden',	'value'=>'text'],
			'content'		=> ['title'=>'',	'type'=>'textarea']
		];

		$weixin_setting	= weixin_get_setting();

		if(weixin_get_type() >= 3 && !empty($weixin_setting['weixin_dkf'])){
			$weixin_kf_list	= weixin()->get_customservice_kf_list();

			if(!is_wp_error($weixin_kf_list)){
				$weixin_kf_options	= [''=>' '];
				foreach ($weixin_kf_list as $weixin_kf_account) {
					$weixin_kf_options[$weixin_kf_account['kf_account']] = $weixin_kf_account['kf_nick'];
				}
				$fields['kf_account'] = ['title'=>'以客服账号回复',	'type'=>'select',	'options'=>$weixin_kf_options];
				$fields['content']['title']	= '内容';
			}
		}

		return $fields;
	}

	public static function get_actions(){
		if(self::$tab == 'masssend'){
			return [];
		}else{
			return [
				'reply'		=> ['title'=>'回复',	'page_title'=>'回复客服消息'],
				'delete'	=> ['title'=>'删除',	'direct'=>true,	'confirm'=>true,	'bulk'=>true],
				// 'dummy'		=> ['title'=>'测试',	'direct'=>true,	'confirm'=>true,	'bulk'=>true]
			];
		}
	}

	public static function get_fields($action_key='', $id=''){
		if(self::$tab == 'masssend'){
			return [
				'MsgId'			=> ['title' => '群发ID',		'type' => 'text',	'show_admin_column' => true],
				'CreateTime'	=> ['title' => '时间',		'type' => 'text',	'show_admin_column' => true],
				'Status'		=> ['title' => '状态',		'type' => 'text',	'show_admin_column' => true],
				'TotalCount'	=> ['title' => '所有',		'type' => 'text',	'show_admin_column' => true],
				'FilterCount'	=> ['title' => '过滤之后',	'type' => 'text',	'show_admin_column' => true],
				'SentCount'		=> ['title' => '发送成功',	'type' => 'text',	'show_admin_column' => true],
				'SentRate'		=> ['title' => '成功率',		'type' => 'text',	'show_admin_column' => true],
				'ErrorCount'	=> ['title' => '发送失败',	'type' => 'text',	'show_admin_column' => true],
			];
		}else{
			if($action_key == 'reply'){
				return self::get_reply_fields();
			}else{
				return [
					'username'	=> ['title'=>'用户',	'type'=>'text',		'show_admin_column'=>true],
					// 'address'	=> ['title'=>'地址',	'type'=>'text',		'show_admin_column'=>true],
					'MsgType'	=> ['title'=>'类型',	'type'=>'select',	'show_admin_column'=>true,	'options'=>self::get_message_types()],
					'Content'	=> ['title'=>'内容',	'type'=>'text',		'show_admin_column'=>true],
					'Response'	=> ['title'=>'回复',	'type'=>'select',	'show_admin_column'=>true,	'options'=>self::get_response_types()],
					'CreateTime'=> ['title'=>'时间',	'type'=>'text',		'show_admin_column'=>true],
				];
			}
		}
	}
}