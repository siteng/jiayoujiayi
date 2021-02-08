<?php
class WEIXIN_CRON{
	public static function delete_old_messages(){
		if(!class_exists('WEIXIN_Message')){
			include_once(WEIXIN_ROBOT_PLUGIN_DIR.'includes/class-weixin-message.php');
		}

		WEIXIN_Message::Query()->where_lt('CreateTime', (time()-MONTH_IN_SECONDS))->delete();
	}

	public static function cron_get_user_list($next_openid=''){
		if(weixin_get_type() < 3 ) return;

		if($next_openid == ''){
			WEIXIN_User::Query()->update(array('subscribe'=>0));	// 第一次抓取将所有的用户设置为未订阅
		}

		$response = weixin()->get_user_list($next_openid);

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
			$datas	= array_map(function($openid){
				return array('openid'=>$openid, 'subscribe'=>1);
			}, $response['data']['openid']);

			WEIXIN_User::insert_multi($datas);
		}

		if(!is_admin()){
			exit;
		}
	}

	public static function cron_get_users($i=0){
		if(weixin_get_type() < 3 ) return;

		$openids	= WEIXIN_User::Query()->where('subscribe',1)->where_lt('last_update', time()-MONTH_IN_SECONDS*6)->limit(100)->get_col('openid');

		if($openids){
			if(count($openids) > 90){	// 如果有大量的用户，就再抓一次咯
				$i++;
				wp_schedule_single_event(time()+10,'weixin_get_users',array($i));
			}else{
				update_option('weixin_'.weixin_get_appid().'_users_sync', time());
			}

			$users = weixin()->batch_get_user_info($openids);	// 只要一个没有，或者太久，就全部到微信服务器取一下，反正都是一次 http request 

			if(is_wp_error($users) && $users->get_error_code() == '40003'){	// 突然有用户取消关注
				exit;
			}

			if(!is_wp_error($users) && $users && isset($users['user_info_list'])){
				$users	= $users['user_info_list'];
				$users	= array_map(['WEIXIN_User','sanitize'], $users);

				if($subscribe_users	= array_filter($users, function($user){ return $user['subscribe']; })){
					WEIXIN_User::insert_multi($subscribe_users);
				}

				if($unsubscribe_users	= array_filter($users, function($user){ return !$user['subscribe']; })){
					WEIXIN_User::insert_multi($unsubscribe_users);
				}
			}
		}else{
			update_option('weixin_'.weixin_get_appid().'_users_sync', time());
			
			WPJAM_Notice::add(array(
				'type'		=> 'success',
				'notice'	=> '用户信息同步成功！',
			));
		}

		if(!is_admin()){
			exit;
		}
	}
}

add_action('init', function(){
	if(wp_using_ext_object_cache()){
		wpjam_register_cron(['WEIXIN_CRON', 'delete_old_messages'], ['day'=>0]);
	}else{
		add_action('weixin_delete_messages', ['WEIXIN_CRON', 'delete_old_messages']);

		if(!wpjam_is_scheduled_event('weixin_delete_messages')) {
			$time	= strtotime(get_gmt_from_date(current_time('Y-m-d').' 02:00:00')) + rand(0,7200);
			wp_schedule_event($time, 'twicedaily', 'weixin_delete_messages');
		}
	}

	add_action('weixin_get_user_list',	['WEIXIN_CRON', 'cron_get_user_list']);
	add_action('weixin_get_users',		['WEIXIN_CRON', 'cron_get_users']);
});