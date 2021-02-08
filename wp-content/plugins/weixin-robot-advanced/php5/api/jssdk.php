<?php
// 微信页面浏览统计
add_action('wp_ajax_weixin_view', 'weixin_robot_view_ajax_action_callback');
add_action('wp_ajax_nopriv_weixin_view', 'weixin_robot_view_ajax_action_callback');
function weixin_robot_view_ajax_action_callback(){
	if(is_weixin()){
		check_ajax_referer( "weixin_nonce" );

		$data = weixin_robot_get_ajax_post_data();

		$data['type']		= 'View';
		$data['sub_type']	= $data['sub_type']?$data['sub_type']:'direct';

		if($weixin_openid	= weixin_robot_get_user_openid()){

			if(empty($_COOKIE['weixin_ip']) && $ip = $data['ip']) {		// 根据 IP 设置用户所在的地区
				weixin_robot_set_cookie('weixin_ip', $ip, time()+86400);

				$weixin_user = array();
				$weixin_user['ip']	= $ip;
				weixin_robot_update_user($weixin_openid, $weixin_user);

				$ipdata = wpjam_get_ipdata($ip);
				// if(!is_wp_error($ipdata)){
				// 	$weixin_user['ip_country']	= $ipdata['country'];
				// 	$weixin_user['ip_region']	= $ipdata['region'];
				// 	$weixin_user['ip_city']		= $ipdata['city'];
				// 	$weixin_user['isp']			= $ipdata['isp'];

				// 	weixin_robot_update_user($weixin_openid, $weixin_user);
				// }
			}

			if(empty($_COOKIE['weixin_ua']) && $ua = $data['ua']){	// 根据 User-Agent 设置用户所使用的手机和操作系统
				weixin_robot_set_cookie('weixin_ua',$ua, time()+86400*7);

				$ua_data = wpjam_get_ua_data($ua);

				$weixin_user = array();
				$weixin_user['os']			= $ua_data['os'];
				$weixin_user['os_ver']		= $ua_data['os_ver'];
				
				$weixin_user['weixin_ver']	= substr($ua_data['weixin_ver'], 0,8);

				if($ua_data['device'] == 'iPhone'){
					$weixin_user['device']	= $ua_data['device'].'_'.$_POST['screen_width'].'x'.$_POST['screen_height'];
				}else{
					$weixin_user['device']	= $ua_data['device'];
				}

				// $weixin_user['screen_width']	= $_POST['screen_width'];
				// $weixin_user['screen_height']	= $_POST['screen_height'];
				// $weixin_user['retina']			= $_POST['retina'];
				
				weixin_robot_update_user($weixin_openid, $weixin_user);
			}
		}

		do_action('weixin_view', $data);

	}
	exit;
}

// 微信页面转发统计
add_action('wp_ajax_weixin_share', 'weixin_robot_share_ajax_action_callback');
add_action('wp_ajax_nopriv_weixin_share', 'weixin_robot_share_ajax_action_callback');
function weixin_robot_share_ajax_action_callback(){
	if(is_weixin()){
		check_ajax_referer( "weixin_nonce" );

		if(isset($_POST['sub_type'])){
			$data = weixin_robot_get_ajax_post_data();
			$data['type']		= 'Share';
			do_action('weixin_share', $data);
		}
	}
	exit;
}

function weixin_robot_get_ajax_post_data(){
	
	$data = array();
	$data['post_id']		= $_POST['post_id'];
	$data['sub_type']		= $_POST['sub_type'];
	$data['url']			= weixin_robot_remove_query_arg($_POST['link']);
	$data['refer']			= isset($_POST['refer'])?$_POST['refer']:'';
	$data['network_type']	= isset($_POST['network_type'])?$_POST['network_type']:'';
	$data['screen_width']	= isset($_POST['screen_width'])?$_POST['screen_width']:'';
	$data['screen_height']	= isset($_POST['screen_height'])?$_POST['screen_height']:'';
	$data['retina']			= isset($_POST['retina'])?$_POST['retina']:'';
	$data['ip']				= wpjam_get_ip();
	$data['ua']				= wpjam_get_ua();

	$weixin_openid	= weixin_robot_get_user_openid();
	if($data['refer'] && $data['refer']==$weixin_openid){
		$data['refer'] = '';	// 自己推荐自己就不要了。
	}

	return $data;
}

