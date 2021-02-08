<?php
class WPJAM_Builtin_Page{
	protected static $summary	= '';
	protected static $instance	= null;

	public static function load(){
		if($instance	= self::get_instance()){
			$instance->page_load();
		}

		if(!wp_doing_ajax()){
			if(self::$summary = apply_filters('wpjam_builtin_page_summary', '', $GLOBALS['current_screen'])){
				add_filter('wpjam_html', ['WPJAM_Builtin_Page', 'page_summary']);
			}
		}
	}

	public static function page_summary($html){
		return str_replace('<hr class="wp-header-end">', '<hr class="wp-header-end">'.wpautop(self::$summary), $html);
	}

	public static function get_instance(){
		$screen_base	= $GLOBALS['current_screen']->base;

		do_action('wpjam_builtin_page_load', $screen_base, $GLOBALS['current_screen']);

		if(is_null(static::$instance)){
			if(in_array($screen_base, ['term', 'edit-tags'])){
				$taxonomy	= $GLOBALS['current_screen']->taxonomy ?? '';
				return new WPJAM_Term_Page($taxonomy);
			}elseif(in_array($screen_base, ['edit', 'upload', 'post'])){
				$post_type	= $GLOBALS['current_screen']->base == 'upload' ? 'attachment' : ($GLOBALS['current_screen']->post_type ?? '');
				return new WPJAM_Post_Page($post_type);
			}elseif($screen_base == 'users'){
				return new WPJAM_User_Page();
			}
		}

		return static::$instance;
	}
}

class WPJAM_Post_Page extends WPJAM_Builtin_Page{
	private $post_type;
	private $pt_obj;
	private $post_options;

	public static function get_post_id(){
		if(isset($_GET['post'])){
			$post_id	= (int)$_GET['post'];
		}elseif(isset($_POST['post_ID'])){
			$post_id	= (int)$_POST['post_ID'];
		}else{
			$post_id	= 0;
		}

		return $post_id;
	}

	protected function __construct($post_type){
		$this->post_type	= $post_type;
		$this->pt_obj		= get_post_type_object($this->post_type);
	}

	public function page_load(){
		if($GLOBALS['current_screen']->base == 'post'){
			$this->add_style();
			
			$edit_form_hook	= $this->post_type == 'page' ? 'edit_page_form' : 'edit_form_advanced';

			add_action($edit_form_hook,			[$this, 'on_edit_post_form'], 99);
			add_action('add_meta_boxes',		[$this, 'on_add_meta_boxes'], 10, 2);
			add_action('wp_after_insert_post',	[$this, 'on_after_insert_post'], 999, 2);

			add_filter('post_updated_messages',		[$this, 'filter_post_updated_messages']);
			add_filter('admin_post_thumbnail_html',	[$this, 'filter_admin_post_thumbnail_html'], 10, 2);
			add_filter('redirect_post_location',	[$this, 'filter_redirect_post_location'], 10, 2);

			add_filter('post_edit_category_parent_dropdown_args',	[$this, 'filter_post_edit_category_parent_dropdown_args']);
		}elseif(in_array($GLOBALS['current_screen']->base, ['edit', 'upload'])){
			if($this->post_options	= wpjam_get_post_options($this->post_type)){
				foreach ($this->post_options as $meta_box => $post_option) {
					foreach ($post_option['fields'] as $post_field_key => $post_field) {
						if(!empty($post_field['show_admin_column'])){
							wpjam_register_list_table_column($post_field_key, $post_field);
						}
					}
				}
			}

			$GLOBALS['wpjam_list_table']	= new WPJAM_Posts_List_Table();
		}
	}

	public function get_post_type(){
		return $this->post_type;
	}
	
	public function filter_post_updated_messages($messages){
		if(!in_array($this->post_type, ['page', 'post', 'attachment'])){
			$key	= $this->pt_obj->hierarchical ? 'page' : 'post';

			if(isset($messages[$key])){
				$messages[$key]	= array_map([$this, 'updated_message_replace'], $messages[$key]);
			}
		}
		
		return $messages;
	}

	public function updated_message_replace($message){
		$label_name	= $this->pt_obj->labels->name;
		return str_replace(['文章', '页面'], [$label_name, $label_name], $message);
	}

	public function filter_admin_post_thumbnail_html($content, $post_id){
		if($post_id && !empty($this->pt_obj->thumbnail_size)){
			$content	.= wpautop('尺寸：'.$this->pt_obj->thumbnail_size);
		}

		return $content;
	}

