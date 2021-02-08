<?php

global $wpdb;

$wpdb->weixin_user_tags					= $wpdb->prefix . 'weixin_user_tags';
$wpdb->weixin_user_tag_relationships	= $wpdb->prefix . 'weixin_user_tag_relationships';


// if($wpdb->get_var("show tables like '{$wpdb->weixin_user_tags}'") != $wpdb->weixin_user_tags) {
// 		$sql = "
// 		CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_user_tags}` (
// 		  `id` bigint(20) NOT NULL auto_increment,
// 		  `name` varchar(200) NOT NULL,
// 		  `count` int(10) NOT NULL,
// 		  PRIMARY KEY  (`id`)
// 		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// 		";
 
// 		dbDelta($sql);
// 	}

// 	if($wpdb->get_var("show tables like '{$wpdb->weixin_user_tag_relationships}'") != $wpdb->weixin_user_tag_relationships) {
// 		$sql = "
// 		CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_user_tag_relationships}` (
// 		  `weixin_openid` varchar(30) NOT NULL,
// 		  `tag_id` bigint(20) NOT NULL,
// 		  `source` varchar(255) NOT NULL,
// 		  `time` int(10) NOT NULL,
// 		  PRIMARY KEY  (`weixin_openid`,`tag_id`),
//   		  KEY `tag_id` (`tag_id`)
// 		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// 		";
 
// 		dbDelta($sql);
// 	}

// 对用户标签操作之前的检测
function weixin_robot_prepare_tag($tag){

	if(is_array($tag)){
		if(isset($tag['name'])){
			$tag['name'] = trim($tag['name']);
		}
	}else{
		if(!trim($tag)){
			return new WP_Error('empty-tag','标签名为空');
		}
		$tag = array('name'=>trim($tag));
	}

	if(empty($tag['name']) && empty($tag['id'])){
		return new WP_Error('illegal-tag','非法标签');
	}

	return $tag;
}

// 获取用户标签
function weixin_robot_get_tag($tag, $create = false){
	global $wpdb;

	$tag = weixin_robot_prepare_tag($tag);

	if(is_wp_error($tag)){
		return $tag;
	}

	if(isset($tag['name'])){
		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->weixin_user_tags} WHERE name = %s",trim($tag['name']));
	}elseif(isset($tag['id'])){
		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->weixin_user_tags} WHERE id = %d",trim($tag['id']));
	}

	if($wpdb->get_row($sql, ARRAY_A)){
		return $wpdb->get_row($sql, ARRAY_A);
	}else{
		if($create){
			return weixin_robot_create_tag($tag);
		}else{
			return false;
		}
	}
}

// 创建用户标签
function weixin_robot_create_tag($tag){
	global $wpdb;

	$tag = weixin_robot_prepare_tag($tag);

	if(is_wp_error($tag)){
		return $tag;
	}

	if(isset($tag['name'])){

		$current_tag = weixin_robot_get_tag($tag);

		if($current_tag){
			return new WP_Error('tag-exist','该标签已经存在');
		}

		$wpdb->insert($wpdb->weixin_user_tags, $tag);

		return weixin_robot_get_tag($tag);
	}

	return false;
}

// 修改用户标签
function weixin_robot_update_tag($id, $tag){
	global $wpdb;

	$tag = weixin_robot_prepare_tag($tag);

	if(is_wp_error($tag)){
		return $tag;
	}

	$wpdb->update($wpdb->weixin_user_tags, $tag, array('id'=>$id));
	if($wpdb->last_error){
		return new WP_Error('update-fail',$wpdb->last_error);
	}else{
		return weixin_robot_get_tag($tag);
	}
}

// 更新标签的使用数量
function weixin_robot_update_tag_count($tag){
	global $wpdb;
	$tag	= weixin_robot_get_tag($tag);
	$id		= $tag['id'];
	
	// $count 	= $wpdb->get_var("SELECT count(*) FROM {$wpdb->weixin_user_tag_relationships} WHERE tag_id = $id"); 
	$count 	= $wpdb->get_var($wpdb->prepare("SELECT count(*) FROM {$wpdb->weixin_users} wu INNER JOIN {$wpdb->weixin_user_tag_relationships} wutr on wu.openid=wutr.weixin_openid  WHERE subscribe=1 AND tag_id=%d",$id)); 
	$tag 	= array('id'=>$id,'count'=>$count);
	return weixin_robot_update_tag($id, $tag);
}

