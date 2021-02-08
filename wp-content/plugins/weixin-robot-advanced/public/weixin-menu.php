<?php
class WEIXIN_MenuButton{
	private static $tab = '';

	public static function set_tab($tab){
		self::$tab	= $tab;
	}

	public static function get_primary_key(){
		return 'pos';
	}

	public static function get($pos){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);

		if(!$menu){
			$button	= [];
			$button['pos']	= $pos;
			return $button;
		}

		list($position, $sub_position)	= explode('_', $pos);

		if($sub_position == -1){
			$button	= $menu['button'][$position]??[];
		}else{
			$button	= $menu['button'][$position]['sub_button'][$sub_position]??[];
		}

		$button['pos']	= $pos;

		return $button;
	}

	public static function update($pos, $data){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);

		unset($data['menu_id']);

		list($position, $sub_position)	= explode('_', $pos);

		$buttons	= $menu['button']??[];

		if($sub_position == -1){
			if($data['type'] == 'main'){
				$data['type']	= '';
				$data['key']	= $data['url'] = '';

				if(!empty($buttons[$position]['sub_button'])){
					$data['sub_button']	= $buttons[$position]['sub_button'];
				}
			}

			$buttons[$position]	= $data;
		}else{
			$buttons[$position]['sub_button'][$sub_position]	= $data;
		}

		if($menu){
			$index	= $menu['index'] ?? '';

			return WEIXIN_Menu::update($index, ['button'=>$buttons]);
		}else{
			return WEIXIN_Menu::insert(['button'=>$buttons,'type'=>'menu']);
		}
	}

	public static function reply($pos, $data){
		$reply_type		= $data['reply_type']??'text';
		$reply			= $data[$reply_type];

		$button			= self::get($pos);

		$reply_data			= [
			'keyword'	=> $button['key'],
			'match'		=> 'full',
			'type'		=> $reply_type,
			$reply_type	=> $reply,
			'status'	=> 1
		];

		return WEIXIN_AdminReplySetting::set($reply_data);
	}

	public static function delete($pos){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);

		if(!$menu){
			return;
		}

		list($position, $sub_position)	= explode('_', $pos);

		$buttons	= $menu['button'];

		if($sub_position == -1){
			unset($buttons[$position]);
			$buttons	= array_values($buttons);
		}else{
			unset($buttons[$position]['sub_button'][$sub_position]);
			$buttons[$position]['sub_button']	= array_values($buttons[$position]['sub_button']);
		}

		$index	= $menu['index'] ?? '';

		return WEIXIN_Menu::update($index, ['button'=>$buttons]);
	}

	public static function move_down($pos){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);

		if(!$menu){
			return;
		}

		list($position, $sub_position)	= explode('_', $pos);

		$buttons	= $menu['button'];

		if($sub_position == -1){
			$temp_button	= $buttons[$position];
			$post_position	= $position+1;

			$buttons[$position]			= $buttons[$post_position];
			$buttons[$post_position]	= $temp_button;

		}else{
			$temp_sub_button	= $buttons[$position]['sub_button'][$sub_position];
			$post_sub_position	= $sub_position+1;

			$buttons[$position]['sub_button'][$sub_position]		= $buttons[$position]['sub_button'][$post_sub_position];
			$buttons[$position]['sub_button'][$post_sub_position]	= $temp_sub_button;
		}


		$index	= $menu['index'] ?? '';

		return WEIXIN_Menu::update($index, ['button'=>$buttons]);
	}

	public static function move_up($pos){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);

		if(!$menu){
			return;
		}

		list($position, $sub_position)	= explode('_', $pos);

		$buttons	= $menu['button'];

		if($sub_position == -1){
			$temp_button	= $buttons[$position];
			$prev_position	= $position-1;

			$buttons[$position]			= $buttons[$prev_position];
			$buttons[$prev_position]	= $temp_button;

		}else{
			$temp_sub_button	= $buttons[$position]['sub_button'][$sub_position];
			$prev_sub_position	= $sub_position-1;

			$buttons[$position]['sub_button'][$sub_position]		= $buttons[$position]['sub_button'][$prev_sub_position];
			$buttons[$position]['sub_button'][$prev_sub_position]	= $temp_sub_button;
		}

		$index	= $menu['index'] ?? '';

		return WEIXIN_Menu::update($index, ['button'=>$buttons]);
	}

	public static function sync(){
		return WEIXIN_Menu::sync();
	}

	public static function create($data=[]){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		return WEIXIN_Menu::create_menu($menu_id);
	}

	public static function duplicate(){
		$menu_id	= wpjam_get_data_parameter('menu_id');

		if($menu_id){
			$menu	= WEIXIN_Menu::get();
			return WEIXIN_Menu::update($menu_id, ['button'=>$menu['button']]);
		}else{
			return new WP_Error('invalid_menuid', '非法menuid');
		}
	}

	public static function parse($item=[]){
		list($position, $sub_position)	= explode('_', $item['pos']);

		if(empty($item['name'])){
			$item['add']	= true;
			$item['name']	= wpjam_get_list_table_row_action('edit', ['title'=>'新增', 'id'=>$item['pos']]);
		}

		if($sub_position != -1){
			$item['name']		= '└── '.$item['name'];
			$item['position']	= '└─ '.($sub_position+1);
		}else{
			$item['name']		= $item['name'];
			$item['position']	= $position+1;
		}

		$type	= $item['type']??'';

		if(empty($type)){
			$item['value']	= '';
		}elseif($type == 'view'){
			$item['value']	= $item['url'];
		}elseif($type == 'miniprogram'){
			$item['value']	= '小程序AppID：'.$item['appid'] . '<br />小程序页面路径：'. $item['pagepath'];
		}elseif($type == 'view_limited' || $type == 'media_id'){
			$item['value']	= $item['media_id'];
		}else{
			$item['value']	= $item['key'];
		}

		if(self::$tab == 'tree'){
			static $menu_stats;

			if(!isset($menu_stats)){
				$menu_stats	= apply_filters('weixin-menu-tree-stats', false);

				if(!$menu_stats){
					global $wpjam_stats_labels;
   
   					extract($wpjam_stats_labels);
					// $where = "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp} AND MsgType = 'event' AND Event in('CLICK', 'VIEW', 'scancode_push', 'scancode_waitmsg', 'pic_sysphoto', 'scancode_waitmsg', 'pic_weixin', 'location_select') AND EventKey !='' ";

					// $sql = "SELECT EventKey, count(*) as count FROM {$wpdb->weixin_messages} WHERE 1=1 AND {$where} GROUP BY EventKey";

					$counts = WEIXIN_AdminMessage::Query()->where_gt('CreateTime', $wpjam_start_timestamp)->where_lt('CreateTime', $wpjam_end_timestamp)->where('MsgType', 'event')->where_in('Event',['CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin'])->where_not('EventKey', '')->group_by('EventKey')->get_results('EventKey, count(*) as count');

					$counts	= wp_list_pluck($counts, 'count', 'EventKey');

					// $sql = "SELECT count(*) as total FROM {$wpdb->weixin_messages} WHERE 1=1 AND {$where}";
					$total = WEIXIN_AdminMessage::Query()->where_gt('CreateTime', $wpjam_start_timestamp)->where_lt('CreateTime', $wpjam_end_timestamp)->where('MsgType', 'event')->where_in('Event',['CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin'])->where_not('EventKey', '')->get_var("count(*)");

					$menu_stats = compact('total', 'counts');
				}
			}

			extract($menu_stats);

			if(!empty($item['key']) || !empty($item['url'])){
				// $item['count']		= isset($counts[$item['value']])?$counts[$item['value']]->count:0;
				$item['count']		= $counts[$item['value']]??0;
				$item['percent']	= round($item['count']/$total*100,2).'%'; 
			}else{
				$item['count']		= '';
				$item['percent']	= '';
			}
		}

		return $item;
	}

	public static function query_items($limit, $offset){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);
		$buttons	= $menu['button']??[];

		$editable 	= (self::$tab != 'tree' && (empty($menu_id) || empty($menu['menuid'])))?true:false;

		$items		= [];

		for ($position=0; $position <3 ; $position++) { 
			$button	= $buttons[$position]??'';
			if($button){

				$button['pos']	= $position.'_'.'-1';
				$items[]		= $button;

				if(!empty($button['sub_button'])){
					for ($sub_position=0; $sub_position <5 ; $sub_position++) { 
						$sub_button		= $button['sub_button'][$sub_position]??'';

						if($sub_button){
							$sub_button['pos']	= $position.'_'.$sub_position;
							$items[]	= $sub_button;
						}elseif($editable){
							$items[]	= ['pos'=>$position.'_'.$sub_position];
							break;
						}
					}
				// }elseif($editable && empty($button['key']) && empty($button['url'])){	// 主按钮没有设置 key 就可以设置子按钮
				}elseif($editable && empty($button['type'])){	// 主按钮没有设置 key 就可以设置子按钮
					$items[]	= ['pos'=>$position.'_'.'0'];
				}
			}elseif($editable){
				$items[]	= ['pos'=>$position.'_'.'-1'];
				break;
			}
		}

		$total = count($items);

		return compact('items', 'total');
	}

	public static function item_callback($item){
		$menu_id	= wpjam_get_data_parameter('menu_id');
		$menu		= WEIXIN_Menu::get($menu_id);

		$buttons	= $menu['button'] ?? [];
		$editable 	= (self::$tab != 'tree' && (empty($menu_id) || empty($menu['menuid'])))?true:false;

		$item		= self::parse($item);

		if(self::$tab == 'tree'){
			return $item;
		}

		$click_types = self::get_types(true);

		if(!empty($item['add'])){
			unset($item['row_actions']);
		}else{
			list($position, $sub_position)	= explode('_', $item['pos']);

			if($editable){
				if($sub_position == -1){
					if($position == 0){
						unset($item['row_actions']['move_up']);
					}

					if($position >= count($buttons)-1){
						unset($item['row_actions']['move_down']);
					}
				}else{
					if($sub_position == 0){
						unset($item['row_actions']['move_up']);
					}

					if($sub_position >= count($buttons[$position]['sub_button'])-1){
						unset($item['row_actions']['move_down']);
					}
				}
			}else{
				unset($item['row_actions']);
			}

			$type	= $item['type']??'';

			if(empty($type) || $type == 'view' || $type =='miniprogram' || $type == 'mian') {
				unset($item['row_actions']['reply']);
			}
		}

		return $item;
	}

	public static function get_types($require_reply=false){
		$types	= array(
			'main'				=> '主菜单（含有子菜单）', 
			'view'				=> '跳转URL',
			'click'				=> '点击推事件', 
			'miniprogram'		=> '小程序',
			'scancode_push'		=> '扫码推事件',
			'scancode_waitmsg'	=> '扫码带提示',
			'pic_sysphoto'		=> '系统拍照发图',
			'pic_photo_or_album'=> '拍照或者相册发图',
			'pic_weixin'		=> '微信相册发图器',
			'location_select'	=> '地理位置选择器',
			// 'media_id'			=> '下发素材消息',
			// 'view_limited'		=> '跳转图文消息URL',
		);

		if($require_reply){
			unset($types['main']);
			unset($types['view']);
			unset($types['miniprogram']);
		}

		return $types;
	}

	public static function get_actions(){
		if(self::$tab == 'tree'){
			return [];
		}else{
			$actions	= [
				'edit'		=> ['title'=>'编辑',		'response'=>'list'],
				'reply'		=> ['title'=>'设置回复',	'response'=>'list',	'page_title'=>'设置自定义回复', 'submit_text'=>'设置'],
				'delete'	=> ['title'=>'删除',	'response'=>'list',	'direct'=>true,	'confirm'=>true],
				'move_up'	=> ['title'=>'<span class="dashicons dashicons-arrow-up-alt"></span>',	'page_title'=>'向上移动',	'direct'=>true,	'response'=>'list'],
				'move_down'	=> ['title'=>'<span class="dashicons dashicons-arrow-down-alt"></span>','page_title'=>'向下移动',	'direct'=>true,	'response'=>'list'],
			];

			if(self::$tab == 'default'){
				$actions['create']	= ['title'=>'同步到微信',		'page_title'=>'同步到微信',	'overall'=>true,	'direct'=>true];
				$actions['sync']	= ['title'=>'从微信获取',		'page_title'=>'从微信获取',	'overall'=>true,	'direct'=>true];
				// $actions['del_all']	= ['title'=>'删除本地菜单',	'page_title'=>'删除本地菜单',	'overall'=>true];
			}elseif(self::$tab == 'buttons'){
				$menu_id	= wpjam_get_data_parameter('menu_id');

				if($menu_id){
					$menu	= WEIXIN_Menu::get($menu_id);

					if(empty($menu['menuid'])){
						$actions['duplicate']	= ['title'=>'从默认菜单复制',		'page_title'=>'从默认菜单复制',	'overall'=>true,	'direct'=>true];
						$actions['create']		= ['title'=>'添加个性化菜单',		'page_title'=>'添加个性化菜单',	'overall'=>true,	'direct'=>true];
					}else{
						$actions	= [];
					}
				}
			}

			return $actions;
		}
	}

	public static function get_fields($action_key='', $pos=0){
		if(self::$tab == 'tree'){
			return [
				'name'		=> ['title'=>'按钮名称',		'type'=>'view',	'show_admin_column'=>'only'],
				'position'	=> ['title'=>'位置',			'type'=>'view',	'show_admin_column'=>'only'],
				'type'		=> ['title'=>'按钮类型',		'type'=>'view',	'show_admin_column'=>'only',	'options'=>self::get_types()],
				'value'		=> ['title'=>'KEY/URL',		'type'=>'view',	'show_admin_column'=>'only'],
				'count'		=> ['title'=>'点击数',		'type'=>'view',	'show_admin_column'=>'only'],
				'percent'	=> ['title'=>'点击率',		'type'=>'view',	'show_admin_column'=>'only'],
			];
		}else{
			$fields	= [
				'name'		=> ['title'=>'按钮名称',		'type'=>'text',		'show_admin_column'=>true,	'description'=>'按钮描述，既按钮名字，不超过16个字节，子菜单不超过40个字节'],
				'position'	=> ['title'=>'位置',			'type'=>'view',		'show_admin_column'=>'only'],
				'type'		=> ['title'=>'按钮类型',		'type'=>'select',	'show_admin_column'=>true,	'options'=>self::get_types()],
				'value'		=> ['title'=>'KEY/URL',		'type'=>'text',		'show_admin_column'=>'only'],
				'key'		=> ['title'=>'按钮KEY值',	'type'=>'text',		'show_if'=>['key'=>'type', 'compare'=>'NOT IN', 'value'=>['main', 'view', 'miniprogram']],	'description'=>' '],
				'appid'		=> ['title'=>'小程序AppID',	'type'=>'text',		'show_if'=>['key'=>'type', 'compare'=>'=', 'value'=>'miniprogram']],
				'pagepath'	=> ['title'=>'页面路径',		'type'=>'text',		'show_if'=>['key'=>'type', 'compare'=>'=', 'value'=>'miniprogram']],
				'url'		=> ['title'=>'链接',			'type'=>'url',		'show_if'=>['key'=>'type', 'compare'=>'IN', 'value'=>['view', 'miniprogram']],	'description'=>' '],
			];

			if($action_key == 'edit'){
				list($position, $sub_position)	= explode('_', $pos);

				if($sub_position != -1){
					unset($fields['type']['options']['main']);
				}
			}elseif($action_key == 'reply'){
				$fields		= WEIXIN_AdminReplySetting::get_fields('', 0, 'reply_type');
				$button		= self::get($pos);
				$keyword	= $button['key']??'';

				$fields['keyword']['value']	= $keyword;
				$fields['keyword']['type']	= 'view';
				$fields['keyword']['title']	= '按钮KEY值';

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
			}

			return $fields;
		}
	}

	public static function load_list_table_page($current_tab){
		WEIXIN_MenuButton::set_tab($current_tab);

		wpjam_register_list_table('weixin-menu-buttons', [
			'title'		=> wpjam_get_current_tab_setting('title'),
			'singular'	=> 'weixin-button',
			'plural'	=> 'weixin-buttons',
			'model'		=> 'WEIXIN_MenuButton'
		]);

		wp_add_inline_style('list-tables', 'td.column-value{word-break: break-all;}');

		if($current_tab == 'tree'){
			add_action('wpjam_weixin_buttons_extra_tablenav', function($which){
				if($which == 'top'){
					global $wpjam_stats_labels;

					wpjam_stats_header();

					extract($wpjam_stats_labels);
				}
			});
		}

		add_action('admin_head', function(){
			$key_descriptions	= [
				'click' 			=> '请输入按钮KEY值，KEY值可以为搜索关键字，或者个性化菜单定义的关键字。用户点击按钮后，微信服务器会推送event类型的消息，并且带上按钮中开发者填写的key值',
				'scancode_push'		=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起扫一扫工具，完成扫码操作后显示扫描结果（如果是URL，将进入URL）。',
				'scancode_waitmsg'	=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起扫一扫工具，完成扫码操作后，将推送扫码的结果，同时收起扫一扫工具，然后弹出“消息接收中”提示框。',
				'pic_sysphoto'		=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起系统相机，完成拍照操作后，将推送拍摄的相片和事件，同时收起系统相机。',
				'pic_photo_or_album'=> '请输入按钮KEY值，用户点击按钮后，微信客户端将弹出选择器供用户选择“拍照”或者“从手机相册选择”。用户选择后即走其他两种流程。',
				'pic_weixin'		=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起微信相册，完成选择操作后，将推送选择的相片和事件，同时收起相册。',
				'location_select'	=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起地理位置选择工具，完成选择操作后，将推送选择的地理位置，同时收起位置选择工具。',
				'media_id'			=> '请输入永久素材的 Media_id，用户点击按钮后，微信服务器会将永久素材 Media_id 对应的素材下发给用户，永久素材类型可以是图片、音频、视频、图文消息。',
				'view_limited'		=> '请输入图文永久素材的 Media_id，用户点击按钮后，微信客户端将打开文永久素材的 Media_id 对应的图文消息URL，永久素材类型只支持图文消息。'
			];

			$url_descriptions	= [
				'view'				=> '请输入要跳转的链接。用户点击按钮后，微信客户端将会打开该链接。',
				'miniprogram'		=> '请输入不支持小程序的老版本客户端将打开的链接，必填，否则无法提交！'
			];

			if(weixin_get_type() == 4){
				$url_descriptions['view']	.= '可与网页授权获取用户基本信息接口结合，获得用户基本信息。';
			}

			foreach ($key_descriptions as $key => $value) {
				$key_descriptions[$key]	= $key_descriptions[$key].'<br /><br />*用于消息接口（event类型）推送，不超过128字节，如果按钮还有子按钮，可不填，其他必填，否则报错。';
			}

			?>
			<script type="text/javascript">
			jQuery(function($){
				var key_descriptions	= <?php echo wpjam_json_encode($key_descriptions);?>;
				var url_descriptions	= <?php echo wpjam_json_encode($url_descriptions);?>;

				$('body').on('change', 'select#type', function(){
					var selected = $(this).val();

					if(selected == 'miniprogram' || selected == 'view'){
						$('#tr_url span').html(url_descriptions[selected]);
					}else if(selected != 'main'){
						$('#tr_key span').html(key_descriptions[selected]);
					}

					tb_position();
				});

				$('body').on('list_table_action_success', function(response){
					$('body select#type').change();
				});
			});
			</script>
			<?php
		});
	}
}

