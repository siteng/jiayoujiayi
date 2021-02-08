<?php
// 新增临时素材
function weixin_robot_upload_media($media='', $type='image'){
	$url	= 'https://api.weixin.qq.com/cgi-bin/media/upload?type='.$type;
	$post	= array('media'=> new CURLFile($media,'',basename($media)));

	$response = weixin_robot_remote_request($url, 'file', $post );

	if(is_wp_error($response)){
		return $response;
	}

	if($response['type'] == 'thumb'){
		return $response['thumb_media_id'];
	}else{
		return $response['media_id'];
	}
}

// 上传图片获取微信图片链接
function weixin_robot_upload_img_media($media){
	$url	= 'https://api.weixin.qq.com/cgi-bin/media/uploadimg';
	$post	= array('media'=> new CURLFile($media,'',basename($media)));

	$response = weixin_robot_remote_request($url, 'file', $post );

	if(is_wp_error($response)){
		return $response;
	}

	return $response['url'];
}

// 获取临时素材
function weixin_robot_get_media($media_id, $type='image'){
	if($type=='image'){
		$image_url	= 'https://api.weixin.qq.com/cgi-bin/media/get?media_id='.$media_id;

		$media_dir	= substr($media_id, 0, 1).'/'.substr($media_id, 1, 1);
		if(!is_dir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'media/'.$media_dir)){
			mkdir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'media/'.$media_dir, 0777, true);
		}

		$media		= WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'media/'.$media_dir.'/'.$media_id.'.jpg';
		$media_url	= WEIXIN_ROBOT_PLUGIN_TEMP_URL.'media/'.$media_dir.'/'.$media_id.'.jpg';

		if(!file_exists($media)){
			$response = weixin_robot_remote_request($image_url, 'get', array(), array('stream'=>true, 'filename'=>$media,'need_json_decode'=>false));
			if(is_wp_error($response)){
				return $response;
			}
		}

		return $media_url;
	}
}

// 生成下载临时素材的链接
function weixin_robot_get_media_download_url($media_id){
	return 'https://api.weixin.qq.com/cgi-bin/media/get?media_id='.$media_id.'&access_token='.weixin_robot_get_access_token();
}

// 将远程图片新增到到素材，type = thumb 上传缩略图
function weixin_robot_upload_remote_image_media($image_url, $type='image'){
	$media = weixin_robot_download_remote_image($image_url);
	if(!is_wp_error($media)){
		$response = weixin_robot_upload_media($media, $type);

		unlink($media);
		return $response;
	}else{
		return false;
	}
}

// 获取永久素材
function weixin_robot_get_material($media_id, $type='image', $force=false){
	$url	= 'https://api.weixin.qq.com/cgi-bin/material/get_material';
	$data	= json_encode( array('media_id' => $media_id) );

	if($type=='image' || $type=='thumb'){

		$media_dir	= substr($media_id, 0, 1).'/'.substr($media_id, 1, 1);
		if(!is_dir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'material/'.$media_dir)){
			mkdir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'material/'.$media_dir, 0777, true);
		}

		$media		= WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'material/'.$media_dir.'/'.$media_id.'.jpg';
		$media_url	= WEIXIN_ROBOT_PLUGIN_TEMP_URL.'material/'.$media_dir.'/'.$media_id.'.jpg';

		if(file_exists($media) && $force == false){
			return $media_url;
		}

		// $response 	= weixin_robot_remote_request($url, 'post', $data);
		$response 	= weixin_robot_remote_request($url, 'post', $data, array('stream'=>true, 'filename'=>$media,'need_json_decode'=>false));

		if(is_wp_error($response)){
			if($response->get_error_code() == '40007'){	//  invalid media_id
				$im = imagecreatetruecolor(120, 20);
				$text_color = imagecolorallocate($im, 233, 14, 91);
				imagestring($im, 1, 5, 5,  'invalid media_id', $text_color);

				imagejpeg($im, $media, 100 ); // 存空图片，防止重复请求
			}
			return $response;
		}

		// imagejpeg(imagecreatefromstring($response), $media, 100 );

		return $media_url;
	}elseif($type == 'news'){
		$material = wp_cache_get( $media_id, 'weixin_material' );
		if($material === false || $force){
			$response 	= weixin_robot_remote_request($url, 'post', $data);

			if(is_wp_error($response)){
				return $response;
			}

			$material = $response['news_item'];
			wp_cache_set( $media_id, $material, 'weixin_material', 86400 );
		}
		return $material;
	}
}