function weixin_robot_get_js_api_ticket(){
	$weixin_js_api_ticket = get_transient('weixin_js_api_ticket');
	if($weixin_js_api_ticket == false){
		$url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi';
		$response = weixin_robot_remote_request($url);
		if(is_wp_error($response)){
			return false;
		}else{
			set_transient('weixin_js_api_ticket',			$response['ticket'],				$response['expires_in']-600);
			set_transient('weixin_js_api_ticket_expires',	time()+$response['expires_in']-600,	$response['expires_in']-600);	// 第三方接口需要用到
		 	$weixin_js_api_ticket = $response['ticket'];
		}
	}

	return $weixin_js_api_ticket;
}

function weixin_robot_get_wx_card_ticket(){
	$weixin_wx_card_ticket = get_transient('weixin_wx_card_ticket');
	if($weixin_wx_card_ticket == false){
		$url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=wx_card';
		$response = weixin_robot_remote_request($url);
		if(is_wp_error($response)){
			return false;
		}else{
			set_transient('weixin_wx_card_ticket',			$response['ticket'],				$response['expires_in']-600);
			set_transient('weixin_wx_card_ticket_expires',	time()+$response['expires_in']-600,	$response['expires_in']-600);	// 第三方接口需要用到
		 	$weixin_wx_card_ticket = $response['ticket'];
		}
	}

	return $weixin_wx_card_ticket;
}

