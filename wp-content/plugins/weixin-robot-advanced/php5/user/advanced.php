<?php
// 从微信服务器获取用户详细资料，需要微信认证订阅号和服务号
function weixin_robot_get_user_info($openid){
	$url = 'https://api.weixin.qq.com/cgi-bin/user/info?openid='.urlencode($openid);
	$weixin_user = weixin_robot_remote_request($url);

	if(is_wp_error($weixin_user)){
		return false;
	}

	$weixin_user = weixin_robot_prepare_user($weixin_user);

	return $weixin_user;
}

function weixin_robot_prepare_user($weixin_user){

	if($weixin_user && $weixin_user['subscribe'] == 1){
		$weixin_user['nickname']	= wpjam_strip_invalid_text(substr($weixin_user['nickname'], 0, 254));
		$weixin_user['city']		= wpjam_strip_invalid_text(substr($weixin_user['city'], 0, 254));
		$weixin_user['province']	= wpjam_strip_invalid_text(substr($weixin_user['province'], 0, 254));
		$weixin_user['country']		= wpjam_strip_invalid_text(substr($weixin_user['country'], 0, 254));
	}

	$weixin_user['last_update']	= time();

	if(isset($weixin_user['tagid_list'])){
		$weixin_user['tagid_1']	= 0;
		$weixin_user['tagid_2']	= 0;
		$weixin_user['tagid_3']	= 0;
		
		$tagid_list = $weixin_user['tagid_list'];
		unset($weixin_user['tagid_list']);

		if($tagid_list){
			for ($i=0; $i <3 ; $i++) { 
				if(isset($tagid_list[$i])){
					$key = $i+1;
					$weixin_user['tagid_'.$key]	= $tagid_list[$i];
				}
			}
		}
	}

	return $weixin_user;

}


add_action('plugins_loaded', 'weixin_robot_set_get_user_list_cron');
function weixin_robot_set_get_user_list_cron(){
	if(!wpjam_is_scheduled_event('weixin_get_user_list')) {
		
		$today		= date('Y-m-d', current_time('timestamp'));
		$r_min_1	= rand(0,5);
		$r_min_2	= rand(0,9);
		$r_sec		= rand(0,5);

		$time	= strtotime($today.' 02:'.$r_min_1.$r_min_2.':'.$r_sec.'0 +0800');	//每天2点左右到服务抓取一遍数据

		wp_schedule_event( $time, 'daily', 'weixin_get_user_list' );
	}
}

