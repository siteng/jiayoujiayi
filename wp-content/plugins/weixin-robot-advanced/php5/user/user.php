<?php 
// 获取微信用户资料，
// 1. 首先从数据里面获取，
// 2. 如果数据库里没有，认证公众号就从微信服务器获取。
function weixin_robot_get_user($weixin_openid='', $args=array()){
	global $wpdb;

	extract(wp_parse_args( $args, array('force'=>false, 'oauth'=>false) ));

	if(!$weixin_openid ) $weixin_openid = weixin_robot_get_user_openid();			// 如果没有提供 weixin_openid 从 cookie 里面获取
	if(!$weixin_openid || strlen($weixin_openid) < 28 || strlen($weixin_openid) > 34)  return false;

	$force			= $force && (WEIXIN_TYPE >=3);									// 只有认证订阅号和认证服务号才有 force 选项

	if($force || $oauth){
		weixin_robot_update_users_subscribe();										// 把内存中的用户信息赶快先更新到数据库中，不然会获取错误
	}

	if($force){
		$weixin_user	= false;													// 如果强制从微信服务器获取，就不从缓存中获取
	}else{
		$weixin_user	= wp_cache_get($weixin_openid,'weixin_user'); 
	}

	if($weixin_user === false){
		$weixin_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->weixin_users} WHERE openid=%s",$weixin_openid),ARRAY_A);

		if($weixin_user){
			// if(WEIXIN_TYPE >= 3 && $weixin_user['subscribe'] && ( current_time('timestamp') - $weixin_user['last_update'] > 86400*10 || $force ) ) { 
			// if(WEIXIN_TYPE >= 3 && $weixin_user['subscribe'] && $force) { 
			if(WEIXIN_TYPE >= 3 && $weixin_user['subscribe'] && $force) { 
				if($weixin_user = weixin_robot_get_user_info($weixin_openid)){

					$wpdb->update($wpdb->weixin_users, $weixin_user, array('openid'=>$weixin_openid));
				}
			}
		}else{
			if(WEIXIN_TYPE >= 3){
				if($oauth){	// 通过网页授权的用户，一定要先直接插入
					$weixin_user = array('subscribe' => 0, 'openid'=>trim($weixin_openid));
					$wpdb->insert($wpdb->weixin_users, $weixin_user);
					// trigger_error($wpdb->last_query);
				}else{
					$weixin_user_lock = wp_cache_get($weixin_openid, 'weixin_user_lock');
					if($weixin_user_lock === false){
						wp_cache_set($weixin_openid, 1, 'weixin_user_lock', 15);	// 15 秒的内存锁
						if(($weixin_user = weixin_robot_get_user_info($weixin_openid)) && ($weixin_user['subscribe'] == 1)){
							$wpdb->insert($wpdb->weixin_users, $weixin_user);
						}
					}else{
						return array('subscribe' => 0, 'openid'=>trim($weixin_openid) ); 
					}
				}
			}else{
				$weixin_user = array('subscribe' => 0, 'openid'=>trim($weixin_openid) ); 
				$wpdb->insert($wpdb->weixin_users, $weixin_user);
			}
		}

		$weixin_user	= $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->weixin_users} WHERE openid=%s",$weixin_openid),ARRAY_A);
		$weixin_user	= ($weixin_user)?$weixin_user:array();
		wp_cache_set($weixin_openid, $weixin_user, 'weixin_user',36000);
		
	}
	return $weixin_user;
}

// 更新微信用户
function weixin_robot_update_user($weixin_openid, $weixin_user){ 
	global $wpdb;

	// $old_user = weixin_robot_get_user($weixin_openid);

	// if($old_user){
	// 	$weixin_user = wp_parse_args($weixin_user,$old_user);
	// 	$wpdb->update($wpdb->weixin_users,$weixin_user,array('openid'=>$weixin_openid));
	// }

	if($weixin_user){
		$wpdb->update($wpdb->weixin_users,$weixin_user,array('openid'=>$weixin_openid));
		wp_cache_delete($weixin_openid, 'weixin_user');

		// trigger_error($wpdb->last_query);

		return true;
	}
}

// 用户订阅和取消订阅的时候，快速更新用户的订阅状态
function weixin_robot_update_user_subscribe($weixin_openid, $weixin_user){
	// 异步更新用户详细资料和
	// wp_remote_post( home_url('/api/weixin_user.json?weixin_openid='.$weixin_openid), array(
	// 	'timeout'   => 0.01,
	// 	'blocking'  => false,
	// 	'headers' 	=> array('Accept-Encoding'=>''),
	// 	'body'		=> $weixin_user,
	// ) );

	global $wpdb;
	// $weixin_user['last_update']	= 0;
	// $weixin_user['openid']		= $weixin_openid;

	$weixin_update_users	= wp_cache_get('weixin_update_users', 'weixin_users');
	$weixin_update_users	= ($weixin_update_users === false)?array('time'=>time(),'users'=>array()):$weixin_update_users;
	
	$weixin_update_users['users'][$weixin_openid]	= $weixin_user;

	if(count($weixin_update_users['users']) <10 && (time()-$weixin_update_users['time'] < 120)){
		wp_cache_set('weixin_update_users', $weixin_update_users, 'weixin_users', 3600);
	}else{	// 达到了 10 个用户或者过了2分钟再去写数据库，
		weixin_robot_update_users_subscribe();
	}
}

