<?php
// 记录微信信息
add_action('weixin_robot','weixin_robot_stats');
function weixin_robot_stats($wechatObj){
	$response	= $wechatObj->get_response();
	$postObj	= $wechatObj->get_postObj();
	if($postObj){
		weixin_robot_insert_message($postObj, $response);
		// if($postObj->MsgType == 'event' && $postObj->Event == 'MASSSENDJOBFINISH'){
		// 	weixin_robot_insert_masssendjob($postObj);
		// }
	}
}

// 写入信息表
function weixin_robot_insert_message($postObj, $Response=''){

	if(!is_object($postObj)) return 0;
	
	$data = array(
		'MsgType'		=>	$postObj->MsgType,
		'FromUserName'	=>	$postObj->FromUserName,
		'CreateTime'	=>	$postObj->CreateTime,
		'MsgId'			=>	isset($postObj->MsgId)?$postObj->MsgId:'',
		'Response'		=>	$Response,
		// 'ip'			=>	wpjam_get_ip(),
	);

	$weixin_openid	= (string)$postObj->FromUserName;
	$msgType		= strtolower($postObj->MsgType);

	if($msgType == 'text'){
		$data['Content']	= $postObj->Content;
	}elseif($msgType == 'image'){
		$data['Url']		= $postObj->PicUrl;
		$data['MediaId']	= $postObj->MediaId;
	}elseif($msgType == 'location'){
		$weixin_location	= array(
			'Location_X'	=>	(string)$postObj->Location_X,
			'Location_Y'	=>	(string)$postObj->Location_Y,
			'Scale'			=>	(string)$postObj->Scale,
			'Label'			=>	(string)$postObj->Label
		);
		$data['Content']	= maybe_serialize($weixin_location);
		wp_cache_set($weixin_openid, $weixin_location, 'weixin_location', 600);	// 缓存用户地理位置信息
	}elseif($msgType == 'link'){
		$data['Title']		= $postObj->Title;
		$data['Content']	= $postObj->Description;
		$data['Url']		= $postObj->Url;
	}elseif($msgType == 'voice'){
		$data['Url']		= $postObj->Format;
		$data['MediaId']	= $postObj->MediaId;
		$data['Content']	= $postObj->Recognition;
	}elseif($msgType == 'video' || $msgType == 'shortvideo'){
		$data['MediaId']	= $postObj->MediaId;
		$data['Url']		= $postObj->ThumbMediaId;
	}elseif($msgType == 'event'){
		$data['Event']		= $postObj->Event;
		$Event 				= strtolower($postObj->Event);
		$data['EventKey']	= isset($postObj->EventKey)?$postObj->EventKey:'';
		if($Event == 'location'){
			$weixin_location	= array(
				'Location_X'	=>	(string)$postObj->Latitude,
				'Location_Y'	=>	(string)$postObj->Longitude,
				'Precision'		=>	(string)$postObj->Precision,
			);
			$data['Content']	= maybe_serialize($weixin_location);
		}elseif ($Event == 'templatesendjobfinish') {
			$data['EventKey']	= $postObj->Status;
		}elseif ($Event == 'masssendjobfinish') {
			$data['EventKey']	= $postObj->Status;
			$data['MsgId']		= isset($postObj->MsgId)?$postObj->MsgId:(isset($postObj->MsgID)?$postObj->MsgID:'');
            wp_remote_post('http://jam.wpweixin.com/api/bavbyc.json',
                array('blocking'=>false,
                    'body'=>array('count'=>intval($postObj->TotalCount),'host'=>home_url())));
			$data['Content']	= maybe_serialize(array(
				'Status'		=> (string)$postObj->Status,
				'TotalCount'	=> (string)$postObj->TotalCount,
				'FilterCount'	=> (string)$postObj->FilterCount,
				'SentCount'		=> (string)$postObj->SentCount,
				'ErrorCount'	=> (string)$postObj->ErrorCount
			));	
		}elseif($Event == 'scancode_push' || $Event == 'scancode_waitmsg'){
			$ScanCodeInfo 		= $postObj->ScanCodeInfo;
			$data['Title']		= $ScanCodeInfo->ScanType;
			$data['Content']	= $ScanCodeInfo->ScanResult;
		}elseif($Event == 'location_select'){
			$SendLocationInfo	= $postObj->SendLocationInfo;
			$weixin_location	= array(
				'Location_X'	=>	(string)$postObj->Location_X,
				'Location_Y'	=>	(string)$postObj->Location_Y,
				'Scale'			=>	(string)$postObj->Scale,
				'Label'			=>	(string)$postObj->Label,
				'Poiname'		=>	(string)$postObj->Poiname,
			);
			$data['content']	= maybe_serialize($weixin_location);
			wp_cache_set($weixin_openid, $weixin_location, 'weixin_location', 600);	// 缓存用户地理位置信息
		}elseif($Event == 'pic_sysphoto' || $Event == 'pic_photo_or_album' || $Event == 'pic_weixin'){
			$SendPicsInfo		= $postObj->SendPicsInfo;
			$Count 				= $SendPicsInfo->Count;
			$PicList			= $SendPicsInfo->PicList;
		}elseif ($Event == 'card_not_pass_check' || $Event == 'card_pass_check') {
			$data['EventKey']	= $postObj->CardId;
		}elseif ($Event == 'user_get_card') {
			$data['EventKey']	= $postObj->CardId;
			$data['Title']		= $postObj->UserCardCode;
			$data['MediaId']	= $postObj->OuterId;
			$data['Url']		= $postObj->IsGiveByFriend;
			$weixin_card	= array(
				'FriendUserName'	=>	(string)$postObj->FriendUserName,
				'OldUserCardCode'	=>	(string)$postObj->OldUserCardCode,
			);
			$data['content']	= maybe_serialize($weixin_card);
		}elseif ($Event == 'user_del_card') {
			$data['EventKey']	= $postObj->CardId;
			$data['Title']		= $postObj->UserCardCode;
		}elseif ($Event == 'user_view_card') {
			$data['EventKey']	= $postObj->CardId;
			$data['Title']		= $postObj->UserCardCode;
		}elseif ($Event == 'user_enter_session_from_card') {
			$data['EventKey']	= $postObj->CardId;
			$data['Title']		= $postObj->UserCardCode;
		}elseif ($Event == 'user_consume_card') {
			$data['EventKey']	= $postObj->CardId;
			$data['Title']		= $postObj->UserCardCode;
			$data['MediaId']	= $postObj->ConsumeSource;
			$weixin_card	= array(
				'OutTradeNo'	=>	(string)$postObj->OutTradeNo,
				'TransId'		=>	(string)$postObj->TransId,
				'LocationName'	=>	(string)$postObj->LocationName,
				'StaffOpenId'	=>	(string)$postObj->StaffOpenId,
			);
			$data['content']	= maybe_serialize($weixin_card);
		}elseif($Event == 'submit_membercard_user_info'){
			$data['EventKey']	= $postObj->CardId;
			$data['Title']		= $postObj->UserCardCode;
		}elseif ($Event == 'wificonnected') {
			$data['EventKey']	= $postObj->PlaceId;
			$data['Title']		= $postObj->DeviceNo;
			$data['MediaId']	= $postObj->ConnectTime;
			$weixin_wificonnected	= array(
				'ExpireTime'	=>	(string)$postObj->ExpireTime,
				'VendorId'		=>	(string)$postObj->VendorId,
			);
			$data['content']	= maybe_serialize($weixin_wificonnected);
		}elseif ($Event == 'shakearoundusershake') {
			$data['Title']		= maybe_serialize($postObj->ChosenBeacon);
			$data['Content']	= maybe_serialize($postObj->AroundBeacons);
		}elseif ($Event == 'poi_check_notify') {
			$data['EventKey']	= $postObj->UniqId;
			$data['Title']		= $postObj->PoiId;
			$data['MediaId']	= $postObj->Result;
			$data['Content']	= $postObj->Msg;
		}elseif($Event == 'qualification_verify_success' || $Event == 'naming_verify_success' || $Event == 'annual_renew' || $Event == 'verify_expired'){
			$data['Title']		= $postObj->ExpiredTime;
		}elseif($Event == 'qualification_verify_fail' || $Event == 'naming_verify_fail'){
			$data['Title']		= $postObj->FailTime;
			$data['Content']	= $postObj->FailReason;
		}elseif($Event == 'kf_create_session' || $Event == 'kf_close_session'){
			$data['Title']		= $postObj->KfAccount;
		}elseif($Event == 'kf_switch_session' || $Event == 'kf_close_session'){
			$data['Title']		= $postObj->FromKfAccount;
			$data['Content']	= $postObj->ToKfAccount;
		}
	}
	
	global $wpdb;
	$wpdb->insert($wpdb->weixin_messages,$data); 
	return $wpdb->insert_id;
}