class WEIXIN_Menu{
	public static function sync(){
		$response	= weixin()->get_menu();

		if(is_wp_error($response)){
			return $response;
		}

		$menus	= self::get_menus();

		if(isset($response['menu']['button'])){
			$menus['menu']	= $response['menu'];
		}else{
			$menus['menu']	= $menus['menu'] ?? [];
		}

		if(isset($response['conditionalmenu'])){
			foreach($response['conditionalmenu'] as $conditionalmenu){
				$menuid	= $conditionalmenu['menuid'];

				if($menus && isset($menus['conditionalmenu'])){
					$index	= self::has_menuid($menuid);
				}else{
					$index	= false;
				}

				if($index === false){
					$menus['conditionalmenu'][$menuid]	= array_merge($conditionalmenu, ['name'=>$menuid, 'index'=>$menuid]);
				}else{
					$menus['conditionalmenu'][$menuid]	= array_merge($menus['conditionalmenu'][$menuid], $conditionalmenu); 
				}
			}
		}else{
			$menus['conditionalmenu']	= $menus['conditionalmenu'] ?? [];
		}

		self::update_menus($menus);

		return true;
	}

	public static function create_menu($index){
		$menu		= self::get($index);
		$buttons	= $menu['button'];

		ksort($buttons);	// 按照 key 排序

		$buttons	= array_values($buttons);	// 防止中间某个key未填

		foreach($buttons as $position=>$button){
			if(!empty($button['sub_button'])){
				$sub_buttons = $button['sub_button'];
				ksort($sub_buttons);
				$sub_buttons = array_values($sub_buttons);
				$buttons[$position]['sub_button'] = $sub_buttons;
			}
		}

		if($index){
			$response	= weixin()->add_conditional_menu($buttons, $menu['matchrule']);

			if(is_wp_error($response)){
				return $response;
			}

			$menuid	= $response['menuid'];

			$menus	= self::get_menus();

			$menus[$menuid]	= $menus['conditionalmenu'][$index];
			unset($menus['conditionalmenu'][$index]);

			return self::update_menus($menus);
		}else{
			return weixin()->create_menu($buttons);
		}
	}

