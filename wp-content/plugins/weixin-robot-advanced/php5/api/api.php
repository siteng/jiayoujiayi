<?php
// 判断当前用户操作是否在微信内置浏览器中
function is_weixin(){ 
	static $is_weixin;

	if ( isset($is_weixin) )
		return $is_weixin;

	if(isset($_GET['debug'])){
		return true;
	}

	if ( empty($_SERVER['HTTP_USER_AGENT']) ) {
		$is_weixin = false;
	}elseif ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
		if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'https://servicewechat.com') !== false ) {	
			//小程序的 referer 格式固定为 https://servicewechat.com/{appid}/{version}/page-frame.html，其中 {appid} 为小程序的 appid，{version} 为小程序的版本号，版本号为 0 表示为开发版。
			$is_weixin = false;
		}else {
			$is_weixin = true;
		}
	}else{
		$is_weixin = false;
	}

	return $is_weixin;
}

// 加载扩展
function weixin_robot_include_extends($admin=false){
	if($weixin_extends	= get_option('weixin-robot-extends')){
		$weixin_extend_dir 	= $admin? WEIXIN_ROBOT_PLUGIN_DIR.'extends/admin': WEIXIN_ROBOT_PLUGIN_DIR.'extends';
		foreach ($weixin_extends as $weixin_extend_file => $value) {
			if($value){
				$weixin_extend_file	= $weixin_extend_dir.'/'.$weixin_extend_file;
				if(is_file($weixin_extend_file)){
					if($admin){
						include_once($weixin_extend_file);
					}else{
						include($weixin_extend_file);
					}
				}
			}
		}
	}
}

// 获取微信机器人设置
function weixin_robot_get_setting($setting_name){
	return wpjam_get_setting('weixin-robot', $setting_name);
}

// 设置默认选项
add_filter('weixin-robot_defaults', 'weixin_robot_get_defaults');
function weixin_robot_get_defaults($defaults){
	$default_options = array(
		'weixin_token'					=> 'weixin',
		'weixin_type'					=> 1,
		'weixin_message_mode'			=> 1,
		
		'weixin_keyword_allow_length'	=> '16',
		'weixin_count'					=> '5',
	);
	return apply_filters('weixin_default_option',$default_options);
}

define('WEIXIN_TYPE', 		trim(weixin_robot_get_setting('weixin_type')));
define('WEIXIN_APPID', 		trim(weixin_robot_get_setting('weixin_app_id')));
define('WEIXIN_APP_SECRET', trim(weixin_robot_get_setting('weixin_app_secret')));

// 微信 API 请求
function weixin_robot_remote_request($url, $method='get', $data='', $args=array()){
	if(WEIXIN_TYPE == -1)	return false;
	
	$args = wp_parse_args( $args, array(
		'need_access_token'	=> true,
		'timeout'			=> 10,
	) );

	if($args['need_access_token']){
		$access_token = weixin_robot_get_access_token();
		// wpjam_print_r($access_token);
		if($access_token){
			$url = add_query_arg(array('access_token'=>$access_token), $url);
			$url = str_replace('%40', '@', $url);
		}else{
			return new WP_Error('41001', '无法获取 access_token');	
		}
	}

	unset($args['need_access_token']);

	$args['body']	= $data;
	$args['method']	= $method;

	$response = wpjam_remote_request($url, $args);

	if(is_wp_error($response)){
		return $response;
	}

	if(isset($response['errcode']) && $response['errcode']){
		$errcode	= $response['errcode'];
		$errmsg		= $response['errmsg'];
		
		trigger_error($url."\n".$errcode.' : '.$errmsg."\n".var_export($args['body'],true)."\n");

		if($errcode == '40001' || $errcode == '40014' || $errcode == '42001'){
			// 40001 获取access_token时AppSecret错误，或者access_token无效。请开发者认真比对AppSecret的正确性，或查看是否正在为恰当的公众号调用接口
			// 40014 不合法的access_token，请开发者认真比对access_token的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口
			// 42001 access_token超时，请检查access_token的有效期，请参考基础支持-获取access_token中，对access_token的详细机制说明
			delete_transient('weixin_access_token');
		}elseif($errcode == '50002'){	
			// 50002 用户受限，可能是违规后接口被封禁
			$weixin_robot_option = wpjam_get_option('weixin-robot');
			$weixin_robot_option['weixin_type'] = -1;
			update_option('weixin-robot', $weixin_robot_option);
		}

		$errmsg		= weixin_robot_get_errmsg($errcode, $errmsg);
		
		return new WP_Error($errcode, $errmsg);
	}

	return $response;
}

