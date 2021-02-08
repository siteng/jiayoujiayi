<?php
$response	=  weixin()->get_jsapi_ticket();

if(is_wp_error($response)){
	wpjam_send_json($response);
}

$response['expires_in']	= $response['expires_in']-time();

wpjam_send_json($response);