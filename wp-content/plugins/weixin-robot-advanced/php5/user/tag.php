<?php
// 1. 最多100个标签
// 2. 用户最多打3个标签
// 3. 三个系统默认保留的标签不能修改
// 4. 粉丝数超过10w的标签不能删除
// 5. 批量为用户打标签每次最多50个用户，取消打标签也是

// 因为获取用户详细资料的接口已有标签信息，所以获取用户标签接口无意义

// 微信 batchget 用户资料里面的 tagid 列表是错的，==> 微信已经修正成对的，
function weixin_robot_get_tags($force=false){

	$weixin_user_tags	= get_transient('weixin_user_tags');

	if($weixin_user_tags === false || $force){
		$url			= 'https://api.weixin.qq.com/cgi-bin/tags/get';
		$response	= weixin_robot_remote_request($url);
		if(is_wp_error($response)){
			return $response;
		}

		$weixin_user_tags = $response['tags'];

		$weixin_user_tags_new = array();

		foreach ($weixin_user_tags as $weixin_user_tag) {
			$weixin_user_tags_new[$weixin_user_tag['id']]	= $weixin_user_tag;
		}

		$weixin_user_tags = $weixin_user_tags_new;

		set_transient('weixin_user_tags', $weixin_user_tags, DAY_IN_SECONDS);
	}

	return $weixin_user_tags;
}

function weixin_robot_create_tag($name){
	delete_transient('weixin_user_tags');

	$url	= 'https://api.weixin.qq.com/cgi-bin/tags/create';
	$data	= wpjam_json_encode(array('tag'=>compact('name')));
	return weixin_robot_remote_request($url, 'post', $data); 
}

function weixin_robot_update_tag($id, $name){
	delete_transient('weixin_user_tags');

	$url	= 'https://api.weixin.qq.com/cgi-bin/tags/update';
	$data	= wpjam_json_encode(array('tag'=>compact('id','name')));
	return weixin_robot_remote_request($url, 'post', $data); 
}

function weixin_robot_delete_tag($tagid){
	delete_transient('weixin_user_tags');

	$tagid	= (int)$tagid;

	$url	= 'https://api.weixin.qq.com/cgi-bin/tags/delete';
	$data	= wpjam_json_encode(compact('tagid'));
	return weixin_robot_remote_request($url, 'post', $data); 
}

function weixin_robot_batch_tagging($openid_list, $tagid){
	delete_transient('weixin_user_tags');

	if(is_string($openid_list)){
		$openid_list = array($openid_list);
	}

	$url 		= 'https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging';
	$data		= wpjam_json_encode(compact('openid_list','tagid'));
	$response	= weixin_robot_remote_request($url, 'post', $data); 

	if(!is_wp_error($response)){
		if(count($openid_list) > 1){
			weixin_robot_batch_get_user_info($openid_list);
		}
	}

	return $response;
}

function weixin_robot_batch_untagging($openid_list, $tagid){
	delete_transient('weixin_user_tags');

	if(is_string($openid_list)){
		$openid_list = array($openid_list);
	}

	$url 		= 'https://api.weixin.qq.com/cgi-bin/tags/members/batchuntagging';
	$data		= wpjam_json_encode(compact('openid_list','tagid'));
	$response	= weixin_robot_remote_request($url, 'post', $data); 

	if(!is_wp_error($response)){
		if(count($openid_list)>1){
			weixin_robot_batch_get_user_info($openid_list);
		}
	}
	
	return $response;
}

function weixin_robot_get_user_tagid_list($openid){	// 因为获取用户详细资料的接口已有标签信息，该接口无效
	$url	= 'https://api.weixin.qq.com/cgi-bin/tags/getidlist';
	$data	= wpjam_json_encode(compact('openid'));
	return weixin_robot_remote_request($url, 'post', $data); 
}


add_action('weixin_get_tag_user_list', 'weixin_robot_get_tag_user_list', 10, 3 );
function weixin_robot_get_tag_user_list($tagid=0, $next_openid='',$continue=false){
	if($tagid == 0){
		$continue = true;
		
		$weixin_user_tags	= weixin_robot_get_tags($force = true);

		foreach ($weixin_user_tags as $weixin_user_tag) {
			if(!empty($weixin_user_tag['count'])){
				$tagid = $weixin_user_tag['id'];
				break;
			}
		}
	}

	$url		= 'https://api.weixin.qq.com/cgi-bin/user/tag/get';
	$data		= wpjam_json_encode(compact('tagid','next_openid'));
	$response	=  weixin_robot_remote_request($url, 'post', $data); 

	if(is_wp_error($response)){
		wp_schedule_single_event(time()+60,'weixin_get_tag_user_list',array($tagid, $next_openid, $continue));	// 失败了，就1分钟后再来一次	
		return $response;
	}

	$count 			=  $response['count'];
	$next_openid	=  isset($response['next_openid'])?$response['next_openid']:'';

	if($next_openid && $count > 0){
		wp_schedule_single_event(time()+10,'weixin_get_tag_user_list',array($tagid, $next_openid, $continue));
	}elseif($continue){
		$weixin_user_tags	= weixin_robot_get_tags($force = true);

		$next				= 0;
		foreach ($weixin_user_tags as $current_tagid => $weixin_usert_tag) {
			if($current_tagid == $tagid){
				$next	 = 1;	// 下一个就是我们下一次抓取的
			}elseif($next && $weixin_usert_tag['count']>0){
				wp_schedule_single_event(time()+10,'weixin_get_tag_user_list',array($current_tagid, '', $continue));
			}
		}
	}

	if($count){
		$openid_list =  $response['data']['openid'];

		foreach ($openid_list as $openid) {
			$weixin_user = weixin_robot_get_user($openid);
			for ($i=1; $i <= 3; $i++) { 
				if(empty($weixin_user['tagid_'.$i])){
					$weixin_user['tagid_'.$i] = $tagid;	// 有空
					weixin_robot_update_user($openid, $weixin_user);
					break;
				}else{
					if($weixin_user['tagid_'.$i] == $tagid){	// 已有
						break;
					}else{	// 被人占用了
						continue;
					}
				}
			}
		}
	}
}