function weixin_robot_get_errmsg($errcode, $errmsg=''){
	$errmsgs = array(
		'-1'	=>'系统繁忙，此时请开发者稍候再试',
		'0'		=>'请求成功',
		'40001'	=>'获取access_token时AppSecret错误，或者access_token无效。请开发者认真比对AppSecret的正确性，或查看是否正在为恰当的公众号调用接口',
		'40002'	=>'不合法的凭证类型',
		'40003'	=>'不合法的OpenID，请开发者确认OpenID（该用户）是否已关注公众号，或是否是其他公众号的OpenID',
		'40004'	=>'不合法的媒体文件类型',
		'40005'	=>'不合法的文件类型',
		'40006'	=>'不合法的文件大小',
		'40007'	=>'不合法的媒体文件id',
		'40008'	=>'不合法的消息类型',
		'40009'	=>'不合法的图片文件大小',
		'40010'	=>'不合法的语音文件大小',
		'40011'	=>'不合法的视频文件大小',
		'40012'	=>'不合法的缩略图文件大小',
		'40013'	=>'不合法的AppID，请开发者检查AppID的正确性，避免异常字符，注意大小写',
		'40014'	=>'不合法的access_token，请开发者认真比对access_token的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口',
		'40015'	=>'不合法的菜单类型',
		'40016'	=>'不合法的按钮个数',
		'40017'	=>'不合法的按钮个数',
		'40018'	=>'不合法的按钮名字长度',
		'40019'	=>'不合法的按钮KEY长度',
		'40020'	=>'不合法的按钮URL长度',
		'40021'	=>'不合法的菜单版本号',
		'40022'	=>'不合法的子菜单级数',
		'40023'	=>'不合法的子菜单按钮个数',
		'40024'	=>'不合法的子菜单按钮类型',
		'40025'	=>'不合法的子菜单按钮名字长度',
		'40026'	=>'不合法的子菜单按钮KEY长度',
		'40027'	=>'不合法的子菜单按钮URL长度',
		'40028'	=>'不合法的自定义菜单使用用户',
		'40029'	=>'不合法的oauth_code',
		'40030'	=>'不合法的refresh_token',
		'40031'	=>'不合法的openid列表',
		'40032'	=>'不合法的openid列表长度',
		'40033'	=>'不合法的请求字符，不能包含\uxxxx格式的字符',
		'40035'	=>'不合法的参数',
		'40038'	=>'不合法的请求格式',
		'40039'	=>'不合法的URL长度',
		'40050'	=>'不合法的分组id',
		'40051'	=>'分组名字不合法',
		'40053'	=>'不合法的actioninfo，请开发者确认参数正确。',
		'40056'	=>'不合法的Code码。',
		'40071'	=>'不合法的卡券类型。',
		'40072'	=>'不合法的编码方式。',
		'40078'	=>'不合法的卡券状态。',
		'40079'	=>'不合法的时间。',
		'40080'	=>'不合法的CardExt。',
		'40099'	=>'卡券已被核销。',
		'40109'	=>'code数量超过100个。',
		'40100'	=>'不合法的时间区间。',
		'40116'	=>'不合法的Code码。',
		'40117'	=>'分组名字不合法',
		'40118'	=>'media_id大小不合法',
		'40119'	=>'button类型错误',
		'40120'	=>'button类型错误',
		'40121'	=>'不合法的media_id类型',
		'40122'	=>'不合法的库存数量。',
		'40124'	=>'会员卡设置查过限制的 custom_field字段。',
		'40127'	=>'卡券被用户删除或转赠中。',
		'40132'	=>'微信号不合法',
		'40137'	=>'不支持的图片格式',
		'41001'	=>'缺少access_token参数',
		'41002'	=>'缺少appid参数',
		'41003'	=>'缺少refresh_token参数',
		'41004'	=>'缺少secret参数',
		'41005'	=>'缺少多媒体文件数据',
		'41006'	=>'缺少media_id参数',
		'41007'	=>'缺少子菜单数据',
		'41008'	=>'缺少oauth code',
		'41009'	=>'缺少openid',
		'41011'	=>'缺少必填字段。',
		'41012'	=>'缺少cardid参数。',
		'42001'	=>'access_token超时，请检查access_token的有效期，请参考基础支持-获取access_token中，对access_token的详细机制说明',
		'42002'	=>'refresh_token超时',
		'42003'	=>'oauth_code超时',
		'43001'	=>'需要GET请求',
		'43002'	=>'需要POST请求',
		'43003'	=>'需要HTTPS请求',
		'43004'	=>'需要接收者关注',
		'43005'	=>'需要好友关系',
		'43009'	=>'自定义SN权限，请前往公众平台申请。',
		'43010'	=>'无储值权限，请前往公众平台申请。',
		'44001'	=>'多媒体文件为空',
		'44002'	=>'POST的数据包为空',
		'44003'	=>'图文消息内容为空',
		'44004'	=>'文本消息内容为空',
		'45001'	=>'多媒体文件大小超过限制',
		'45002'	=>'消息内容超过限制',
		'45003'	=>'标题字段超过限制',
		'45004'	=>'描述字段超过限制',
		'45005'	=>'链接字段超过限制',
		'45006'	=>'图片链接字段超过限制',
		'45007'	=>'语音播放时间超过限制',
		'45008'	=>'图文消息超过限制',
		'45009'	=>'接口调用超过限制',
		'45010'	=>'创建菜单个数超过限制',
		'45015'	=>'回复时间超过限制',
		'45016'	=>'系统分组，不允许修改',
		'45017'	=>'分组名字过长',
		'45018'	=>'分组数量超过上限',
		'45021'	=>'字段超过长度限制，请参考相应接口的字段说明。',
		'45030'	=>'该cardid无接口权限。',
		'45031'	=>'库存为0。',
		'45033'	=>'用户领取次数超过限制get_limit',
		'46001'	=>'不存在媒体数据',
		'46002'	=>'不存在的菜单版本',
		'46003'	=>'不存在的菜单数据',
		'46004'	=>'不存在的用户',
		'47001'	=>'解析JSON/XML内容错误',
		'48001'	=>'api功能未授权，请确认公众号已获得该接口，可以在公众平台官网-开发者中心页中查看接口权限',
		'50001'	=>'用户未授权该api',
		'61451'	=>'参数错误(invalid parameter)',
		'61452'	=>'无效客服账号(invalid kf_account)',
		'61453'	=>'客服帐号已存在(kf_account exsited)',
		'61454'	=>'客服帐号名长度超过限制(仅允许10个英文字符，不包括@及@后的公众号的微信号)(invalid kf_acount length)',
		'61455'	=>'客服帐号名包含非法字符(仅允许英文+数字)(illegal character in kf_account)',
		'61456'	=>'客服帐号个数超过限制(10个客服账号)(kf_account count exceeded)',
		'61457'	=>'无效头像文件类型(invalid file type)',
		'61459'	=>'客服不在线',
		'61450'	=>'系统错误(system error)',
		'61500'	=>'日期格式错误',
		'61501'	=>'日期范围错误',
		'9001001'	=>'POST数据参数不合法',
		'9001002'	=>'远端服务不可用',
		'9001003'	=>'Ticket不合法',
		'9001004'	=>'获取摇周边用户信息失败',
		'9001005'	=>'获取商户信息失败',
		'9001006'	=>'获取OpenID失败',
		'9001007'	=>'上传文件缺失',
		'9001008'	=>'上传素材的文件类型不合法',
		'9001009'	=>'上传素材的文件尺寸不合法',
		'9001010'	=>'上传失败',
		'9001020'	=>'帐号不合法',
		'9001021'	=>'已有设备激活率低于50%，不能新增设备',
		'9001022'	=>'设备申请数不合法，必须为大于0的数字',
		'9001023'	=>'已存在审核中的设备ID申请',
		'9001024'	=>'一次查询设备ID数量不能超过50',
		'9001025'	=>'设备ID不合法',
		'9001026'	=>'页面ID不合法',
		'9001027'	=>'页面参数不合法',
		'9001028'	=>'一次删除页面ID数量不能超过10',
		'9001029'	=>'页面已应用在设备中，请先解除应用关系再删除',
		'9001030'	=>'一次查询页面ID数量不能超过50',
		'9001031'	=>'时间区间不合法',
		'9001032'	=>'保存设备与页面的绑定关系参数错误',
		'9001033'	=>'门店ID不合法',
		'9001034'	=>'设备备注信息过长',
		'9001035'	=>'设备申请参数不合法',
		'9001036'	=>'查询起始值begin不合法'
	);
	
	return isset($errmsgs[$errcode])?$errmsgs[$errcode]:$errmsg;
}

