<?php
// 根据场景 ID 获取带参数的二维码
function weixin_robot_get_qrcode($scene){
	$weixin_qrcode = wp_cache_get($scene, 'weixin_qrcode');

	if($weixin_qrcode === false){
		global $wpdb;

		if($weixin_qrcode = $wpdb->get_row("SELECT * FROM {$wpdb->weixin_qrcodes} WHERE scene = {$scene}",ARRAY_A)){
			wp_cache_set($scene, $weixin_qrcode, 'weixin_qrcode', 3600);
		}
	}
	return $weixin_qrcode;
}

// 创建带参数的二维码
function weixin_robot_create_qrcode($args=array()){
	global $wpdb;

	extract( wp_parse_args( $args, array(
		'scene'		=> '',
		'name'		=> '',
		'type'		=> 'QR_LIMIT_SCENE',
		'expire'	=> '1200'
	) ) );

	$data = array();

	if($type == 'QR_SCENE'){
		$data['expire_seconds'] = $expire;
	}

	$data['action_name']	= $type;

	$data['action_info']	= array(
		'scene'=>array(
			'scene_id'=>$scene
		)
	);
	
	$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create';

	$response = weixin_robot_remote_request($url, 'post', wpjam_json_encode($data) );

	if(is_wp_error($response)){
		return $response;
	}

	$data = array(
		'scene'	=> $scene,
		'name'	=> $name,
		'type'	=> $type,
		'ticket'=> $response['ticket']
	);

	if($type == 'QR_SCENE'){
		$data['expire'] = time()+$response['expire_seconds'];
	}

	if($name){
		if($qrcode = weixin_robot_get_qrcode($scene)){
			wp_cache_delete($scene, 'weixin_qrcode');
			return $wpdb->update($wpdb->weixin_qrcodes, $data, array('scene'=>$scene));
		}else{
			wp_cache_delete($scene, 'weixin_qrcode');
			return $wpdb->insert($wpdb->weixin_qrcodes, $data);
		}
	}else{
		return $data;
	}
}

// 记录用户订阅渠道
function weixin_robot_qrcode_subscibe($openid, $scene, $type='subscribe'){
	global $wpdb;

	$time	= time();
	$data	= compact('openid', 'scene', 'type', 'time'); 

	$wpdb->insert($wpdb->weixin_subscribes, $data);
}



