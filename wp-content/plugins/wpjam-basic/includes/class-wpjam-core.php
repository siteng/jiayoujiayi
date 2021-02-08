<?php
class WPJAM_Platform{
	protected static $platforms	= [
		'weapp'		=> [
			'bit'		=> 1,
			'order'		=> 4,
			'title'		=> '小程序',
			'verify'	=> 'is_weapp'
		],
		'weixin'	=> [
			'bit'		=> 2,
			'order'		=> 4,
			'title'		=> '微信网页',
			'verify'	=> 'is_weixin'
		],
		'mobile'	=>[
			'bit'		=> 4,
			'order'		=> 8,
			'title'		=> '移动网页',
			'verify'	=> 'wp_is_mobile'
		],
		'web'			=>[
			'bit'		=> 8,
			'title'		=> '网页',
			'verify'	=> '__return_true'
		],
		'template'		=>[
			'bit'		=> 8,
			'title'		=> '网页',
			'verify'	=> '__return_true'
		]
	];

	public static function register($key, $args=[]){
		self::$platforms[$key]	= $args;
	}

	public static function unregister($key){
		unset(self::$platforms[$key]);
	}

	public static function get_all($sort=true){
		$platforms	= self::$platforms;

		if($sort){
			uasort($platforms, function ($p1, $p2){
				$order1	= $p1['order'] ?? 10;
				$order2	= $p2['order'] ?? 10;
				return $order1 <=> $order2;
			});
		}

		return $platforms;
	}

	public static function get_options($type='bit'){
		$platforms	= self::get_all();

		if($type == 'key'){
			return wp_list_pluck($platforms, 'title');
		}elseif($type == 'bit'){
			$platforms	= array_filter($platforms, function($platform){
				return !empty($platform['bit']);
			});

			return wp_list_pluck($platforms, 'title', 'bit');
		}else{
			return wp_list_pluck($platforms, 'bit');
		}
	}

	public static function is_platform($platform){
		$platforms	= self::get_all();

		if(is_numeric($platform)){
			$options	= array_flip(wp_list_pluck($platforms, 'bit'));

			if(isset($options[$platform])){
				$platform	= $options[$platform];
			}
		}

		$platform	= $platforms[$platform] ?? false;

		if($platform){
			return call_user_func($platform['verify']);
		}else{
			return false;
		}
	}

	public static function get_current_platform($platforms=[], $type='bit'){
		$options	= self::get_options($type);

		foreach($options as $platform=>$title){
			if($platforms){
				if(in_array($platform, $platforms) && self::is_platform($platform)){
					return $platform;
				}
			}else{
				if(self::is_platform($platform)){
					return $platform;
				}
			}
		}

		return '';
	}
}

class WPJAM_Path{
	private $page_key;
	private $page_type	= '';
	private $post_type	= '';
	private $taxonomy	= '';
	private $fields		= [];
	private $tabbars	= [];
	private $title		= '';
	private $paths		= [];
	private $pages		= [];
	private $callbacks	= [];
	private static $path_objs	= [];

	public function __construct($page_key, $args=[]){
		$this->page_key		= $page_key;
		$this->page_type	= $args['page_type'] ?? '';
		$this->title		= $args['title'] ?? '';

		if($this->page_type == 'post_type'){
			$this->post_type	= $args['post_type'] ?? $this->page_key;
		}elseif($this->page_type == 'taxonomy'){
			$this->taxonomy		= $args['taxonomy'] ?? $this->page_key;
		}
	}

	public function get_title(){
		return $this->title;
	}

	public function get_page_type(){
		return $this->page_type;
	}

	public function get_post_type(){
		return $this->post_type;
	}

	public function get_taxonomy(){
		return $this->taxonomy;
	}

	public function get_taxonomy_key(){
		if($this->taxonomy == 'category'){
			return 'cat';
		}elseif($this->taxonomy == 'post_tag'){
			return 'tag_id';
		}elseif($this->taxonomy){
			return $this->taxonomy.'_id';
		}else{
			return '';
		}
	}