// 获取 Access Token 并缓存
function weixin_robot_get_access_token(){
	if(WEIXIN_APPID && WEIXIN_APP_SECRET) {
		$weixin_access_token = get_transient('weixin_access_token');

		if($weixin_access_token === false){
			$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.WEIXIN_APPID.'&secret='.WEIXIN_APP_SECRET;
			
			$response = weixin_robot_remote_request($url,'get','', array('need_access_token'=>false));

			if(is_wp_error($response)){
				return false;
			}else{
				set_transient('weixin_access_token',			$response['access_token'],			$response['expires_in']-600);
				set_transient('weixin_access_token_expires',	time()+$response['expires_in']-600,	$response['expires_in']-600);	// 第三方接口需要用到
			 	$weixin_access_token = $response['access_token'];
			}
		}
		return $weixin_access_token;
	}
	
	return false;
}

// 获取获取微信服务器IP地址
function weixin_robot_clear_quota(){
	$last_clear_quota = get_transient('weixin_clear_quota');
	if(!$last_clear_quota){
		set_transient( 'weixin_clear_quota', 1, 3600 );
		$url 	= 'https://api.weixin.qq.com/cgi-bin/clear_quota';
		$data	= array('appid'=>WEIXIN_APPID);
		$response	= weixin_robot_remote_request($url, 'post', json_encode($data));
		if(is_wp_error($response)){
			return $response;
		}else{
			return true;
		}
	}else{
		return new WP_Error('-1', '一小时内你刚刚清理过');
	}

	
}

