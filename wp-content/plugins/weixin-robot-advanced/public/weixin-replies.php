<?php
include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-reply.php';

wpjam_register_plugin_page_tab('custom',	['title'=>'自定义回复',		'function'=>'list']);
wpjam_register_plugin_page_tab('default',	['title'=>'默认回复',			'function'=>'list']);
wpjam_register_plugin_page_tab('builtin',	['title'=>'内置回复',			'function'=>'list']);
wpjam_register_plugin_page_tab('text',		['title'=>'文本回复附加信息',	'function'=>'option',	'option_name'=>'weixin-robot']);
wpjam_register_plugin_page_tab('third',		['title'=>'第三方平台',		'function'=>'option',	'option_name'=>'weixin-robot']);

if(weixin_has_feature('weixin_search')){
	wpjam_register_plugin_page_tab('advanced',	['title'=>'高级回复',		'function'=>'option',	'option_name'=>'weixin-robot']);
}

if(weixin_get_type() >= 3){
	wpjam_register_plugin_page_tab('messages',	['title'=>'最新消息',		'function'=>'list',		'list_table_name'=>'weixin-messages']);
}

add_filter('wpjam_plugin_page_load', function($plugin_page, $current_tab){
	if(in_array($current_tab, ['custom', 'default', 'builtin'])){
		WEIXIN_AdminReplySetting::set_tab($current_tab);

		wpjam_register_list_table('weixin-replies', [
			'title'		=> wpjam_get_current_tab_setting('title'),
			'singular'	=> 'weixin-reply',
			'plural'	=> 'weixin-replies',
			'model'		=> 'WEIXIN_AdminReplySetting',
			'ajax'		=> true,
			'fixed'		=> false,
		]);

		add_action('admin_enqueue_scripts', function(){
			wp_enqueue_style('weixin-news-items',	WEIXIN_ROBOT_PLUGIN_URL.'static/news-items.css');
			wp_add_inline_style('weixin-news-items', join("\n",[
				'th.column-title{width:126px;}',
				'th.column-keyword{width:210px;}',
				'th.column-keywords{width:40%;}',
				'th.column-match{width:70px;}',
				'th.column-type{width:84px;}',
				'th.column-status{width:56px;}',
				'th.column-MsgType{width:60px;}',
				'th.column-Response{width:94px;}',
				'th.column-CreateTime{width:84px;}',
				'th.column-username{width:240px;}'
			]));
		});
	}elseif($current_tab == 'messages'){
		WEIXIN_AdminMessage::set_tab('messages');

		wpjam_register_list_table('weixin-messages', [
			'title'		=> '消息管理',
			'singular'	=> 'weixin-message',
			'plural'	=> 'weixin-messages',
			'model'		=> 'WEIXIN_AdminMessage',
			'ajax'		=> true
		]);
	}elseif($current_tab == 'text'){
		wpjam_register_option('weixin-robot', [
			'summary'	=>'<p>文本回复附加信息是指统一在文本回复之后统一添加一段文字。</p>',
			'fields'	=>[
				'weixin_text_reply_append'	=> ['title'=>'',	'type'=>'textarea',	'style'=>'max-width:640px;',	'rows'=>10],
			]
		]);
	}elseif($current_tab == 'third'){
		wpjam_register_option('weixin-robot', [
			'summary'	=>'<p>如果第三方的回复的数据对所有用户都相同，建议缓存。</p>',
			'fields'	=>[
				'weixin_3rd_1_fieldset'	=> ['title'=>'第三方平台1',	'type'=>'fieldset',	'fields'=>[
					'weixin_3rd_1'			=> ['title'=>'名称',		'type'=>'text',		'class'=>'all-options'],
					'weixin_3rd_cache_1'	=> ['title'=>'缓存时间',	'type'=>'number',	'class'=>'all-options',	'description'=>'秒，输入空或者0为不缓存！'],
					'weixin_3rd_url_1'		=> ['title'=>'链接',		'type'=>'url'],
					'weixin_3rd_search'		=> ['title'=>'',		'type'=>'checkbox',	'description'=>'所有在本站找不到内容的关键词都提交到第三方平台1处理。']
				]],

				'weixin_3rd_2_fieldset'	=> ['title'=>'第三方平台2',	'type'=>'fieldset',	'fields'=>[
					'weixin_3rd_2'			=> ['title'=>'名称',		'type'=>'text',		'class'=>'all-options'],
					'weixin_3rd_cache_2'	=> ['title'=>'缓存时间',	'type'=>'number',	'class'=>'all-options',	'description'=>'秒'],
					'weixin_3rd_url_2'		=> ['title'=>'链接',		'type'=>'url']
				]],

				'weixin_3rd_3_fieldset'	=> ['title'=>'第三方平台3',	'type'=>'fieldset',	'fields'=>[
					'weixin_3rd_3'			=> ['title'=>'名称',		'type'=>'text',		'class'=>'all-options'],
					'weixin_3rd_cache_2'	=> ['title'=>'缓存时间',	'type'=>'number',	'class'=>'all-options',	'description'=>'秒'],
					'weixin_3rd_url_3'		=> ['title'=>'链接',		'type'=>'url']
				]]
			]
		]);
	}elseif($current_tab == 'advanced'){
		wpjam_register_option('weixin-robot', [
			'summary'	=>'<p>设置返回下面各种类型文章的关键字。</p>',
			'fields'	=>[
				'new'		=> ['title'=>'最新文章',			'type'=>'text',	'class'=>'',	'value'=>'n'],
				'rand'		=> ['title'=>'随机文章',			'type'=>'text',	'class'=>'',	'value'=>'r'],
				'comment'	=> ['title'=>'留言最高文章',		'type'=>'text',	'class'=>'',	'value'=>'c'],
				'comment-7'	=> ['title'=>'7天留言最高文章',	'type'=>'text',	'class'=>'',	'value'=>'c7'],
				'hot'		=> ['title'=>'浏览最高文章',		'type'=>'text',	'class'=>'',	'value'=>'t'],
				'hot-7'		=> ['title'=>'7天浏览最高文章',	'type'=>'text',	'class'=>'',	'value'=>'t7'],
			]
		]);
	}
}, 10, 2);