	public function get_fields(){
		if($this->fields){
			$fields	= $this->fields ?? '';

			if($fields && is_callable($fields)){
				$fields	= call_user_func($fields, $this->page_key);
			}

			return $fields;
		}else{
			$fields	= [];

			if($this->page_type == 'post_type'){
				if($post_type_obj = get_post_type_object($this->post_type)){
					$fields[$this->post_type.'_id']	= ['title'=>'',	'type'=>'text',	'class'=>'all-options',	'data_type'=>'post_type',	'post_type'=>$this->post_type, 'placeholder'=>'请输入'.$post_type_obj->label.'ID或者输入关键字筛选',	'required'];
				}
			}elseif($this->page_type == 'taxonomy'){
				if($taxonomy_obj = get_taxonomy($this->taxonomy)){
					$taxonomy_key	= $this->get_taxonomy_key();

					if($taxonomy_obj->hierarchical){
						$levels		= $taxonomy_obj->levels ?? 0;
						$terms		= wpjam_get_terms(['taxonomy'=>$this->taxonomy,	'hide_empty'=>0], $levels);
						$terms		= wpjam_flatten_terms($terms);
						$options	= $terms ? wp_list_pluck($terms, 'name', 'id') : [];

						$fields[$taxonomy_key]	= ['title'=>'',	'type'=>'select',	'options'=>$options];
					}else{
						$fields[$taxonomy_key]	= ['title'=>'',	'type'=>'text',		'data_type'=>'taxonomy',	'taxonomy'=>$this->taxonomy];
					}
				}
			}elseif($this->page_type == 'author'){
				$fields['author']	= ['title'=>'',	'type'=>'select',	'options'=>wp_list_pluck(get_users(['who'=>'authors']), 'display_name', 'ID')];
			}

			return $fields;
		}
	}

	public function get_tabbar($type){
		return $this->tabbars[$type] ?? false;
	}

	public function set_title($title){
		$this->title	= $title;
	}

	public function set_path($type, $path=''){
		$this->paths[$type]	= $path;

		if($path){
			if(strrpos($path, '?')){
				$path_parts	= explode('?', $path);
				$this->pages[$type]	= $path_parts[0];
			}else{
				$this->pages[$type]	= $path;
			}
		}
	}

	public function remove_path($type){
		unset($this->paths[$type]);
	}

	public function set_callback($type, $callback=''){
		$this->callbacks[$type]	= $callback;
	}

	public function set_fields($type, $fields=[]){
		$this->fields	= array_merge($this->fields, $fields);
	}

	public function set_tabbar($type, $tabbar=false){
		$this->tabbars[$type]	= $tabbar;
	}

	public function get_page($type){
		return $this->pages[$type] ?? '';
	}