	public static function get($index=''){
		$menus	= self::get_menus();

		if(empty($menus)){
			return [];
		}

		if($index){
			$menus	= $menus['conditionalmenu'];
			return $menus[$index] ?? [];
		}else{
			return $menus['menu'] ?? [];
		}
	}

	public static function update($index, $data){
		$menus	= self::get_menus();

		if($index){
			if(empty($menus['conditionalmenu'])){
				$menus['conditionalmenu']	= [];
			}

			$menus['conditionalmenu'][$index]	= array_merge($menus['conditionalmenu'][$index], $data);
		}else{
			$menus['menu']['button']	= $data['button'];
		}

		return self::update_menus($menus);
	}

	public static function insert($data){
		$menus	= self::get_menus();

		$type	= $data['type'] ?? 'conditionalmenu';

		unset($data['type']);

		if($type == 'menu'){
			$menus['menu']	= $data;
		}else{
			$data['index']	= $index = time();
			$menus['conditionalmenu'][$index]	= $data;

			if(count($menus['conditionalmenu']) > 20){
				return new WP_Error('conditionalmenu_over_quota', '个性化菜单最多20个');
			}
		}

		$result	= self::update_menus($menus);

		if($type != 'menu' && !is_wp_error($result)){
			return $index;
		}

		return $result;
	}

