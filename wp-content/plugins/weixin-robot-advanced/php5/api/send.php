<?php
add_action( 'weixin_send_future_mass_message', 'weixin_robot_send_future_mass_message', 10, 3 );
function weixin_robot_send_future_mass_message($tag_id, $msgtype='text', $content=''){
	$response = weixin_robot_sendall_mass_message($tag_id, $msgtype, $content);

	$msgtype_options	= array( 'mpnews'=>'图文',	 'image'=>'图片', 'voice'=>'语音', 'text'=>'文本' );
	

	$message = '<br />群发对象：';
	if($tag_id == 'all'){
		$message			.= '所有用户';  
	}else{
		$weixin_user_tags	= weixin_robot_get_tags();
		$message			.= ''.$weixin_user_tags[$tag_id]['name'];
	}

	$message	.= '<br />群发时间：'.date('Y-m-d H:i:s', current_time('timestamp'));
	$message	.= '<br />发送类型：'.$msgtype_options[$msgtype];
	$message	.= '<br />群发内容：'.'<code>'.$content.'</code>';


	if(is_wp_error($response)){
		$admin_notice = array(
			'type'		=> 'error',
			'notice'	=> '定时群发失败：'.$response->get_error_code().':'.$response->get_error_message().'！<br />'.$message.'<br />',
		);
	}else{
		$admin_notice = array(
			'type'		=> 'updated',
			'notice'	=> '定时群发成功！'.$message.'<br />',
		);
	}

	$admin_notice['page']	= 'weixin-robot-masssend';

	wpjam_add_admin_notice($admin_notice);
}

function weixin_robot_sendall_mass_message($tag_id, $msgtype='text', $content=''){
	$data	= weixin_robot_get_message_send_data($msgtype, $content);

	if($tag_id == 'all'){
		$data['filter']	= array('is_to_all'=>true);
	}else{
		$data['filter']	= array('tag_id'=>$tag_id, 'is_to_all'=>false);
	}

	
	$url = 'https://api.weixin.qq.com/cgi-bin/message/mass/sendall';

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data));
}

function weixin_robot_send_mass_message($touser, $msgtype='text', $content=''){
	$data 			= weixin_robot_get_message_send_data($msgtype, $content);
	$data['touser']	= $touser;

	$url = 'https://api.weixin.qq.com/cgi-bin/message/mass/send';

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data));
}

function weixin_robot_preview_mass_message($towxname, $msgtype='text', $content=''){
	$data				= weixin_robot_get_message_send_data($msgtype, $content);
	$data['towxname']	= $towxname;
	
	$url = 'https://api.weixin.qq.com/cgi-bin/message/mass/preview';

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data));
}

function weixin_robot_send_custom_message($touser, $msgtype='text', $content='', $kf_account=''){
	if(empty($content))	return;

	$data	= weixin_robot_get_message_send_data($msgtype, $content);

	$data['touser']	= $touser;
	if($kf_account){
		$data['customservice']	= array('kf_account' => $kf_account);
	}

	$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send';

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data) );
}

function weixin_robot_send_template_message($touser, $template_id, $data, $url='', $topcolor='#FF0000'){
	$data['touser']		= $touser;
	$data['template_id']= $template_id;
	$data['data']		= $data;

	if($url){
		$data['url']	= $url;
	}

	if($topcolor){
		$data['topcolor']	= $topcolor;
	}

	$url = 'https://api.weixin.qq.com/cgi-bin/message/template/send';

	return weixin_robot_remote_request($url, 'post', wpjam_json_encode($data) );
}

function weixin_robot_get_message_send_data($msgtype='text', $content='', $title='', $description=''){
	$data 				= array();
	$data['msgtype']	= $msgtype;
	
	switch ($msgtype) {
		case 'text':
			$data['text']	= array('content'	=> $content);
			break;

		case 'voice':
			$data['voice']	= array('media_id'	=> $content);
			break;

		case 'image':
			$data['image']	= array('media_id'	=> $content);
			break;

		// case 'video':
		// 	$data['video']	= array('media_id'	=> $content, 'title'=> $title, 'description'=>$description);
		// 	break;

		case 'news':
			$data['news']	= array('articles'	=> $content);
			break;

		case 'mpnews':
			$data['mpnews']	= array('media_id'	=> $content);
			break;

		case 'mpvideo':
			$data['mpvideo']= array('media_id'	=> $content);
			break;

		case 'wxcard':
			$data['wxcard']	= $content;
			break;
		
		default:
			break;
	}

	return $data;
}

function weixin_robot_upload_news_media($articles){
	$url = 'https://api.weixin.qq.com/cgi-bin/media/uploadnews';
	return weixin_robot_remote_request($url, 'post', $articles );
}

