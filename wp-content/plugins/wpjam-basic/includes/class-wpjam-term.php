<?php
class WPJAM_Term{
	private $term_id;
	private $taxonomy;
	private $tax_obj;
	private $thumbnail_url		= null;

	private static $instances	= [];

	private function __construct($term_id){
		$this->term_id	= (int)$term_id;
		$this->taxonomy	= get_term($term_id)->taxonomy;
		$this->tax_obj	= get_taxonomy($this->taxonomy);
	}

	public function get_term_id(){
		return $this->term_id;
	}

	public function get_taxonomy(){
		return $this->taxonomy;
	}

	public function get_thumbnail_url($size='full', $crop=1){
		if(is_null($this->thumbnail_url)){
			$this->thumbnail_url	= apply_filters('wpjam_term_thumbnail_url', '', get_term($this->term_id));
		}

		return $this->thumbnail_url ? wpjam_get_thumbnail($this->thumbnail_url, $size, $crop) : '';
	}

	public function parse_for_json(){
		$term	= get_term($this->term_id);

		$term_json	= [];

		$term_json['id']		= $this->term_id;
		$term_json['taxonomy']	= $term->taxonomy;
		$term_json['name']		= $term->name;

		if(get_queried_object_id() == $this->term_id){
			$term_json['page_title']	= $term->name;
			$term_json['share_title']	= $term->name;
		}

		if($this->tax_obj->public || $this->tax_obj->publicly_queryable || $this->tax_obj->query_var){
			$term_json['slug']		= $term->slug;
		}

		$term_json['count']			= (int)$term->count;
		$term_json['description']	= $term->description;
		$term_json['parent']		= $term->parent;

		return apply_filters('wpjam_term_json', $term_json, $this->term_id);
	}

	public static function get_instance($term=null){
		$term	= $term ?: get_queried_object();
		$term	= self::get_term($term);

		if(!($term instanceof WP_Term)){
			return new WP_Error('term_not_exists', '分类不存在');
		}

		if(!taxonomy_exists($term->taxonomy)){
			return new WP_Error('taxonomy_not_exists', '文章模式不存在');
		}

		$term_id	= $term->term_id;

		if(!isset($instances[$term_id])){
			$instances[$term_id]	= new self($term_id);
		}

		return $instances[$term_id];
	}

	/**
	* $max_depth = -1 means flatly display every element.
	* $max_depth = 0 means display all levels.
	* $max_depth > 0 specifies the number of display levels.
	*
	*/
	public static function get_terms($args, $max_depth=-1){
		$taxonomy	= $args['taxonomy'];
		$parent		= 0;

		$raw_args	= $args;

		if(isset($args['parent']) && ($max_depth != -1 && $max_depth != 1)){
			$parent		= $args['parent'];
			unset($args['parent']);
		}

		$args['update_term_meta_cache']	= false;

		$terms	= get_terms($args) ?: [];

		if(is_wp_error($terms) || empty($terms)){
			return $terms;
		}

		$lazyloader	= wp_metadata_lazyloader();
		$lazyloader->queue_objects('term', wp_list_pluck($terms, 'term_id'));

		if($max_depth == -1){
			foreach ($terms as &$term) {
				$term = self::get($term);
			}
		}else{
			$top_level_terms	= [];
			$children_terms		= [];

			foreach($terms as $term){
				if($parent){
					if($term->term_id == $parent){
						$top_level_terms[] = $term;
					}elseif($term->parent && $max_depth > 1){
						$children_terms[$term->parent][] = $term;
					}
				}else{
					if(empty($term->parent)){
						$top_level_terms[] = $term;
					}elseif($max_depth > 1){
						$children_terms[$term->parent][] = $term;
					}
				}
			}

			if($terms = $top_level_terms){
				foreach($terms as &$term){
					$term = self::get_children($term, $children_terms, $max_depth, 0);
				}
			}
		}

		return apply_filters('wpjam_terms', $terms, $raw_args, $max_depth);
	}

	public static function get_children($term, $children_terms=[], $max_depth=-1, $depth=0){
		$term	= self::get($term);

		$term['children'] = [];

		if($children_terms){
			$term_id	= $term['id'];

			if(($max_depth == 0 || $max_depth > $depth+1) && isset($children_terms[$term_id])){
				foreach($children_terms[$term_id] as $child){
					$term['children'][]	= self::get_children($child, $children_terms, $max_depth, $depth + 1);
				}
			} 
		}

		return $term;
	}

	public static function flatten($terms, $depth=0){
		$terms_flat	= [];

		if($terms){
			foreach ($terms as $term){
				$term['name']	= str_repeat('&nbsp;', $depth*3).$term['name'];
				$terms_flat[]	= $term;

				if(!empty($term['children'])){
					$depth++;

					$terms_flat	= array_merge($terms_flat, self::flatten($term['children'], $depth));

					$depth--;
				}
			}
		}

		return $terms_flat;
	}

	public static function get($term){
		$instance	= self::get_instance($term);

		return is_wp_error($instance) ? [] : $instance->parse_for_json();
	}

