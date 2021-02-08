<?php
global $wpdb;
$response = array();

$weixin_custom_replies 	= $wpdb->get_results("SELECT `keyword`, `match`, reply, `status`, `time`, `type` FROM {$wpdb->weixin_custom_replies}", ARRAY_A);

if($weixin_custom_replies){
	$response['custom_replies'] = $weixin_custom_replies;
}

wpjam_send_json($response);