	public static function delete($index){
		$menu	= self::get($index);
		$menus	= self::get_menus();

		unset($menus['conditionalmenu'][$index]);

		$result	= self::update_menus($menus);

		if(!empty($menu['menuid'])){
			$response	= weixin()->del_conditional_menu($menu['menuid']);

			if(is_wp_error($response)){
				return $response;
			}
		}

		return $result;
	}

	public static function has_menuid($menuid){
		$menus	= self::get_menus();

		if(!$menus || empty($menus['conditionalmenu'])){
			return false;
		}

		foreach($menus['conditionalmenu'] as $index => $conditionalmenu) {
			if($conditionalmenu['menuid'] == $menuid){
				return $index;
			}
		}

		return false;
	}

	public static function get_menus(){
		return get_option('weixin_'.weixin_get_appid().'_menus', []);
	}

	public static function update_menus($menus){
		return update_option('weixin_'.weixin_get_appid().'_menus', $menus);
	}

	public static function get_primary_key(){
		return 'index';
	}

	public static function query_items($limit, $offset){
		$menus	= self::get_menus();

		$items	= $menus['conditionalmenu'] ?? [];
		$total	= count($items); 

		return compact('items', 'total');
	}

	public static function item_callback($item){
		global $plugin_page;

		$item['menuid']		= $item['menuid'] ?? '';

		if($item['matchrule'] && isset($item['matchrule']['group_id'])){
			$tag_id	= $item['matchrule']['group_id'];

			unset($item['matchrule']['group_id']);
		}else{
			$tag_id	= $item['matchrule']['tag_id'] ?? '';
		}

		$item['matchrule']	= array_filter($item['matchrule']);

		if($tag_id !== ''){
			$item['matchrule']['tag_id']	= $tag_id;
		}

		if($item['matchrule']){
			$matchrule			= [];
			$matchrule_fields	= self::get_fields()['matchrule']['fields'];

			foreach ($item['matchrule'] as $key => $value) {
				if(!empty($matchrule_fields[$key]['options'])){
					$value	= $matchrule_fields[$key]['options'][$value] ?? $value;
				}

				$matchrule[]	= '<span class="matchrule-title">'.$matchrule_fields[$key]['title'].'</span><span class="matchrule-value">'.$value.'</span>';
			}

			$item['matchrule']	= wpautop(implode("\n\n", $matchrule));
		}else{
			$item['matchrule']	= '';
		}

		$set_title	= $item['menuid'] ? '查看按钮' : '设置按钮';
		$edit_title	= $item['menuid'] ? '修改名称' : '设置匹配规则';

		$item['row_actions']['edit']	= str_replace('编辑', $edit_title, $item['row_actions']['edit']);
		$item['row_actions']['set']		= '<a href="'.admin_url('admin.php?page='.$plugin_page).'&tab=buttons&menu_id='.$item['index'].'" title="'.$set_title.'">'.$set_title.'</a>';

		return $item;
	}