// 删除用户标签
function weixin_robot_delete_tag($tag){
	global $wpdb;

	$tag = weixin_robot_get_tag($tag);

	if(is_wp_error($tag)){
		return $tag;
	}
		
	$weixin_openids = weixin_robot_get_tag_users($tag,false);
	if($weixin_openids){
		foreach ($weixin_openids as $weixin_openid) {
			wp_cache_delete($weixin_openid, 'weixin_user_tags');
		}
	}

	$wpdb->delete($wpdb->weixin_user_tag_relationships, array('tag_id'=>$tag['id']));
	$wpdb->delete($wpdb->weixin_user_tags, array('id'=>$tag['id']));
}

// 获取某个用户的所有标签
function weixin_robot_get_user_tags($weixin_openid){

	$weixin_user_tags = wp_cache_get($weixin_openid, 'weixin_user_tags');

	if($weixin_user_tags === false){
		global $wpdb;

		$sql = "SELECT wutt.* FROM {$wpdb->weixin_user_tags} wutt INNER JOIN {$wpdb->weixin_user_tag_relationships} wutrt WHERE wutt.id = wutrt.tag_id AND wutrt.weixin_openid = '{$weixin_openid}'";

		$weixin_user_tags = $wpdb->get_results($sql, ARRAY_A);

		if($weixin_user_tags){
			wp_cache_set($weixin_openid, $weixin_user_tags, 'weixin_user_tags', 36000);
		}else{
			wp_cache_set($weixin_openid, array(), 'weixin_user_tags', 36000);
		}
	}

	return $weixin_user_tags;
}

function weixin_robot_get_user_tag_ids($weixin_openid){
	$weixin_user_tags		= weixin_robot_get_user_tags($weixin_openid);
	$weixin_user_tag_ids	= array();

	if($weixin_user_tags){
		foreach ($weixin_user_tags as $weixin_user_tag) {
			$weixin_user_tag_ids[]	= $weixin_user_tag['id'];
		}
	}
	return $weixin_user_tag_ids;
}

// 获取某个标签的所有用户
function weixin_robot_get_tag_users($tag='', $subscribe=true){
	global $wpdb;

	if($tag){
		$tag = weixin_robot_get_tag($tag);

		if(is_wp_error($tag)){
			return $tag;
		}
		if($subscribe){
			return $wpdb->get_col($wpdb->prepare("SELECT wutr.weixin_openid FROM {$wpdb->weixin_users} wu INNER JOIN {$wpdb->weixin_user_tag_relationships} wutr on wu.openid=wutr.weixin_openid  WHERE subscribe=1 AND tag_id=%d",$tag['id']));
		}else{
			return $wpdb->get_col($wpdb->prepare("SELECT weixin_openid FROM {$wpdb->weixin_user_tag_relationships} WHERE tag_id=%d",$tag['id']));
		}
	}else{
		if($subscribe){
			return $wpdb->get_col("SELECT openid FROM {$wpdb->weixin_users} WHERE subscribe=1 AND openid NOT IN (SELECT weixin_openid FROM {$wpdb->weixin_user_tag_relationships})");
		}else{
			return $wpdb->get_col("SELECT openid FROM {$wpdb->weixin_users} WHERE openid NOT IN (SELECT weixin_openid FROM {$wpdb->weixin_user_tag_relationships})");
		}
	}
}