//删除永久素材
function weixin_robot_del_material($media_id){
	$url		= 'https://api.weixin.qq.com/cgi-bin/material/del_material';
	$data		= json_encode(compact('media_id'));
	$response 	= weixin_robot_remote_request($url, 'post', $data );

	wp_cache_delete($media_id, 'weixin_material');

	return $response;
}

// 新增图文素材
function weixin_robot_add_news_material($articles){
	$url		= 'https://api.weixin.qq.com/cgi-bin/material/add_news';
	$data		= wpjam_json_encode(compact('articles'));
	$response 	= weixin_robot_remote_request($url, 'post', $data );

	return $response;
}

// 修改图文素材
function weixin_robot_update_news_material($media_id, $index, $articles){
	$url		= 'https://api.weixin.qq.com/cgi-bin/material/update_news';
	$data		= wpjam_json_encode(compact('media_id', 'index', 'articles'));
	$response 	= weixin_robot_remote_request($url, 'post', $data );
	return $response;
}


//新增其他类型永久素材
function weixin_robot_add_material($media, $type='image', $args=array()){
	extract(wp_parse_args( $args, array(
		'description'	=> '',
		'filename'		=> '',
		'filetype'		=> '',
	)));

	$url	= 'https://api.weixin.qq.com/cgi-bin/material/add_material';

	$data	= array();
	$data['type']	= $type;

	$filename 		= ($filename)?$filename:basename($media);
	$data['media']	= new CURLFile($media, $filetype, $filename);

	// if($form_data){
	// 	$data['form-data']	= $form_data;
	// }

	if($description){
		$data['description']= json_encode($description);
	}

	$response = weixin_robot_remote_request($url, 'file', $data);

	return $response;
}

// 获取素材列表
function weixin_robot_batch_get_material($type = 'news', $offset = 0, $count = 20 ){
	// $key		= md5($type.$offset.$count);
	// $material	= false;//wp_cache_get($key, 'weixin_material');
	// if($material === false || $force){
		$url		= 'https://api.weixin.qq.com/cgi-bin/material/batchget_material';
		$data		= json_encode( array( "type" => $type, "offset" => $offset, "count" => $count ) );
		$material	= weixin_robot_remote_request($url, 'post', $data );
		if(is_wp_error($material)){
			return false;
		}
	// 	wp_cache_set( $key, $material, 'weixin_material', 300 );
	// }
		
	return $material;
}

// 获取素材总数
function weixin_robot_get_material_count(){
	$material_count = get_transient('weixin_material_count');
	if($material_count === false){
		$url	= 'https://api.weixin.qq.com/cgi-bin/material/get_materialcount';
		$material_count = weixin_robot_remote_request($url);
		if(is_wp_error($material_count)){
			return false;
		}

		set_transient( 'weixin_material_count', $material_count, 30 );
	}

	return $material_count;
	
}

// 下载远程图片到本地
// function weixin_robot_download_remote_image($image_url, $media=''){

// 	if(strpos($image_url, home_url()) === 0){
// 		return str_replace(home_url('/'), ABSPATH, $image_url);	// 本地图片就用本地路径
// 	}

// 	$media = ($media)?$media:WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'media/temp/'.md5($image_url).'.jpg';

// 	$response = weixin_robot_remote_request($image_url, 'get', '', array('stream'=>true, 'filename'=>$media,'need_json_decode'=>false));