// 获取微信短连接
function weixin_robot_get_short_url($long_url){
	$url = 'https://api.weixin.qq.com/cgi-bin/shorturl';

	$data 	= array('action'=>'long2short', 'long_url'=>$long_url);

	$response = weixin_robot_remote_request($url, 'post', json_encode($data) );

	if(is_wp_error($response)){
		return $response;
	}

	return $response['short_url'];
}

// 语义查询
function weixin_robot_semantic_search($query, $category, $location=array(), $uid='' ){
	$url = 'https://api.weixin.qq.com/semantic/semproxy/search';

	$appid 	= WEIXIN_APPID;

	if(empty($uid)){
		global $wechatObj;
		$uid = isset($wechatObj)?$wechatObj->get_weixin_openid():'';
	}

	extract(wp_parse_args( $location, array(
		'latitude'	=> '',
		'longitude'	=> '',
		'city'		=> '',
		'region'	=> ''
	) ) );

	$data 	= compact('query', 'category', 'appid', 'uid', 'latitude', 'longitude', 'city', 'region');

	$response = weixin_robot_remote_request($url, 'post', wpjam_json_encode($data));

	if(is_wp_error($response)){
		return $response['semantic'];
	}

	return $response;
}

// 获取获取微信服务器IP地址
function weixin_robot_get_callback_ip(){
	$ip_list	= get_transient('ip_list');
	if($ip_list === false){
		$response	= weixin_robot_remote_request('https://api.weixin.qq.com/cgi-bin/getcallbackip');
		if(is_wp_error($response)){
			return $response;
		}
		$ip_list = $response['ip_list'];
		set_transient('ip_list', $ip_list, DAY_IN_SECONDS);
	}
	return $ip_list;
}

