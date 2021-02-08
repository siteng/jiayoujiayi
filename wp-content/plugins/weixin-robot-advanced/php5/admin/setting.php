<?php

add_filter('wpjam_settings', 'weixin_robot_settings');
function weixin_robot_settings($wpjam_settings){
	$wpjam_settings['weixin-robot']					= array('sections'=>weixin_robot_get_option_sections() );
	$wpjam_settings['weixin-robot-extends']			= array('sections'=>weixin_robot_get_extends_option_sections());
	if(WEIXIN_TYPE == 4){
		$wpjam_settings['weixin-robot-campaigns']	= array('sections'=>weixin_robot_get_campaigns_option_sections());
	}
	return $wpjam_settings;
}

/* 基本设置的字段 */
function weixin_robot_get_option_sections(){
	global $plugin_page;

	if($plugin_page == 'weixin-robot'){

		$weixin_section_fields = array(
			'weixin_type'					=> array('title'=>'微信账号类型',		'type'=>'select',	'options'=>array('-1'=>' ','1'=>'订阅号','2'=>'服务号','2.5'=>'认证订阅号（微博认证）','3'=>'认证订阅号（微信认证）','4'=>'认证服务号'),'description'=>'获得<strong>资质认证</strong>认证订阅号选择“认证订阅号（微信认证）”，获得<strong>资质认证</strong>的服务号选择“认证服务号”。'),
			'weixin_app_id'					=> array('title'=>'AppID(应用ID)',	'type'=>'text',		'required'),
			'weixin_app_secret'				=> array('title'=>'APPSecret(应用密钥)','type'=>'text',	'required'),
			'weixin_url'					=> array('title'=>'URL(服务器地址)',	'type'=>'text', 	'readonly',	'value'=>home_url()),
			'weixin_token'					=> array('title'=>'Token(令牌)',		'type'=>'text',		'required'),
			'weixin_message_mode'			=> array('title'=>'消息加解密方式',	'type'=>'select',	'options'=>array('1'=>'明文模式','2'=>'兼容模式','3'=>'安全模式（推荐）')),
			'weixin_encodingAESKey'			=> array('title'=>'EncodingAESKey',	'type'=>'text'),
			'weixin_dkf'					=> array('title'=>'开启客服功能',		'type'=>'checkbox',	'description'=>'请首先在微信公众号后台开启。'),
			// 'weixin_oauth20'					=> array('title'=>'全局开启网页授权',	'type'=>'checkbox',	'description'=>'请首先在<strong>微信公众号后台</strong> &gt; <strong>公众号设置</strong> &gt; <strong>功能设置</strong> 的“<strong>网页授权域名</strong>”中输入当前博客域名。'),
			// 'weixin_force_subscribe_url'	=> array('title'=>'未关注强制跳转链接',	'type'=>'url',		'description'=>'在任意 url 后面加上<code>?weixin_force_subscribe</code>，就会跳转到该链接。')
		);

		if(!current_user_can('manage_options')){
			unset($weixin_section_fields['weixin_token']);
			unset($weixin_section_fields['weixin_app_id']);
			unset($weixin_section_fields['weixin_app_secret']);
			unset($weixin_section_fields['weixin_encodingAESKey']);
		}

		$site_section_fields = array(
			'weixin_search'					=> array('title'=>'开启文章搜索',			'type'=>'checkbox',	'description'=>'开启文章搜索，除了在自定义回复和内置回复的关键字之外，会去搜索博客文章。'), 
			'weixin_count'					=> array('title'=>'文章图文最大条数',		'type'=>'range',	'min'=>1,	'max'=>8,	'step'=>1), 
			'weixin_keyword_allow_length'	=> array('title'=>'搜索关键字最大长度',	'type'=>'number',	'description'=>'一个汉字算两个字节，一个英文单词算两个字节，空格不算，搜索多个关键字可以用空格分开！',	'min'=>8,	'max'=>20,	'step'=>2),
			'weixin_content_wrap'			=> array('title'=>'文章图片预览',			'type'=>'text',		'description'=>'输入文章内容的class或者ID开启微信图片预览功能，留空则不启用该功能'),
			'weixin_hide_option_menu'		=> array('title'=>'全局隐藏右上角菜单',	'type'=>'checkbox',	'description'=>'全局隐藏微网站右上角按钮')
		);

		$sections = array(
			'weixin'	=> array('title'=>'微信设置',		'fields'=>$weixin_section_fields,		'callback'=>'' ),
			'site'		=> array('title'=>'站点设置',		'fields'=>$site_section_fields,			'callback'=>'' ),
		);

		return apply_filters('weixin_setting',$sections);

	}elseif($plugin_page == 'weixin-robot-replies'){
		global $current_tab;

		if(isset($_GET['tab'])){
			$current_tab	= $_GET['tab'];
		}

		$sections = array();
		if(isset($current_tab) && $current_tab == 'third'){
			$third_party_section_fields = array(
				'weixin_3rd_1_fieldset'	=> array('title'=>'第三方自定义回复平台1',	'type'=>'fieldset',	'fields'=>array(
					'weixin_3rd_1'			=> array('title'=>'名称',	'type'=>'text',		'style'=>'width:120px;'),
					'weixin_3rd_cache_1'	=> array('title'=>'缓存时间',	'type'=>'number',	'style'=>'width:120px;','description'=>'秒，输入空或者0为不缓存！'),
					'weixin_3rd_url_1'		=> array('title'=>'链接',	'type'=>'url'),
					'weixin_3rd_search'		=> array('title'=>'',		'type'=>'checkbox',	'description'=>'所有在WordPress找不到内容的关键词都提交到第三方微信自定义回复平台1处理。')
				)),

				'weixin_3rd_2_fieldset'	=> array('title'=>'第三方自定义回复平台2',	'type'=>'fieldset',	'fields'=>array(
					'weixin_3rd_2'			=> array('title'=>'名称',	'type'=>'text',		'style'=>'width:120px;'),
					'weixin_3rd_cache_2'	=> array('title'=>'缓存时间',	'type'=>'number',	'style'=>'width:120px;','description'=>'秒'),
					'weixin_3rd_url_2'		=> array('title'=>'链接',	'type'=>'url')
				)),

				'weixin_3rd_3_fieldset'	=> array('title'=>'第三方自定义回复平台3',	'type'=>'fieldset',	'fields'=>array(
					'weixin_3rd_3'			=> array('title'=>'名称',	'type'=>'text',		'style'=>'width:120px;'),
					'weixin_3rd_cache_2'	=> array('title'=>'缓存时间',	'type'=>'number',	'style'=>'width:120px;','description'=>'秒'),
					'weixin_3rd_url_3'		=> array('title'=>'链接',	'type'=>'url')
				))
			);

			$sections = array( 'third_reply'	=> array('title'=>'第三方平台',		'fields'=>$third_party_section_fields,	'summary'=>'<p>如果第三方的回复的数据对所有用户都相同，建议缓存。</p>') );
		}

		return apply_filters('weixin_reply_setting',$sections);
	}
}

