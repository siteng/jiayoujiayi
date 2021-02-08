<?php
//自定义回复，内置回复，函数回复，关键字太长处理等。
add_action('weixin_reply','weixin_robot_reply');
function weixin_robot_reply($keyword){
	if( weixin_robot_context_reply($keyword) )	return true;	// 上下文回复	
	if( weixin_robot_custom_reply($keyword) )	return true;	// 自定义回复	
	if( weixin_robot_builtin_reply($keyword) )	return true;	// 内置回复
	if( weixin_robot_query_reply($keyword) )	return true;	// 搜索回复
}

function weixin_robot_context_reply($keyword){
	global $wechatObj;

	$weixin_openid	= $wechatObj->get_weixin_openid();
	$msg_type		= $wechatObj->get_msgType();

	if($msg_type != 'text') {
		//wp_cache_delete($weixin_openid, 'context_keyword');
		return false;
	}

	$context_keyword = wp_cache_get($weixin_openid, 'context_keyword');

	if($context_keyword == false) return false;

	$weixin_builtin_replies 	= weixin_robot_get_builtin_replies();

	if($weixin_builtin_replies && isset($weixin_builtin_replies[$context_keyword]) ){
		call_user_func($weixin_builtin_replies[$context_keyword]['function'], $keyword);
		return true;
	}else{
		return false;
	}	
}