// 设置某个用户的所有标签
function weixin_robot_update_user_tags($weixin_openid, $new_tags){
	$old_tag_ids = $new_tag_ids = array();

	do_action('weixin_user_tags',$new_tags);

	$old_tag_ids = weixin_robot_get_user_tag_ids($weixin_openid);
	
	// if($old_tags){
	// 	foreach ($old_tags as $old_tag) {
	// 		$old_tag_ids[] = $old_tag['id'];
	// 	}
	// }

	if($new_tags){
		foreach ($new_tags as $new_tag) {
			$new_tag = weixin_robot_get_tag($new_tag, true);
			$new_tag_ids[] = $new_tag['id'];
		}
	}

	$delete_tag_ids = array_diff($old_tag_ids, $new_tag_ids);
	if($delete_tag_ids){
		foreach ($delete_tag_ids as $delete_tag_id) {
			$delete_tag = array('id'=>$delete_tag_id);
			weixin_robot_delete_user_tag($weixin_openid, $delete_tag, false);
		}
	}
	
	$insert_tag_ids = array_diff($new_tag_ids, $old_tag_ids);

	if($insert_tag_ids){
		foreach ($insert_tag_ids as $insert_tag_id) {
			$insert_tag = array('id'=>$insert_tag_id);
			weixin_robot_insert_user_tag($weixin_openid, $insert_tag, array('delete_cache'=>false));
		}
	}

	wp_cache_delete($weixin_openid, 'weixin_user_tags');
}

// 给某个用户设置标签
function weixin_robot_insert_user_tag($weixin_openid, $tag, $args=array()){
	global $wpdb;

	extract(wp_parse_args($args, array(
		'delete_cache'	=> true,
		'update_count'	=> true,
		'source'		=> ''
	)));

	// $weixin_user = weixin_robot_get_user($weixin_openid);
	// $weixin_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->weixin_users} WHERE openid=%s",$weixin_openid),ARRAY_A);
	// if(!$weixin_user || $weixin_user['subscribe']==0){
	// 	return false;
	// }

	$tag = weixin_robot_get_tag($tag, true);

	if(is_wp_error($tag)){
		return $tag;
	}

	$sql = $wpdb->prepare("SELECT * FROM {$wpdb->weixin_user_tag_relationships} WHERE tag_id = %d AND weixin_openid = %s", $tag['id'], $weixin_openid);

	if($wpdb->query($sql)){
		return weixin_robot_get_user_tags($weixin_openid);
	}

	// $user_tag_ids = weixin_robot_get_user_tag_ids($weixin_openid);

	// if(isset($_GET['debug'])){
	// 	wpjam_print_r($user_tag_ids);
	// 	wpjam_print_r($tag);
	// }

	// if($user_tag_ids && in_array($tag['id'],$user_tag_ids)){
	// 	return weixin_robot_get_user_tags($weixin_openid);
	// }

	$wpdb->insert($wpdb->weixin_user_tag_relationships, array(
		'tag_id'		=> $tag['id'], 
		'weixin_openid'	=> $weixin_openid,
		'source'		=> $source,
		'time'			=> time()
	));

	if($update_count){
		weixin_robot_update_tag_count($tag);
	}

	if($delete_cache){
		wp_cache_delete($weixin_openid, 'weixin_user_tags');
	}

	return weixin_robot_get_user_tags($weixin_openid);
}

// 删除某个用户的标签
function weixin_robot_delete_user_tag($weixin_openid, $tag, $delete_cache=true){
	global $wpdb;

	$tag = weixin_robot_get_tag($tag);

	if(is_wp_error($tag)){
		return $tag;
	}

	$wpdb->delete($wpdb->weixin_user_tag_relationships, array('tag_id'=>$tag['id'], 'weixin_openid'=>$weixin_openid));
	weixin_robot_update_tag_count($tag);

	if($delete_cache){
		wp_cache_delete($weixin_openid, 'weixin_user_tags');
	}

	return weixin_robot_get_user_tags($weixin_openid);
}


// 插入用户订阅标签
function weixin_robot_insert_user_subscribe_tag($weixin_openid, $scene, $source='subscribe'){
	if($weixin_qrcode = weixin_robot_get_qrcode($scene)){
		if($weixin_qrcode['name']){
			weixin_robot_insert_user_tag($weixin_openid, $weixin_qrcode['name'], array('source'=>$source));
		}	
	}
}

add_action('weixin_subscribe', 'weixin_robot_insert_user_subscribe_tag', 2);
add_action('weixin_scan', 'weixin_robot_insert_user_subscribe_tag', 3);