// 从微信服务器获取关注用户列表
add_action( 'weixin_get_user_list', 'weixin_robot_get_user_list', 10, 1 );
function weixin_robot_get_user_list($next_openid = ''){
	if(WEIXIN_TYPE < 3 ) return;

	global $wpdb;

	if($next_openid == ''){
		// $wpdb->delete($wpdb->weixin_users, array(
		// 	'subscribe'			=> 0,
		// 	'subscribe_time'	=> 0,
		// 	'unsubscribe_time'	=> 0
		// ));

		$wpdb->query("UPDATE {$wpdb->weixin_users} set subscribe=0");	// 第一次抓取将所有的用户设置为未订阅
	}
	
	$url = 'https://api.weixin.qq.com/cgi-bin/user/get?next_openid='.$next_openid;

	$response = weixin_robot_remote_request($url);

	if(is_wp_error($response)){
		if($response->get_error_code() != '45009'){
			wp_schedule_single_event(time()+60,'weixin_get_user_list',array($next_openid));	// 失败了，就1分钟后再来一次	
		}
		return $response;
	}

	$next_openid	= $response['next_openid'];
	$count			= $response['count'];

	if($next_openid && $count > 0){
		wp_schedule_single_event(time()+10,'weixin_get_user_list',array($next_openid));
	}else{
		wp_schedule_single_event(time()+5,'weixin_get_users');
	}

	if($count){
		$openid_list		= $response['data']['openid'];
		$openid_list_str	= "'".implode("','", $openid_list)."'";

		$sql = "SELECT openid FROM {$wpdb->weixin_users} WHERE openid in ({$openid_list_str})";
		$have_openids = $wpdb->get_col($sql);
		
		if($have_openids){
			
			foreach ($have_openids as $openid) {
				wp_cache_delete($openid, 'weixin_user');
			}

			$have_openids_str = "'".implode("','", $have_openids)."'";
			$sql = "UPDATE {$wpdb->weixin_users} SET subscribe = 1 WHERE openid in ({$have_openids_str})";

			$wpdb->query($sql);
		}

		$new_openids = array_diff($openid_list, $have_openids);

		if($new_openids){
			foreach ($new_openids as $openid) {
				// $wpdb->insert($wpdb->weixin_users, array('subscribe'=>1,'openid'=>$openid));

				$wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->weixin_users` (`openid`, `subscribe`) VALUES (%s, 1) ON DUPLICATE KEY UPDATE `subscribe` = 1", $openid ) );

			}
		}
	}
}

add_action( 'weixin_get_users', 'weixin_robot_get_users',10,1);
function weixin_robot_get_users($i=0){
	if(WEIXIN_TYPE < 3 ) return;
	global $wpdb;

	$timestamp = current_time('timestamp') - DAY_IN_SECONDS*30;

	$sql = "SELECT openid FROM {$wpdb->weixin_users} WHERE subscribe = 1 AND (last_update < {$timestamp} OR subscribe_time=0) ORDER BY RAND() LIMIT 0, 100";
	// $sql = "SELECT openid FROM {$wpdb->weixin_users} WHERE subscribe = 1 AND last_update < {$timestamp} ORDER BY last_update ASC LIMIT 0, 100";
	$openids = $wpdb->get_col($sql);

	if(isset($_GET['debug'])){
		echo $sql;
		wpjam_print_r($openids);
	}

	if($openids){
		if(count($openids) > 90){	// 如果有大量的用户，就再抓一次咯
			$i++;
			wp_schedule_single_event(time()+10,'weixin_get_users',array($i));
		}
		weixin_robot_batch_get_user_info($openids);
	}else{
		// if($i < 1000000){
		// 	weixin_robot_get_tag_user_list(); // 最后把用户标签信息同步一次，不需要了，因为获取用户详细资料的接口已有标签信息
		// }
	}
}

function weixin_robot_batch_get_user_info($openid_list){
	global $wpdb;
	$url		= 'https://api.weixin.qq.com/cgi-bin/user/info/batchget';
	$user_list	= array();
	foreach ($openid_list as $openid) {
		$user_list[]	= array(
			'openid'	=> $openid,
			'lang'		=> 'zh_CN'
		);
	}

	$user_list		= array('user_list'=>$user_list);
	$weixin_users	= weixin_robot_remote_request($url, 'post', json_encode($user_list));

	// wpjam_print_r($weixin_users);

	if(is_wp_error($weixin_users)){
		return false;
	}
	
	if($weixin_users && isset($weixin_users['user_info_list'])){
		$weixin_users	= $weixin_users['user_info_list'];
		foreach ($weixin_users as $weixin_user) {
			$weixin_user = weixin_robot_prepare_user($weixin_user);
			$wpdb->update($wpdb->weixin_users, $weixin_user, array('openid'=>$weixin_user['openid']));
			wp_cache_delete($weixin_user['openid'], 'weixin_user');
		}
	}
}

// 修改用户备注名
function weixin_robot_update_user_remark($openid, $remark){
	$url	= 'https://api.weixin.qq.com/cgi-bin/user/info/updateremark';
	$post 	= array('openid'=>$openid, 'remark'=>urlencode($remark));

	$weixin_user = array('remark' => $remark);
	weixin_robot_update_user($openid, $weixin_user);

	return weixin_robot_remote_request($url, 'post', urldecode(json_encode($post)) );
}

function weixin_robot_can_send_user($openid){
	global $wpdb;
	if($wpdb->query($wpdb->prepare("SELECT id FROM {$wpdb->weixin_messages}  WHERE CreateTime > ".WEIXIN_CUSTOM_SEND_LIMIT." AND FromUserName = %s;",$openid))){
		return true;
	}else{
		return false;
	}
}

function weixin_robot_get_can_send_users(){
	// $can_send_users = wp_cache_get('can_send_users','openids');
	// if($can_send_users === false){
		global $wpdb;
		$can_send_users = $wpdb->get_col("SELECT FromUserName FROM {$wpdb->weixin_messages}  WHERE CreateTime > ".WEIXIN_CUSTOM_SEND_LIMIT." GROUP BY FromUserName;");
		// wp_cache_set('can_send_users', $can_send_users, 'openids', '20');
	// }
	return $can_send_users;
}

// 发送客服信息
function weixin_robot_send_user($openid, $content, $reply_type='text', $kf_account=''){
	if(empty($content)) return;
	$msgtype = $reply_type;

	
	wp_cache_set( $openid, 1, 'weixin_custom_mode', MINUTE_IN_SECONDS*10 );

	if($reply_type == 'img'){
		$counter = 0;

		$articles = $article	= array();

		$img_reply_query 		= new WP_Query(array('post__in'=>explode(',', $content),'orderby'=>'post__in','post_type'=>'any'));

		if($img_reply_query->have_posts()){
			while ($img_reply_query->have_posts()) {
				$img_reply_query->the_post();

				$article['title']		= apply_filters('weixin_title', get_the_title()); 
				$article['description']	= apply_filters('weixin_description', get_post_excerpt( '',apply_filters( 'weixin_description_length', 150 ) ) );
				$article['url']			= add_query_arg('weixin_openid', $openid, apply_filters('weixin_url', get_permalink()));

				if($counter == 0){
					$article['picurl'] = weixin_robot_get_post_thumb('', array(640,320));
				}else{
					$article['picurl'] = weixin_robot_get_post_thumb('', array(80,80));
				}
				$counter ++;
				$articles[] = $article;
			}
			$msgtype	= 'news';
			$content	= $articles;
		}
		wp_reset_query();
	}elseif($reply_type == 'img2'){
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
		$msgtype	= 'news';
		$content	= $articles;
	}elseif($reply_type == 'news'){
		$material	= weixin_robot_get_material($content, 'news');
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
			$msgtype	= 'news';
			$content	= $articles;
		}
	}

	return weixin_robot_send_custom_message($openid, $msgtype, $content, $kf_account);
}