add_action('weixin-robot_option_page', 'weixin_robot_option_page');
function weixin_robot_option_page(){
	global $plugin_page;
	if(!empty($_GET['settings-updated'])){
		weixin_robot_delete_transient_cache(false);
		weixin_robot_activation();
	}
	if(isset($_GET['del'])){
		delete_option('weixin-robot');
	}
	?>
	<script type="text/javascript">
	jQuery(function($){
		<?php if(WEIXIN_TYPE < 3 ) { ?>
		$('#weixin_dkf').parent().parent().hide();
		<?php } ?>

		<?php if(WEIXIN_TYPE < 4 ) { ?>
		$('#weixin_force_subscribe_url').parent().parent().hide();
		$('#weixin_oauth20').parent().parent().hide();
		<?php } ?>

		<?php if(!weixin_robot_get_setting('weixin_oauth20')) { ?>
		$('#weixin_force_subscribe_url').parent().parent().hide();
		<?php } ?>



		<?php if(weixin_robot_get_setting('weixin_message_mode') == 1){?>
		$('#weixin_encodingAESKey').parent().parent().hide();
		<?php } ?>
		$('#weixin_type').change(function(){
			var weixin_type_selected = $("select#weixin_type").val();

			if(weixin_type_selected == '3' || weixin_type_selected == '4'){
				$('#weixin_dkf').parent().parent().show();
			}else{
				$('#weixin_dkf').parent().parent().hide();
			}

			if(weixin_type_selected == '4'){
				$('#weixin_oauth20').parent().parent().show();

				<?php if(weixin_robot_get_setting('weixin_oauth20')) { ?>
				$('#weixin_force_subscribe_url').parent().parent().show();
				<?php } ?>

			}else{
				$('#weixin_oauth20').parent().parent().hide();
			}
		});

		$('#weixin_message_mode').change(function(){
			var weixin_message_mode_selected = $("select#weixin_message_mode").val();

			if(weixin_message_mode_selected == '1'){
				$('#weixin_encodingAESKey').parent().parent().hide();
			}else{
				$('#weixin_encodingAESKey').parent().parent().show();
			}
		});

		$('#weixin_oauth20').change(function(){
			var weixin_oauth20 = $("input#weixin_oauth20").prop('checked');

			if(weixin_oauth20){
				$('#weixin_force_subscribe_url').parent().parent().show();
			}else{
				$('#weixin_force_subscribe_url').parent().parent().hide();
			}
		});
	});
	</script>
	<?php
}

// add_filter('weixin-robot_field_validate', 'weixin_robot_field_validate');
// function weixin_robot_field_validate( $weixin_robot ) {
// 	return $weixin_robot;
// }