<?php

// 定义高级回复在自定义回复中的 tab
add_filter('weixin-robot-replies_tabs', 'weixin_robot_add_advanced_reply_tab',11);
function weixin_robot_add_advanced_reply_tab($tabs){
	$tabs['advanced']	= array('title'=>'高级回复', 'function'=>'weixin_robot_advanced_reply_page'); // 高级回复和默认回复处理方式一样
	return $tabs;
}

function weixin_robot_advanced_reply_page(){
	wpjam_option_page('weixin-robot',array('page_type'=>'default'));
}

add_filter('weixin_reply_setting','weixin_robot_advanced_reply_setting',11);
function weixin_robot_advanced_reply_setting($sections){
	global $plugin_page;
	if($plugin_page == 'weixin-robot-replies' && isset($_GET['tab']) && $_GET['tab'] == 'advanced'){
    
	    $advanced_reply_section_fields = array(
			'new'		=> array('title'=>'最新日志',			'type'=>'text',	'class'=>'tiny-text'),
			'rand'		=> array('title'=>'随机日志',			'type'=>'text',	'class'=>'tiny-text'),
			'comment'	=> array('title'=>'留言最高日志',		'type'=>'text',	'class'=>'tiny-text'),
			'comment-7'	=> array('title'=>'7天留言最高日志',	'type'=>'text',	'class'=>'tiny-text'),
			'hot'		=> array('title'=>'浏览最高日志',		'type'=>'text',	'class'=>'tiny-text'),
			'hot-7'		=> array('title'=>'7天浏览最高日志',	'type'=>'text',	'class'=>'tiny-text'),
		);

		$advanced_reply_section_fields = apply_filters('weixin_advanced_reply',$advanced_reply_section_fields);

		$sections = array(
	    	'advanced_reply'	=> array(
	    		'title'		=>'高级回复',
	    		'fields'	=>$advanced_reply_section_fields,	
	    		'summary'	=>'<p>设置返回下面各种类型日志的关键字。</p>'
	    	)
	    );
	}
    
    return $sections;
}

add_filter('weixin_default_option','weixin_robot_advanced_reply_default_option');
function weixin_robot_advanced_reply_default_option($default_option){

	$advanced_reply_default_option = array(
		'new'			=> 'n',
		'rand'			=> 'r', 
		'hot'			=> 't',
		'comment'		=> 'c',
		'hot-7'			=> 't7',
		'comment-7'		=> 'c7',
		'hot-30'		=> 't30',
		'comment-30'	=> 'c30'
	);

	return array_merge($default_option, $advanced_reply_default_option);
}