	public function filter_redirect_post_location($location, $post_id){
		$referer	= wp_get_referer();
		$fragment	= parse_url($referer, PHP_URL_FRAGMENT);

		if(empty($fragment)){
			return $location;
		}

		if(parse_url($location, PHP_URL_FRAGMENT)){
			return $location;
		}

		return $location.'#'.$fragment;
	}

	public function filter_post_edit_category_parent_dropdown_args($args){
		$levels	= get_taxonomy($args['taxonomy'])->levels ?? 0;

		if($levels == 1){
			$args['parent']	= -1;
		}elseif($levels > 1){
			$args['depth']	= $levels - 1;
		}

		return $args;
	}

	public function on_after_insert_post($post_id, $post){
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
			return;	
		}

		if($_SERVER['REQUEST_METHOD'] != 'POST'){
			return;	// 提交才可以
		}

		if(!empty($_POST['wp-preview']) && $_POST['wp-preview'] == 'dopreview'){
			return; // 预览不保存
		}

		static $did_save_post_option;

		if(!empty($did_save_post_option)){	// 防止多次重复调用
			trigger_error('did_save_post_option');
			return;
		}

		$did_save_post_option = true;

		$this->post_options	= wpjam_get_post_options($this->post_type, $post_id);

		if(empty($this->post_options)){
			return;
		}

		$post_data	= [];
		
		foreach ($this->post_options as $meta_box => $post_option) {
			$fields	= $post_option['fields'];

			if(empty($fields)){
				continue;
			}

			$data	= wpjam_validate_fields_value($fields);

			if(is_wp_error($data)){
				wp_die($data);
			}elseif(empty($data)){
				continue;
			}

			$update_callback	= $post_option['update_callback'] ?? '';

			if($update_callback){
				if(is_callable($update_callback)){
					$result	= call_user_func($update_callback, $post_id, $data, $fields);

					if(is_wp_error($result)){
						wp_die($result);
					}elseif($result === false){
						wp_die('未知错误');
					}
				}
			}else{
				$post_data	= array_merge($post_data, $data);
			}
		}

		if(empty($post_data)) {
			return;
		}
		
		$custom	= get_post_custom($post_id);

