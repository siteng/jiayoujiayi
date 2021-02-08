<?php 
$uid = isset( $_GET['uid'] ) ? $_GET['uid'] : '';
$cardid = isset( $_GET['cardid'] ) ? $_GET['cardid'] : '';

if(! $uid ) wpjam_send_json( array('status' => 'error', 'msg' => 'uid不能为空'));
if(! $cardid ) wpjam_send_json( array('status' => 'error', 'msg' => 'card id不能为空'));


$openid = weixin_robot_get_user_openid($uid);
$user = weixin_robot_get_user($openid);
if(!$user)wpjam_send_json( array('status' => 'error', 'msg' => 'UID有误，用户不存在'));

global $wpdb;

// $sql = "SELECT * FROM $wpdb->weixin_messages WHERE FromUserName = '{$openid}'  AND Event = 'submit_membercard_user_info' LIMIT 1";


$sql = "SELECT * FROM $wpdb->weixin_messages WHERE FromUserName = '{$openid}' AND Title = '{$cardid}' AND Event = 'submit_membercard_user_info' LIMIT 1";


$result = $wpdb->get_row($sql, ARRAY_A);

if($result){
	wpjam_send_json( array('status' => 'ok', 'data' => $result));
}else{
	wpjam_send_json( array('status' => 'error', 'msg' => '会员卡不存在或是您还没有领取'));
}