// 微信活动跳转，用于支持第三方活动
function weixin_robot_make_redirect(){
	if( isset($_GET['weixin_force_subscribe']) || isset($_GET['weixin_redirect']) ){
		$weixin_openid	= weixin_robot_get_user_openid();
		$weixin_user	= weixin_robot_get_user($weixin_openid);
		$subscribe		= ($weixin_user && $weixin_user['subscribe'])?1:0;

		if( !$subscribe && isset($_GET['weixin_force_subscribe']) ){
			if( $weixin_force_subscribe_url	= weixin_robot_get_setting('weixin_force_subscribe_url') ){
				wp_redirect($weixin_force_subscribe_url);
				exit;
			}else{
				wp_die('必须关注微信号','未关注');
			}
		}

		if(!empty($_GET['weixin_redirect'])){

			$weixin_redirect	= $_GET['weixin_redirect'];
			$redirect_host		= parse_url($weixin_redirect, PHP_URL_HOST);

			$campaign_hosts		= wpjam_get_setting('weixin-robot-campaigns', 'hosts');

			$_SESSION['weixin_openid']	= $weixin_openid;

			if(!$campaign_hosts || !in_array($redirect_host, $campaign_hosts)){
				wp_die('该域名未授权，不能跳转！','未授权');
			}

			// if(true !== ($weixin_api_check	= weixin_robot_api_check())){
			// 	wp_die($weixin_api_check['errmsg']);
			// }

			$verify				= md5(WEIXIN_APPID.$weixin_openid);
			$weixin_redirect	= str_replace('[openid]', $weixin_openid, $weixin_redirect);		// 替换 openid
			$weixin_redirect	= add_query_arg(compact('subscribe','verify'), $weixin_redirect);	// 告诉第三方当前用户是否订阅
			
			wp_redirect($weixin_redirect);
			exit;
		}
	}
}

add_filter('wpjam_rewrite_rules', 'weixin_robot_rewrite_rules');
function weixin_robot_rewrite_rules($wpjam_rewrite_rules){
	$wpjam_rewrite_rules['weixin/([^/]+)/?$']	= 'index.php?module=weixin&action=$matches[1]';
	return $wpjam_rewrite_rules;
}

add_filter('wpjam_template', 'weixin_robot_template', 10, 3);
function weixin_robot_template($wpjam_template, $module, $action){
	if($module == 'weixin'){
		if(isset($_GET['debug'])){
			return is_file($wpjam_template)? $wpjam_template : apply_filters('weixin_template', WEIXIN_ROBOT_PLUGIN_DIR.'template/user/'.$action.'.php', $action);
		}
		
		if(is_weixin()){
			if(WEIXIN_TYPE == 4 && !weixin_robot_get_setting('weixin_oauth20')){
				weixin_robot_make_oauth_request();
			}
			
			if($weixin_openid	= weixin_robot_get_user_openid()){
				$weixin_user	= weixin_robot_get_user($weixin_openid);
				if($weixin_user && $weixin_user['subscribe']){
					return is_file($wpjam_template)? $wpjam_template : apply_filters('weixin_template', WEIXIN_ROBOT_PLUGIN_DIR.'template/user/'.$action.'.php', $action);
				}else{
					wp_die('未关注');
				}
			}else{
				wp_die('未登录');
			}
		}else{
			wp_die('请在微信中访问');
		}
	}elseif($module == 'json' && $action && strpos($action, 'weixin') !== false ){
		if(true !== ($weixin_api_check	= weixin_robot_api_check($action))){
			wpjam_send_json($weixin_api_check);
		}

		if(strpos($action, 'stats') !== false){
			return WEIXIN_ROBOT_PLUGIN_DIR.'/template/json/stats/'.$action.'.php';
		}else{
			return WEIXIN_ROBOT_PLUGIN_DIR.'/template/json/'.$action.'.php';
		}
	}
	return $wpjam_template;
}

