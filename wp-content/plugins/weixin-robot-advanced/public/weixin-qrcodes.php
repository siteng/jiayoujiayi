<?php
class WEIXIN_Qrcode extends WPJAM_Model{
	public static function insert($data){
		$scene	= $data['scene'] ?? '';

		if(empty($scene)){
			return new WP_Error('empty_scene','场景值不能为空');
		}

		if(self::get($scene)){
			return new WP_Error('scene_already_added','该场景值已存在');
		}

		$type	= $data['type'];
		$expire	= $data['expire'] ?? 0;

		$response	= weixin()->create_qrcode($type, $scene, $expire);

		if(is_wp_error($response)){
			return $response;
		}

		$data['ticket']	= $response['ticket'];

		if($type == 'QR_SCENE' || $type == 'QR_STR_SCENE'){
			$data['expire'] = time()+$response['expire_seconds'];
		}

		return parent::insert($data);
	}

	public static function subscribe($scene, $data){
		$reply_type		= $data['reply_type'] ?? 'text';
		$reply			= $data[$reply_type];

		$reply_data		= [
			'keyword'	=> $data['keyword'],
			'match'		=> 'full',
			'type'		=> $reply_type,
			$reply_type	=> $reply,
			'status'	=> 1
		];

		return WEIXIN_AdminReplySetting::set($reply_data);
	}

	public static function scan($id, $data){
		return self::subscribe($id, $data);
	}

	public static function item_callback($item){
		$item['ticket']	= '<img src="https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($item['ticket']).'" width="100">';

		if($item['type']=='QR_SCENE'){
			$item['expire']	= $item['expire']-time()>0 ? get_date_from_gmt(date('Y-m-d H:i:s', $item['expire'])) : '已过期';
		}else{
			$item['expire']	= '';
		}
		

		return $item;
	}

	public static function get_actions(){
		return [
			'add'		=> ['title' =>'新增',	'last'=>true],
			'edit'		=> ['title'	=>'编辑'],
			'subscribe'	=> ['title'	=>'关注回复'],
			'scan'		=> ['title'	=>'扫描回复'],
			'delete'	=> ['title'	=>'删除',	'confirm'=>true,	'direct'=>true,	'bulk'=>true]
		];
	}

	public static function get_fields($action_key='', $scene=''){
		if($action_key == 'subscribe' || $action_key=='scan'){
			global $current_tab;

			$fields		= WEIXIN_AdminReplySetting::get_fields('',0,'reply_type');
			$item		= self::get($scene);

			if($action_key == 'subscribe'){
				$keyword	= '[subscribe_'.$scene.']';
			}elseif($action_key == 'scan'){
				$keyword	= '[scan_'.$scene.']';
			}

			$fields['keyword']['value']	= $keyword;

			unset($fields['match']);

			$custom_reply	= WEIXIN_AdminReplySetting::get_by_keyword($keyword);

			if($custom_reply){
				$reply_type		= $custom_reply['type'];
				$fields['reply_type']['value']	= $reply_type;

				if($fields[$reply_type]['type'] == 'fieldset'){
					foreach($fields[$reply_type]['fields'] as $reply_sub_key=>&$reply_sub_field){
						$reply_sub_field['value']	= $custom_reply[$reply_type][$reply_sub_key] ?? '';
					}
				}else{
					$fields[$reply_type]['value']	= $custom_reply[$reply_type];
				}
			}

			unset($fields['status']);
		}else{
			$fields	= [
				'ticket'	=> ['title'=>'二维码',	'type'=>'text',		'show_admin_column'=>'only'],
				'name'		=> ['title'=>'名称',		'type'=>'text',		'show_admin_column'=>true,	'required',	'description'=>'二维码名称无实际用途，仅用于更加容易区分。'],
				'scene'		=> ['title'=>'场景值',	'type'=>'number',	'show_admin_column'=>true,	'min'=>'1',	'max'=>'100000',	'required',	'description'=>'目前参数只支持1-100000'],
				'type'		=> ['title'=>'类型',		'type'=>'select',	'show_admin_column'=>true,	'options'=> self::get_types()],
				'expire'	=> ['title'=>'过期时间',	'type'=>'text',		'show_admin_column'=>true,	'show_if'=>['key'=>'type','value'=>'QR_SCENE'],	'description'=> '二维码有效时间，以秒为单位。最大不超过1800'],
				
			];

			if($action_key == 'edit'){
				unset($fields['type']);
				unset($fields['expire']);
			}

			return $fields;
		}

		return $fields;
	}

	public static function get_types(){
		return [
			'QR_LIMIT_SCENE'	=> '永久二维码',
			'QR_SCENE'			=> '临时二维码'
		];
	}

	protected static $handler;

