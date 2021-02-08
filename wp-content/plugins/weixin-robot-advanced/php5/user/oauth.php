<?php
// 发起 OAuth 请求
function weixin_robot_make_oauth_request(){
	session_start();

	if(isset($_GET['code']) && isset($_GET['state']) ){		// 微信 OAuth 请求

		if($_GET['code'] == 'authdeny'){
			wp_die('用户拒绝');
		}

		if(isset($_SESSION['weixin_scope'])){

			if (!wp_verify_nonce($_GET['state'], $_SESSION['weixin_scope'] ) ) {
				wp_die("非法操作");
			}

			if(isset($_SESSION['weixin_openid']) && $_SESSION['weixin_scope'] != 'snsapi_userinfo'){
				return;
			}

			$weixin_user_oauth	= weixin_robot_get_oauth_access_token($_GET['code']);

			if($weixin_user_oauth){
				$_SESSION['weixin_openid']	= $weixin_user_oauth['openid'];
				$query_id 	= weixin_robot_get_user_query_id($weixin_user_oauth['openid']);
				weixin_robot_set_query_cookie($query_id);			// 已经获取了 openid

				if($_SESSION['weixin_scope'] == 'snsapi_userinfo'){	// 获取用户详细信息
					weixin_robot_oauth_update_user($weixin_user_oauth);
				}

				wp_redirect(weixin_robot_get_current_page_url($for_oauth=true));
				exit;
			}
		}else{
			print_r("dfasdf");exit;
		}
	}elseif(isset($_GET['get_userinfo'])) {		// 发起获取用户信息的 OAuth 请求
		weixin_robot_oauth_redirect('snsapi_userinfo');
	}else{										// 任何页面如果不能从 cookie 中获取 open_id，通过 Oauth 2.0 获取
		weixin_robot_oauth_redirect();
	}
}

// 获取微信 Oauth 跳转链接 
function weixin_robot_oauth_redirect($scope='snsapi_base', $redirect_uri=''){
	if($weixin_openid = weixin_robot_get_user_openid()){
		if($scope == 'snsapi_base'){
			return;
		}elseif($scope == 'snsapi_userinfo'){
			$weixin_user = weixin_robot_get_user($weixin_openid);
			if($weixin_user && isset($weixin_user['last_update']) && $weixin_user['nickname'] && ( current_time('timestamp') - $weixin_user['last_update'] < 3600 ) ){
				return;
			}

			$weixin_user_oauth = wp_cache_get($weixin_openid, 'weixin_user_oauth');
			if($weixin_user_oauth !== false){
				if($weixin_user_oauth['expires_in'] > current_time('timestamp')){	// 内存中如果还有 access token，则继续使用
					weixin_robot_oauth_update_user($weixin_user_oauth);
					return;
				}else{
					$refresh_token		= $weixin_user_oauth['refresh_token'];	
					$weixin_user_oauth	= weixin_robot_refresh_oauth_access_token($weixin_openid, $refresh_token); // 	刷新 access token
					if(is_wp_error($weixin_user_oauth)){	// 
						print_r($weixin_user_oauth);
						exit;
					}else{
						weixin_robot_oauth_update_user($weixin_user_oauth);
						return;
					}
				}
			}
		}	
	}

	if(isset($_SESSION['weixin_openid'])){
		unset($_SESSION['weixin_openid']);
	}

	wp_redirect(weixin_robot_get_oauth_redirect($scope, $redirect_uri));
	exit;
}

// 获取微信 Oauth 跳转链接 
function weixin_robot_get_oauth_redirect($scope='snsapi_base', $redirect_uri=''){
	$_SESSION['weixin_scope'] = $scope;

	$redirect_uri	= ($redirect_uri)?$redirect_uri : weixin_robot_get_current_page_url($for_oauth=true);
	$state			= wp_create_nonce($scope);

	return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.WEIXIN_APPID.'&redirect_uri='.urlencode($redirect_uri).'&response_type=code&scope='.$scope.'&state='.$state.'#wechat_redirect';
}

