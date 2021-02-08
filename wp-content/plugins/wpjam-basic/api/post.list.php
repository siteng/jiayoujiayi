<?php
/* 规则：
** 1. 分成主的查询和子查询（$query_args['sub']=1）
** 2. 主查询支持 $_GET 参数 和 $_GET 参数 mapping
** 3. 子查询（sub）只支持 $query_args 参数
** 4. 主查询返回 next_cursor 和 total_pages，current_page，子查询（sub）没有
** 5. $_GET 参数只适用于 post.list 
** 6. term.list 只能用 $_GET 参数 mapping 来传递参数
*/

global $wp, $wp_query;

if(isset($module_args)){
	$query_args	= $module_args;
}else{
	$query_args	= $args;	
}

$is_main_query	= !($query_args['sub'] ?? false);

if(!$is_main_query){	// 子查询不支持 $_GET 参数
	$wp->query_vars	= [];
}

// 缓存处理
$wp->set_query_var('cache_results', true);
// $wp->set_query_var('update_post_meta_cache', true);
// $wp->set_query_var('update_post_term_cache', true);
// $wp->set_query_var('lazy_load_term_meta', false);	// 在 the_posts filter 的时候，已经处理了

// $query_args['ignore_sticky_posts']	= $query_args['ignore_sticky_posts'] ?? true;

if($query_args){
	foreach ($query_args as $query_key => $query_var) {
		$wp->set_query_var($query_key, $query_var);
	}
}

$post_type	= $wp->query_vars['post_type'] ?? '';

if(!empty($query_args['output'])){
	$output	= $query_args['output'];
}elseif($post_type && !is_array($post_type)){
	$output	= $post_type.'s';
}else{
	$output	= 'posts';
}

if($is_main_query){
	$posts_per_page	= (int)wpjam_get_parameter('posts_per_page');

	if($posts_per_page){
		if($posts_per_page	> 20){
			$posts_per_page	= 20;
		}

		$wp->set_query_var('posts_per_page', $posts_per_page);
	}

	$offset		= (int)wpjam_get_parameter('offset');

	if($offset){
		$wp->set_query_var('offset', $offset);
	}

	$orderby	= $wp->query_vars['orderby'] ?? 'date';
	$paged		= $wp->query_vars['paged'] ?? null;
	
	if(empty($paged) && is_null(wpjam_get_parameter('s')) && !is_array($orderby) && in_array($orderby, ['date', 'post_date'])){
		$use_cursor	= true;
	}else{
		$use_cursor	= false;
	}

	if($use_cursor){
		if($cursor	= (int)wpjam_get_parameter('cursor',	['default'=>0])){
			$wp->set_query_var('cursor', $cursor);
			$wp->set_query_var('ignore_sticky_posts', true);
		}

		if($since	= (int)wpjam_get_parameter('since',	['default'=>0])){
			$wp->set_query_var('since', $since);
			$wp->set_query_var('ignore_sticky_posts', true);
		}
	}

	// taxonomy 参数处理，同时支持 $_GET 和 $query_args 参数
	if($post_type){
		$taxonomy_objs	= get_object_taxonomies($post_type, 'objects');
	}else{
		$taxonomy_objs	= get_taxonomies(['public'=>true], 'objects');
	}

	if($taxonomy_objs){
		foreach ($taxonomy_objs as $taxonomy=>$taxonomy_obj) {
			if($taxonomy == 'category'){
				foreach (['category_id', 'cat_id'] as $cat_key) {
					if($term_id	= (int)wpjam_get_parameter($cat_key)){
						$wp->set_query_var('cat', $term_id);
						break;
					}
				}
			}elseif($taxonomy == 'post_tag'){
				if($term_id	= (int)wpjam_get_parameter('tag_id')){
					$wp->set_query_var('tag_id', $term_id);
				}
			}else{
				if($term_id	= (int)wpjam_get_parameter($taxonomy.'_id')){
					$wp->set_query_var($taxonomy.'_id', $term_id);
				}
			}
		}

		if($term_id	= (int)wpjam_get_parameter('term_id')){
			$wp->set_query_var('term_id', $term_id);
		}
	}
}

wpjam_parse_query_vars($wp);

$wp->query_posts();

$posts_json = [];

if($wp_query->have_posts()){
	if($wp_query->is_home && $wp_query->is_paged <= 1 && empty($wp_query->query['ignore_sticky_posts'])){
		$query_args['sticky_posts']	= get_option('sticky_posts') ?: [];
	}

	$posts_json	= apply_filters('wpjam_posts_json', $wp_query->posts, $query_args);
	$posts_json	= array_map(function($post_json) use ($query_args){ return wpjam_get_post($post_json->ID, $query_args); }, $posts_json);
}

if($is_main_query){
	if(is_category() || is_tag() || is_tax()){
		if($current_term	= get_queried_object()){
			$taxonomy		= $current_term->taxonomy;
			$current_term	= wpjam_get_term($current_term, $taxonomy);

			$response['current_taxonomy']	= $current_term['taxonomy'];
			$response['current_'.$taxonomy]	= $current_term;

			foreach(['page_title','share_title'] as $key){
				if(empty($response[$key])){
					$response[$key]	= $current_term[$key];
				}
			}
		}
	}elseif(is_author()){
		$current_author	= get_queried_object();

		$response['current_author']	= [
			'nickname'		=> $current_author->display_name,
			'id'			=> $current_author->ID,
			'avatar'		=> get_avatar_url($current_author->ID, 200),
			'description'	=> $current_author->description
		];

		foreach(['page_title','share_title'] as $key){
			if(empty($response[$key])){
				$response[$key]	= $current_author->display_name;
			}
		}
	}elseif(is_post_type_archive()){
		foreach(['page_title','share_title'] as $key){
			if(empty($response[$key])){
				$response[$key]	= get_queried_object()->label;
			}
		}
	}

	$response['total']			= (int)$wp_query->found_posts;
	$response['total_pages']	= (int)$wp_query->max_num_pages;
	$response['current_page']	= (int)($wp_query->get('paged') ?: 1);
	
	if($use_cursor){
		$response['next_cursor']	= ($posts_json && $wp_query->max_num_pages>1) ? end($posts_json)['timestamp'] : 0;
	}
}

$response[$output]	= $posts_json;