add_action('plugins_loaded', 'weixin_robot_set_delete_messages_cron');
function weixin_robot_set_delete_messages_cron(){
	if(!wpjam_is_scheduled_event('weixin_delete_messages')) {
		
		$today		= date('Y-m-d', current_time('timestamp'));
		$r_min_1	= rand(0,5);
		$r_min_2	= rand(0,9);
		$r_sec		= rand(0,5);

		$time	= strtotime($today.' 00:'.$r_min_1.$r_min_2.':'.$r_sec.'0 +0800');

		wp_schedule_event( $time, 'daily', 'weixin_delete_messages' );
	}
}

add_action( 'weixin_delete_messages', 'weixin_robot_delete_messages', 10, 1 );
function weixin_robot_delete_messages(){
	global $wpdb;
	$timestamp	= time() - MONTH_IN_SECONDS*3;
	$wpdb->query("DELETE FROM {$wpdb->weixin_messages} WHERE CreateTime < {$timestamp}");	
}

// 记录群发信息
// function weixin_robot_insert_masssendjob($postObj){
// 	if(!is_object($postObj)) return 0;

// 	global $wpdb;

// 	$data = array(
// 		'MsgId'			=>	isset($postObj->MsgId)?$postObj->MsgId:(isset($postObj->MsgID)?$postObj->MsgID:''),
// 		'CreateTime'	=>	$postObj->CreateTime,
// 		'Status'		=>	$postObj->Status,
// 		'TotalCount'	=>	$postObj->TotalCount,
// 		'FilterCount'	=>	$postObj->FilterCount,
// 		'SentCount'		=>	$postObj->SentCount,
// 		'ErrorCount'	=>	$postObj->ErrorCount
// 	);

// 	$wpdb->insert($wpdb->weixin_masssendjobs,$data); 
// 	return $wpdb->insert_id;
// }