	public static function insert($data){
		$taxonomy	= $data['taxonomy'] ?? '';

		if(empty($taxonomy)){
			return new WP_Error('empty_taxonomy', '分类模式不能为空');
		}

		$name			= $data['name']			?? '';
		$parent			= $data['parent']		?? 0;
		$slug			= $data['slug']			?? '';
		$description	= $data['description']	?? '';

		if(term_exists($name, $taxonomy)){
			return new WP_Error('term_exists', '相同名称的'.get_taxonomy($taxonomy)->label.'已存在。');
		}

		$term	= wp_insert_term(wp_slash($name), $taxonomy, wp_slash(compact('parent','slug','description')));

		if(is_wp_error($term)){
			return $term;
		}

		$term_id	= $term['term_id'];

		$meta_input	= $data['meta_input']	?? [];

		if($meta_input){
			foreach($meta_input as $meta_key => $meta_value) {
				update_term_meta($term_id, $meta_key, $meta_value);
			}
		}

		return $term_id;
	}

	public static function update($term_id, $data){
		$taxonomy		= $data['taxonomy']	?? '';

		if(empty($taxonomy)){
			return new WP_Error('empty_taxonomy', '分类模式不能为空');
		}

		$term	= self::get_term($term_id, $taxonomy);

		if(is_wp_error($term)){
			return $term;
		}

		if(isset($data['name'])){
			$exist	= term_exists($data['name'], $taxonomy);

			if($exist){
				$exist_term_id	= $exist['term_id'];

				if($exist_term_id != $term_id){
					return new WP_Error('term_name_duplicate', '相同名称的'.get_taxonomy($taxonomy)->label.'已存在。');
				}
			}
		}

		$term_args = [];

		$term_keys = ['name', 'parent', 'slug', 'description'];

		foreach($term_keys as $key) {
			$value = $data[$key] ?? null;
			if (is_null($value)) {
				continue;
			}

			$term_args[$key] = $value;
		}

		if(!empty($term_args)){
			$term =	wp_update_term($term_id, $taxonomy, wp_slash($term_args));
			if(is_wp_error($term)){
				return $term;
			}
		}

		$meta_input		= $data['meta_input']	?? [];

		if($meta_input){
			foreach($meta_input as $meta_key => $meta_value) {
				update_term_meta($term['term_id'], $meta_key, $meta_value);
			}
		}

		return true;
	}

	public static function delete($term_id){
		$term	= get_term($term_id);

		if(is_wp_error($term) || empty($term)){
			return $term;
		}

		return wp_delete_term($term_id, $term->taxonomy);
	}

	public static function update_meta($term_id, $meta_key, $meta_value){
		if($meta_value){
			return update_term_meta($term_id, $meta_key, wp_slash($meta_value));
		}else{
			return delete_term_meta($term_id, $meta_key);
		}
	}

	public static function get_by_ids($term_ids){
		return self::update_caches($term_ids);
	}

	public static function update_caches($term_ids, $args=[]){
		if($term_ids){
			$term_ids 	= array_filter($term_ids);
			$term_ids 	= array_unique($term_ids);
		}

		if(empty($term_ids)) {
			return [];
		}

		$update_meta_cache	= $args['update_meta_cache'] ?? true;

		_prime_term_caches($term_ids, $update_meta_cache);

		if(function_exists('wp_cache_get_multiple')){
			$cache_values	= wp_cache_get_multiple($term_ids, 'terms');

			foreach ($term_ids as $term_id) {
				if(empty($cache_values[$term_id])){
					wp_cache_add($term_id, false, 'terms', 10);	// 防止大量 SQL 查询。
				}
			}

			return $cache_values;
		}else{
			$cache_values	= [];

			foreach ($term_ids as $term_id) {
				$cache	= wp_cache_get($term_id, 'terms');

				if($cache !== false){
					$cache_values[$term_id]	= $cache;
				}
			}

			return $cache_values;
		}
	}

	public static function get_term($term, $taxonomy='', $output=OBJECT, $filter='raw'){
		if($term && is_numeric($term)){	// 不存在情况下的缓存优化
			$found	= false;
			$cache	= wp_cache_get($term, 'terms', false, $found);

			if($found){
				if(is_wp_error($cache)){
					return $cache;
				}elseif(!$cache){
					return null;
				}
			}else{
				$_term	= WP_Term::get_instance($term, $taxonomy);

				if(is_wp_error($_term)){
					return $_term;
				}elseif(!$_term){	// 防止重复 SQL 查询。
					wp_cache_add($term, false, 'terms', 10);
					return null;
				}
			}
		}

		return get_term($term, $taxonomy, $output, $filter);
	}

	public static function validate($term_id, $taxonomy=''){
		$instance	= self::get_instance($term_id);

		if(is_wp_error($instance)){
			return $instance;
		}

		if($taxonomy && $taxonomy != 'any' && $taxonomy != $instance->get_taxonomy()){
			return new WP_Error('invalid_taxonomy', '无效的分类模式');
		}

		return self::get_term($term_id);
	}
}

class WPJAM_Taxonomy{
	protected static $taxonomies	= [];