	public static function get_fields($action_key='', $index=''){
		$tag_options = [];

		$weixin_user_tags	= weixin()->get_tags();

		if(!is_wp_error($weixin_user_tags)){
			$tag_options	= array_combine(array_keys($weixin_user_tags), array_column($weixin_user_tags, 'name'));
		}

		$tag_options = array_merge([''=>'所有'], $tag_options);

		$fields = [
			'name'		=> ['title'=>'菜单名称',	'type'=>'text',		'show_admin_column'=>true,	'required'],
			'matchrule'	=> ['title'=>'匹配规则',	'type'=>'fieldset',	'show_admin_column'=>true,	'fieldset_type'=>'array',	'fields'=>[
				'tag_id'				=> ['title'=>'用户标签',		'type'=>'select',	'options'=>$tag_options],
				'sex'					=> ['title'=>'性别',			'type'=>'select',	'options'=>[''=>'所有', 1=>'男', 2=>'女']],
				'client_platform_type'	=> ['title'=>'客户端版本',	'type'=>'select',	'options'=>[''=>'所有', 1=>'iOS', 2=>'Android', 3=>'其他']],
				'country'				=> ['title'=>'国家',			'type'=>'text',		'class'=>'all-options',	'description'=>'例如：China'],
				'province'				=> ['title'=>'省份',			'type'=>'text',		'class'=>'all-options',	'description'=>'例如：Guangdong'],
				'city'					=> ['title'=>'城市',			'type'=>'text',		'class'=>'all-options',	'description'=>'例如：Guangzhou'],
			],'description'=>'<br />均可为空，但不能全部为空，至少要有一个匹配信息是不为空的。<br />地区信息从大到小验证，小的可以不填，即若填写了省份信息，则国家信息也必填并且匹配，城市信息可以不填。'],
			'menuid'	=> ['title'=>'菜单ID',	'type'=>'view',		'show_admin_column'=>'only']
		];

		if($action_key == 'edit'){
			$menu	= WEIXIN_Menu::get($index);

			if($menu['menuid']){
				unset($fields['matchrule']);
			}
		}

		return $fields;
	}

