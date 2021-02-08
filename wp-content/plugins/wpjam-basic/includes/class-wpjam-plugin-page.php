<?php
class WPJAM_Menu_Page{
	protected static $menu_pages	= [];
	protected static $is_rendering	= true;
	protected static $page_setting	= null;
	protected static $query_data	= [];
	
	public  static function add($menu_slug, $args=[]){
		$network_menu		= !empty($args['network']);
		$is_network_admin	= is_multisite() && is_network_admin();

		if(($network_menu && $is_network_admin) || (!$network_menu && !$is_network_admin)){
			if(!empty($args['parent'])){
				self::$menu_pages[$args['parent']]['subs'][$menu_slug]	= $args;
			}else{
				self::$menu_pages[$menu_slug]	= $args;
			}
		}
	}

	public  static function init(){
		if(empty($GLOBALS['plugin_page']) && !self::rendering()){
			return;
		}

		do_action('wpjam_admin_init');
		
		$menu_filter	= (is_multisite() && is_network_admin()) ? 'wpjam_network_pages' : 'wpjam_pages';
		$menu_pages		= apply_filters($menu_filter, self::$menu_pages);

		if(!$menu_pages){
			return;
		}

		if(is_multisite() && is_network_admin()){
			$builtin_parent_pages	= [
				'settings'	=> 'settings.php',
				'theme'		=> 'themes.php',
				'themes'	=> 'themes.php',
				'plugins'	=> 'plugins.php',
				'users'		=> 'users.php',
				'sites'		=> 'sites.php',
			];
		}else{
			$builtin_parent_pages	= [
				'dashboard'	=> 'index.php',
				'management'=> 'tools.php',
				'options'	=> 'options-general.php',
				'theme'		=> 'themes.php',
				'themes'	=> 'themes.php',
				'plugins'	=> 'plugins.php',
				'posts'		=> 'edit.php',
				'media'		=> 'upload.php',
				'links'		=> 'link-manager.php',
				'pages'		=> 'edit.php?post_type=page',
				'comments'	=> 'edit-comments.php',
				'users'		=> current_user_can('edit_users') ? 'users.php' : 'profile.php',
			];
			
			if($custom_post_types = get_post_types(['_builtin'=>false, 'show_ui'=>true])){
				foreach ($custom_post_types as $custom_post_type) {
					$builtin_parent_pages[$custom_post_type.'s'] = 'edit.php?post_type='.$custom_post_type;
				}
			}
		}

		foreach($menu_pages as $menu_slug=>$menu_page){
			if(isset($builtin_parent_pages[$menu_slug])){
				$parent_slug	= $builtin_parent_pages[$menu_slug];
				$parent_page	= $parent_slug;

				$menu_page['subs']	= wpjam_sort_items($menu_page['subs']);
			}else{
				if(empty($menu_page['menu_title'])){
					continue;
				}
				
				$menu_page	= self::parse_page($menu_slug, $menu_page);

				$parent_slug	= $menu_slug;
				$parent_page	= 'admin.php';
			}

			if(!empty($menu_page['subs'])){
				foreach($menu_page['subs'] as $sub_menu_slug => $sub_menu_page){
					$menu_page	= self::parse_page($sub_menu_slug, $sub_menu_page, $parent_slug, $parent_page);

					if(!self::rendering() && $GLOBALS['plugin_page'] == $sub_menu_slug){
						break;
					}
				}	
			}

			if(!self::rendering() && self::$page_setting){
				break;
			}
		}
	}

	public  static function rendering($is_rendering=null){
		if(!is_null($is_rendering)){
			$old_rendering	= self::$is_rendering;
			self::$is_rendering	= $is_rendering;

			return (bool)$old_rendering;
		}

		return (bool)self::$is_rendering;
	}