// 微信自定义回复
function weixin_robot_custom_reply($keyword){
	global $wechatObj, $weixin_query_match;

	// 前缀匹配，只支持2个字
	$prefix_keyword = mb_substr($keyword, 0, 2);

	$weixin_custom_keywords			= weixin_robot_get_custom_keywords('full');		// 完全匹配
	$weixin_custom_keywords_prefix	= weixin_robot_get_custom_keywords('prefix');	// 前缀匹配
	$weixin_custom_keywords_fuzzy	= weixin_robot_get_custom_keywords('fuzzy');	// 模糊匹配

	if($weixin_custom_keywords && isset($weixin_custom_keywords[$keyword]) ){
		$weixin_query_match		= 'full';
		$weixin_custom_reply 	= $weixin_custom_keywords[$keyword];
	}elseif($weixin_custom_keywords_prefix && isset($weixin_custom_keywords_prefix[$prefix_keyword]) ){
		$weixin_query_match		= 'prefix';
		$weixin_custom_reply	= $weixin_custom_keywords_prefix[$prefix_keyword];
	}elseif($weixin_custom_keywords_fuzzy && preg_match('/'.implode('|', array_keys($weixin_custom_keywords_fuzzy)).'/', $keyword, $matches)){
		$weixin_query_match		= 'fuzzy';
		$fuzzy_keyword			= $matches[0];
		$weixin_custom_reply	= $weixin_custom_keywords_fuzzy[$fuzzy_keyword];
	}else{
		return false;
	}

	if(isset($_GET['debug'])){
		print_r($weixin_custom_reply);
	}

	$rand_key = array_rand($weixin_custom_reply, 1);
	$weixin_custom_reply = $weixin_custom_reply[$rand_key];

	$reply = str_replace("\r\n", "\n", $weixin_custom_reply->reply);

	if($weixin_custom_reply->type == 'text'){		// 文本回复
		$wechatObj->set_response('custom-text');
		$wechatObj->textReply($reply);
	}elseif($weixin_custom_reply->type == 'img'){	// 文章图文回复
		add_filter('weixin_query','weixin_robot_img_reply_query');
		if($weixin_query_match == 'prefix'){
			$keyword = $prefix_keyword;
		}elseif($weixin_query_match == 'fuzzy'){
			$keyword = $fuzzy_keyword;
		}
		weixin_robot_post_query_reply($keyword);
		$wechatObj->set_response('custom-img');
	}elseif($weixin_custom_reply->type == 'img2'){	// 自定义图文回复
		$items = '';
		$raw_items = explode("\n\n", $reply);
		foreach ($raw_items as $raw_item ) {
			$lines = explode("\n", $raw_item);
			if( isset($lines[0]) && isset($lines[1]) && isset($lines[2]) && isset($lines[3])){
				$items .= $wechatObj->get_item($lines[0], $lines[1], $lines[2], $lines[3]);
			}else{
				trigger_error($keyword."\n".$reply."\n".'自定义图文不完整');
				return false;
			}
		}
		$items = wpjam_cdn_html_replace($items);
		$wechatObj->picReply(count($raw_items),$items);
		$wechatObj->set_response('custom-img2');
	}elseif($weixin_custom_reply->type == 'news'){	// 素材图文回复
		$material	= weixin_robot_get_material($reply, 'news');
		if(is_wp_error($material)){
			$wechatObj->textReply('素材图文错误：'.$material->get_error_code().' '.$material->get_error_message());
		}else{
			$items	= '';
			foreach ($material as $news_item) {
				// $thumb	= weixin_robot_get_material($news_item['thumb_media_id'], 'thumb');
				// $thumb	= is_wp_error($thumb)?'':$thumb;
				$items	.= $wechatObj->get_item($news_item['title'], $news_item['digest'], $news_item['thumb_url'], $news_item['url']);
			}
			// $items = wpjam_cdn_html_replace($items);
			$wechatObj->picReply(count($material),$items);
			$wechatObj->set_response('custom-news');
		}
	}elseif($weixin_custom_reply->type == '3rd'){	// 第三方回复
		weixin_robot_3rd_reply($reply, $keyword);
	}elseif($weixin_custom_reply->type == 'dkf'){	// 多客服
		$wechatObj->transferCustomerServiceReply($reply);
		$wechatObj->set_response('dkf');
	}elseif($weixin_custom_reply->type == 'image'){	// 图片回复
		$wechatObj->set_response('custom-image');
		$wechatObj->imageReply($reply);
	}elseif($weixin_custom_reply->type == 'voice'){	// 语音回复
		$wechatObj->set_response('custom-voice');
		$wechatObj->voiceReply($reply);
	}elseif($weixin_custom_reply->type == 'music'){	// 音乐回复
	 	$wechatObj->set_response('custom-music');
		$raw_items 		= explode("\n", $reply);
		$title 			= isset($raw_items[0])?$raw_items[0]:'';
		$description	= isset($raw_items[1])?$raw_items[1]:'';
		$music_url		= isset($raw_items[2])?$raw_items[2]:'';
		$hq_music_url	= isset($raw_items[3])?$raw_items[3]:'';
		$thumb_media_id	= isset($raw_items[4])?$raw_items[4]:'';
		$wechatObj->musicReply($title, $description, $music_url, $hq_music_url, $thumb_media_id);
	}elseif($weixin_custom_reply->type == 'video'){	// 视频回复
		$wechatObj->set_response('custom-video');
		$raw_items 	= explode("\n", $reply);
		$MediaId	= $raw_items[0];
		$title 		= isset($raw_items[1])?$raw_items[1]:'';
		$description= isset($raw_items[2])?$raw_items[2]:'';
		$wechatObj->videoReply($MediaId, $title, $description);
	}elseif($weixin_custom_reply->type == 'function'){	// 函数回复
		call_user_func($reply, $keyword);
	}elseif($weixin_custom_reply->type == 'wxcard'){
		$wechatObj->set_response('wxcard');
		$raw_items 	= explode("\n", $reply);
		$card_id	= isset($raw_items[0])?$raw_items[0]:'';
		$outer_id	= isset($raw_items[1])?$raw_items[1]:'';
		$code		= isset($raw_items[2])?$raw_items[2]:'';
		$openid		= isset($raw_items[3])?$raw_items[3]:'';

		$card_ext	= weixin_robot_generate_card_ext(compact('card_id','outer_id','code','openid'));

		// file_put_contents(WP_CONTENT_DIR.'/debug/card.log',var_export(compact('card_id','outer_id','code','openid'),true),FILE_APPEND);


		$wxcard		= compact('card_id','card_ext');

		$weixin_openid	= $wechatObj->get_weixin_openid();
		$response = weixin_robot_send_user($weixin_openid, $wxcard, 'wxcard');

		weixin_robot_empty_string_reply();
	}
	return true;
}

