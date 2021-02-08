<?php
function weixin_get_oauth_access_token($code=''){
	if($code){
		$oauth_access_token	= weixin()->get_oauth_access_token($code);

		if(is_wp_error($oauth_access_token)){
			return $oauth_access_token;
		}

		$openid	= $oauth_access_token['openid'];
	}else{
		$openid	= weixin_get_current_openid();

		if(is_wp_error($openid)){
			return $openid;
		}

		$oauth_access_token	= weixin()->get_oauth_access_token_by_openid($openid);

		if($oauth_access_token === false){
			return $oauth_access_token;
		}
	}

	if($oauth_access_token['scope'] == 'snsapi_userinfo'){
		$weixin_user	= WEIXIN_User::get($openid);

		if(empty($weixin_user) || $weixin_user['last_update'] < time() - DAY_IN_SECONDS){
			$oauth_userinfo	= weixin()->get_oauth_userinfo($openid, $oauth_access_token['access_token']);

			if(!is_wp_error($oauth_userinfo)){
				WEIXIN_User::sync_by_oauth($oauth_userinfo);	
			}
		}
	}

	$setcookie	= !(weixin_get_type() == 4 && wpjam_is_json_request()); 

	WEIXIN_User::generate_access_token($openid, $setcookie);

	return $oauth_access_token;
}

function weixin_has_oauth_access_token($scope='snsapi_base'){
	$oauth_access_token	= weixin_get_oauth_access_token();

	if(is_wp_error($oauth_access_token)){
		return false;
	}

	if($scope == 'snsapi_userinfo'){
		return $oauth_access_token['scope'] == 'snsapi_userinfo';
	}else{
		return true;
	}
}

function weixin_oauth_request($scope='snsapi_userinfo'){
	global $weixin_did_oauth;
	
	if(isset($weixin_did_oauth)){	// 防止重复请求
		return;
	}
		
	$weixin_did_oauth	= true;

	if(!empty($_GET['scope'])){
		$scope	= $_GET['scope'];
	}

	if(!in_array($scope, ['snsapi_userinfo', 'snsapi_base']) || weixin_has_oauth_access_token($scope)){
		return;
	}

	$redirect_url	= remove_query_arg(['code', 'state', 'scope', 'get_openid', 'weixin_oauth', 'nsukey'], wpjam_get_current_page_url());

	if(isset($_GET['code']) && isset($_GET['state']) && isset($_GET['scope'])){		// 微信 OAuth 请求

		if($_GET['code'] == 'authdeny'){
			wp_die('用户拒绝');
		}

		if(!wp_verify_nonce($_GET['state'], $scope)){
			wp_die("非法操作");
		}		

		$oauth_access_token	= weixin_get_oauth_access_token($_GET['code']);

		if(is_wp_error($oauth_access_token)){
			wp_die($oauth_access_token);
		}
	}else{
		$redirect_url	= add_query_arg(compact('scope'), $redirect_url);
		$redirect_url	= weixin()->get_oauth_redirect_url($scope, $redirect_url);
	}

	wp_redirect($redirect_url);

	exit;
}