function weixin_robot_api_check($action=''){
	$access_token	= isset($_GET['access_token'])?$_GET['access_token']:'';
	$tokens			= get_option('weixin_api_access_tokens');
	
	if( empty($access_token) || empty($tokens) || empty($tokens[$access_token]) ){
		return array('errmsg'=>'无权限！');
	}

	$today	= date('Y-m-d', current_time('timestamp'));

	if(!empty($tokens[$access_token]['date']) && $tokens[$access_token]['date'] < $today){	// 已经过期
		return array('errmsg'=>'Access Token 已经过期！');
	}

	if($action){
		$limits	= array(
			'get_weixin_access_token'		=> 100,
			'get_weixin_js_api_ticket'		=> 1000,
			'get_weixin_article_stats'		=> 1000,
			'get_weixin_message_stats'		=> 1000,
			'get_weixin_messagedist_stats'	=> 1000,
			'get_weixin_read_stats'			=> 1000,
			'get_weixin_share_stats'		=> 1000,
			'get_weixin_user_stats'			=> 1000,
			'get_weixin_user'				=> 100000,
			'get_weixin_member_card'		=> 100000,
			'weixin_tag'					=> 10000,
		);

		$limits	= apply_filters( 'weixin_api_limit_', $limits );

		$limit	= isset($limits[$action])?$limits[$action]:50;

		$today	= date('Y-m-d', current_time('timestamp'));

		$times	= wp_cache_get($action.$access_token, 'weixin_api_limit_'.$today);
		$times	= ($times)?$times:0;

		wp_cache_set( $action.$access_token, $times+1, 'weixin_api_limit_'.$today, DAY_IN_SECONDS);

		if($times > $limit){
			trigger_error("第三方 API 请求超过使用限制！");
			return array('errmsg'=>'API 请求超过使用限制！');
		}
	}

	return true;
}

// 下面这两个函数会逐渐取消
// 微信对外 JSON 接口
// function weixin_robot_json_template_redirect(){
// 	$weixin_json_template = $_GET['weixin_json'];	
// 	$template_file = WEIXIN_ROBOT_PLUGIN_DIR.'/template/json/old/'.$weixin_json_template.'.php';
// 	if(is_file($template_file)){
// 		header('Content-type: application/json');
// 		include($template_file);
// 	}
// 	exit;
// }

// // 微信用户中心
// function weixin_robot_user_template_redirect(){	
// 	$weixin_openid	= weixin_robot_get_user_openid();
// 	if($weixin_openid){
// 		$weixin_user	= weixin_robot_get_user($weixin_openid);
// 		if($weixin_user && $weixin_user['subscribe']){
// 			$weixin_user_template = $_GET['weixin_user'];
// 			$tempalte_file = TEMPLATEPATH.'/weixin/user/'.$weixin_user_template.'.php';
// 			$tempalte_file = (is_file($tempalte_file)) ? $tempalte_file : WEIXIN_ROBOT_PLUGIN_DIR.'/template/'.$weixin_user_template.'.php';
// 			if(is_file($tempalte_file)){
// 				include($tempalte_file);
// 			}
// 		}else{
// 			wp_die('未关注');
// 		}
// 	}else{
// 		wp_die('未登录');
// 	}
// 	exit;
// }