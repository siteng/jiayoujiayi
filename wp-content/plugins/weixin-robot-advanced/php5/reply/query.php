<?php 
// 搜索回复
function weixin_robot_query_reply($keyword){
	if(apply_filters('weixin_custom_keyword', false, $keyword)){
		return true;
	}

	if(weixin_robot_get_setting('weixin_3rd_search')){ // 如果使用第三方搜索，跳转到第三方
		weixin_robot_3rd_reply();
		return true;
	}

	// 检测关键字是不是太长了
	$keyword_length = mb_strwidth(preg_replace('/[\x00-\x7F]/','',$keyword),'utf-8')+str_word_count($keyword)*2;

	

	$weixin_keyword_allow_length = weixin_robot_get_setting('weixin_keyword_allow_length');
	if($keyword_length > $weixin_keyword_allow_length){
		weixin_robot_too_long_reply();
		return true;
	}

	if(weixin_robot_get_setting('weixin_search')){	// 如果支持搜索日志

		// 搜索日志
		if(weixin_robot_post_query_reply($keyword)){
			return true;
		}
		
	}else{
		weixin_robot_not_found_reply($keyword);
		return true;
	}
}

function weixin_robot_get_custom_mode(){
	global $wechatObj;

	if($openid	= $wechatObj->get_weixin_openid()){
		$weixin_custom_mode = wp_cache_get($openid, 'weixin_custom_mode');	// 如果由用户在后台回复了客服消息，这里就回复空字符串
		if($weixin_custom_mode === 1){
			weixin_robot_empty_string_reply();
			return true;
		}else{
			wp_cache_set($openid, 1, 'weixin_custom_mode', MINUTE_IN_SECONDS);
		}
	}

	return false;
}

// 找不到内容时回复
function weixin_robot_not_found_reply($keyword){
	global $wechatObj;

	if(weixin_robot_get_custom_mode()){
		return;
	}

	weixin_robot_default_reply('[default]');
	$wechatObj->set_response('not-found',$force=true);
}

// 关键字太长回复
function weixin_robot_too_long_reply(){
	global $wechatObj;

	if(weixin_robot_get_custom_mode()){
		return;
	}

	weixin_robot_default_reply('[too-long]');
	$wechatObj->set_response('too-long',$force=true);
}

// 日志搜索回复
function weixin_robot_post_query_reply($keyword=''){
	global $wechatObj;

	// 获取除 page 和 attachmet 之外的所有日志类型
	$post_types = get_post_types( array('exclude_from_search' => false) );
	

	if(isset($_GET['debug'])){
		print_r($post_types);
	}

	unset($post_types['page']);
	unset($post_types['attachment']);

	$weixin_count = weixin_robot_get_setting('weixin_count');

	$weixin_query_array = array(
		's'						=> $keyword, 
		'ignore_sticky_posts'	=> true,
		'posts_per_page'		=> weixin_robot_get_setting('weixin_count'),
		'post_status'			=> 'publish',
		'post_type'				=> $post_types
	);

	$weixin_query_array = apply_filters('weixin_query',$weixin_query_array); 

	if(empty($wechatObj->get_response)){
		if(isset($weixin_query_array['s'])){
			$wechatObj->set_response('query');
		}elseif(isset($weixin_query_array['cat'])){
			$wechatObj->set_response('cat');
		}elseif(isset($weixin_query_array['tag_id'])){
			$wechatObj->set_response('tag');
		}
	}

	global $wp_the_query;
	$wp_the_query->query($weixin_query_array);



	$items = '';

	$counter = 0;

	if($wp_the_query->have_posts()){
		while ($wp_the_query->have_posts()) {
			$wp_the_query->the_post();

			$title	= apply_filters('weixin_title', get_the_title()); 
			$excerpt= apply_filters('weixin_description', get_post_excerpt( '',apply_filters( 'weixin_description_length', 150 ) ) );
			$url	= apply_filters('weixin_url', get_permalink());

			if($counter == 0){
				$thumb = weixin_robot_get_post_thumb('', array(640,320));
			}else{
				$thumb = weixin_robot_get_post_thumb('', array(80,80));
			}

			$items = $items . $wechatObj->get_item($title, $excerpt, $thumb, $url);
			$counter ++;
		}
	}

	$articleCount = $counter;
	if($articleCount > 10) $articleCount = 10;

	if($articleCount){
		$wechatObj->picReply($articleCount,$items);
	}else{
		weixin_robot_not_found_reply($keyword);
	}
}

// 获取日志缩略图
function weixin_robot_get_post_thumb($post='',$size){
	return wpjam_get_post_thumbnail_src($post, $size);
}

//如果搜索关键字是分类名或者 tag 名，直接返回该分类或者tag下最新日志
add_filter('weixin_query','weixin_robot_taxonomy_query', 99);
function weixin_robot_taxonomy_query($weixin_query_array){
	if(isset($weixin_query_array['s'])){
		global $wpdb, $wechatObj;
		$keyword = $wpdb->esc_like($weixin_query_array['s']);
		// $term = $wpdb->get_row("SELECT term_id, slug, taxonomy FROM {$wpdb->prefix}term_taxonomy tt INNER JOIN {$wpdb->prefix}terms t USING ( term_id ) WHERE lower(t.name) = '".$wpdb->esc_like($keyword)."' OR t.slug = '".$wpdb->esc_like($keyword)."' LIMIT 0 , 1");
		$term = $wpdb->get_row($wpdb->prepare("SELECT term_id, slug, taxonomy FROM {$wpdb->prefix}term_taxonomy tt INNER JOIN {$wpdb->prefix}terms t USING ( term_id ) WHERE lower(t.name) = %s OR t.slug = %s LIMIT 0 , 1",$keyword,$keyword));

		if($term){
			if($term->taxonomy == 'category'){
				unset($weixin_query_array['s']);
				$weixin_query_array['cat']		= $term->term_id;
				$wechatObj->set_response('cat');
			}elseif ($term->taxonomy == 'post_tag') {
				unset($weixin_query_array['s']);
				$weixin_query_array['tag_id']	= $term->term_id;
				$wechatObj->set_response('tag');
			}else{
				unset($weixin_query_array['s']);
				$weixin_query_array[$term->taxonomy]	= $term->slug;
				$wechatObj->set_response('taxonomy');
			}
			$weixin_query_array = apply_filters('weixin_taxonomy_query',$weixin_query_array,$term);
		}
	}
	return $weixin_query_array;
}

// 通过自定义字段设置改变图文的链接
// 给用户添加 query_id 或者 openid，用于访问页面时，获取当前用户
add_filter('weixin_url','weixin_robot_url_add_query_id', 99);
function weixin_robot_url_add_query_id($url){
	if($weixin_url = get_post_meta(get_the_ID(), 'weixin_url', true)){
		$url = $weixin_url;
	}

	if(WEIXIN_TYPE == 3){	// 认证订阅号才能加，普通订阅号会出问题，后面不能通过 JS SDK 去掉
		return weixin_robot_url_add_query_key($url);
	}
	
	return $url;
}

// 给当前连接加上 Query ID
function weixin_robot_url_add_query_key($url){
	if(WEIXIN_TYPE == 4) return $url;
	
	global $wechatObj;
	$weixin_openid	= $wechatObj->get_weixin_openid();
	$query_id		= weixin_robot_get_user_query_id($weixin_openid);
	$query_key		= weixin_robot_get_user_query_key();
	return add_query_arg($query_key, $query_id, $url);
}