<?php
$expires_in		= get_transient('weixin_access_token_expires')-time();
$expires_in		= ($expires_in > 0)?$expires_in:30;

$access_token	=  weixin_robot_get_access_token();

$response 		= array('access_token' => $access_token, 'expires_in'=>$expires_in);

wpjam_send_json($response);