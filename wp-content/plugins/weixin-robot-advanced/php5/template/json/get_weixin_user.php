<?php
$response = array();
$weixin_openid = isset($_GET['weixin_openid'])?$_GET['weixin_openid']:'';

if($weixin_openid){
	$response = weixin_robot_get_user($weixin_openid);

	if(!$response){
		$response = array('err'=>'无此微信用户');
	}
}else{
	if(is_weixin()){
		$weixin_openid	= weixin_robot_get_user_openid();
		$response 		= weixin_robot_get_user($weixin_openid);
		if(empty($response['nickname'])){
			wp_redirect(home_url('/api/get_weixin_user.json?get_userinfo'));
		}
	}else{
		$response = array('err'=>'weixin_openid 不能为空');
	}
}

wpjam_send_json($response);