	private static function parse_page($menu_slug, $menu_page, $parent_slug='', $parent_page='admin.php'){
		$menu_title	= $menu_page['menu_title'] ?? '';
		$page_title	= $menu_page['page_title'] = $menu_page['page_title'] ?? $menu_title;
		$capability	= $menu_page['capability'] ?? 'manage_options';

		if(self::rendering()){
			if($parent_slug){
				$page_hook	= add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, ['WPJAM_Plugin_Page', 'admin_page']);
			}else{
				$icon		= $menu_page['icon'] ?? '';
				$position	= $menu_page['position'] ?? '';

				$page_hook	= add_menu_page($page_title, $menu_title, $capability, $menu_slug, ['WPJAM_Plugin_Page', 'admin_page'], $icon, $position);

				if(!empty($menu_page['subs'])){
					$menu_page['subs']	= wpjam_sort_items($menu_page['subs']);

					if(isset($menu_page['subs'][$menu_slug])){
						$menu_page['subs']	= array_merge([$menu_slug=>$menu_page['subs'][$menu_slug]], $menu_page['subs']);
					}
				}
			}

			$menu_page['page_hook']	= $page_hook;
		}

		if($GLOBALS['plugin_page'] == $menu_slug){
			self::$page_setting	= $menu_page;
			$current_admin_url	= add_query_arg(['page'=>$menu_slug], $parent_page);

			$GLOBALS['current_admin_url']	= is_network_admin() ? network_admin_url($current_admin_url) : admin_url($current_admin_url);
		}

		return $menu_page;
	}
}

class WPJAM_Plugin_Page extends WPJAM_Menu_Page{
	protected static $instance	= null;
	protected static $is_tab	= false;
	protected static $tabs		= [];

	protected $in_tab	= false;
	protected $function	= '';
	protected $wp_error	= null;

	public  static function get_page_setting($key='', $tab_setting=false){
		if($tab_setting){
			$current_tab	= $GLOBALS['current_tab'] ?? '';

			if($current_tab){
				$page_setting	= self::$page_setting['tabs'][$current_tab] ?? [];

				if($key){
					return $page_setting ? ($page_setting[$key] ?? '') : '';
				}else{
					return $page_setting;
				}
			}else{
				return [];
			}
		}else{
			if(in_array($key, ['list_table_name', 'option_name', 'dashboard_name', 'form_name', 'widgets', 'function'])){
				$function	= self::$page_setting['function'] ?? '';

				if($function == 'tab'){
					$value	= self::get_page_setting($key, true);
				}else{
					$value	= self::$page_setting[$key] ?? '';
				}

				if($key == 'function'){
					return $value == 'list' ? 'list_table' : $value;
				}else{
					return $value ?: $GLOBALS['plugin_page'];
				}
			}elseif($key == 'tabs'){
				$tabs	= self::$page_setting['tabs'] ?? [];

				if(self::$tabs){
					$_tabs	= array_filter(self::$tabs, function($args){
						return empty($args['plugin_page']) || $args['plugin_page'] == $GLOBALS['plugin_page'];
					});

					$tabs	= array_merge($tabs, $_tabs);
				}

				$tabs	= apply_filters(wpjam_get_filter_name($GLOBALS['plugin_page'], 'tabs'), $tabs);
				return wpjam_sort_items($tabs);
			}elseif($key){
				return self::$page_setting[$key] ?? '';
			}else{
				return self::$page_setting;
			}
		}
		
	}

	public  static function get_query_data(){
		return self::$query_data;
	}

	public  static function register_tab($key, $args){
		self::$tabs[$key]	= $args;
	}

	public  static function unregister_tab($key){
		unset(self::$tabs[$key]);
	}

	public  static function tab_load(){
		$tabs	= self::get_page_setting('tabs');

		if(empty($tabs)){
			return new WP_Error('empty_tabs', 'Tabs 未设置');
		}

		$tab_keys	= array_map('sanitize_key', array_keys($tabs));
		$tabs		= array_combine($tab_keys, array_values($tabs));

		self::$page_setting['tabs']		= $tabs;
		self::$page_setting['tab_url']	= $GLOBALS['current_admin_url'];

		if(self::rendering()){
			$current_tab	= wpjam_get_parameter('tab', ['sanitize_callback'=>'sanitize_key']);
			$current_tab	= $current_tab ?: $tab_keys[0];
		}else{
			$current_tab	= wpjam_get_parameter('current_tab', ['method'=>'POST', 'sanitize_callback'=>'sanitize_key']);
		}
		
		if(empty($current_tab) || empty($tabs[$current_tab])){
			return new WP_Error('invalid_tab', '无效的 Tab');
		}elseif(empty($tabs[$current_tab]['function'])){
			return new WP_Error('empty_tab_function', 'Tab 未设置 function');
		}else{
			$GLOBALS['current_tab']			= $current_tab;
			$GLOBALS['current_admin_url']	= $GLOBALS['current_admin_url'].'&tab='.$current_tab;

			return self::load($tabs[$current_tab], true);
		}
	}