	private function get_post_path($args){
		$post_id	= (int)($args[$this->post_type.'_id'] ?? 0);

		if(empty($post_id)){
			$pt_object	= get_post_type_object($this->post_type);
			return new WP_Error('empty_'.$this->post_type.'_id', $pt_object->label.'ID不能为空并且必须为数字');
		}

		if($args['path_type'] == 'template'){
			return get_permalink($post_id);
		}else{
			if(strpos($args['path'], '%post_id%')){
				return str_replace('%post_id%', $post_id, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_term_path($args){
		$tax_key	= $this->get_taxonomy_key();
		$term_id	= (int)($args[$tax_key] ?? 0);

		if(empty($term_id)){
			$tax_object	= get_taxonomy($this->taxonomy);
			return new WP_Error('empty_'.$tax_key, $tax_object->label.'ID不能为空并且必须为数字');
		}

		if($args['path_type'] == 'template'){
			return get_term_link($term_id, $this->taxonomy);
		}else{
			if(strpos($args['path'], '%term_id%')){
				return str_replace('%term_id%', $term_id, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_author_path($args){

		$author	= (int)($args['author'] ?? 0);

		if(empty($author)){
			return new WP_Error('empty_author', '作者ID不能为空并且必须为数字。');
		}

		if($args['path_type'] == 'template'){
			return get_author_posts_url($author);
		}else{
			if(strpos($args['path'], '%author%')){
				return str_replace('%author%', $author, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_callback($type){
		if(!empty($this->callbacks[$type])){
			return $this->callbacks[$type];
		}elseif($this->page_type == 'post_type'){
			return [$this, 'get_post_path'];
		}elseif($this->page_type == 'taxonomy'){
			return [$this, 'get_term_path'];
		}elseif($this->page_type == 'author'){
			return [$this, 'get_author_path'];
		}else{
			return '';
		}
	}

	public function get_path($type, $args=[]){
		$path		= $this->paths[$type] ?? '';
		$callback	= $this->get_callback($type);

		if($callback && is_callable($callback)){
			$args['path_type']	= $type;
			$args['path']		= $path;

			return call_user_func($callback, $args);
		}else{
			if(isset($this->paths[$type])){
				return $path;
			}else{
				if(isset($args['backup'])){
					return new WP_Error('invalid_page_key_backup', '备用页面无效');
				}else{
					return new WP_Error('invalid_page_key', '页面无效');
				}
			}
		}
	}

	public function get_raw_path($type){
		return $this->paths[$type] ?? '';
	}

	public function has($types, $operator='AND', $strict=false){
		$types	= (array) $types;

		foreach ($types as $type){
			$has	= isset($this->paths[$type]) || isset($this->callbacks[$type]);

			if($strict && $has && isset($this->paths[$type]) && $this->paths[$type] === false){
				$has	= false;
			}

			if($operator == 'AND'){
				if(!$has){
					return false;
				}
			}elseif($operator == 'OR'){
				if($has){
					return true;
				}
			}
		}

		if($operator == 'AND'){
			return true;
		}elseif($operator == 'OR'){
			return false;
		}
	}

	public static function parse_item($item, $path_type, $backup=false){
		if($backup){
			$page_key	= $item['page_key_backup'] ?: 'none';
		}else{
			$page_key	= $item['page_key'] ?? '';
		}

		$parsed	= [];

		if($page_key == 'none'){
			if(!empty($item['video'])){
				$parsed['type']		= 'video';
				$parsed['video']	= $item['video'];
				$parsed['vid']		= wpjam_get_qqv_id($item['video']);
			}else{
				$parsed['type']		= 'none';
			}
		}elseif($page_key == 'external'){
			if($path_type == 'web'){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['url'];
			}
		}elseif($page_key == 'web_view'){
			if($path_type == 'web'){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['src'];
			}else{
				$parsed['type']		= 'web_view';
				$parsed['src']		= $item['src'];
			}
		}elseif($page_key){
			if($path_obj = self::get_instance($page_key)){
				if($backup){
					$backup_item	= ['backup'=>true];

					if($path_fields = $path_obj->get_fields()){
						foreach($path_fields as $field_key => $path_field){
							$backup_item[$field_key]	= $item[$field_key.'_backup'] ?? '';
						}
					}

					$path	= $path_obj->get_path($path_type, $backup_item);
				}else{
					$path	= $path_obj->get_path($path_type, $item);
				}

				if(!is_wp_error($path)){
					if(is_array($path)){
						$parsed	= $path;
					}else{
						$parsed['type']		= '';
						$parsed['page_key']	= $page_key;
						$parsed['path']		= $path;
					}
				}
			}
		}

		return $parsed;
	}

	public static function validate_item($item, $path_types){
		$page_key	= $item['page_key'];

		if($page_key == 'none'){
			return true;
		}elseif($page_key == 'web_view'){
			$path_types	= array_diff($path_types, ['web']);
		}

		if($path_obj = self::get_instance($page_key)){
			$backup_check	= false;

			foreach ($path_types as $path_type) {
				$path	= $path_obj->get_path($path_type, $item);

				if(is_wp_error($path)){
					if(count($path_types) <= 1 || $path->get_error_code() != 'invalid_page_key'){
						return $path;
					}else{
						$backup_check	= true;
						break;
					}
				}
			}
		}else{
			if(count($path_types) <= 1){
				return new WP_Error('invalid_page_key', '页面无效');
			}

			$backup_check	= true;
		}

		if($backup_check){
			$page_key	= $item['page_key_backup'] ?: 'none';

			if($page_key == 'none'){
				return true;
			}

			if($path_obj = self::get_instance($page_key)){
				$backup		= ['backup'=>true];

				if($path_obj && ($path_fields = $path_obj->get_fields())){
					foreach($path_fields as $field_key => $path_field){
						$backup[$field_key]	= $item[$field_key.'_backup'] ?? '';
					}
				}

				foreach ($path_types as $path_type) {
					$path	= $path_obj->get_path($path_type, $backup);

					if(is_wp_error($path)){
						return $path;
					}
				}
			}else{
				return new WP_Error('invalid_page_key_backup', '备用页面无效');
			}
		}

		return true;
	}

	public static function get_item_link_tag($parsed, $text){
		if($parsed['type'] == 'none'){
			return $text;
		}elseif($parsed['type'] == 'external'){
			return '<a href_type="web_view" href="'.$parsed['url'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'web_view'){
			return '<a href_type="web_view" href="'.$parsed['src'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'mini_program'){
			return '<a href_type="mini_program" href="'.$parsed['path'].'" appid="'.$parsed['appid'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'contact'){
			return '<a href_type="contact" href="" tips="'.$parsed['tips'].'">'.$text.'</a>';
		}elseif($parsed['type'] == ''){
			return '<a href_type="path" page_key="'.$parsed['page_key'].'" href="'.$parsed['path'].'">'.$text.'</a>';
		}
	}

	public static function get_tabbar_options($path_type){
		$options	= [];

		if($path_objs	= self::$path_objs){
			foreach ($path_objs as $page_key => $path_obj){
				if($tabbar	= $path_obj->get_tabbar($path_type)){
					if(is_array($tabbar)){
						$text	= $tabbar['text'];
					}else{
						$text	= $path_obj->get_title();
					}

					$options[$page_key]	= $text;
				}
			}
		}

		return $options;
	}

	public static function get_path_fields($path_types, $for=''){
		if(empty($path_types)){
			return [];
		}

		$path_types	= (array) $path_types;

		$backup_fields_required	= count($path_types) > 1 && $for != 'qrcode';

		if($backup_fields_required){
			$backup_fields	= ['page_key_backup'=>['title'=>'',	'type'=>'select',	'options'=>['none'=>'只展示不跳转'],	'description'=>'&emsp;跳转页面不生效时将启用备用页面']];
			$backup_show_if_keys	= [];
		}

		$page_key_fields	= ['page_key'	=> ['title'=>'',	'type'=>'select',	'options'=>[]]];

		if($path_objs = self::$path_objs){
			$strict	= ($for == 'qrcode');

			foreach ($path_objs as $page_key => $path_obj){
				if(!$path_obj->has($path_types, 'OR', $strict)){
					continue;
				}

				$page_key_fields['page_key']['options'][$page_key]	= $path_obj->get_title();

				if($path_fields = $path_obj->get_fields()){
					foreach($path_fields as $field_key => $path_field){
						if(isset($page_key_fields[$field_key])){
							$page_key_fields[$field_key]['show_if']['value'][]	= $page_key;
						}else{
							$path_field['title']	= '';
							$path_field['show_if']	= ['key'=>'page_key','compare'=>'IN','value'=>[$page_key]];

							$page_key_fields[$field_key]	= $path_field;
						}
					}
				}

				if($backup_fields_required){
					if($path_obj->has($path_types, 'AND')){
						if($page_key == 'module_page' && $path_fields){
							$backup_fields['page_key_backup']['options'][$page_key]	= $path_obj->get_title();

							foreach($path_fields as $field_key => $path_field){
								$path_field['show_if']	= ['key'=>'page_key_backup','value'=>$page_key];
								$backup_fields[$field_key.'_backup']	= $path_field;
							}
						}elseif(empty($path_fields)){
							$backup_fields['page_key_backup']['options'][$page_key]	= $path_obj->get_title();
						}
					}else{
						if($page_key == 'web_view'){
							if(!$path_obj->has(array_diff($path_types, ['web']), 'AND')){
								$backup_show_if_keys[]	= $page_key;
							}
						}else{
							$backup_show_if_keys[]	= $page_key;
						}
					}
				}
			}
		}

		if($for == 'qrcode'){
			return ['page_key_set'	=> ['title'=>'页面',	'type'=>'fieldset',	'fields'=>$page_key_fields]];
		}else{
			$page_key_fields['page_key']['options']['none']	= '只展示不跳转';

			$fields	= ['page_key_set'	=> ['title'=>'页面',	'type'=>'fieldset',	'fields'=>$page_key_fields]];

			if($backup_fields_required){
				$show_if	= ['key'=>'page_key','compare'=>'IN','value'=>$backup_show_if_keys];

				$fields['page_key_backup_set']	= ['title'=>'备用',	'type'=>'fieldset',	'fields'=>$backup_fields, 'show_if'=>$show_if];
			}

			return $fields;
		}
	}

	public static function get_page_keys($path_type){
		$pages	= [];

		if($path_objs = self::$path_objs){
			foreach ($path_objs as $page_key => $path_obj){
				if($page = $path_obj->get_page($path_type)){
					$pages[]	= compact('page_key', 'page');
				}
			}
		}

		return $pages;
	}

	public static function create($page_key, $args=[]){
		$path_obj	= self::get_instance($page_key);

		if(is_null($path_obj)){
			$path_obj	= new WPJAM_Path($page_key, $args);

			self::$path_objs[$page_key]	= $path_obj;
		}

		if(!empty($args['path_type'])){
			$path_type	= $args['path_type'];

			if(isset($args['path'])){
				$path_obj->set_path($path_type, $args['path']);
			}

			if(!empty($args['callback'])){
				$path_obj->set_callback($path_type, $args['callback']);
			}

			if(!empty($args['fields'])){
				$path_obj->set_fields($path_type, $args['fields']);
			}

			$tabbar	= $args['tabbar'] ?? false;
			$path_obj->set_tabbar($path_type, $tabbar);
		}

		return $path_obj;
	}

	public static function unregister($page_key, $path_type=''){
		if($path_type){
			if($path_obj = self::get_instance($page_key)){
				$path_obj->remove_path($path_type);
			}
		}else{
			unset(self::$path_objs[$page_key]);
		}
	}

	public static function get_instance($page_key){
		return self::$path_objs[$page_key] ?? null;
	}

	public static function get_by($args=[]){
		$path_objs	= [];

		if(self::$path_objs && $args){
			$path_type	= $args['path_type'] ?? '';
			$page_type	= $args['page_type'] ?? '';
			$post_type	= $args['post_type'] ?? '';
			$taxonomy	= $args['taxonomy'] ?? '';

			foreach (self::$path_objs as $page_key => $path_obj) {
				if($path_type && !$path_obj->has($path_type)){
					continue;
				}

				if($page_type && $path_obj->get_page_type() != $page_type){
					continue;
				}

				if($post_type && $path_obj->get_post_type() != $post_type){
					continue;
				}

				if($taxonomy && $path_obj->get_taxonomy() != $taxonomy){
					continue;
				}

				$path_objs[$page_key]	= $path_obj;
			}
		}

		return $path_objs;
	}

	public static function get_all(){
		return self::$path_objs;
	}
}

// 1. 需要在使用的 CLASS 中设置 public static $meta_type
// 2. 需要全局定义 $wpdb->{$meta_type}meta = CLASS::get_meta_table();
trait WPJAM_Meta_Trait{
	public static function add_meta($id, $meta_key, $meta_value, $unique=false){
		return add_metadata(self::$meta_type, $id, $meta_key, wp_slash($meta_value), $unique);
	}

	public static function delete_meta($id, $meta_key, $meta_value=''){
		return delete_metadata(self::$meta_type, $id, $meta_key, $meta_value);
	}

	public static function get_meta($id, $key = '', $single = false){
		return get_metadata(self::$meta_type, $id, $key, $single);
	}

	public static function update_meta($id, $meta_key, $meta_value, $prev_value=''){
		if($meta_value){
			return update_metadata(self::$meta_type, $id, $meta_key, wp_slash($meta_value), $prev_value);
		}else{
			return delete_metadata(self::$meta_type, $id, $meta_key, $prev_value);
		}
	}

	public static function delete_meta_by_key($meta_key){
		return delete_metadata(self::$meta_type, null, $meta_key, '', true);
	}

	public static function update_meta_cache($object_ids){
		if($object_ids){
			update_meta_cache(self::$meta_type, $object_ids);
		}
	}

	public static function create_meta_table(){
		global $wpdb;

		$column	= sanitize_key(self::$meta_type).'_id';
		$table	= self::get_meta_table();

		if($wpdb->get_var("show tables like '{$table}'") != $table) {
			$wpdb->query("CREATE TABLE {$table} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				{$column} bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY {$column} ({$column}),
				KEY meta_key (meta_key(191))
			)");
		}
	}

	public static function get_meta_table(){
		return $GLOBALS['wpdb']->prefix.sanitize_key(self::$meta_type).'meta';
	}

	public static function lazyload_meta_callback($check) {
		$meta_lazyloader	= self::get_meta_lazyloader();

		if($pending_objects = $meta_lazyloader->get_pending_objects()){
			self::update_meta_cache($pending_objects);
		}

		return $check;
	}

	private static $meta_lazyloader;

	private static function get_meta_lazyloader(){
		if(null === self::$meta_lazyloader){
			self::$meta_lazyloader = new WPJAM_Lazyloader(self::$meta_type, 'get_'.self::$meta_type.'_metadata', [get_called_class(), 'lazyload_meta_callback']);
		}

		return self::$meta_lazyloader;
	}

	public static function meta_lazyload($ids){
		$lazyloader	= self::get_meta_lazyloader();
		$lazyloader->queue_objects($ids);
	}
}

class WPJAM_Lazyloader{
	private $pending_objects	= [];
	private $object_type;
	private $filter;
	private $callback;

	public function __construct($object_type, $filter, $callback) {
		$this->object_type	= $object_type;
		$this->filter		= $filter;
		$this->callback		= $callback;
	}

	public function queue_objects($object_ids){
		foreach($object_ids as $object_id){
			if(!isset($this->pending_objects[$object_id])){
				$this->pending_objects[$object_id]	= 1;
			}
		}

		add_filter($this->filter, $this->callback);
	}

	public function get_pending_objects($reset=true){
		$pending_objects	= $this->pending_objects ? array_keys($this->pending_objects) : [];

		if($reset){
			$this->pending_objects	= [];
			remove_filter($this->filter, $this->callback);
		}

		return $pending_objects;
	}
}

class WPJAM_Setting{
	protected static $option_settings	= [];

	public static function register($option_name, $args=[]){
		self::$option_settings[$option_name]	= apply_filters('wpjam_register_option_args', $args, $option_name);
	}

	public static function unregister($option_name){
		unset(self::$option_settings[$option_name]);
	}

	public static function get_option_settings(){
		return self::$option_settings;
	}

	public static function get_option_setting($option_name){
		if(self::$option_settings && !empty(self::$option_settings[$option_name])){
			$option_setting	= self::$option_settings[$option_name];
		}else{
			$option_setting	= apply_filters(wpjam_get_filter_name($option_name, 'setting'), []);

			if(!$option_setting){
				$option_settings	= apply_filters_deprecated('wpjam_settings', [[], $option_name], 'WPJAM Basic 4.6', 'wpjam_register_option');

				if(!$option_settings || empty($option_settings[$option_name])) {
					return false;
				}

				$option_setting	= $option_settings[$option_name];
			}
		}

		if(is_callable($option_setting)){
			$option_setting	= call_user_func($option_setting, $option_name);
		}

		if(empty($option_setting['sections'])){	// 支持简写
			if(isset($option_setting['fields'])){
				$fields		= $option_setting['fields'];
				$title		= $option_setting['title'] ?? '';

				unset($option_setting['fields']);
				$option_setting['sections']	= [$option_name => compact('fields', 'title')];
			}else{
				$option_setting['sections']	= $option_setting;
			}
		}

		foreach ($option_setting['sections'] as $section_id => &$section) {
			if(is_callable($section['fields'])){
				$section['fields']	= call_user_func($section['fields'], $option_name, $section_id);
			}
		}

		return wp_parse_args($option_setting, [
			'option_group'		=> $option_name, 
			'option_page'		=> $option_name, 
			'option_type'		=> 'array',	// array：设置页面所有的选项作为一个数组存到 options 表， single：每个选项单独存到 options 表。
			'capability'		=> 'manage_options',
			'update_callback'	=> 'update_option',
			'ajax'				=> true,
			'sections'			=> []
		]);
	}

	public static function get_option($option_name, $blog_id=0){
		if(is_multisite()){
			if(is_network_admin()){
				return get_site_option($option_name) ?: [];
			}else{
				if($blog_id){
					return get_blog_option($blog_id, $option_name) ?: [];
				}else{
					return get_option($option_name) ?: [];
				}
			}
		}else{
			return get_option($option_name) ?: [];
		}
	}

	public static function update_option($option_name, $option_value, $blog_id=0){
		if(is_multisite()){
			if(is_network_admin()){
				return update_site_option($option_name, $option_value);
			}else{
				if($blog_id){
					return update_blog_option($blog_id, $option_name, $option_value);
				}else{
					return update_option($option_name, $option_value);
				}
			}
		}else{
			return update_option($option_name, $option_value);
		}
	}

	public static function get_setting($option_name, $setting_name, $blog_id=0){
		$option_value	= is_string($option_name) ? self::get_option($option_name, $blog_id) : $option_name;

		if($option_value && isset($option_value[$setting_name])){
			$value	= $option_value[$setting_name];

			if($value && is_string($value)){
				return  str_replace("\r\n", "\n", trim($value));
			}else{
				return $value;
			}
		}else{
			return null;
		}
	}

	public static function update_setting($option_name, $setting_name, $setting_value, $blog_id=0){
		$option_value	= self::get_option($option_name, $blog_id);

		$option_value[$setting_name]	= $setting_value;

		return self::update_option($option_name, $option_value, $blog_id);
	}

	public static function delete_setting($option_name, $setting_name, $blog_id=0){
		$option_value	= self::get_option($option_name, $blog_id);

		if($option_value && isset($option_value[$setting_name])){
			unset($option_value[$setting_name]);
		}

		return self::update_option($option_name, $option_value, $blog_id);
	}
}

trait WPJAM_Setting_Trait{
	private static $instance	= null;

	private $settings		= [];
	private $option_name	= '';
	private $site_default	= false;

	private function init($option_name, $site_default=false){
		$this->option_name	= $option_name;
		$this->site_default	= $site_default;

		$this->reset_settings();
	}

	public static function get_instance(){
		if(is_null(self::$instance)){
			self::$instance	= new self();
		}

		return self::$instance;
	}

	public function get_settings(){
		return $this->settings;
	}

	public function reset_settings(){
		$option_value	= get_option($this->option_name) ?: [];

		if(is_multisite() && $this->site_default){
			$site_default	= get_site_option($this->option_name) ?: [];
			$option_value	= $option_value + $site_default;
		}
		
		$this->settings		= $option_value;
	}

	public function get_setting($name){
		return $this->settings[$name] ?? null;
	}

	public function update_settings($settings){
		$this->settings	= $settings;
		return $this->save();
	}

	public function update_setting($name, $value){
		$this->settings[$name]	= $value;
		return $this->save();
	}

	public function delete_setting($name){
		unset($this->settings[$name]);
		return $this->save();
	}

	private function save(){
		return update_option($this->option_name, $this->settings);
	}
}