//获取自定义回复列表
function weixin_robot_get_custom_keywords($match='all'){
	global $wpdb;

	$match = (trim($match))?trim($match):'all';

	$weixin_custom_keywords = get_transient('weixin_custom_keywords_'.$match);

	if($weixin_custom_keywords === false){
		if($match == 'all'){
			$sql = "SELECT * FROM $wpdb->weixin_custom_replies WHERE status = 1";
		}else{
			$sql = $wpdb->prepare("SELECT keyword,reply,type FROM $wpdb->weixin_custom_replies WHERE `match` = %s AND status = 1", $match);
		}

		$weixin_custom_original_keywords = $wpdb->get_results($sql);
		
		$weixin_custom_keywords = array(); 
		if($weixin_custom_original_keywords){
			foreach ($weixin_custom_original_keywords as $weixin_custom_keyword ) {
				$key = strtolower(trim($weixin_custom_keyword->keyword));
				if(strpos($key,',')){
					foreach (explode(',', $key) as $new_key) {
						$new_key = strtolower(trim($new_key));
						if($new_key !== ''){
							$weixin_custom_keywords[$new_key][] = $weixin_custom_keyword;
						}
					}
				}else{
					$weixin_custom_keywords[$key][] = $weixin_custom_keyword;
				}
			}
		}

		if($match == 'full'){
			$weixin_builtin_replies	= weixin_robot_get_builtin_replies($match);

			foreach (array('[too-long]','[default]') as $keyword) {	// 将这两个作为函数回复写入到自定义回复中
				if(isset($weixin_custom_keywords[$keyword])) continue;

				if(isset($weixin_builtin_replies[$keyword])){
					$weixin_custom_keyword = new stdClass();
					$weixin_custom_keyword->keyword	= $keyword;
					$weixin_custom_keyword->reply	= $weixin_builtin_replies[$keyword]['function'];
					$weixin_custom_keyword->type	= 'function';

					$weixin_custom_keywords[$keyword][]	= $weixin_custom_keyword;
				}
			}
		}

		// if($match == 'full'){
		// 	$weixin_default_replies	= weixin_robot_get_default_reply_keywords();
		// 	$weixin_builtin_replies	= weixin_robot_get_builtin_replies($match);

		// 	foreach ($weixin_default_replies as $default_keyword => $default_reply) {
		// 		if(isset($weixin_custom_keywords[$default_keyword])) continue;

		// 		$weixin_custom_keyword = new stdClass();
		// 		$weixin_custom_keyword->keyword		= $default_keyword;

		// 		if(isset($weixin_builtin_replies[$default_keyword])){
		// 			$weixin_custom_keyword->reply	= $weixin_builtin_replies[$default_keyword]['function'];
		// 			$weixin_custom_keyword->type	= 'function';
		// 		}else{
		// 			$weixin_custom_keyword->reply	= $default_reply['value'];
		// 			$weixin_custom_keyword->type	= 'text';
		// 		}

		// 		$weixin_custom_keywords[$default_keyword][]	= $weixin_custom_keyword;
		// 	}
		// }

		uksort($weixin_custom_keywords, 'weixin_robot_key_len_sort'); // 按照键的长度降序排序

		set_transient('weixin_custom_keywords_'.$match, $weixin_custom_keywords, 3600);
	}
	return $weixin_custom_keywords;
}

function weixin_robot_key_len_sort($v, $w){
    $lv = strlen($v);
    $lw = strlen($w);
    return ($lv == $lw)?0:(($lv > $lw)?-1:1);
}