	public static function register($name, $args=[]){
		if(empty($args['object_type'])){
			return;
		}

		$object_type	= $args['object_type'];

		$args	= $args['args'] ?? $args;
		$args	= wp_parse_args($args, [
			'object_type'		=> $object_type,
			'show_ui'			=> true,
			'show_in_nav_menus'	=> false,
			'show_admin_column'	=> true,
			'hierarchical'		=> true,
			'rewrite'			=> true,
			'permastruct'		=> false,
			'supports'			=> ['slug', 'description', 'parent']
		]);

		$permastruct	= $args['permastruct'];

		if($permastruct){
			$args['rewrite']	= true;

			if(strpos($permastruct, '%term_id%') || strpos($permastruct, '%'.$name.'_id%')){
				$args['query_var']	= false;
				$args['supports']	= array_diff($args['supports'], ['slug']);
			}
		}

		if($args['rewrite']){
			if(is_array($args['rewrite'])){
				$args['rewrite']	= wp_parse_args($args['rewrite'], ['with_front'=>false, 'feed'=>false, 'hierarchical'=>false]);
			}else{
				$args['rewrite']	= ['with_front'=>false, 'feed'=>false, 'hierarchical'=>false];
			}
		}

		self::$taxonomies[$name]	= $args;
	}

	public static function unregister($name){
		unset(self::$taxonomies[$name]);
	}

	public static function get_all(){
		return apply_filters_deprecated('wpjam_taxonomies', [self::$taxonomies], 'WPJAM Basic 4.6', 'wpjam_register_taxonomy');
	}

	public static function on_registered($name, $object_type, $args){
		$permastruct	= $args['permastruct'] ?? '';

		if($permastruct){
			if(strpos($permastruct, '%term_id%') || strpos($permastruct, '%'.$name.'_id%')){
				$GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct']	= str_replace('%term_id%', '%'.$name.'_id%', $permastruct);

				add_rewrite_tag('%'.$name.'_id%', '([^/]+)', 'taxonomy='.$name.'&term_id=');
				remove_rewrite_tag('%'.$name.'%');
			}elseif(strpos($permastruct, '%'.get_taxonomy($name)->rewrite['slug'].'%')){
				$GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct']	= $permastruct;
			}
		}
	}

	public static function filter_labels($labels){
		$taxonomy	= str_replace('taxonomy_labels_', '', current_filter());
		$args		= self::$taxonomies[$taxonomy];
		$_labels	= $args['labels'] ?? [];

		$labels		= (array)$labels;
		$name		= $labels['name'];

		if(empty($args['hierarchical'])){
			$search		= ['标签', 'Tag', 'tag'];
			$replace	= [$name, ucfirst($name), $name];
		}else{
			$search		= ['目录', '分类', 'categories', 'Categories', 'Category'];
			$replace	= ['', $name, $name, $name.'s', ucfirst($name).'s', ucfirst($name)];
		}

		foreach ($labels as $key => &$label) {
			if($label && empty($_labels[$key]) && $label != $name){
				$label	= str_replace($search, $replace, $label);
			}
		}

		return $labels;
	}

	public static function filter_pre_term_link($term_link, $term){
		$taxonomy	= $term->taxonomy;

		if(array_search('%'.$taxonomy.'_id%', $GLOBALS['wp_rewrite']->rewritecode, true)){
			$term_link	= str_replace('%'.$taxonomy.'_id%', $term->term_id, $term_link);
		}

		return $term_link;
	}
}

class WPJAM_Term_Option{
	protected static $term_options	= [];

	public static function register($key, $args=[]){
		if(isset(self::$term_options[$key])){
			trigger_error('Term Option 「'.$key.'」已经注册。');
		}

		self::$term_options[$key]	= apply_filters('wpjam_register_term_option_args', $args, $key);
	}

	public static function unregister($key){
		unset(self::$term_options[$key]);
	}

	public static function get_options($taxonomy, $term_id=null){
		$taxonomy_options	= [];

		if($term_options = apply_filters_deprecated('wpjam_term_options', [self::$term_options, $taxonomy, $term_id], 'WPJAM Basic 4.6', 'wpjam_register_term_option')){
			foreach ($term_options as $key => $term_option) {
				if(is_callable($term_option)){
					$term_option	= call_user_func($term_option, $term_id, $key);

					foreach ($term_option as $key => $term_field) {
						$taxonomy_options[$key]	= $term_field;
					}
				}else{
					$term_option	= wp_parse_args( $term_option, [
						'taxonomies'	=> 'all',
						'taxonomy'		=> ''
					]);

					if($term_option['taxonomy'] && $term_option['taxonomies'] == 'all'){
						$term_option['taxonomies'] = [$term_option['taxonomy']];
					}

					if($term_option['taxonomies'] == 'all' || in_array($taxonomy, $term_option['taxonomies'])){
						$taxonomy_options[$key]	= $term_option;
					}
				}
			}
		}

		return apply_filters_deprecated('wpjam_'.$taxonomy.'_term_options', [$taxonomy_options], 'WPJAM Basic 4.6', 'wpjam_register_term_option');
	}
}