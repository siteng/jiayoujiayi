<?php
wp_cache_add_global_groups('weixin_settings');

class WEIXIN_Setting extends WPJAM_Model {

	const SOURCE_DEFAULT = 0;
	const SOURCE_AUTHORIZE = 1;

	public static function insert($data){
		if(empty($data['blog_id'])){
			return new WP_Error('empty_blog_id', 'blog_id 不能为空');
		}

		if(self::get_by('blog_id', $data['blog_id'])){
			return new WP_Error('weixin_exists', '该站点已经绑定了微信公众号');
		}

		if(empty($data['component_blog_id'])){
			$appid	= $data['appid'];
			$secret	= $data['secret'];

			if(!weixin_exists($appid, $secret)){
				return new WP_Error('weixin_not_exists', '输入的 appid 和 secret 有误，请仔细核对！');
			}
		}

		$data['time']	= time();
		
		return parent::insert($data);
	}

	public static function update($appid, $data){
		$weixin_setting = self::get($appid);

		if($weixin_setting){
			if(empty($weixin_setting['component_blog_id']) && empty($data['component_blog_id']) && isset($data['secret'])){
				
				$secret	= $data['secret'];
				
				if($secret != $weixin_setting['secret'] && !weixin_exists($appid, $secret)){
					return new WP_Error('weixin_not_exists', '输入的 appid 和 secret 有误，请仔细核对！');
				}
			}
		}else{
			return new WP_Error('weixin_setting_not_exists', '系统中没有你更新的小程序，可能已经被删除了。');
		}

		$result = parent::update($appid, $data);

		if(is_wp_error($result)){
			return $result;
		}

		unset($data['blog_id']);	// 不能修改 blog_id

		// if(is_multisite()){
		// 	$old_blog_id	= $weixin_setting['blog_id'];
		// 	$new_blog_id	= $data['blog_id'] ?? 0;

		// 	if($new_blog_id && $old_blog_id != $new_blog_id){	// 迁移设置
		// 		if($weixin_setting = get_blog_option($old_blog_id, 'weixin_'.$appid)){
		// 			update_blog_option($new_blog_id, 'weixin_'.$appid, $weixin_setting);
		// 		}
		// 	}
		// }

		return $result;
	}

	public static function delete($appid){
		if($weixin_setting = self::get($appid)){
			if($weixin_setting['blog_id']){
				delete_blog_option($weixin_setting['blog_id'], 'weixin-robot');
			}

			global $wpdb;

			$table	= $wpdb->base_prefix . 'weixin_'.$appid.'_users';
			$sql	= "DROP TABLE {$table}";

			$wpdb->query($sql);

			return parent::delete($appid);
		}else{
			return new WP_Error('weixin_setting_not_exists', '系统中没有你更新的小程序，可能已经被删除了。');
		}
	}

	public static function get_setting($appid){
		if($weixin_setting = self::get($appid)){
			$setting_ex		= get_blog_option($weixin_setting['blog_id'], 'weixin-robot') ?: [];
			$weixin_setting	= array_merge($weixin_setting, $setting_ex);
		}

		return $weixin_setting;
	}

	// public static function get_settings($blog_id){
		
	// 	$weixin_settings	= self::get_by('blog_id', $blog_id);
	// 	$weixin_settings	= array_map(function($weixin_setting) use($blog_id){

	// 		$weixin_setting_ex	= get_blog_option($blog_id, 'weixin_'.$weixin_setting['appid']);
	// 		$weixin_setting_ex	= $weixin_setting_ex ?: [];

	// 		return array_merge($weixin_setting, $weixin_setting_ex);
	// 	}, $weixin_settings);

	// 	return $weixin_settings;
	// }

	public static function query_items($limit, $offset){
		if(empty($_GET['orderby'])){
			self::get_handler()->order_by('time');
		}

		list('items'=>$items, 'total'=>$total) = parent::query_items($limit, $offset);

		if($items){
			_prime_site_caches(wp_list_pluck($items, 'blog_id'));
		}

		return compact('items', 'total');
	}

	public static function item_callback($item){
		$item['time']	= get_date_from_gmt(date('Y-m-d H:i:s', $item['time']));

		if($detail = get_blog_details($item['blog_id'])){
			$item['name']		= $item['name'] ?: $detail->blogname;
			$item['name']		= '<a href="'.get_admin_url($item['blog_id'],'admin.php?page=weixin-settings').'">'.$item['name'].'</a>';
			$item['blog_id']	= $detail->blogname;
		}else{
			$item['name']		= '<span class="red">站点已经删除</span>';
		}
		
		return $item;
	}

	public static function get_actions(){
		return [
			'delete'	=> ['title'	=>'删除',	'confirm'=>true,	'direct'=>true]
		];
	}

	public static function get_fields($action_key='', $id=''){
		$fields = [
			'name'				=> ['title'=>'公众号名',		'type'=>'text',	'show_admin_column'=>true, 	'required'],
			'appid'				=> ['title'=>'公众号ID',		'type'=>'text',	'show_admin_column'=>true,	'required'],
			// 'secret'			=> ['title'=>'公众号密钥',	'type'=>'text',	'required'],
			'blog_id'			=> ['title'=>'所属站点',		'type'=>'text',	'show_admin_column'=>true,	'value'=>get_current_blog_id()],
			// 'component_blog_id'	=> ['title'=>'第三方平台',	'type'=>'text',	'show_admin_column'=>'only'],
			'time'				=> ['title'=>'添加时间',		'type'=>'view',	'show_admin_column'=>'only',	'sortable_column'=>true],
		];

		return $fields;
	}

	private static 	$handler;

	public static function get_handler(){
		global $wpdb;
		if(is_null(self::$handler)){
			self::$handler = new WPJAM_DB(self::get_table(), array(
				'primary_key'		=> 'appid',
				'cache_key'			=> 'blog_id',
				'cache_group'		=> 'weixin_settings',
				'field_types'		=> ['blog_id'=>'%d','time'=>'%d'],
				'searchable_fields'	=> ['appid','name'],
				'filterable_fields'	=> ['component_blog_id'],
			));
		}
		return self::$handler;
	}

	public static function get_table(){
		global $wpdb;
		return $wpdb->base_prefix . 'weixins';
	}

	public static function create_table($appid=''){
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		global $wpdb;

		$table	= self::get_table();

		if($wpdb->get_var("show tables like '{$table}'") != $table) {
			$sql = "
			CREATE TABLE IF NOT EXISTS `{$table}` (
				`blog_id` bigint(20) NOT NULL,
				`name` varchar(255) NOT NULL,
				`appid` varchar(32) NOT NULL,
				`secret` varchar(40) NOT NULL,
				`type` varchar(7) NOT NULL,
				`component_blog_id` bigint(20) NOT NULL DEFAULT 0,
				`time` int(10) NOT NULL,

				PRIMARY KEY	(`appid`),
				KEY `type` (`type`),
				KEY `blog_id` (`blog_id`)
			) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			";
	 
			dbDelta($sql);
		}
	}
}