<?php

global $wpdb;
$response = array();

$weixin_qrcodes 	= $wpdb->get_results("SELECT `scene`, `name`, `type`, `expire` FROM {$wpdb->weixin_qrcodes}", ARRAY_A);

if($weixin_qrcodes){
	$response['qrcode'] = $weixin_qrcodes;
}

wpjam_send_json($response);