	public  static function load($page_setting, $in_tab=false){
		$function		= $page_setting['function'] ?? null;
		$load_callback	= $page_setting['load_callback'] ?? '';

		if($in_tab){
			$file_key	= 'tab_file';
			do_action('wpjam_plugin_page_load', $GLOBALS['plugin_page'], $GLOBALS['current_tab']);

			if($load_callback && is_callable($load_callback)){
				call_user_func($load_callback, $GLOBALS['current_tab']);
			}
		}else{
			$file_key	= 'page_file';
			self::$is_tab	= ($function == 'tab');
			do_action('wpjam_plugin_page_load', $GLOBALS['plugin_page'], '');

			if($load_callback && is_callable($load_callback)){
				call_user_func($load_callback, $GLOBALS['plugin_page']);
			}
		}

		if(!empty($page_setting[$file_key]) && file_exists($page_setting[$file_key])){
			include $page_setting[$file_key];
		}

		if(!empty($page_setting['chart'])){
			WPJAM_Chart::init($page_setting['chart']);
		}

		if(!empty($page_setting['query_args'])){
			foreach($page_setting['query_args'] as $query_arg) {
				self::$query_data[$query_arg]	= wpjam_get_data_parameter($query_arg);
			}

			$GLOBALS['current_admin_url']	= add_query_arg(self::$query_data, $GLOBALS['current_admin_url']);
		}

		if($function == 'tab'){
			if($in_tab){
				wp_die('tab 不能嵌套 tab');
			}

			$result	= self::tab_load();

			if(is_wp_error($result)){
				if(wp_doing_ajax()){
					wpjam_send_json($result);
				}else{
					return wpjam_admin_add_error($result);
				}
			}
		}else{
			$instance	= self::get_instance();
			
			if($page_hook	= self::$page_setting['page_hook'] ?? ''){
				add_action('load-'.$page_hook, [$instance, 'page_load']);
			}
		}
	}

	public static function is_tab(){
		return self::$is_tab;
	}

	public  static function admin_page(){
		echo '<div class="wrap">';

		$instance	= self::get_instance();
		$function	= self::$page_setting['function'] ?? null;

		if($function == 'tab'){
			$instance->tab_page(self::$page_setting);
		}else{
			$instance->plugin_page(self::$page_setting);
		}

		echo '</div>';
	}

