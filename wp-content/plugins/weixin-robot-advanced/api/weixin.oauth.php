<?php
$weixin_appid = weixin_get_appid();

if(empty($weixin_appid)){
	wpjam_send_json(['errcode'=>'empty_appid', 'errmsg'=>'公众号 appid 为空']);
}

$code	= wpjam_get_parameter('code',	['method'=>'REQUEST',	'required'=>true]);
// $state	= wpjam_get_parameter('state',	['method'=>'POST',	'required'=>true]);
// $scope	= wpjam_get_parameter('scope',	['method'=>'POST',	'default'=>'snsapi_userinfo']);

// if(!wp_verify_nonce($scope, $scope)){
// 	wp_die("非法操作");
// }

$oauth_access_token	= weixin()->get_oauth_access_token($code);

if(is_wp_error($oauth_access_token)){
	wpjam_send_json($oauth_access_token);
}

$openid	= $oauth_access_token['openid'];

if($oauth_access_token['scope'] == 'snsapi_userinfo'){
	$weixin_user	= WEIXIN_User::get($openid);

	if(empty($weixin_user) || $weixin_user['last_update'] < time() - DAY_IN_SECONDS){
		$oauth_userinfo	= weixin()->get_oauth_userinfo($openid, $oauth_access_token['access_token']);

		if(!is_wp_error($oauth_userinfo)){
			WEIXIN_User::sync_by_oauth($oauth_userinfo);	
		}
	}
}

$user	= WEIXIN_User::parse_for_json($openid);

do_action('weixin_user_signuped', $user);

wpjam_send_json([
	'errcode'		=> 0,
	'access_token'	=> WEIXIN_User::generate_access_token($openid),
	'expired_in'	=> DAY_IN_SECONDS - 600,
	'user'			=> $user,
]);