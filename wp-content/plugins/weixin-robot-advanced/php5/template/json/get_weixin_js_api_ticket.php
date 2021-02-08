<?php
$ticket		=  weixin_robot_get_js_api_ticket();

$expires_in	= get_transient('weixin_js_api_ticket_expires')-time();
$expires_in	= ($expires_in > 0)?$expires_in:30;

$response	= array('ticket' => $ticket, 'expires_in'=>$expires_in);

wpjam_send_json($response);