// 微信转发前端JS代码
add_action( 'wp_enqueue_scripts', 'weixin_robot_enqueue_scripts' );
function weixin_robot_enqueue_scripts() {
	wp_register_style('weui', '//res.wx.qq.com/open/libs/weui/0.4.3/weui.min.css');
	
	if(is_404() || !is_weixin())	return;

	$js_api_ticket	= weixin_robot_get_js_api_ticket();
	$url			= wpjam_get_current_page_url();
	$timestamp		= time();
	// $nonce_str		= weixin_robot_create_nonce_str();
	$nonce_str		= wp_generate_password(16, $special_chars = false);
	$signature		= sha1("jsapi_ticket=$js_api_ticket&noncestr=$nonce_str&timestamp=$timestamp&url=$url");
	
	$size		= array(120,120);
	if(is_singular()){
		$img	= weixin_robot_get_post_thumb('',$size);
		$title	= get_the_title();
		$desc	= get_post_excerpt();	
		$post_id= get_the_ID();	
	}else{
		$img 	= wpjam_get_default_thumbnail_src($size);
		if($title	= wp_title('',false)){
			$title	= wp_title('',false);
		}else{
			$title	= get_bloginfo('name');
		}
		$desc	= '';
		$post_id= 0;
	}

	$link		= weixin_robot_get_current_page_url();

	// 转发 hook，用于插件修改
	$link				= apply_filters('weixin_share_url',			$link);
	
	if($weixin_openid	= weixin_robot_get_user_openid()){
		$link			= add_query_arg( array('weixin_refer' => $weixin_openid), $link );
	}

	$js_api_list		= array(
		'checkJsApi', 
		'onMenuShareTimeline', 
		'onMenuShareAppMessage', 
		'onMenuShareQQ', 
		'onMenuShareWeibo', 
		'onMenuShareQZone',
		'getNetworkType',
		'previewImage',
		'hideOptionMenu',
		'showOptionMenu',
		'hideMenuItems',
		'showMenuItems',
		'hideAllNonBaseMenuItem',
		'showAllNonBaseMenuItem',
		'closeWindow',
	);			

	$js_api_list		= array_merge(apply_filters('weixin_share_js_api_list',	array()),$js_api_list);

	$img				= apply_filters('weixin_share_img',			$img);
	$title				= apply_filters('weixin_share_title',		$title);
	$desc				= apply_filters('weixin_share_desc',		$desc);
	$debug				= apply_filters('weixin_jssdk_debug',		false);
	$notify				= apply_filters('weixin_share_notify',		weixin_robot_get_setting('weixin_share_notify'));
	$hide_option_menu	= apply_filters('weixin_hide_option_menu',	weixin_robot_get_setting('weixin_hide_option_menu'));
	$refresh_url 		= apply_filters('weixin_refresh_url',		'', $link);
	$content_wrap 		= apply_filters('weixin_content_wrap',		weixin_robot_get_setting('weixin_entry_content'));
	
	$notify				= ($notify)?1:0;
	$hide_option_menu	= ($hide_option_menu)?1:0;

	wp_deregister_script('jquery');
	wp_register_script('jquery', '//res.wx.qq.com/open/libs/jquery/2.1.4/jquery.js', array(), '2.1.4' );	// 使用微信官方 jQuery 库
	
	wp_enqueue_script('jweixin', '//res.wx.qq.com/open/js/jweixin-1.0.0.js', array('jquery') );
	wp_enqueue_script('weixin', WEIXIN_ROBOT_PLUGIN_URL.'/template/static/weixin5.js', array('jweixin', 'jquery') );

	// wp_enqueue_script('weixin', 'http://shop.wpweixin.com/wp-content/plugins/weixin-robot-test/static/weixin-test.js', array('jweixin', 'jquery') );
	// wp_localize_script('weixin', 'weixin_data', array(
	wpjam_localize_script('weixin', 'weixin_data', array(
			'appid' 			=> WEIXIN_APPID,
			'debug' 			=> $debug,
			'timestamp'			=> $timestamp,
			'nonce_str'			=> $nonce_str,
			'signature'			=> $signature,
			'jsApiList'			=> $js_api_list,

			'img'				=> $img,
			'link'				=> $link,
			'title'				=> $title,
			'desc'				=> $desc,

			'refresh_url'		=> $refresh_url,
			'post_id'			=> $post_id,
			'notify'			=> $notify,
			'content_wrap'		=> $content_wrap,
			'hide_option_menu'	=> $hide_option_menu,

			'ajax_url'			=> admin_url('admin-ajax.php'),
			'nonce'				=> wp_create_nonce( 'weixin_nonce' )
		)	
	);
}

// 获取当前页面，并去掉 openid 和 query_id 等参数
function weixin_robot_get_current_page_url($for_oauth=false){
	$url = wpjam_get_current_page_url();
	$url = weixin_robot_remove_query_arg($url, $for_oauth);
	return $url;
}

function weixin_robot_remove_query_arg($url, $for_oauth=false){
	if($for_oauth){
		$query_args	= array('code', 'state', 'get_userinfo', 'get_openid', 'weixin_oauth','nsukey');
	}else{
		$query_key	= weixin_robot_get_user_query_key();
		$query_args	= array('weixin_openid', 'weixin_user_id', $query_key, 'isappinstalled', 'from', 'weixin_refer','nsukey');
	}
	return remove_query_arg( $query_args, $url ); 
}

function weixin_robot_get_share_types(){
	return array(
		'ShareAppMessage'	=> '发送给朋友',
		'ShareTimeline'		=> '分享到朋友圈',
		'ShareQQ'			=> '分享到QQ',
		'ShareWeibo'		=> '分享到微博',
		'favorite'			=> '收藏',
		'connector'			=> '分享到第三方',
	);
}

function weixin_robot_get_source_types(){
	return array(
		'direct'		=> '直接访问',
		'timeline'		=> '来自朋友圈',
		'groupmessage'	=> '来自微信群',
		'singlemessage'	=> '来自好友分享'
	);
}

function weixin_robot_get_network_types(){
	return array(
		'network_type:wifi'	=> 'wifi网络',
		'network_type:edge'	=> '非wifi,包含3G/2G',
		'network_type:fail'	=> '网络断开连接',
		'network_type:wwan'	=> '2g或者3g'
	);
}