function weixin_robot_update_users_subscribe(){
	global $wpdb;

	$weixin_update_users	= wp_cache_get('weixin_update_users', 'weixin_users');
	wp_cache_delete('weixin_update_users', 'weixin_users');

	if($weixin_update_users['users']){
		foreach ($weixin_update_users['users'] as $weixin_openid=> $weixin_user) {

			$weixin_user['unsubscribe_time']	= isset($weixin_user['unsubscribe_time'])?$weixin_user['unsubscribe_time']:0;

			$wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->weixin_users` (`openid`, `subscribe`, `unsubscribe_time`) VALUES (%s, %d, %d) ON DUPLICATE KEY UPDATE `subscribe` = VALUES(`subscribe`), `unsubscribe_time` = VALUES(`unsubscribe_time`)", $weixin_openid, $weixin_user['subscribe'], $weixin_user['unsubscribe_time'] ) );

			// if($wpdb->query($wpdb->prepare("SELECT openid FROM {$wpdb->weixin_users} WHERE openid=%s",$weixin_openid))){
			// 	$wpdb->update($wpdb->weixin_users, $weixin_user, array('openid'=>$weixin_openid));
			// }else{
			// 	$weixin_user_lock = wp_cache_get($weixin_openid, 'weixin_user_lock');
			// 	if($weixin_user_lock === false){
			// 		wp_cache_set($weixin_openid, 1, 'weixin_user_lock', 15);	// 15 秒的内存锁
			// 		$weixin_user['openid']	= $weixin_openid;
			// 		$wpdb->insert($wpdb->weixin_users, $weixin_user);
			// 	}
			// }

			wp_cache_delete($weixin_openid, 'weixin_user');
		}

		if(!wpjam_is_scheduled_event('weixin_get_users')){	// 10分钟，再去扫描用户详细资料
			wp_schedule_single_event(time()+600,'weixin_get_users',array(1000000));
		}
	}
}

// 获取 user query key，并且提供 hook 统一设置
function weixin_robot_get_user_query_key(){
	$weixin_user_query_key = apply_filters('weixin_user_query_key','weixin_user_id');
	if(is_multisite()){
		$weixin_user_query_key = $weixin_user_query_key.'_'.get_current_blog_id();
	}
	return $weixin_user_query_key;
}

// 设置 user query cookie
function weixin_robot_set_query_cookie($query_id=''){
	$query_key 	= weixin_robot_get_user_query_key();
	if(WEIXIN_TYPE != 4 && empty($query_id)){
		$query_id = (!empty($_GET[$query_key]))?$_GET[$query_key]:'';
	}
	if($query_id){
		$expire	= time() + WEEK_IN_SECONDS;
		weixin_robot_set_cookie($query_key, $query_id, $expire);
	}
}

function weixin_robot_set_cookie($key, $value, $expire){
	$secure_logged_in_cookie = 'https' === parse_url( get_option( 'home' ), PHP_URL_SCHEME );

	setcookie($key, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
    if ( COOKIEPATH != SITECOOKIEPATH ){
        setcookie($key, $value, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
    }
    $_COOKIE[$key] = $value;
}

// 获取当前用户的 query id，或者根据 weixin openid 算出 query id
function weixin_robot_get_user_query_id($weixin_openid=''){
	if($weixin_openid){
		$weixin_robot_user_md5 = apply_filters('weixin_robot_user_md5','weixin');
	    $check = substr(md5($weixin_robot_user_md5.$weixin_openid),0,2);
	    return $check . $weixin_openid;
	}else{
		$query_key = weixin_robot_get_user_query_key();

		if(WEIXIN_TYPE != 4 && isset($_GET[$query_key])){
			return $_GET[$query_key];
		}elseif(isset($_COOKIE[$query_key])){
			return $_COOKIE[$query_key];
		}else{
			return '';
		}	
	}
}

// 获取当前用户的 openid，或者根据 query_id 算出 weixin openid
function weixin_robot_get_user_openid($query_id=''){
	if(!$query_id){
		$query_id = weixin_robot_get_user_query_id();
	}

	if(!$query_id){
		return false;
	}
	
    $weixin_openid = substr($query_id, 2);
    if($query_id == weixin_robot_get_user_query_id($weixin_openid)){
        return $weixin_openid;
    }else{
        return false;
    }
}

// 获取用户的最新的地理位置并缓存10分钟。
function weixin_robot_get_user_location($weixin_openid, $from='cache', $timespan='3600'){
	$location = wp_cache_get($weixin_openid,'weixin_location');
	if($location === false || $from != 'cache'){
		global $wpdb;

		$timestamp	= time() - $timespan;

		$sql = $wpdb->prepare("SELECT  Content FROM {$wpdb->weixin_messages} WHERE Content != '' AND (MsgType='Location' OR (MsgType ='Event' AND Event='LOCATION')) AND FromUserName=%s AND CreateTime>%d ORDER BY CreateTime DESC LIMIT 0,1;",$weixin_openid,$timestamp);
		// file_put_contents(WP_CONTENT_DIR.'/debug/location.log',$sql);

		$location	= $wpdb->get_var($sql);
		$location	= maybe_unserialize($location);
		wp_cache_set($weixin_openid, $location, 'weixin_location', 600);
	}
	return $location;
}