	public  static function admin_enqueue_scripts(){
		$screen_base	= $GLOBALS['current_screen']->base;

		if($screen_base == 'customize'){
			return;
		}

		add_thickbox();

		if($screen_base == 'post'){
			$post = get_post();
			if(!$post && !empty($GLOBALS['post_ID'])){
				$post = $GLOBALS['post_ID'];
			}

			wp_enqueue_media(['post'=>$post]);
		}else{
			wp_enqueue_media();
		}

		$ver	= get_plugin_data(WPJAM_BASIC_PLUGIN_FILE)['Version'];

		wp_enqueue_style('wpjam-style',		WPJAM_BASIC_PLUGIN_URL.'static/style.css', ['wp-color-picker', 'editor-buttons'], $ver);

		wp_enqueue_script('wpjam-script',	WPJAM_BASIC_PLUGIN_URL.'static/script.js', ['jquery', 'thickbox', 'wp-backbone', 'jquery-ui-sortable', 'jquery-ui-tooltip', 'jquery-ui-tabs', 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-ui-autocomplete', 'wp-color-picker'], $ver);
		wp_enqueue_script('wpjam-form',		WPJAM_BASIC_PLUGIN_URL.'static/form.js',   ['wpjam-script', 'mce-view'], $ver);

		if($screen_base == 'edit'){
			wp_enqueue_script('wpjam-posts',	WPJAM_BASIC_PLUGIN_URL.'static/posts.js',	['wpjam-form'], $ver, true);
		}

		$function		= self::get_page_setting('function');
		$item_prefix	= '.tr-';

		if($GLOBALS['plugin_page']){
			if($function == 'option'){
				$params	= [];
			}else{
				$params	= $_REQUEST;

				foreach (['page', 'tab', '_wp_http_referer', '_wpnonce'] as $query_key) {
					unset($params[$query_key]);
				}

				$params	= $params ? array_map('sanitize_textarea_field', $params) : [];
			}
		}else{
			$params	= null;	

			if(in_array($screen_base, ['upload', 'edit'])){
				$item_prefix	= '#post-';
			}elseif($screen_base == 'edit-tags'){
				$item_prefix	= '#tag-';
			}elseif($screen_base == 'users'){
				$item_prefix	= '#user-';
			}
		}

		$params	= $params ?: new stdClass();

		wp_localize_script('wpjam-script', 'wpjam_page_setting', [
			'screen_id'		=> $GLOBALS['current_screen']->id,
			'screen_base'	=> $screen_base,
			'plugin_page'	=> $GLOBALS['plugin_page'] ?? null,
			'current_tab'	=> $GLOBALS['current_tab'] ?? null,
			'function'		=> $function,
			'params'		=> $params,
			'item_prefix'	=> $item_prefix
		]);
	}

	public  static function print_media_templates(){
		echo '<div id="tb_modal" style="display:none; background: #f1f1f1;"></div>';
		echo WPJAM_Field::get_field_tmpls();
	}

	public  static function get_instance(){
		if(is_null(static::$instance)){
			$function	= self::get_page_setting('function');

			if($function == 'option'){
				static::$instance	= new WPJAM_Option_Page(self::get_page_setting('option_name'));
			}elseif($function == 'dashboard'){
				static::$instance	= new WPJAM_Dashboard_Page(self::get_page_setting('dashboard_name'));
			}elseif(in_array($function, ['list_table', 'list'])){
				static::$instance	= new WPJAM_List_Table_Page(self::get_page_setting('list_table_name'));
			}elseif($function == 'form'){
				static::$instance	= new WPJAM_Form_Page(self::get_page_setting('form_name'));
			}else{
				if(empty($function)){
					$function	= wpjam_get_filter_name($GLOBALS['plugin_page'], 'page');
				}

				static::$instance	= new WPJAM_Plugin_Page($function);
			}
		}
		
		return static::$instance;
	}

	protected function __construct($function=''){
		if($function){
			if(!is_callable($function)){
				$this->wp_error	= new WP_Error('invalid_function', $function.'无效或者不存在');
			}else{
				$this->function	= $function;
			}	
		}
	}

	public function page_load(){
		if($this->wp_error && is_wp_error($this->wp_error)){
			wpjam_admin_add_error($this->wp_error);
			return false;
		}

		return true;
	}

	public function plugin_page($page_setting){
		if($this->wp_error && is_wp_error($this->wp_error)){
			$this->page_title($page_setting);
		}else{
			$this->page($page_setting);
		}
	}

	public function page($page_setting){
		$this->page_title($page_setting);
		
		if(!empty($page_setting['chart'])){
			WPJAM_Chart::form();
		}

		call_user_func($this->function);
	}

	public function tab_page($page_setting){
		$function	= wpjam_get_filter_name($GLOBALS['plugin_page'], 'page');	// 所有 Tab 页面都执行的函数
		$tabs		= $page_setting['tabs'];
		$summary	= $page_setting['summary'] ?? '';

		$current_tab	= $GLOBALS['current_tab'] ?? '';
		$tab_count		= count($tabs);

		if($tab_count > 1){
			$page_setting['summary']	= $summary;
			
			$this->page_title($page_setting);
			
			if(is_callable($function)){
				call_user_func($function);
			}

			echo '<nav class="nav-tab-wrapper wp-clearfix">';
			
			foreach ($tabs as $tab_key => $tab) {
				$class	= 'nav-tab';
				$class	.= $current_tab == $tab_key ? ' nav-tab-active' : '';

				echo '<a class="'.$class.'" href="'.$page_setting['tab_url'].'&tab='.$tab_key.'">'.$tab['title'].'</a>';
			}

			echo '</nav>';
		}else{
			if(is_callable($function)){
				call_user_func($function);
			}
		}

		if($tabs && $current_tab && isset($tabs[$current_tab])){
			$this->in_tab	= true;
			$page_setting	= $tabs[$current_tab];

			if($tab_count == 1 && $summary && !isset($page_setting['summary'])){
				$page_setting['summary']	= $summary;
			}

			$this->plugin_page($page_setting);
		}
	}

	public function page_title($page_setting){
		$page_title	= $page_setting['page_title'] ?? $page_setting['title'];
		$subtitle	= $page_setting['subtitle'] ?? '';

		if($page_title){
			if($this->in_tab && count(self::get_page_setting('tabs')) > 1){
				echo '<h2>'.$page_title.$subtitle.'</h2>';
			}else{
				echo '<h1 class="wp-heading-inline">'.$page_title.'</h1>';
				echo $subtitle;
				echo '<hr class="wp-header-end">';
			}
		}

		$summary	= $page_setting['summary'] ?? '';

		if($this->in_tab){
			$summary	= apply_filters('wpjam_plugin_page_summary', $summary, $GLOBALS['plugin_page'], $GLOBALS['current_tab']);
		}else{
			$summary	= apply_filters('wpjam_plugin_page_summary', $summary, $GLOBALS['plugin_page'], '');
		}

		if($summary){
			echo wpautop($summary);
		}
	}

	public function ajax_response(){
		if($this->wp_error && is_wp_error($this->wp_error)){
			wpjam_send_json($this->wp_error);
		}
	}
}

class WPJAM_Form_Page extends WPJAM_Plugin_Page{
	private $form_name		= '';
	private $page_action	= ''; 

	protected function __construct($form_name){
		$page_action	= wpjam_get_page_action($form_name);

		if(is_wp_error($page_action)){
			$this->wp_error		= $page_action;
		}else{
			$this->form_name	= $form_name;
			$this->page_action	= $page_action;
		}
	}

	public function page($page_setting){
		if($summary	= $this->page_action->get_summary()){
			$page_setting['summary']	= $summary;
		}

		$this->page_title($page_setting);

		$form	= $this->page_action->get_form();

		if(is_wp_error($form)){
			wp_die($form);
		}

		echo $form;
	}
}

class WPJAM_Option_Page extends WPJAM_Plugin_Page{
	private $option_name	= '';
	private $option_setting	= [];

	protected function __construct($option_name){
		$this->option_name	= $option_name;
		$option_setting		= wpjam_get_option_setting($option_name);

		if(!$option_setting){
			$this->wp_error	= new WP_Error('option_setting_unregistered', $this->option_name.'的设置未注册');
		}else{
			$this->option_setting	= $option_setting;
		}

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-option-action',	[$this, 'ajax_response']);
		}else{
			add_action('admin_action_update', [$this, 'register_settings']);
		}
	}

	public function page_load(){
		if(parent::page_load()){
			$this->register_settings();
		}
	}

	public function ajax_response(){
		parent::ajax_response();

		$this->register_settings();

		$capability	= $this->option_setting['capability'] ?: 'manage_options';

		if(!current_user_can($capability)){
			wpjam_send_json([
				'errcode'	=> 'bad_authentication',
				'errmsg'	=> '无权限'
			]);
		}

		$option_page	= wpjam_get_data_parameter('option_page');
		$nonce			= wpjam_get_data_parameter('_wpnonce');

		if(!wp_verify_nonce($nonce, $option_page.'-options')){
			wpjam_send_json([
				'errcode'	=> 'invalid_nonce',
				'errmsg'	=> '非法操作'
			]);
		}

		$allowed_options = apply_filters('allowed_options', []);
		
		$options	= $allowed_options[$option_page];

		if(empty($options)){
			wpjam_send_json([
				'errcode'	=> 'invalid_option',
				'errmsg'	=> '字段未注册'
			]);
		}

		$option_action	= wpjam_get_parameter('option_action', ['method'=>'POST']);

		foreach($options as $option){
			$option = trim($option);

			if($option_action == 'reset'){
				delete_option($option);
			}else{
				$value	= wpjam_get_data_parameter($option);

				if(isset($value)){
					if(!is_array($value)){
						$value = trim($value);
					}
				}

				$update_callback	= $this->option_setting['update_callback'];

				if($update_callback && is_callable($update_callback)){
					call_user_func($update_callback, $option, $value);
				}
			}
		}

		if($settings_errors = get_settings_errors()){
			$errmsg = '';

			foreach ($settings_errors as $key => $details) {
				if (in_array($details['type'], ['updated', 'success', 'info'])) {
					continue;
				}

				$errmsg	.= $details['message'].'&emsp;';
			}

			wpjam_send_json(['errcode'=>'update_failed', 'errmsg'=>$errmsg]);
		}else{
			$data = get_option($option);

			wpjam_send_json(['data'=>$data]);
		}	
	}

	public function register_settings(){
		$capability		= $this->option_setting['capability'];
		if($capability != 'manage_options'){
			add_filter('option_page_capability_'.$this->option_setting['option_page'], function() use($capability){
				return $capability; 
			});	
		}

		$option_type	= $this->option_setting['option_type'];
		$option_group	= $this->option_setting['option_group'];
		$sections		= $this->option_setting['sections'];

		$args	= [
			'option_type'		=> $option_type,
			'sanitize_callback'	=> [$this, 'sanitize_callback']
		];

		if($option_type == 'array'){
			$fields	= [];
		}

		// 只需注册字段，add_settings_section 和 add_settings_field 可以在具体设置页面添加
		foreach ($sections as $section_id => $section){
			if($option_type == 'single'){
				foreach ($section['fields'] as $key => $field) {
					if($field['type'] == 'fieldset'){
						if(empty($field['fieldset_type']) || $field['fieldset_type'] == 'single'){
							foreach ($field['fields'] as $sub_key => $sub_field) {
								$args['fields']	= [$sub_key => $sub_field];

								register_setting($option_group, $sub_key, $args);
							}

							continue;
						}
					}

					$args['fields']	= [$key => $field];

					register_setting($option_group, $key, $args);
				}
			}else{
				$fields	= array_merge($fields, $section['fields']);
			}
		}

		if($option_type == 'array'){
			$args['fields']	= $fields;

			register_setting($option_group, $this->option_name, $args);	
		}
	}

	public function sanitize_callback($value){
		$registered		= get_registered_settings();
		$option_args	= $registered[$this->option_name] ?? [];
		
		if(empty($option_args)){
			return $value;
		}

		$option_type	= $option_args['option_type'];
		$values			= $option_type == 'array' ? $value : [$this->option_name=>$value];
		$values			= wpjam_validate_fields_value($option_args['fields'], $values);

		if(is_wp_error($values)){
			add_settings_error($this->option_name, $values->get_error_code(), $values->get_error_message());

			return $this->get_option();
		}
		
		$values	= $option_type == 'array' ?  $values+$this->get_option() : $values[$this->option_name];

		$sanitize_callback	= $this->option_setting['sanitize_callback'] ?? '';

		if($sanitize_callback && is_callable($sanitize_callback)){
			$values	= call_user_func($sanitize_callback, $values);

			if(is_wp_error($values)){
				add_settings_error($this->option_name, $values->get_error_code(), $values->get_error_message());

				return $this->get_option();
			}
		}

		return $values;
	}

	// 部分代码拷贝自 do_settings_sections 和 do_settings_fields 函数
	public function page($page_setting){
		if(isset($this->option_setting['summary'])){
			$page_setting['summary']	= $this->option_setting['summary'];
		}

		$this->page_title($page_setting);

		$sections		= $this->option_setting['sections'];
		$section_count	= count($sections);

		if(!$this->in_tab && $section_count > 1){
			echo '<div class="tabs">';

			echo '<h2 class="nav-tab-wrapper wp-clearfix"><ul>';

			foreach($sections as $section_id => $section){
				$attr	= WPJAM_Field::parse_wrap_attr($section);

				echo '<li id="tab_title_'.$section_id.'" '.$attr.'><a class="nav-tab" href="#tab_'.$section_id.'">'.$section['title'].'</a></li>';
			}

			echo '</ul></h2>';
		}

		if(is_multisite() && is_network_admin()){	
			if($_SERVER['REQUEST_METHOD'] == 'POST'){	// 如果是 network 就自己保存到数据库	
				$fields = array_merge(...array_values(wp_list_pluck($sections, 'fields')));
				$value	= wpjam_get_parameter($this->option_name, ['method'=>'POST']);
				$value	= wpjam_validate_fields_value($fields, $value);
				$value	= $value+(get_site_option($this->option_name) ?: []);

				update_site_option($this->option_name,  $value);
				
				echo '<div class="notice notice-success is-dismissible"><p>设置已保存。</p></div>';
			}
			
			echo '<form action="'.add_query_arg(['settings-updated'=>'true'], wpjam_get_current_page_url()).'" method="POST">';
		}else{
			$attr	= $this->option_setting['ajax'] ? ' id="wpjam_option"' : '';

			echo '<form action="options.php" method="POST"'.$attr.'>';
			
			settings_errors();
		}

		if(!$this->option_setting['ajax']){
			echo '<input type="hidden" name="screen_id" value="'.get_current_screen()->id.'" />';

			if(!empty($GLOBALS['current_tab'])){
				echo '<input type="hidden" name="current_tab" value="'.$GLOBALS['current_tab'].'" />';
			}
		}
		
		settings_fields($this->option_setting['option_group']);

		foreach($sections as $section_id => $section){
			echo '<div id="tab_'.$section_id.'"'.'>';

			if($section_count > 1 && !empty($section['title'])){
				if(!$this->in_tab){
					echo '<h2>'.$section['title'].'</h2>';
				}else{
					echo '<h3>'.$section['title'].'</h3>';
				}
			}

			if(!empty($section['callback'])) {
				call_user_func($section['callback'], $section);
			}

			if(!empty($section['summary'])) {
				echo wpautop($section['summary']);
			}
			
			if(!$section['fields']) {
				echo '</div>';
				continue;
			}

			$args	= [
				'fields_type'		=> 'table',
				'value_callback'	=> [$this, 'value_callback']
			];

			if($this->option_setting['option_type'] == 'array'){
				$args['name']	= $this->option_name;
			}

			wpjam_fields($section['fields'], $args);
			
			echo '</div>';
		}

		if($section_count > 1){
			echo '</div>';
		}
		
		echo '<p class="submit">';

		echo get_submit_button('', 'primary', 'submit', false);

		if(!empty($this->option_setting['reset'])){
			echo '&emsp;'.get_submit_button('重置选项', 'secondary', 'reset', false);
		}

		echo '<span class="spinner"  style="float: none; height: 28px;"></span>';
		echo '</p>';

		echo '</form>';
	}

	public function value_callback($name){
		if($this->option_setting['option_type'] == 'array'){
			$option_value	= $this->get_option();
			return $option_value[$name] ?? null;
		}else{
			return get_option($name, null);
		}
	}

	public function get_option(){
		if(is_multisite()){
			if(is_network_admin()){
				$option_value	= get_site_option($this->option_name) ?: [];
			}else{
				$option_value	= get_option($this->option_name) ?: [];

				if(!empty($this->option_setting['site_default'])){
					$site_default	= get_site_option($this->option_name) ?: [];
					$option_value	= $option_value + $site_default;
				}
			}
		}else{
			$option_value	= get_option($this->option_name) ?: [];
		}

		return $option_value;
	}
}

class WPJAM_Dashboard_Page extends WPJAM_Plugin_Page{
	private $dashboard_name		= '';
	private $dashboard_widgets	= [];

