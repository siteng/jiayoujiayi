<?php
// 微信 CRM 用户管理
// 获取微信用户自定义字段
function weixin_robot_crm_get_user($weixin_openid=''){
	global $wpdb;

	if(!$weixin_openid ) $weixin_openid = weixin_robot_get_user_openid();
	if(!$weixin_openid )  return false;

	$weixin_crm_user = wp_cache_get($weixin_openid,'weixin_crm_user');

	if($weixin_crm_user === false){
		$weixin_crm_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->weixin_crm_users} WHERE weixin_openid=%s",$weixin_openid),ARRAY_A);

		if($weixin_crm_user) wp_cache_set($weixin_openid, $weixin_crm_user, 'weixin_crm_user',36000);
	}
	return $weixin_crm_user;
}

// 更新微信用户自定义字段
function weixin_robot_crm_update_user($weixin_crm_user){
	global $wpdb;

	$weixin_openid = $weixin_crm_user['weixin_openid'] = isset($weixin_crm_user['weixin_openid'])?$weixin_crm_user['weixin_openid']:weixin_robot_get_user_openid();
	if(!$weixin_openid)  return false;
	
	$old_user = weixin_robot_crm_get_user($weixin_openid);

	if($old_user){
		wp_cache_delete($weixin_openid, 'weixin_crm_user');
		if(WEIXIN_TYPE >= 3 && isset($weixin_crm_user['name'])){
			weixin_robot_update_user_remark($weixin_openid, $weixin_crm_user['name']);
		}
		$weixin_crm_user = wp_parse_args($weixin_crm_user,$old_user);
		return $wpdb->update($wpdb->weixin_crm_users,$weixin_crm_user,array('weixin_openid'=>$weixin_openid));
	}else{
		return false;
	}
}

// 新增微信用户自定义字段
function weixin_robot_crm_insert_user($weixin_crm_user){
	global $wpdb;

	$weixin_openid = $weixin_crm_user['weixin_openid'] = isset($weixin_crm_user['weixin_openid'])?$weixin_crm_user['weixin_openid']:weixin_robot_get_user_openid();
	if(!$weixin_openid)  return false;
	
	$old_user = weixin_robot_crm_get_user($weixin_openid);

	if($old_user){
		return false;
	}else{
		if(empty($weixin_crm_user['registered_time'])){
			$weixin_crm_user['registered_time'] = time();
		}
		if(WEIXIN_TYPE >= 3 && isset($weixin_crm_user['name'])){
			weixin_robot_update_user_remark($weixin_openid, $weixin_crm_user['name']);
		}
		return $wpdb->insert($wpdb->weixin_crm_users,$weixin_crm_user);
	}
}