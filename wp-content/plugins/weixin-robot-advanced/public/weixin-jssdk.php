<?php
class WEIXIN_JSSDK{
	public static function get_js_api_list($list=[]){
		$weixin_type	= weixin_get_type();
		$js_api_list	= [];

		if($weixin_type >= 3){
			$js_api_list['share']	= [
				'title'	=> '分享接口',
				'list'	=> ['updateAppMessageShareData', 'updateTimelineShareData']
			];
		}

		$js_api_list['ui']		= [
			'title'	=> '界面操作',
			'list'	=> ['closeWindow', 'hideMenuItems', 'showMenuItems', 'hideAllNonBaseMenuItem', 'showAllNonBaseMenuItem']
		];

		$js_api_list['image']	= [
			'title'	=> '图像接口',
			'list'	=> ['chooseImage', 'previewImage', 'uploadImage', 'downloadImage', 'getLocalImgData']
		];

		$js_api_list['voice']	= [
			'title'	=> '音频接口',
			'list'	=> ['startRecord', 'stopRecord', 'onVoiceRecordEnd', 'playVoice', 'pauseVoice', 'stopVoice', 'onVoicePlayEnd', 'uploadVoice', 'downloadVoice', 'translateVoice']
		];

		$js_api_list['location']	= [
			'title'	=> '地理位置',
			'list'	=> ['openLocation', 'getLocation', 'uploadImage', 'downloadImage', 'getLocalImgData']
		];

		$js_api_list['beacon']	= [
			'title'	=> '摇一摇周边',
			'list'	=> ['startSearchBeacons', 'stopSearchBeacons', 'onSearchBeacons']
		];

		$js_api_list['qrcode']	= [
			'title'	=> '微信扫一扫',
			'list'	=> ['scanQRCode']
		];

		if($weixin_type >= 3){
			$js_api_list['card']	= [
				'title'	=> '微信卡券',
				'list'	=> ['chooseCard', 'addCard', 'openCard']
			];
		}

		if($weixin_type == 4){
			$js_api_list['wxpay']	= [
				'title'	=> '微信支付',
				'list'	=> ['chooseWXPay', 'openAddress']
			];
		}

		if($list){
			return wp_array_slice_assoc($js_api_list, $list);
		}else{
			return $js_api_list; 
		}
	}

	public static function set_verify_txt($value){
		$verify_txt = $value['verify_txt'] ?? '';

		if($verify_txt && preg_match('/MP_verify_(.*)\.txt/i', $verify_txt, $match)){
			WPJAM_VerifyTXT::set('weixin', $verify_txt, $match[1]);
		}

		unset($value['verify_txt']);

		return $value;
	}

	public static function load_option_page(){
		// 		'微信JS-SDK是微信公众平台 面向网页开发者提供的基于微信内的网页开发工具包。
// 通过使用微信JS-SDK，网页开发者可借助微信高效地使用拍照、选图、语音、位置等手机系统的能力，
// 同时可以直接使用微信分享、扫一扫、卡券、支付等微信特有的能力，为微信用户提供更优质的网页体验。';
		$summary	= '
1. 先登录「微信公众平台」进入「公众号设置」-「功能设置」
2. 点击配置「JS接口安全域名」，复制验证文件名填到下面对应框并保存。
3. 返回公众号后台，将博客地址填入「JS接口安全域名」。';

		wpjam_register_option('weixin-robot',	[
			'sanitize_callback'	=> ['WEIXIN_JSSDK', 'set_verify_txt'],
			'summary'			=> $summary,
			'fields'			=> [
				'weixin_share'	=> ['title'=>'开启网页分享',	'type'=>'checkbox',	'description'=>'开启网页分享，直接调用文章标题，摘要，缩略图，链接用于微信分享。'],
				'js_api_debug'	=> ['title'=>'开启调试模式',	'type'=>'checkbox',	'show_if'=>['key'=>'weixin_share', 'value'=>1],	'description'=>'调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。'],
				'verify_txt'	=> ['title'=>'域名验证文件名','type'=>'text', 	'value'=>WPJAM_VerifyTXT::get_name('weixin'), 'description'=>'请输入微信公众号后台提供的域名验证文件名（包括.txt），保存之后到微信验证。'],
				// 'js_api_list'	=> ['title'=>'JS接口列表',	'type'=>'checkbox',	'options'=>wp_list_pluck(self::get_js_api_list(), 'title'),	'value'=>['share']],
			]
		]);
	}