	protected function __construct($dashboard_name){
		$this->dashboard_name		= $dashboard_name;
		$this->dashboard_widgets	= wpjam_get_plugin_page_setting('widgets') ?: [];
		$this->dashboard_widgets	= apply_filters(wpjam_get_filter_name($dashboard_name,'dashboard_widgets'), $this->dashboard_widgets);

		if(!$this->dashboard_widgets){
			$this->wp_error	= new WP_Error('dashboard_widgets_unregistered', $dashboard_name.'的 widgets 未注册');
		}
	}

	public function page($page_setting){
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		// wp_dashboard_setup();
		
		wp_enqueue_script('dashboard');
		
		if(wp_is_mobile()) {
			wp_enqueue_script('jquery-touch-punch');
		}

		$filter_name	= wpjam_get_filter_name($this->dashboard_name, 'welcome_panel');
		
		if(has_action($filter_name)){
			echo '<div id="welcome-panel" class="welcome-panel">';
			do_action($filter_name);
			echo '</div>';
		}else{
			$this->page_title($page_setting);
		}

		foreach ($this->dashboard_widgets as $widget_id => $meta_box){
			self::add_widget($widget_id, $meta_box);
		}

		echo '<div id="dashboard-widgets-wrap">';
		wp_dashboard();
		echo '</div>';
	}