// 使用 Oauth 详细新更新用户数据
function weixin_robot_oauth_update_user($weixin_user_oauth){

	$access_token	= $weixin_user_oauth['access_token'];
	$weixin_openid	= $weixin_user_oauth['openid'];

	$oauth_userifo	= weixin_robot_get_oauth_userifo($weixin_openid, $access_token);

	// trigger_error(var_export($oauth_userifo,true));

	if(is_wp_error($oauth_userifo)){
		return $oauth_userifo;
	}


	$weixin_user	= weixin_robot_get_user($weixin_openid, array('oauth'=>true,'force'=>true));

	// trigger_error(var_export($weixin_user,true));

	//file_put_contents(WP_CONTENT_DIR.'/debug/weixin.log',var_export($weixin_user,true));

	$weixin_user['nickname']	= $oauth_userifo['nickname'];
	$weixin_user['sex']			= $oauth_userifo['sex'];
	$weixin_user['province']	= $oauth_userifo['province'];
	$weixin_user['city']		= $oauth_userifo['city'];
	$weixin_user['country']		= $oauth_userifo['country'];
	$weixin_user['headimgurl']	= $oauth_userifo['headimgurl'];
	$weixin_user['privilege']	= serialize($oauth_userifo['privilege']);

	if(isset($oauth_userifo['unionid'])){
		$weixin_user['unionid']	= $oauth_userifo['unionid'];
	}elseif(isset($weixin_user_oauth['unionid'])){
		$weixin_user['unionid']	= $weixin_user_oauth['unionid'];
	}

	weixin_robot_update_user($weixin_openid, $weixin_user);

}

// 获取微信 Oauth Access Token
function weixin_robot_get_oauth_access_token($code){
	$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.WEIXIN_APPID.'&secret='.WEIXIN_APP_SECRET.'&code='.$code.'&grant_type=authorization_code';
	
	$weixin_user_oauth	= weixin_robot_remote_request($url, 'get', '', array('need_access_token'=>false));
	$weixin_user_oauth	= weixin_robot_handle_oauth_access_token($weixin_user_oauth);

	return $weixin_user_oauth;
}

function weixin_robot_refresh_oauth_access_token($weixin_openid, $refresh_token){
	$url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.WEIXIN_APPID.'&grant_type=refresh_token&refresh_token='.$refresh_token;
	
	$weixin_user_oauth	= weixin_robot_remote_request($url, 'get', '', array('need_access_token'=>false));
	$weixin_user_oauth	= weixin_robot_handle_oauth_access_token($weixin_user_oauth);
	
	if(!$weixin_user_oauth){
		wp_cache_delete($weixin_openid, 'weixin_user_oauth');
	}
	return $weixin_user_oauth;
}

function weixin_robot_handle_oauth_access_token($weixin_user_oauth){
	if(isset($_GET['test'])){
		wpjam_print_r($weixin_user_oauth);
	}
	if(!is_wp_error($weixin_user_oauth) && isset($weixin_user_oauth['openid'])){
		if(strpos($weixin_user_oauth['scope'], 'snsapi_userinfo')  !== false){
			$weixin_user_oauth['expires_in']	= $weixin_user_oauth['expires_in'] + current_time('timestamp') - 600;
			wp_cache_set($weixin_user_oauth['openid'], $weixin_user_oauth, 'weixin_user_oauth', DAY_IN_SECONDS*7);
		}
		return $weixin_user_oauth;
	}else{
		return false;
	}
}

// 获取微信 OAuth 用户详细信息
function weixin_robot_get_oauth_userifo($weixin_openid, $access_token){
	$url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$weixin_openid.'&lang=zh_CN';
	return weixin_robot_remote_request($url,'get','',array('need_access_token'=>false));
}

// 更新数据库中的用户 Oauth 信息
// function weixin_robot_update_user_oauth($weixin_openid,$weixin_user_oauth){ 
// 	global $wpdb;

// 	$old_user_oauth = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->weixin_user_oauth} WHERE weixin_openid=%s",$weixin_openid),ARRAY_A);

// 	if($old_user_oauth){
// 		$wpdb->update($wpdb->weixin_user_oauth,$weixin_user_oauth,array('weixin_openid'=>$weixin_openid));
// 		wp_cache_delete($weixin_openid, 'weixin_user_oauth');
// 	}else{
// 		$weixin_user_oauth['weixin_openid'] = $weixin_openid;
// 		$wpdb->insert($wpdb->weixin_user_oauth,$weixin_user_oauth);
// 	}
// }
