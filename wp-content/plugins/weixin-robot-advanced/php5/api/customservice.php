<?php
function weixin_robot_customservice_add_kf_account($data){
	$url	= 'https://api.weixin.qq.com/customservice/kfaccount/add';
	return weixin_robot_remote_request($url,'post',wpjam_json_encode($data));
}

function weixin_robot_customservice_update_kf_account($data){
	$url	= 'https://api.weixin.qq.com/customservice/kfaccount/update';
	return weixin_robot_remote_request($url,'post',wpjam_json_encode($data));
}

function weixin_robot_customservice_delete_kf_account($kf_account){
	$url	= 'https://api.weixin.qq.com/customservice/kfaccount/del?kf_account='.urldecode($kf_account);
	return weixin_robot_remote_request($url);
}

function weixin_robot_customservice_invite_kf_account_worker($kf_account, $invite_wx){
	$url	= 'https://api.weixin.qq.com/customservice/kfaccount/inviteworker';
	return weixin_robot_remote_request($url,'post',wpjam_json_encode(compact('kf_account','invite_wx')));
}

function weixin_robot_customservice_upload_kf_account_headimg($kf_account, $media){
	$url	= 'https://api.weixin.qq.com/customservice/kfaccount/uploadheadimg?kf_account='.urldecode($kf_account);
	$data	= array('media'=> curl_file_create($media));
	return weixin_robot_remote_request($url,'file', $data);
}

function weixin_robot_customservice_get_kf_list(){
	$weixin_kf_list	= get_transient('weixin_kf_list');
	if($weixin_kf_list === false){
		$url		= 'https://api.weixin.qq.com/cgi-bin/customservice/getkflist';
		$response	= weixin_robot_remote_request($url);
		if(is_wp_error($response)){
			set_transient( 'weixin_kf_list', array(), 60 );
			return false;
		}else{
			$weixin_kf_list = $response['kf_list'];

			set_transient( 'weixin_kf_list', $weixin_kf_list, 3600 );
		}
	}

	if($weixin_kf_list){
		$new_weixin_kf_list = array();
		foreach ($weixin_kf_list as $weixin_kf_account) {
			$weixin_kf_account['online_status']	= '';
			// $weixin_kf_account['auto_accept']	= '';
			$weixin_kf_account['accepted_case']	= '';
			$new_weixin_kf_list[$weixin_kf_account['kf_account']] = $weixin_kf_account;
		}

		$online_status = array(
			'1'	=> 'Web在线',
			'2'	=> '手机在线',
			'3'	=> 'Web和手机同时在线'
		);

		if($weixin_online_kf_list = weixin_robot_customservice_get_online_kf_list()){
			foreach ($weixin_online_kf_list as $weixin_online_kf_acount) {
				$account 	= $weixin_online_kf_acount['kf_account'];
				$new_weixin_kf_list[$account]['online_status']	= $online_status[$weixin_online_kf_acount['status']];
				// $new_weixin_kf_list[$account]['auto_accept']	= $weixin_online_kf_acount['auto_accept'];
				$new_weixin_kf_list[$account]['accepted_case']	= $weixin_online_kf_acount['accepted_case'];
			}
		}

		return $new_weixin_kf_list;
	}

	return false;
}

function weixin_robot_customservice_get_online_kf_list(){
	$weixin_online_kf_list = get_transient('weixin_online_kf_list');
	if($weixin_online_kf_list === false){
		$url		= 'https://api.weixin.qq.com/cgi-bin/customservice/getonlinekflist';
		$response	= weixin_robot_remote_request($url);
		if(is_wp_error($response)){
			set_transient( 'weixin_online_kf_list', array(), 30 );
			return false;
		}else{
			$weixin_online_kf_list = $response['kf_online_list'];
			set_transient( 'weixin_online_kf_list', $weixin_online_kf_list, 30 );
		}
	}

	return $weixin_online_kf_list;
}

function weixin_robot_customservice_create_kf_session($kf_account, $openid, $text=''){
	$url	= 'https://api.weixin.qq.com/customservice/kfsession/create';
	$data	= compact('kf_account', 'openid', 'text');

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data));
}

function weixin_robot_customservice_close_kf_session($kf_account, $openid, $text=''){
	$url	= 'https://api.weixin.qq.com/customservice/kfsession/close';
	$data	= compact('kf_account', 'openid', 'text');

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data));
}

function weixin_robot_customservice_get_kf_session($openid){
	$url	= 'https://api.weixin.qq.com/customservice/kfsession/getsession?openid='.$openid;

	return weixin_robot_remote_request($url);
}

function weixin_robot_customservice_get_kf_session_list($kf_account){
	$url	= 'https://api.weixin.qq.com/customservice/kfsession/getsessionlist?kf_account='.$kf_account;

	$response	= weixin_robot_remote_request($url);

	if(is_wp_error($response)){
		return $response;
	}

	return $response['sessionlist'];
}

function weixin_robot_customservice_get_kf_wait_case_session_list($kf_account){
	$url	= ' https://api.weixin.qq.com/customservice/kfsession/getwaitcase';

	return weixin_robot_remote_request($url);
}