	public  static function add_widget($widget_id, $meta_box){
		$title		= $meta_box['title'] ?? '';
		$callback	= $meta_box['callback'] ?? wpjam_get_filter_name($widget_id, 'dashboard_widget_callback');
		$context	= $meta_box['context'] ?? 'normal';	// 位置，normal 左侧, side 右侧
		$priority	= $meta_box['priority'] ?? 'core';
		$args		= $meta_box['args'] ?? [];
		
		add_meta_box($widget_id, $title, $callback, get_current_screen(), $context, $priority, $args);
	}
}

class WPJAM_List_Table_Page extends WPJAM_Plugin_Page{
	private $list_table_name	= '';
	private $list_table			= null;

	protected function __construct($list_table_name){
		$this->list_table_name	= $list_table_name;

		$args	= WPJAM_List_Table::get_list_table_setting($list_table_name);

		if(empty($args)){
			$this->wp_error	= new WP_Error('invalid_list_table_args', '无效的 List Table 参数');
		}elseif(empty($args['model']) || !class_exists($args['model'])){
			$this->wp_error = new WP_Error('invalid_model', 'List Table 的 Model 未定义或不存在');
		}else{
			$args	= wp_parse_args($args, ['primary_key'=>'id', 'name'=>$list_table_name, 'screen'=>get_current_screen()]);

			if(isset($args['layout']) && $args['layout'] == 2){
				$this->list_table	= new WPJAM_Left_List_Table($args);
			}else{
				$this->list_table	= new WPJAM_List_Table($args);
			}

			if(wp_doing_ajax()){
				add_action('wp_ajax_wpjam-list-table-action',	[$this, 'ajax_response']);
			}

			$GLOBALS['wpjam_list_table']	= $this->list_table;	// 兼容代码，不可去掉
		}	
	}

	public function page_load(){
		if(parent::page_load()){
			$result = $this->list_table->prepare_items();

			if(is_wp_error($result)){
				wpjam_admin_add_error($result);
			}
		}
	}

	public function page($page_setting){
		$title		= $this->list_table->_args['title'];
		$summary	= $this->list_table->_args['summary'] ?? null;

		if($title){
			$page_setting['page_title']	= $title;
		}

		if(isset($summary)){
			$page_setting['summary']	= $summary;
		}
		
		$page_setting['subtitle']	= $this->list_table->get_subtitle();

		$this->page_title($page_setting);

		$this->list_table->list_page();
	}

	public function ajax_response(){
		parent::ajax_response();

		$this->list_table->ajax_response();
	}
}