// 微信第三方回复
function weixin_robot_3rd_reply($no=1, $keyword=''){
	global $wechatObj;

	$third_cache	= weixin_robot_get_setting('weixin_3rd_cache_'.$no);
	$third_url		= weixin_robot_get_setting('weixin_3rd_url_'.$no);
	$timestamp		= isset($_GET["timestamp"])?$_GET["timestamp"]:'';
	$nonce 			= isset($_GET["nonce"])?$_GET["nonce"]:'';
	$signature		= isset($_GET["signature"])?$_GET["signature"]:'';
	$encrypt_type 	= isset($_GET["encrypt_type"])?$_GET["encrypt_type"]:'';
	$msg_signature 	= isset($_GET["msg_signature"])?$_GET["msg_signature"]:'';

	$msgType		= $wechatObj->get_msgType();
	

	$third_response = false;

	if($msgType == 'text'){
		$postObj	= $wechatObj->get_postObj();
		$keyword	= $postObj->Content;
		if($keyword && $timestamp && $nonce && $signature && $third_cache){
			$third_response	= wp_cache_get($keyword, 'weixin_3rd');
			// file_put_contents(WP_CONTENT_DIR.'/debug/3rd.log',$third_response);
		}
	}

	if($third_response === false){
		if($msg_signature){	// 如果加密要传输 msg_signature
			$third_url	= add_query_arg(compact('timestamp','nonce','signature','msg_signature','encrypt_type'),$third_url);
		}else{
			$third_url	= add_query_arg(compact('timestamp','nonce','signature'),$third_url);
		}

		// $postStr	= (isset($GLOBALS["HTTP_RAW_POST_DATA"]))?$GLOBALS["HTTP_RAW_POST_DATA"]:'';
		$postStr	= file_get_contents('php://input');

		$response	= wp_remote_post(
			$third_url, 
			array( 
				'headers' => array(
					'Content-Type'		=> 'text/xml',
					'Accept-Encoding'	=> ''
				),
				'body'=>$postStr
			)
		);

		if(is_wp_error($response)){
			$third_response = '';
		}else{
			$third_response = $response['body'];
			if(($msgType == 'text') && $keyword && $timestamp && $nonce && $signature && $third_cache){
				wp_cache_set($keyword, $third_response, 'weixin_3rd', $third_cache);
			}
		}
	}

		// file_put_contents(WP_CONTENT_DIR.'/debug/3rd.log',var_export($response,true));

	echo $third_response;
	$wechatObj->set_response('3rd');
}

//自定义图文日志查询
function weixin_robot_img_reply_query($weixin_query_array){
	global $weixin_query_match;

	$keyword = $weixin_query_array['s'];

	$weixin_custom_keywords	= weixin_robot_get_custom_keywords($weixin_query_match);
	$weixin_custom_reply 	= $weixin_custom_keywords[$keyword];

	$rand_key 				= array_rand($weixin_custom_reply, 1);
	$weixin_custom_reply	= $weixin_custom_reply[$rand_key];

	$post_ids = explode(',', $weixin_custom_reply->reply);

	$weixin_query_array['post__in']			= $post_ids;
	$weixin_query_array['posts_per_page']	= count($post_ids);
	$weixin_query_array['orderby']			= 'post__in';

	unset($weixin_query_array['s']);
	$weixin_query_array['post_type']	= 'any';

	return $weixin_query_array;
}

// openid 和 query_id 替换
// function weixin_robot_str_replace($str){
// 	global $wechatObj;
// 	$weixin_openid = $wechatObj->get_weixin_openid();
// 	if($weixin_openid){
// 		$query_id = weixin_robot_get_user_query_id($weixin_openid);	
// 		return str_replace(array("\r\n", '[openid]', '[query_id]'), array("\n", $weixin_openid, $query_id), $str);
// 	}
// 	return $str;
// }