// 	if(is_wp_error($response)){
// 		return $response;
// 	}
	
// 	// $response = weixin_robot_remote_request($image_url,'get');
// 	// imagejpeg(imagecreatefromstring($response),$media, 100 );

// 	return $media;
// }


function weixin_robot_download_remote_image($image_url, $media=''){ 
	// add_action('http_api_curl','weixin_robot_remote_referer');
	// function weixin_robot_remote_referer($handle){
	// 	// wpjam_print_r($handle);
	// 	// echo "nnnnnn";
	// 	// exit;
	// 	curl_setopt ($handle, CURLOPT_REFERER, 'http://mp.weixin.qq.com');
	// }

	if(strpos($image_url, home_url()) === 0){
		return str_replace(home_url('/'), ABSPATH, $image_url);	// 本地图片就用本地路径
	}

	$media	= ($media)?$media:WEIXIN_ROBOT_PLUGIN_TEMP_DIR.'media/temp/'.md5($image_url).'.jpg';

	if(!file_exists($media)){
		$ua		= 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.89 Safari/537.36';

		$response = weixin_robot_remote_request($image_url, 'file', '', array('stream'=>true, 'filename'=>$media,'need_json_decode'=>false));

		if(is_wp_error($response)){
			return $response;
		}
	}

	return $media;
}

function weixin_robot_add_remote_article($mp_url,$thumb_media_id=''){
	$article = weixin_robot_parse_mp_article($mp_url);
	if(is_wp_error($article)){
		return $article;
	}

	if(!$thumb_media_id){
		$thumb_url	= $article['thumb_url'];
		
		$media	= weixin_robot_download_remote_image($thumb_url);
		if(is_wp_error($media)){
			return $media;
		}
		
		$response = weixin_robot_add_material($media);
		if(is_wp_error($response)){
			return $response;
		}
		unlink($media);
		
		$thumb_media_id	= $response['media_id'];

	}

	$article['thumb_media_id']	= $thumb_media_id;
	$article['content']			= strip_tags($article['content'],'<p><img><br><span><section><strong><iframe><blockquote>');
	
	unset($article['thumb_url']);

	$articles[]	= $article; 
	return weixin_robot_add_news_material($articles);
}

function weixin_robot_parse_mp_article($mp_url){
	$response	= wp_remote_get($mp_url);

	if(is_wp_error($response)){
		return $response;
	}

	$mp_html	= $response['body'];

	$title = $digest = $author = $content = $content_source_url = $thumb_url = '';
	$show_cover_pic = 0;

	if(preg_match_all('/var msg_title = \"(.*?)\";/i', $mp_html, $matches)){
		$title	= str_replace(array('&nbsp;','&amp;'), array(' ','&'), $matches[1][0]);
	}

	if(preg_match_all('/var msg_desc = \"(.*?)\";/i', $mp_html, $matches)){
		$digest	= str_replace(array('&nbsp;','&amp;'), array(' ','&'), $matches[1][0]);
	}

	if(preg_match_all('/<em class=\"rich_media_meta rich_media_meta_text\">(.*?)<\/em>/i', $mp_html, $matches)){
		$author	= str_replace(array('&nbsp;','&amp;'), array(' ','&'), $matches[1][0]);
	}

	if(preg_match_all('/<div class=\"rich_media_content \" id=\"js_content\">[\s\S]{106}([\s\S]*?)[\s\S]{22}<\/div>/i', $mp_html, $matches)){
		$content	= $matches[1][0];
	}

	if(preg_match_all('/var msg_source_url = \'(.*?)\';/i', $mp_html, $matches)){
		$content_source_url	= $matches[1][0];
	}
	
	if(preg_match_all('/var msg_cdn_url = \"(.*?)\";/i', $mp_html, $matches)){
		$thumb_url	= str_replace('/640', '/0', $matches[1][0]);
	}

	return compact('title','thumb_url','author','digest','show_cover_pic','content','content_source_url');	
}

