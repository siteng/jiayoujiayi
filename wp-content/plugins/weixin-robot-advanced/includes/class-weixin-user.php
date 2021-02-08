<?php
wp_cache_add_global_groups('weixin_users');

class WEIXIN_User extends WPJAM_Model {
	use WEIXIN_Trait;

	public static function get($openid){
		if(!is_string($openid)){
			trigger_error(var_export($openid, true));
		}

		if(!$openid || strlen($openid) < 28 || strlen($openid) > 34){
			return false;
		}

		$weixin_user	= parent::get($openid);

		if($weixin_user){
			if($user_subscribes = self::cache_get('subscribes')){
				if(isset($user_subscribes['users'][$openid])){
					$weixin_user['subscribe']	= $user_subscribes['users'][$openid]['subscribe'];
				}
			}
		}

		if($weixin_user){
			$weixin_user['appid']	= self::get_appid();
			$weixin_user['blog_id']	= self::get_blog_id();
		}

		return $weixin_user;
	}

	public static function batch_get_user_info($openids, $force=false){
		$openids = array_unique($openids);
		$openids = array_filter($openids);
		$openids = array_values($openids);

		if($force === false){	// 先从内存和数据库中取
			$timestamp	= time() - MONTH_IN_SECONDS*3;

			$users	= self::get_ids($openids);
			$users	= array_filter($users, function($user){
				return (empty($user['subscribe']) || ($user['subscribe'] && isset($user['nickname'])));
			});

			if(count($users) >= count($openids)){
				$nonupdated_users	= array_filter($users, function($user)use($timestamp){
					return (empty($user['last_update']) || $user['last_update'] < $timestamp);
				});

				if(!$nonupdated_users){
					return $users;
				}
			}
		}

		$users = weixin()->batch_get_user_info($openids);	// 只要一个没有，或者太久，就全部到微信服务器取一下，反正都是一次 http request 

		if(is_wp_error($users)){
			return $users;
		}
		
		if($users && isset($users['user_info_list'])){
			$users	= $users['user_info_list'];

			$users	= array_map("self::sanitize", $users);

			if($subscribe_users	= array_filter($users, function($user){ return $user['subscribe']; })){
				parent::insert_multi($subscribe_users);
			}

			if($unsubscribe_users	= array_filter($users, function($user){ return !$user['subscribe']; })){
				parent::insert_multi($unsubscribe_users);
			}
		}

		return self::get_by_ids($openids);
	}

	public static function render_user($user){
		if(!$user){
			return [];
		}

		if(!$user['subscribe']){
			if(empty($user['subscribe_time'])){
				return [];
			}
			
			$user['nickname'] = '<span style="color:red; text-decoration:line-through; transform: rotate(1deg);">'.$user['nickname'].'</span>';
		}

		$user_sexs	= ['1'=>'男','2'=>'女','0'=>'未知'];
		$user_sex	= $user_sexs[$user['sex']]??'未知';;

		$user['username'] = $user['nickname']??'';
		if(isset($user['headimgurl'])){
			$user['headimgurl'] = str_replace('/0', '/64', $user['headimgurl']);
			$user['username'] = '<img src="'.$user['headimgurl'].'" width="32" class="alignleft" style="margin-right:10px;" />'.$user['username'].'（'.$user_sex.'）';
		}

		$user['subscribe_time']	= get_date_from_gmt(date('Y-m-d H:i:s',$user['subscribe_time']));

		$user['address']	= $user['country'].' '.$user['province'].' '.$user['city'];

		if(!empty($user['unsubscribe_time'])){
			$user['unsubscribe_time']	= get_date_from_gmt(date('Y-m-d H:i:s',$user['unsubscribe_time']));
		}else{
			$user['unsubscribe_time']	= '';
		}

		return $user;
	}