		foreach ($post_data as $key => $value) {
			if(empty($value)){
				if(isset($custom[$key])){
					delete_post_meta($post_id, $key);
				}
			}else{
				if(empty($custom[$key]) || maybe_unserialize($custom[$key][0]) != $value){
					WPJAM_Post::update_meta($post_id, $key, $value);
				}
			}
		}
	}

	public function on_add_meta_boxes($post_type, $post){
		$this->post_options	= wpjam_get_post_options($this->post_type, $post->ID);

		if(empty($this->post_options)){
			return;
		}

		$context	= (!function_exists('use_block_editor_for_post_type') || !use_block_editor_for_post_type($post_type)) ? 'wpjam' : 'normal';

		// 输出日志自定义字段表单
		foreach($this->post_options as $meta_box => $post_option){
			$post_option = wp_parse_args($post_option, [
				'title'		=> '',
				'context'	=> $context,
				'priority'	=> 'default',
				'callback'	=> [$this, 'meta_box_cb']
			]);
			
			if($post_option['title']){
				$args	= [
					'fields_type'	=> $post_option['context'] == 'side' ? 'list' : 'table',
					'is_add'		=> $GLOBALS['current_screen']->action == 'add',
				];

				if(!$args['is_add']){
					$args['id']	= $post->ID;

					if(!empty($post_option['data'])){
						$args['data']	= $post_option['data'];
					}else{
						$args['value_callback']	= [$this, 'value_callback'];
					}
				}

				add_meta_box($meta_box, $post_option['title'], $post_option['callback'], $post_type, $post_option['context'], $post_option['priority'], $args);
			}
		}
	}

	public function meta_box_cb($post, $meta_box){
		$post_option	= $this->post_options[$meta_box['id']];

		if(!empty($post_option['summary'])){
			echo wpautop($post_option['summary']);
		}

		wpjam_fields($post_option['fields'], $meta_box['args']);
	}

	public function value_callback($name, $post_id){
		if($post_id && metadata_exists('post', $post_id, $name)){
			return get_post_meta($post_id, $name, true);
		}

		return null;
	}

	public function on_edit_post_form($post){
		// 下面代码 copy 自 do_meta_boxes
		$context	= 'wpjam';
		$page		= $GLOBALS['current_screen']->id;
		$meta_boxes	= $GLOBALS['wp_meta_boxes'][$page][$context] ?? [];

		if(empty($meta_boxes)) {
			return;
		}

		$nav_tab_title	= '';
		$meta_box_count	= 0;

		foreach(['high', 'core', 'default', 'low'] as $priority){
			if(empty($meta_boxes[$priority])){
				continue;
			}

			foreach ((array)$meta_boxes[$priority] as $meta_box) {
				if(empty($meta_box['id']) || empty($meta_box['title'])){
					continue;
				}

				$meta_box_count++;
				$meta_box_title	= $meta_box['title'];
				$nav_tab_title	.= '<li><a class="nav-tab" href="#tab_'.$meta_box['id'].'">'.$meta_box_title.'</a></li>';
			}
		}

		if(empty($nav_tab_title)){
			return;
		}

		echo '<div id="'.htmlspecialchars($context).'-sortables">';
		echo '<div id="'.$context.'" class="postbox tabs">' . "\n";

		if($meta_box_count == 1){
			echo '<div class="postbox-header">';
			echo '<h2 class="hndle">'.$meta_box_title.'</h2>';
			echo '</div>';
		}else{
			echo '<h2 class="nav-tab-wrapper"><ul>'.$nav_tab_title.'</ul></h2>';
		}

		echo '<div class="inside">';

		foreach (['high', 'core', 'default', 'low'] as $priority) {
			if (!isset($meta_boxes[$priority])){
				continue;
			}
			
			foreach ((array) $meta_boxes[$priority] as $meta_box) {
				if(empty($meta_box['id']) || empty($meta_box['title'])){
					continue;
				}
				
				echo '<div id="tab_'.$meta_box['id'].'">';	
				call_user_func($meta_box['callback'], $post, $meta_box);
				echo "</div>\n";
			}
		}

		echo "</div>\n";

		echo "</div>\n";
		echo "</div>";
	}

	public function add_style(){
		if($taxonomies	= get_object_taxonomies($this->post_type, 'objects')){
			$style	= '';

			foreach($taxonomies as $taxonomy => $tax_obj){
				if(isset($tax_obj->levels) && $tax_obj->levels == 1){
					$style	.= '#new'.$taxonomy.'_parent{display:none;}'."\n";
				}
			}

			if($style){
				wp_add_inline_style('list-tables', $style);
			}
		}
	}
}

class WPJAM_Term_Page extends WPJAM_Builtin_Page{
	private $taxonomy;
	private $tax_obj;
	private $term_options;

	protected function __construct($taxonomy){
		$this->taxonomy	= $taxonomy;
		$this->tax_obj	= get_taxonomy($this->taxonomy);
	}

	public function page_load(){
		$this->add_style();

		add_filter('term_updated_messages',			[$this, 'filter_term_updated_messages']);
		add_filter('taxonomy_parent_dropdown_args',	[$this, 'filter_taxonomy_parent_dropdown_args'], 10, 3);

		if($GLOBALS['current_screen']->base == 'term'){
			add_action($this->taxonomy.'_edit_form_fields',	[$this, 'on_edit_form_fields']);
		}elseif($GLOBALS['current_screen']->base == 'edit-tags'){
			add_action('edited_term',	[$this, 'on_edited_term'], 10, 3);

			if($this->term_options	= wpjam_get_term_options($this->taxonomy)){
				if(wp_doing_ajax()){
					if($_POST['action'] == 'add-tag'){
						add_filter('pre_insert_term',	[$this, 'filter_pre_insert_term'], 10, 2);
						add_action('created_term', 		[$this, 'on_created_term'], 10, 3);
					}
				}else{
					add_action($this->taxonomy.'_add_form_fields', 	[$this, 'on_add_form_fields']);

					foreach($this->term_options as $term_field_key=>$term_field){
						if(!empty($term_field['show_admin_column'])){
							wpjam_register_list_table_column($term_field_key, $term_field);
						}
					}
				}
			}

			$GLOBALS['wpjam_list_table']	= new WPJAM_Terms_List_Table();
		}
	}

	public function filter_term_updated_messages($messages){
		if(!in_array($this->taxonomy, ['post_tag', 'category'])){
			$messages[$this->taxonomy]	= array_map([$this, 'updated_message_replace'], $messages['_item']);
		}

		return $messages;
	}