	public static function get_handler(){
		if(is_null(static::$handler)){
			static::$handler	= new WPJAM_Option('weixin_'.weixin_get_appid().'_qrcodes', ['total'=>50, 'primary_key'=>'scene']);
		}
		return static::$handler;
	}

	public static function load_plugin_page(){
		wpjam_register_plugin_page_tab('qrcodes',	['title'=>'带参数二维码',	'function'=>'list',	'list_table_name'=>'weixin-qrcodes',	'load_callback'=>['WEIXIN_Qrcode', 'load_list_table_page']]);
		wpjam_register_plugin_page_tab('shorturl',	['title'=>'链接缩短',		'function'=>'form',	'form_name'=>'weixin_short_url',	'load_callback'=>['WEIXIN_ShortUrl', 'load_form_page']]);
		wpjam_register_plugin_page_tab('shorturls',	['title'=>'常用短链',		'function'=>'list',	'list_table_name'=>'weixin-shorturl',	'load_callback'=>['WEIXIN_ShortUrl', 'load_list_table_page']]);
	}

	public static function load_list_table_page(){
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-reply.php';

		wpjam_register_list_table('weixin-qrcodes', [
			'title'				=> '二维码',
			'primary_column'	=> 'name',
			'singular'			=> 'weixin-qrcode',
			'plural'			=> 'weixin-qrcodes',
			'model'				=> 'WEIXIN_Qrcode',
		]);

		wp_add_inline_style('list-tables', 'th.column-name{width:30%;}');
	}
}

class WEIXIN_ShortUrl extends WPJAM_Model{
	public static function insert($data){
		$url	= $data['url'] ?? '';
		$name	= $data['name'] ?? '';

		if(empty($url)){
			return new WP_Error('empty_url','链接不能为空');
		}

		$short	= weixin()->shorturl($url);

		if(is_wp_error($short)){
			return $short;
		}

		$key	= str_replace('https://mmbizurl.cn/s/', '', $short);

		return parent::insert(compact('url', 'short', 'key', 'name'));
	}

	public static function get_actions(){
		return [
			'add'		=> ['title' =>'新增',	'last'=>true],
			'edit'		=> ['title' =>'编辑',	'last'=>true],
			'delete'	=> ['title'	=>'删除',	'confirm'=>true,	'direct'=>true,	'bulk'=>true]
		];
	}

	public static function get_fields($action_key='', $scene=''){
		if($action_key == 'edit'){
			return [
				'name'	=> ['title'=>'名称',	'type'=>'text',	'show_admin_column'=>true]
			];
		}else{
			return [
				'name'	=> ['title'=>'名称',	'type'=>'text',	'show_admin_column'=>true],
				'short'	=> ['title'=>'短链',	'type'=>'url',	'show_admin_column'=>'only'],
				'url'	=> ['title'=>'链接',	'type'=>'url',	'show_admin_column'=>true,	'required'],
			];
		}
	}

	protected static $handler;

	public static function get_handler(){
		if(is_null(static::$handler)){
			static::$handler	= new WPJAM_Option('weixin_'.weixin_get_appid().'_shorturls', ['total'=>50, 'primary_key'=>'key']);
		}
		return static::$handler;
	}

	public static function load_list_table_page(){
		wpjam_register_list_table('weixin-shorturl', [
			'title'		=> '短链',
			'singular'	=> 'weixin-shorturl',
			'plural'	=> 'weixin-shorturls',
			'model'		=> 'WEIXIN_ShortUrl',
			'summary'	=> '常用链接转换成短链之后保存到数据库，方便之后长期常用。'
		]);

		wp_add_inline_style('list-tables', 'th.column-name{width:15%; min-width:84px;} td.column-url{word-break: break-all;;}');
	}

	public static function load_form_page(){
		wpjam_register_page_action('weixin_short_url', [
			'submit_text'	=> '缩短',
			'response'		=> 'append',
			'fields'		=> ['url'	=> ['title'=>'', 'type'=>'textarea', 'style'=>'max-width:640px;', 'value'=>'']],
			'summary'		=>'转换为 <code>https://mmbizurl.cn/s/xxxxx</code> 格式的短连接，支持 http://、https://、weixin://wxpay 格式的链接。', 
			'callback'		=> function(){
				$url	= wpjam_get_data_parameter('url', ['required'=>true]);
				$short	= weixin()->shorturl($url);

				return is_wp_error($short) ? $short : '<h2>短链接为</h2><p>'.$short.'</p>';
			}
		]);
	}
}

add_action('wp_loaded', function(){
	if(weixin_get_type() < 4 || !is_admin()){
		return;
	}

	weixin_add_sub_page('weixin-qrcodes',	['menu_title'=>'渠道管理',	'function'=>'tab',	'load_callback'=>['WEIXIN_Qrcode', 'load_plugin_page']]);
});