	public static function parse_for_json($weixin_user){
		if(!is_array($weixin_user)){
			$weixin_user = static::get($weixin_user);
		}else{
			$weixin_user = static::get($weixin_user['openid']);
		}

		if(!$weixin_user){
			return [];
		}
		
		$weixin_user_json					= [];
		$weixin_user_json['openid']			= $weixin_user['openid'];
		$weixin_user_json['subscribe']		= intval($weixin_user['subscribe']);
		$weixin_user_json['subscribe_time']	= intval($weixin_user['subscribe_time']);
		$weixin_user_json['nickname']		= $weixin_user['nickname'];
		$weixin_user_json['sex']			= intval($weixin_user['sex']);
		$weixin_user_json['avatarurl']		= $weixin_user_json['headimgurl']	= str_replace('/0', '/132', $weixin_user['headimgurl']);
		$weixin_user_json['language']		= $weixin_user['language'] ?? '';
		$weixin_user_json['country']		= $weixin_user['country'] ?? '';
		$weixin_user_json['province']		= $weixin_user['province'] ?? '';
		$weixin_user_json['city']			= $weixin_user['city'] ?? '';
		$weixin_user_json['user_id']		= intval($weixin_user['user_id']);
		$weixin_user_json['appid']			= $weixin_user['appid'];
		$weixin_user_json['blog_id']		= intval($weixin_user['blog_id']);
		
		if(doing_filter('weixin_user_json')){
			return $weixin_user_json;
		}else{
			return apply_filters('weixin_user_json', $weixin_user_json, $weixin_user['openid']);
		}
	}

	public static function sync($user_info){
		$openid	= trim($user_info['openid']);

		if($user_info['subscribe'] == 1){
			$user_info	= self::sanitize($user_info);
			
			parent::insert($user_info);
		}else{
			if($weixin_user	= parent::get($openid)){
				parent::update($openid, ['subscribe'=>0]);
			}
		}

		return parent::get($openid);
	}

	public static function sanitize($user_info, $is_oauth=false){
		if($user_info){
			foreach(['nickname', 'city', 'province', 'country'] as $field){
				if(isset($user_info[$field]) && mb_strlen($user_info[$field], 'UTF-8') > 254){
					$user_info[$field]	= wpjam_strip_invalid_text(mb_substr($user_info[$field], 0, 254));					
				}
			}
		}

		if(!$is_oauth){
			$user_info['last_update']	= time();

			if(isset($user_info['tagid_list']) && is_array($user_info['tagid_list'])){
				$user_info['tagid_list']	= implode(',', $user_info['tagid_list']);
			}else{
				$user_info['tagid_list']	= '';
			}

			unset($user_info['groupid']);
		}

		return $user_info;
	}

	public static function sync_by_oauth($oauth_userinfo){
		$oauth_userinfo	= self::sanitize($oauth_userinfo, true);

		$openid	= trim($oauth_userinfo['openid']);

		$user	= self::get($openid);

		unset($user['appid']);
		unset($user['blog_id']);

		$user	= $user ?: ['subscribe'=>0, 'openid'=>$openid];

		$user['nickname']		= $oauth_userinfo['nickname'];
		$user['sex']			= $oauth_userinfo['sex'];
		$user['province']		= $oauth_userinfo['province'];
		$user['city']			= $oauth_userinfo['city'];
		$user['country']		= $oauth_userinfo['country'];
		$user['headimgurl']		= $oauth_userinfo['headimgurl'];
		$user['unionid']		= $oauth_userinfo['unionid'] ?? '';
		$user['last_update']	= time();
		$user['privilege']		= maybe_serialize($oauth_userinfo['privilege']);

		return self::insert($user);
	}

	public static function subscribe($openid, $subscribe=1){
		$data	= ['subscribe'=>$subscribe, 'openid'=>trim($openid), 'unsubscribe_time'=>0];

		if(!$subscribe){
			$data['unsubscribe_time']	= time();	
		}

		$users	= self::cache_get('subscribes');

		if($users === false){
			$users	= ['time'=>time(),'users'=>[]];	
		}
		
		$users['users'][$openid]	= $data;

		if(count($users['users']) < 20 && (time()-$users['time'] < 300)){
			self::cache_set('subscribes', $users, DAY_IN_SECONDS);
		}else{
			// 达到了 20 个用户或者过了5分钟再去写数据库

			self::cache_delete('subscribes');
			self::insert_multi(array_values($users['users']));
		}
	}

	public static function unsubscribe($openid){
		self::subscribe($openid, 0);
	}

	public static function generate_access_token($openid, $setcookie=false){
		$access_token	= self::cache_get('access_token:'.$openid);

		if($access_token !== false){
			self::cache_delete('access_token:'.$openid);
			self::cache_delete('access_token:'.$access_token);
		}

		$appid			= static::get_appid();
		$access_token	= md5(uniqid($openid.$appid));

		self::cache_set('access_token:'.$access_token, ['appid'=>$appid, 'openid'=>$openid], DAY_IN_SECONDS);
		self::cache_set('access_token:'.$openid, $access_token, DAY_IN_SECONDS);

		if($setcookie){
			self::set_access_token_cookie($access_token);
		}

		return $access_token;
	}