	public static function on_enqueue_scripts(){
		wp_register_style('weui', 'https://res.wx.qq.com/open/libs/weui/2.4.1/weui.min.css');
	
		if(is_404()){
			return;
		}

		$js_api_ticket	= weixin()->get_js_api_ticket();

		if(is_wp_error($js_api_ticket)){
			return $js_api_ticket;
		}

		$js_api_ticket	= $js_api_ticket['ticket'];

		$url			= wpjam_get_current_page_url();
		$timestamp		= time();
		$nonce_str		= wp_generate_password(16, false);
		$signature		= sha1("jsapi_ticket=$js_api_ticket&noncestr=$nonce_str&timestamp=$timestamp&url=$url");
		
		if(is_singular()){
			$img		= wpjam_get_post_thumbnail_url(null,[120,120]);
			$title		= get_the_title();
			$desc		= get_the_excerpt();	
			$post_id	= get_the_ID();	
		}else{
			$img		= wpjam_get_default_thumbnail_url([120,120]);
			if($title	= wp_title('',false)){
				$title	= wp_title('',false);
			}else{
				$title	= get_bloginfo('name');
			}
			$desc		= '';
			$post_id	= 0;
		}

		$js_api_list	= self::get_js_api_list(['share']);

		$js_api_list	= $js_api_list ? array_merge(...array_column($js_api_list, 'list')) : [];
		$js_api_list	= array_merge(['checkJsApi'], $js_api_list);

		// 转发 hook，用于插件修改
		$link	= remove_query_arg(['weixin_openid', 'weixin_access_token', 'isappinstalled', 'from', 'weixin_refer','nsukey'], wpjam_get_current_page_url());
			
		// $openid	= weixin_get_current_openid();
		// if(!is_wp_error($openid)){
		// 	$link	= add_query_arg(['weixin_refer'=>$openid], $link );
		// }

		$weixin_share	= [
			'appid' 		=> weixin_get_appid(),
			'debug' 		=> weixin_get_setting('js_api_debug'),
			'timestamp'		=> $timestamp,
			'nonce_str'		=> $nonce_str,
			'signature'		=> $signature,

			'img'			=> apply_filters('weixin_share_img', $img),
			'title'			=> apply_filters('weixin_share_title', $title),
			'desc'			=> apply_filters('weixin_share_desc', $desc),
			'link'			=> apply_filters('weixin_share_url', $link),
			'post_id'		=> $post_id,

			'jsApiList'		=> $js_api_list,
			'openTagList'	=> ['wx-open-launch-weapp']

			// 'ajax_url'			=> admin_url('admin-ajax.php'),
			// 'nonce'				=> wp_create_nonce('weixin_nonce'),
			// 'refresh_url'		=> apply_filters('weixin_refresh_url',		'', $link, $post_id),
			// 'notify'				=> apply_filters('weixin_share_notify',		0),
			// 'content_wrap'		=> apply_filters('weixin_content_wrap',		''),
			// 'hide_option_menu'	=> apply_filters('weixin_hide_option_menu',	0),
		];
		
		wp_enqueue_script('jweixin', 'https://res.wx.qq.com/open/js/jweixin-1.6.0.js');
		wp_enqueue_script('weixin', WEIXIN_ROBOT_PLUGIN_URL.'static/weixin-share.js', ['jweixin'] );
		
		wpjam_localize_script('weixin', 'weixin_share', $weixin_share);
	}
}

add_action('wp_loaded', function(){
	if(weixin_get_type() >= 3){
		WPJAM_VerifyTXT::register('weixin', ['title'=>'微信公众号验证文件']);
		
		if(is_admin()){
			if(function_exists('weixin_add_sub_page')){
				weixin_add_sub_page('weixin-jssdk',	[
					'menu_title'	=> '网页分享',
					'function'		=> 'option',
					'option_name'	=> 'weixin-robot',	
					'load_callback'	=> ['WEIXIN_JSSDK', 'load_option_page']
				]);
			}
		}else{
			if(weixin_get_setting('weixin_share')){
				add_action('wp_enqueue_scripts', ['WEIXIN_JSSDK', 'on_enqueue_scripts'], 9999);
			}
		}
	}
});