	public static function get_actions(){
		return [
			'add'		=> ['title'=>'新建',	'last'=>true],
			'edit'		=> ['title'=>'编辑'],
			'set'		=> ['title'=>'设置'],
			'duplicate'	=> ['title'=>'复制',	'last'=>true],
			'delete'	=> ['title'=>'删除',	'direct'=>true,	'bulk'=>true, 'confirm'=>true],
		];
	}

	public static function load_list_table_page($current_tab){
		wpjam_register_list_table('weixin-menus', [
			'title'		=> wpjam_get_current_tab_setting('title'),
			'singular'	=> 'weixin-menu',
			'plural'	=> 'weixin-menus',
			'model'		=> 'WEIXIN_Menu',
		]);

		wp_add_inline_style('weixin-news-items', "\n".'span.matchrule-title{width:100px; display: inline-block;}');
	}
}

include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-reply.php';

$defaut_tab_title	= weixin_get_type() > 2 ? '默认菜单' : '自定义菜单';

wpjam_register_plugin_page_tab('default',	[
	'title'				=>$defaut_tab_title,		
	'function'			=>'list',	
	'list_table_name'	=>'weixin-menu-buttons',
	'load_callback'		=>['WEIXIN_MenuButton', 'load_list_table_page']
]);

wpjam_register_plugin_page_tab('tree',		[
	'title'				=>$defaut_tab_title.'统计',
	'function'			=>'list',	
	'list_table_name'	=>'weixin-menu-buttons',
	'load_callback'		=>['WEIXIN_MenuButton', 'load_list_table_page']
]);

if(weixin_get_type() > 2){
	wpjam_register_plugin_page_tab('conditional',	[
		'title'				=>'个性化菜单',	
		'function'			=>'list',	
		'list_table_name'	=>'weixin-menus',
		'load_callback'		=>['WEIXIN_Menu', 'load_list_table_page']
	]);

	if($menu_id	= wpjam_get_data_parameter('menu_id')){
		if($menu = WEIXIN_Menu::get($menu_id)){
			$menu_name	= $menu['name'] ?: $menu['menuid'];

			wpjam_register_plugin_page_tab('buttons',	[
				'title'				=>$menu_name,
				'function'			=>'list',	
				'list_table_name'	=>'weixin-menu-buttons',
				'load_callback'		=>['WEIXIN_MenuButton', 'load_list_table_page'],
				'query_args'		=>['menu_id']
			]);
		}else{
			wp_die('个性化菜单不存在');
		}
	}
}