	public static function get_openid_by_access_token($access_token=''){
		if(empty($access_token)){
			return new WP_Error('empty_access_token', 'Access Token 不能为空！');
		}

		$data	= self::cache_get('access_token:'.$access_token);
		
		if($data === false){
			return new WP_Error('illegal_access_token', 'Access Token 非法或已过期！');
		}

		$appid	= static::get_appid();
		if($data['appid'] != $appid){
			return new WP_Error('illegal_appid', 'appid 不匹配！');
		}

		return $data['openid'];
	}

	public static function set_access_token_cookie($access_token){
		$expiration	= time() + DAY_IN_SECONDS;
		$secure		= is_ssl();

		setcookie('weixin_access_token', $access_token, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure, true);

	    if ( COOKIEPATH != SITECOOKIEPATH ){
	        setcookie('weixin_access_token', $access_token, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, $secure, true);
	    }

	    $_COOKIE['weixin_access_token'] = $access_token;
	}

	protected static $handlers;

	public static function get_table(){
		global $wpdb;
		return $wpdb->base_prefix.'weixin_'.static::get_appid().'_users';
	}

	public static function get_handler(){
		static::$handlers	= static::$handlers ?? [];
		$appid				= static::get_appid();

		if(empty(static::$handlers[$appid])){
			static::$handlers[$appid] = new WPJAM_DB(self::get_table(), [
				'primary_key'		=> 'openid',
				'cache_prefix'		=> $appid,
				'cache_group'		=> 'weixin_users',
				'field_types'		=> ['subscribe'=>'%d','subscribe_time'=>'%d','unsubscribe_time'=>'%d','sex'=>'%d','credit'=>'%d','exp'=>'%d','last_update'=>'%d'],
				'searchable_fields'	=> ['openid', 'nickname'],
				'filterable_fields'	=> ['country','province','city','sex','subscribe_scene'],
			]);
		}
		
		return static::$handlers[$appid];
	}

	public static function create_table(){
		global $wpdb;

		$table = self::get_table();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		if($wpdb->get_var("show tables like '".$table."'") != $table) {
			$sql = "
			CREATE TABLE IF NOT EXISTS {$table} (
				`openid` varchar(30) NOT NULL,
				`nickname` varchar(255) NOT NULL,
				`subscribe` int(1) NOT NULL default '1',
				`subscribe_time` int(10) NOT NULL,
				`unsubscribe_time` int(10) NOT NULL,
				`sex` int(1) NOT NULL,
				`city` varchar(255) NOT NULL,
				`country` varchar(255) NOT NULL,
				`province` varchar(255) NOT NULL,
				`language` varchar(255) NOT NULL,
				`headimgurl` varchar(255) NOT NULL,
				`tagid_list` text NOT NULL,
				`privilege` text NOT NULL,
				`unionid` varchar(30) NOT NULL,
				`remark` text NOT NULL,
				`subscribe_scene` varchar(32) NOT NULL,
				`qr_scene` int(6) NOT NULL,
				`qr_scene_str` varchar(64) NOT NULL,
				`user_id` bigint(20) NOT NULL,
				`last_update` int(10) NOT NULL,
				PRIMARY KEY  (`openid`),
				KEY `user_idx` (`user_id`),
				KEY `subscribe_time` (`subscribe_time`),
				KEY `subscribe` (`subscribe`),
				KEY `last_update` (`last_update`)
			) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			";
	 
			dbDelta($sql);
		}

		do_action('weixin_user_table_created', $table);
	}
}

class WEIXIN_UserTag{
	public static function get($id){
		$tags = self::get_tags();
		
		if(is_wp_error($tags)){
			return $tags;
		}

		return $tags[$id];
	}

	public static function get_tags(){
		return weixin()->get_tags();
	}

	public static function insert($data){
		$tag = weixin()->create_tag($data['name']);

		if(is_wp_error($tag)){
			return $tag;
		}

		return $tag['tag']['id'];
	}

	public static function update($id, $data){
		$tag	= self::get($id);

		if(is_wp_error($tag)){
			return $tag;
		}

		if(trim($data['name']) == trim($tag['name'])){
			return true;
		}

		return weixin()->update_tag($id, $data['name']);
	}

	public static function delete($id){
		return weixin()->delete_tag($id);
	}
}