	public function updated_message_replace($message){
		$label_name	= $this->tax_obj->labels->name;
		return str_replace(['项目', 'Item'], [$label_name, ucfirst($label_name)], $message);
	}

	public function filter_taxonomy_parent_dropdown_args($args, $taxonomy, $action_type){
		if($this->tax_obj->levels > 1){
			$args['depth']	= $this->tax_obj->levels - 1;

			if($action_type == 'edit'){
				$term_id		= $args['exclude_tree'];
				$term_levels	= count(get_ancestors($term_id, $taxonomy, 'taxonomy'));
				$child_levels	= $term_levels;

				$children	= get_term_children($term_id, $taxonomy);
				if($children){
					$child_levels = 0;

					foreach($children as $child){
						$new_child_levels	= count(get_ancestors($child, $taxonomy, 'taxonomy'));
						if($child_levels	< $new_child_levels){
							$child_levels	= $new_child_levels;
						}
					}
				}

				$redueced	= $child_levels - $term_levels;

				if($redueced < $args['depth']){
					$args['depth']	-= $redueced;
				}else{
					$args['parent']	= -1;
				}
			}
		}

		return $args;
	}

	public function filter_term_fields($action='add'){
		$fields	= [];

		foreach($this->term_options as $key => $field){
			if(empty($field['action']) || $field['action'] == $action){
				unset($field['action']);

				$fields[$key]	= $field;
			}
		}

		return $fields;
	}

	public function on_add_form_fields($taxonomy){
		if($fields = $this->filter_term_fields('add')){
			wpjam_fields($fields, [
				'fields_type'	=> 'div',
				'wrap_class'	=> 'form-field',
				'is_add'		=> true
			]);
		}
	}

	public function on_edit_form_fields($term){
		$this->term_options	= wpjam_get_term_options($this->taxonomy, $term->term_id);

		if($fields = $this->filter_term_fields('edit')){
			wpjam_fields($fields, [
				'fields_type'	=> 'tr',
				'wrap_class'	=> 'form-field',
				'id'			=> $term->term_id,
				'value_callback'=> [$this, 'value_callback']
			]);
		}
	}

	public function value_callback($name, $term_id){
		if($term_id && metadata_exists('term', $term_id, $name)){
			return get_term_meta($term_id, $name, true);
		}

		return null;
	}

	public function filter_pre_insert_term($term, $taxonomy){
		if($fields = $this->filter_term_fields('add')){
			$data	= wpjam_validate_fields_value($fields);

			if(is_wp_error($data)){
				return $data;
			}
		}

		return $term;
	}

	public function on_created_term($term_id, $tt_id, $taxonomy){
		if($fields = $this->filter_term_fields('add')){
			if($data = wpjam_validate_fields_value($fields)){
				foreach ($data as $key => $value) {
					if($value){
						WPJAM_Term::update_meta($term_id, $key, $value);
					}
				}
			}
		}
	}

 	public function on_edited_term($term_id, $tt_id, $taxonomy){
 		$this->term_options	= wpjam_get_term_options($this->taxonomy, $term_id);

		if($fields = $this->filter_term_fields('edit')){
			$data	= wpjam_validate_fields_value($fields);

			if(is_wp_error($data)){
				wp_die($data);
			}

			if($data){
				foreach ($data as $key => $value) {
					if($value){
						WPJAM_Term::update_meta($term_id, $key, $value);
					}else{
						if(metadata_exists('term', $term_id, $key)){
							delete_term_meta($term_id, $key);	
						}
					}
				}
			}
		}
	}

	public function add_style(){
		$supports	= $this->tax_obj->supports;

		if($this->tax_obj->levels == 1){
			$supports	= array_diff($supports, ['parent']);
		}

		$style		= '.fixed th.column-slug{width:16%;}
		.fixed th.column-description{width:22%;}
		td.column-name img.wp-term-image{float:left; margin:0px 10px 10px 0;}
		.form-field.term-parent-wrap p{display: none;}
		.form-field span.description{color:#666;}
		';
			
		foreach (['slug', 'description', 'parent'] as $key) { 
			if(!in_array($key, $supports)){
				$style	.= '.form-field.term-'.$key.'-wrap{display: none;}'."\n";
			}
		}

		wp_add_inline_style('list-tables', $style);
	}
}

class WPJAM_User_Page extends WPJAM_Builtin_Page{
	protected function __construct(){}

	public function page_load(){
		$GLOBALS['wpjam_list_table']	= new WPJAM_Users_List_Table();
	}
}