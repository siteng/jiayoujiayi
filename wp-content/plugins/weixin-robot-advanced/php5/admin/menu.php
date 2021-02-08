<?php
function weixin_robot_menu_tabs($tabs){
	$tabs =  array(
		'menu'			=> array('title'=>'默认菜单',		'function'=>'weixin_robot_menu_button_page'),
		'conditional'	=> array('title'=>'个性化菜单',	'function'=>'weixin_robot_conditional_menu_page')
	);

	if(WEIXIN_TYPE < 3){
		unset($tabs['conditional']);
	}

	if(isset($_GET['tab']) && $_GET['tab'] == 'buttons' && !empty($_GET['id'])){
		if($weixin_menu	= weixin_robot_get_local_menu($_GET['id'])){
			$menu_title			= $weixin_menu['name']?:$weixin_menu['menuid'];
			$tabs['buttons']	= array('title'=>$menu_title,	'function'=>'weixin_robot_menu_button_page', 'args'=>array('id'=>$_GET['id']));
		}else{
			wp_die('该菜单不存在');
		}
	}

	return $tabs;
}

function weixin_robot_menu_stats_tabs($tabs){
	return array(
		'menu'	=> array('title'=>'菜单点击统计',		'function'=>'weixin_robot_message_stats_page'),
		'tree'	=> array('title'=>'默认菜单汇总统计',	'function'=>'weixin_robot_menu_tree_stats_page')
	);
}

function weixin_robot_menu_stats_page_load(){
	weixin_robot_menu_page_load();
}

function weixin_robot_menu_page_load(){
	global $weixin_list_table, $plugin_page, $current_tab;

	if($plugin_page == 'weixin-robot-menu-stats' && $current_tab == 'menu'){
		return;
	}

	if($current_tab == 'conditional'){
		
		$style = '
		th.column-name{width:180px;}
		th.column-menuid{width:100px;}
		.tablenav{display:none;}
		';

		$list_table_args = array(
			'plural'		=> 'weixin-menus',
			'singular'		=> 'weixin-menu',
			'item_callback'	=> 'weixin_robot_conditional_menu_item',
			'style'			=> $style,
		);
	}else{
		$columns	= array(
			'name'		=> '按钮',
			'position'	=> '位置',
			'type'		=> '类型',
			'key'		=> 'Key/URL',
		);

		if($plugin_page == 'weixin-robot-menu-stats'){
			$columns['count'] 	= '点击数';
			$columns['percent']	= '比率';
		}
		
		$style = '
		th.column-name{width:200px;}
		th.column-position{width:70px;}
		th.column-type{width:84px;}
		th.column-count{width:70px;}
		th.column-percent{width:70px;}
		.tablenav{display:none;}
		.row-actions .dashicons{font-size:16px; line-height:18px;}
		';

		$list_table_args = array(
			'plural'	=> 'weixin-buttons',
			'singular'	=> 'weixin-button',
			'columns'	=> $columns,
			'style'		=> $style,
		);
	}

	if($plugin_page == 'weixin-robot-menu'){
		$list_table_args['actions_column']	= 'name';
	}

	$weixin_list_table = wpjam_list_table($list_table_args);
}

function weixin_robot_menu_button_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';
	if($action == 'edit' || $action == 'add'){
		weixin_robot_menu_button_edit_page();
	}else{
		weixin_robot_menu_button_list_page();
	}
}

// 菜单按钮列表
function weixin_robot_menu_button_list_page(){
	global $wpdb, $current_admin_url, $weixin_list_table;

	$id			= isset($_GET['id'])?$_GET['id']:'';
	$blog_id	= get_current_blog_id();

	if($id == 0 && get_option('weixin-custom-menus')){
		weixin_robot_activation();						// 1. 创建微信自定义菜单的表
		delete_option('weixin-custom-menus');			// 2. 删除 options 中的自定义菜单设置，使用表来存储
		weixin_robot_get_menu($show_message = false);	// 3. 使用微信自定义菜单的查询接口把远程的微信自定义菜单获取下来
	}else{
		$current_admin_url .= '&id='.$id; 
	}

	$action			= $weixin_list_table->current_action();

	$redirect_to	= wpjam_get_referer();
	if($id){
		$redirect_to	.= '&id='.$id; 
	}

	$weixin_menu	= weixin_robot_get_local_menu($id);
	$buttons		= $weixin_menu['button'];

	if($action == 'delete' || $action == 'up' || $action == 'down'){
		if($action == 'delete'){
			if( !current_user_can( 'delete_weixin' )){
				ob_clean();
				wp_die('无权限');
			}
		}else{
			if( !current_user_can( 'edit_weixin' )){
				ob_clean();
				wp_die('无权限');
			}
		}

		$position		= isset($_GET['position'])?$_GET['position']:0;
		$sub_position	= isset($_GET['sub_position'])?$_GET['sub_position']:'';

		if($sub_position === ''){
			check_admin_referer($action.'-'.$weixin_list_table->get_singular().'-'.$position);
		}else{
			check_admin_referer($action.'-'.$weixin_list_table->get_singular().'-'.$position.'-'.$sub_position);
		}

		if($action == 'delete'){
			if($sub_position === ''){
				unset($buttons[$position]);
			}else{
				unset($buttons[$position]['sub_button'][$sub_position]);
			}
		}elseif($action == 'up') {
			if($sub_position === ''){
				if($position == 0){
					ob_clean();
					wp_die('不能上移');
				}

				$temp_button	= $buttons[$position];
				$prev_position	= $position-1;

				if(isset($buttons[$prev_position])){
					$buttons[$position]		= $buttons[$prev_position];
				}else{
					unset($buttons[$position]);
				}

				$buttons[$prev_position]	= $temp_button;

			}else{
				if($sub_position == 0){
					ob_clean();
					wp_die('不能上移');
				}

				$temp_sub_button	= $buttons[$position]['sub_button'][$sub_position];
				$prev_sub_position	= $sub_position-1;

				if(isset($buttons[$position]['sub_button'][$prev_sub_position])){
					$buttons[$position]['sub_button'][$sub_position]	= $buttons[$position]['sub_button'][$prev_sub_position];
				}else{
					unset($buttons[$position]['sub_button'][$sub_position]);
				}

				$buttons[$position]['sub_button'][$prev_sub_position]	= $temp_sub_button;
			}
		}elseif($action == 'down') {
			if($sub_position === ''){
				if($position == 2){
					ob_clean();
					wp_die('不能下移');
				}

				$temp_button	= $buttons[$position];
				$post_position	= $position+1;

				if(isset($buttons[$post_position])){
					$buttons[$position]		= $buttons[$post_position];
				}else{
					unset($buttons[$position]);
				}

				$buttons[$post_position]	= $temp_button;

			}else{
				if($sub_position == 4){
					ob_clean();
					wp_die('不能下移');
				}

				$temp_sub_button	= $buttons[$position]['sub_button'][$sub_position];
				$post_sub_position	= $sub_position+1;

				if(isset($buttons[$position]['sub_button'][$post_sub_position])){
					$buttons[$position]['sub_button'][$sub_position]	= $buttons[$position]['sub_button'][$post_sub_position];
				}else{
					unset($buttons[$position]['sub_button'][$sub_position]);
				}

				$buttons[$position]['sub_button'][$post_sub_position]	= $temp_sub_button;
			}
		}

		if($id){
			$wpdb->update($wpdb->weixin_menus, array('button'=>wpjam_json_encode($buttons)), compact('id'));
		}else{
			$wpdb->update($wpdb->weixin_menus, array('button'=>wpjam_json_encode($buttons)), array('type'=>'menu','blog_id'=>$blog_id));
		}

		if($action == 'delete'){
			$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
		}else{
			$redirect_to = add_query_arg( array( 'updated' => 'true' ), $redirect_to );
		}

		wp_redirect($redirect_to);

	}elseif($action == 'create'){
		if( !current_user_can( 'edit_weixin' )){
			ob_clean();
			wp_die('无权限');
		}

		check_admin_referer('create-'.$weixin_list_table->get_plural());

		$menu_type		= $weixin_menu['type'];

		ksort($buttons);					// 按照 key 排序
		$buttons = array_values($buttons);	// 防止中间某个key未填

		foreach ($buttons as $position => $button) {
			if(!empty($button['sub_button'])){
				$sub_buttons = $button['sub_button'];
				ksort($sub_buttons);
				$sub_buttons = array_values($sub_buttons);
				$buttons[$position]['sub_button'] = $sub_buttons;
			}
		}

		if($menu_type == 'conditional'){
			$response = weixin_robot_add_conditional_menu($buttons, $weixin_menu['matchrule']);
			if(!is_wp_error($response)){
				$menuid	= $response['menuid'];
				$wpdb->update($wpdb->weixin_menus, array('menuid'=>$menuid), compact('id','blog_id'));
			}
		}else{
			$response = weixin_robot_create_menu($buttons);
		}

		if(is_wp_error($response)){
			$redirect_to = add_query_arg( array( 'created' => urlencode($response->get_error_message()) ), $redirect_to );
		}else{
			weixin_robot_get_menu($show_message=false);		// 同步菜单到微信之后，再次获取微信的菜单数据，和微信官方的菜单数据保持一致
			$redirect_to = add_query_arg( array( 'created' => 'true' ), $redirect_to );
		}
		wp_redirect($redirect_to);
	}elseif($action == 'get'){
		if( !current_user_can( 'edit_weixin' )){
			ob_clean();
			wp_die('无权限');
		}

		check_admin_referer('get-'.$weixin_list_table->get_plural());
		weixin_robot_get_menu();
		$redirect_to = add_query_arg( array( 'geted' => 'true' ), $redirect_to );
		wp_redirect($redirect_to);
	}elseif($action == 'duplicate'){
		if( !current_user_can( 'edit_weixin' )){
			ob_clean();
			wp_die('无权限');
		}
		
		if($default_weixin_menu = weixin_robot_get_local_menu('',$decode=false)){
			$wpdb->update($wpdb->weixin_menus, array('button'=>$default_weixin_menu['button']), compact('id'));
			$redirect_to = add_query_arg( array( 'duplicated' => 'true' ), $redirect_to );
		}else{
			$redirect_to = add_query_arg( array( 'duplicated' => urlencode('默认菜单都没创建！') ), $redirect_to );
		}

		wp_redirect($redirect_to);
	}elseif($action == 'delete-all'){
		if( !current_user_can( 'manage_options' )){
			ob_clean();
			wp_die('无权限');
		}
		check_admin_referer('delete-all'.$weixin_list_table->get_plural());
		weixin_robot_delete_menu();
	}
	
	$weixin_menu	= weixin_robot_get_local_menu($id);
	$menu_type		= $weixin_menu['type'];
	$menuid			= $weixin_menu['menuid'];
	$buttons		= $weixin_menu['button'];

	echo ($menu_type == 'conditional')?'<h2>个性化菜单</h2>':((WEIXIN_TYPE >= 3)?'<h2>默认菜单</h2>':'<h2>自定义菜单</h2>');

	$type_list	= weixin_robot_get_menu_type_list();
	
	$weixin_buttons		= array();

	$added_button 		= ($menu_type == 'conditional' && $menuid)?true:false;

	for ($position=0; $position <3 ; $position++) { 
		$button			= isset($buttons[$position])? $buttons[$position]:'';
		$weixin_button	= array();

		if($button){
			$type	= isset($button['type'])?$button['type']:'';

			$weixin_button['name']		= $button['name'];
			$weixin_button['position']	= $position+1;
			$weixin_button['type']		= isset($type_list[$type])?$type_list[$type]:$type;

			if(empty($type)){
				$weixin_button['key']	= '';
			}elseif($type == 'view'){
				$weixin_button['key']	= $button['url'];
			}elseif($type == 'miniprogram'){
				$weixin_button['key']		= '小程序AppID：'.$button['appid'] . '<br />小程序页面路径：'. $button['pagepath'];
							
			}elseif($type == 'view_limited' || $type == 'media_id'){
				$weixin_button['key']	= $button['media_id'];
			}else{
				$weixin_button['key']	= $button['key'];
			}

			if($menu_type != 'conditional' || empty($menuid)){
				$weixin_button['row_actions'] = array(
					'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&position='.$position.'&TB_iframe=true&width=780&height=460').'" title="编辑按钮" class="thickbox">编辑</a>',
					'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&position='.$position, 'delete-'.$weixin_list_table->get_singular().'-'.$position)).'">删除</a>',
				);

				if(($type != 'view' || $type !='miniprogram') && empty($button['sub_button'])){
					$weixin_button['row_actions']['reply']	= weixin_robot_get_keyword_set_html($weixin_button['key'], '设置回复');
				}

				if($position > 0){
					$weixin_button['row_actions']['up']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=up&position='.$position, 'up-'.$weixin_list_table->get_singular().'-'.$position)).'"><span class="dashicons dashicons-arrow-up-alt"></span></a>';
				}

				if($position < 2){
					$weixin_button['row_actions']['down']	= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=down&position='.$position, 'down-'.$weixin_list_table->get_singular().'-'.$position)).'"><span class="dashicons dashicons-arrow-down-alt"></span></a>';
				}
			}

			$weixin_buttons[]	= $weixin_button;

			if(!empty($button['sub_button'])){
				$sub_buttons		= $button['sub_button'];
				$added_sub_button	= ($menu_type == 'conditional' && $menuid)?true:false;
				for ($sub_position=0; $sub_position <5 ; $sub_position++) { 
					$sub_button		= isset($sub_buttons[$sub_position])?$sub_buttons[$sub_position]:'';
					$weixin_button	= array();

					if($sub_button){

						$type	= isset($sub_button['type'])?$sub_button['type']:'';

						$weixin_button['name']		= '└── '.$sub_button['name'];
						$weixin_button['position']	= '└─ '.($sub_position+1);
						$weixin_button['type']		= $type;
						$weixin_button['type']		= isset($type_list[$type])?$type_list[$type]:$type;

						if(empty($type)){
							$weixin_button['key']	= '';
						}elseif($type == 'view'){
							$weixin_button['key']	= $sub_button['url'];
						}elseif($type == 'miniprogram'){
							$weixin_button['key']		= '小程序AppID：'.$sub_button['appid'] . '<br />小程序页面路径：'. $sub_button['pagepath'];
						}elseif($type == 'view_limited' || $type == 'media_id'){
							$weixin_button['key']	= $sub_button['media_id'];
						}else{
							$weixin_button['key']	= $sub_button['key'];
						}

						if($menu_type != 'conditional' || empty($menuid)){
							$weixin_button['row_actions'] = array(
								'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&position='.$position.'&sub_position='.$sub_position.'&TB_iframe=true&width=780&height=460').'" title="编辑按钮" class="thickbox">编辑</a>',
								'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&position='.$position.'&sub_position='.$sub_position, 'delete-'.$weixin_list_table->get_singular().'-'.$position.'-'.$sub_position)).'">删除</a>',
							);

							if($type != 'view' && $type != 'miniprogram'){
								$weixin_button['row_actions']['reply']	= weixin_robot_get_keyword_set_html($weixin_button['key'], '设置回复');
							}

							if($sub_position > 0){
								$weixin_button['row_actions']['up']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=up&position='.$position.'&sub_position='.$sub_position, 'up-'.$weixin_list_table->get_singular().'-'.$position.'-'.$sub_position)).'"><span class="dashicons dashicons-arrow-up-alt"></span></a>';
							}

							if($sub_position  < 4){
								$weixin_button['row_actions']['down']	= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=down&position='.$position.'&sub_position='.$sub_position, 'down-'.$weixin_list_table->get_singular().'-'.$position.'-'.$sub_position)).'"><span class="dashicons dashicons-arrow-down-alt"></span></>';
							}
						}

						$weixin_buttons[]	= $weixin_button;
					}elseif($added_sub_button == false){
						$weixin_button['name']		= '└── <a href="'.esc_url($current_admin_url.'&action=add&position='.$position.'&sub_position='.$sub_position.'&TB_iframe=true&width=780&height=460').'" title="新增按钮" class="thickbox">新增</a>';
						$weixin_button['position']	= '└─ '.($sub_position+1);
						$weixin_button['key']		= '';
						$weixin_button['type']		= '';

						$weixin_buttons[]	= $weixin_button;

						$added_sub_button	= true;
					}
				}
			}elseif(empty($weixin_button['key'])){	// 主按钮没有设置 key 就可以设置子按钮
				$weixin_button	= array();

				$weixin_button['name']		= '└── <a href="'.esc_url($current_admin_url.'&action=add&position='.$position.'&sub_position=0&TB_iframe=true&width=780&height=460').'" title="新增按钮" class="thickbox">新增</a>';
				$weixin_button['position']	= 1;
				$weixin_button['key']		= '';
				$weixin_button['type']		= '';

				$weixin_buttons[]	= $weixin_button;
			}
		}elseif($added_button === false){
			$weixin_button['name']		= '';
			$weixin_button['position']	= $position+1;
			$weixin_button['key']		= '';
			$weixin_button['type']		= '';

			$weixin_button['row_actions'] = array(
				'add'		=> '<a href="'.esc_url($current_admin_url.'&action=add&position='.$position.'&TB_iframe=true&width=780&height=460').'" title="新增按钮" class="thickbox">新增</a>',
			);

			$weixin_buttons[]	= $weixin_button;

			$added_button		= true;
		}
	}

	$weixin_list_table->prepare_items($weixin_buttons);
	$weixin_list_table->display();
	?>
	<?php if($menu_type == 'conditional'){?>
	<?php if(empty($menuid)){?>
	<p class="submit">
	<?php if(empty($buttons)){ ?>
	<a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=duplicate', 'duplicate-'.$weixin_list_table->get_plural())); ?>" class="button-primary">从默认菜单复制</a>&nbsp;&nbsp;&nbsp;
	<?php }else{ ?>
	<a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=create', 'create-'.$weixin_list_table->get_plural())); ?>" class="button-primary">添加个性化菜单</a>&nbsp;&nbsp;&nbsp;
	<?php } ?>
	</p>
	<?php } ?>
	<?php }else{?>
	<p class="submit">
	<?php if($buttons){ ?>
	<a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=create', 'create-'.$weixin_list_table->get_plural())); ?>" class="button-primary">同步到微信</a>&nbsp;&nbsp;&nbsp;
	<?php } ?>
	<a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=get', 'get-'.$weixin_list_table->get_plural())); ?>" class="button-primary">从微信获取</a>&nbsp;&nbsp;&nbsp;
	<!-- <a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=delete-all', 'delete-all'.$weixin_list_table->get_plural())); ?>" class="button-primary">从微信删除</a> -->
	</p>
	<?php }?>
	
	<?php
}

// 按钮添加和编辑
function weixin_robot_menu_button_edit_page(){
	global $current_admin_url, $wpdb;

	$type_list		= weixin_robot_get_menu_type_list();
	$blog_id		= get_current_blog_id();

	$id				= isset($_GET['id'])?$_GET['id']:'';
	$position		= isset($_GET['position'])?(int)$_GET['position']:0;
	$sub_position	= isset($_GET['sub_position'])?(int)$_GET['sub_position']:'';
	$action			= isset($_GET['action'])?$_GET['action']:'add';

	if($sub_position !== ''){
		unset($type_list['main']);
	}

	
	$current_admin_url	.= ($id)?'&id='.$id:'';
	$current_admin_url	.= '&position='.$position;
	$current_admin_url	.= ($sub_position !== '')?'&sub_position='.$sub_position:'';

	$nonce_action	= ($action == 'add') ? 'add-weixin-menu-'.$position.'-'.$sub_position: 'edit-weixin-menu-'.$position.'-'.$sub_position;

	$form_fields = array(
		'name'			=> array('title'=>'按钮名称',			'type'=>'text',		'description'=>'按钮描述，既按钮名字，不超过16个字节，子菜单不超过40个字节'),
		'type'			=> array('title'=>'按钮类型',			'type'=>'select',	'options'=> $type_list),
		'key'			=> array('title'=>'按钮KEY值',		'type'=>'text'),
		'appid'			=> array('title'=>'小程序的AppID',	'type'=>'text'),
		'pagepath'		=> array('title'=>'小程序的页面路径',	'type'=>'text'),
		'url'			=> array('title'=>'链接',			'type'=>'url',		'class'=>'large-text'),
	);

	$key_descriptions	= array(
		'click' 			=> '请输入按钮KEY值，KEY值可以为搜索关键字，或者个性化菜单定义的关键字。用户点击按钮后，微信服务器会推送event类型的消息，并且带上按钮中开发者填写的key值',
		'scancode_push'		=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起扫一扫工具，完成扫码操作后显示扫描结果（如果是URL，将进入URL）。',
		'scancode_waitmsg'	=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起扫一扫工具，完成扫码操作后，将推送扫码的结果，同时收起扫一扫工具，然后弹出“消息接收中”提示框。',
		'pic_sysphoto'		=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起系统相机，完成拍照操作后，将推送拍摄的相片和事件，同时收起系统相机。',
		'pic_photo_or_album'=> '请输入按钮KEY值，用户点击按钮后，微信客户端将弹出选择器供用户选择“拍照”或者“从手机相册选择”。用户选择后即走其他两种流程。',
		'pic_weixin'		=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起微信相册，完成选择操作后，将推送选择的相片和事件，同时收起相册。',
		'location_select'	=> '请输入按钮KEY值，用户点击按钮后，微信客户端将调起地理位置选择工具，完成选择操作后，将推送选择的地理位置，同时收起位置选择工具。',
		'media_id'			=> '请输入永久素材的 Media_id，用户点击按钮后，微信服务器会将永久素材 Media_id 对应的素材下发给用户，永久素材类型可以是图片、音频、视频、图文消息。',
		'view_limited'		=> '请输入图文永久素材的 Media_id，用户点击按钮后，微信客户端将打开文永久素材的 Media_id 对应的图文消息URL，永久素材类型只支持图文消息。'
	);

	$url_descriptions	= array(
		'view'				=> '请输入要跳转的链接。用户点击按钮后，微信客户端将会打开该链接。',
		'miniprogram'		=> '请输入不支持小程序的老版本客户端将打开的链接。'
	);

	if(WEIXIN_TYPE == 4){
		$url_descriptions['view']	.= '可与网页授权获取用户基本信息接口结合，获得用户基本信息。';
	}

	foreach ($key_descriptions as $key => $value) {
		$key_descriptions[$key]	= $key_descriptions[$key].'<br /><br />*用于消息接口（event类型）推送，不超过128字节，如果按钮还有子按钮，可不填，其他必填，否则报错。';
	}

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data			= wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');
		$weixin_menu	= weixin_robot_get_local_menu($id);
		$buttons		= $weixin_menu['button'];
		
		if($sub_position === ''){
			$button = isset($buttons[$position])?$buttons[$position]:array();

			$button['name']	= $data['name'];
			
			if(trim($data['key']) || trim($data['url']) ){
				$button['type']	= $data['type'];

				if($data['type'] == 'view'){
					$button['url']	= $data['url'];
					if(WEIXIN_TYPE == 4 && strpos($button['url'], 'http://w.url.cn/') === false){
						$short_url	=  weixin_robot_get_short_url($data['url']);
						if(!is_wp_error($short_url)){
							$button['url'] = $short_url;
						}
					}
				}elseif($data['type'] == 'miniprogram' ){
					$button['url']		= $data['url'];
					$button['appid']	= $data['appid'];
					$button['pagepath']	= $data['pagepath'];
				}elseif($data['type'] == 'media_id' || $data['type'] == 'view_limited' ){
					$button['media_id']	= $data['key'];
				}elseif($data['type'] == 'main'){
					$button['key']	= '';
				}else{
					$button['key']	= $data['key'];
				}
				unset($button['sub_button']);
			}else{
				unset($button['key']);
				unset($button['type']);
			}

			$buttons[$position]	= $button;
		}else{
			$sub_button = array();

			$sub_button['name']	= $data['name'];
			$sub_button['type']	= $data['type'];

			if($data['type'] == 'view'){
				$sub_button['url']	= $data['url'];
				if(WEIXIN_TYPE == 4 && strpos($sub_button['url'], 'http://w.url.cn/') === false){
					$short_url	=  weixin_robot_get_short_url($data['url']);
					if(!is_wp_error($short_url)){
						$sub_button['url'] = $short_url;
					}
				}
			}elseif($data['type'] == 'miniprogram'){
				$sub_button['url']		= $data['url'];
				$sub_button['appid']	= $data['appid'];
				$sub_button['pagepath']	= $data['pagepath'];
			}elseif($data['type'] == 'media_id' || $data['type'] == 'view_limited' ){
				$sub_button['media_id']	= $data['key'];
			}elseif($data['type'] == 'main'){
				$sub_button['key']	= '';
			}else{
				$sub_button['key']	= $data['key'];
			}

			// 要重新排下序，不然无法同步到微信
			$buttons[$position]['sub_button'][$sub_position]	= $sub_button;

		}
		
		if($id){
			$wpdb->update($wpdb->weixin_menus, array('button'=>wpjam_json_encode($buttons)), compact('id'));
		}else{
			if($wpdb->query("SELECT id FROM {$wpdb->weixin_menus} WHERE `type`='menu' AND blog_id={$blog_id}")){
				$wpdb->update($wpdb->weixin_menus, array('button'=>wpjam_json_encode($buttons)), array('type'=>'menu','blog_id'=>$blog_id));
			}else{
				$wpdb->insert($wpdb->weixin_menus, array('button'=>wpjam_json_encode($buttons),'type'=>'menu','blog_id'=>$blog_id));
			}
		}
		
		if($action == 'edit'){
			wpjam_admin_add_error('修改成功');
		}else{
			wpjam_admin_add_error('添加成功');
		}
	}

	// if($action == 'edit'){

		$weixin_menu	= weixin_robot_get_local_menu($id);
		$buttons		= $weixin_menu['button'];
		
		if($sub_position === ''){
			$button			= isset($buttons[$position])?$buttons[$position]:'';
			$button_type	= isset($button['type'])?$button['type']:'';
		}else{
			$button			= isset($buttons[$position]['sub_button'][$sub_position])?$buttons[$position]['sub_button'][$sub_position]:'';
			$button_type	= isset($button['type'])?$button['type']:'click';
		}

		$form_fields['name']['value']	= isset($button['name'])?$button['name']:'';
		$form_fields['type']['value']	= $button_type;

		if($button_type== 'view'){
			$form_fields['url']['value']	= $button['url'];
		}elseif($button_type== 'miniprogram'){
			$form_fields['url']['value']		= $button['url'];
			$form_fields['appid']['value']		= $button['appid'];
			$form_fields['pagepath']['value']	= $button['pagepath'];
		}elseif($button_type == 'media_id' || $form_fields['type']['value'] == 'view_limited' ){
			$form_fields['key']['value']	= $button['media_id'];
		}elseif($button_type){
			$form_fields['key']['value']	= isset($button['key'])?$button['key']:'';
		}

		$form_fields['key']['description']	= ($button_type && isset($key_descriptions[$button_type]))?$key_descriptions[$button_type]:$key_descriptions['click'];
		$form_fields['url']['description']	= ($button_type && isset($url_descriptions[$button_type]))?$url_descriptions[$button_type]:$url_descriptions['view'];
	// }else{
		// $form_fields['key']['description']	= $key_descriptions['click'];
	// }

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add&id='.$id:$current_admin_url.'&action=edit&id='.$id;
	$action_text	= ($action=='edit')?'编辑':'新增';

	?>
	<h2><?php echo $action_text;?>按钮</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>
	
	<script type="text/javascript">
	jQuery(function($){

		$('#tr_appid').hide();
		$('#tr_pagepath').hide();
		$('#tr_url').hide();
		$('#tr_key').hide();
		
		<?php if($button_type == 'miniprogram'){ ?>
		$('#tr_appid').show();
		$('#tr_pagepath').show();
		<?php } ?>
		
		<?php if($button_type == 'view' || $button_type == 'miniprogram'){ ?>
		$('#tr_url').show();
		<?php }elseif($button_type && $button_type != 'main'){ ?>
		$('#tr_key').show();
		<?php } ?>
		
		var key_descriptions	= $.parseJSON('<?php echo wpjam_json_encode($key_descriptions);?>');
		var url_descriptions	= $.parseJSON('<?php echo wpjam_json_encode($url_descriptions);?>');

		$("select#type").change(function(){
			var selected = $("select#type").val();

			if(selected == 'miniprogram' || selected == 'view'){
				$('#tr_url p').html(url_descriptions[selected]);

				$('#tr_url').show();
				$('#tr_key').hide();
			}else if(selected == 'main'){
				$('#tr_key').hide();
				$('#tr_url').hide();
			}else{
				$('#tr_key p').html(key_descriptions[selected]);

				$('#tr_url').hide();
				$('#tr_key').show();
			}

			if(selected == 'miniprogram'){
				$('#tr_appid').show();
				$('#tr_pagepath').show();
			}else{
				$('#tr_appid').hide();
				$('#tr_pagepath').hide();
			}

		});
	});
	</script>
	<?php 
}

// 默认菜单汇总统计
function weixin_robot_menu_tree_stats_page(){

	global $wpdb, $current_admin_url, $weixin_list_table,$wpjam_stats_labels;

	$weixin_menu	= weixin_robot_get_local_menu();
	$menu_type		= $weixin_menu['type'];
	$menuid			= $weixin_menu['menuid'];
	$buttons		= $weixin_menu['button'];

	if(WEIXIN_TYPE >= 3){
		echo '<h2>默认菜单汇总统计</h2>';
	}else{
		echo '<h2>自定义菜单汇总统计</h2>';
	}

	extract($wpjam_stats_labels); 
	wpjam_stats_header(); 

	$menu_stats	= apply_filters('weixin-robot-menu-tree-stats', false);
	
	if(!$menu_stats){
		$where = "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp} AND MsgType = 'event' AND Event in('CLICK', 'VIEW', 'scancode_push', 'scancode_waitmsg', 'pic_sysphoto', 'scancode_waitmsg', 'pic_weixin', 'location_select') AND EventKey !='' ";

		$sql = "SELECT EventKey, count(*) as count FROM {$wpdb->weixin_messages} WHERE 1=1 AND {$where} GROUP BY EventKey";
		$counts = $wpdb->get_results($sql, OBJECT_K);

		$sql = "SELECT count(*) as total FROM {$wpdb->weixin_messages} WHERE 1=1 AND {$where}";
		$total = $wpdb->get_var($sql);
	}else{
		extract($menu_stats);
	}
	
	if(empty($buttons)){
		return;
	}

	$type_list	= weixin_robot_get_menu_type_list();
	
	$weixin_buttons		= array();

	foreach ($buttons as $position => $button ) { 
		$weixin_button	= array();

		if($button){
			$type	= isset($button['type'])?$button['type']:'';

			$weixin_button['name']		= $button['name'];
			$weixin_button['position']	= $position+1;
			$weixin_button['type']		= isset($type_list[$type])?$type_list[$type]:$type;

			if($type){
				if($type == 'view'){
					$weixin_button['key']	= $button['url'];
				}elseif($type == 'miniprogram'){
					$weixin_button['key']	= $button['appid'];
				}elseif($type == 'view_limited' || $type == 'media_id'){
					$weixin_button['key']	= $button['media_id'];
				}else{
					$weixin_button['key']	= $button['key'];
				}

				$weixin_button['count']		= $counts[$weixin_button['key']]->count;
				$weixin_button['percent']	= round($weixin_button['count']/$total*100,2).'%'; 
			}else{
				$weixin_button['key']		= '';
				$weixin_button['count']		= $weixin_button['percent'] = ''; 
			}			

			$weixin_buttons[]	= $weixin_button;

			if(!empty($button['sub_button'])){
				$sub_buttons		= $button['sub_button'];
				foreach ($sub_buttons as $sub_position => $sub_button) { 
					$weixin_button	= array();

					if($sub_button){
						$type	= isset($sub_button['type'])?$sub_button['type']:'';

						$weixin_button['name']		= '└── '.$sub_button['name'];
						$weixin_button['position']	= '└─ '.($sub_position+1);
						$weixin_button['type']		= $type;
						$weixin_button['type']		= isset($type_list[$type])?$type_list[$type]:$type;

						if($type){
							if($type == 'view'){
								$weixin_button['key']	= $sub_button['url'];
							}elseif($type == 'miniprogram'){
								$weixin_button['key']	= $sub_button['appid'];
							}elseif($type == 'view_limited' || $type == 'media_id'){
								$weixin_button['key']	= $sub_button['media_id'];
							}else{
								$weixin_button['key']	= $sub_button['key'];
							}
							$weixin_button['count']		= isset($counts[$weixin_button['key']])?$counts[$weixin_button['key']]->count:0;
							$weixin_button['percent']	= round($weixin_button['count']/$total*100,2).'%'; 
						}else{
							$weixin_button['key']		= '';
							$weixin_button['count']		= $weixin_button['percent'] = ''; 
						}			

						$weixin_buttons[]	= $weixin_button;
					}
				}
			}
		}
	}

	$weixin_list_table->prepare_items($weixin_buttons);
	$weixin_list_table->display();
}

function weixin_robot_menu_fields($fields){
	global $current_tab;
	if($current_tab == 'conditional' ){

		$tag_options = array(''=>'所有');
		if($weixin_user_tags	= weixin_robot_get_tags()){
			
			foreach ($weixin_user_tags as $current_tagid => $weixin_user_tag) {
				$tag_options[$current_tagid] = $weixin_user_tag['name'];
			}
		}

		$fields = array(
			'name'		=> array('title'=>'菜单名称',		'type'=>'text',		'show_admin_column'=>true,	'required'),
			'menuid'	=> array('title'=>'菜单ID',		'type'=>'view',		'show_admin_column'=>true),

			'matchrule'	=> array('title'=>'菜单匹配规则',	'type'=>'fieldset',	'show_admin_column'=>true,	'fields'=>array(
				'tag_id'				=> array('title'=>'用户标签',		'type'=>'select',	'options'=>$tag_options),
				'sex'					=> array('title'=>'性别',		'type'=>'select',	'options'=>array(''=>'所有', 1=>'男', 2=>'女')),
				'client_platform_type'	=> array('title'=>'客户端版本',	'type'=>'select',	'options'=>array(''=>'所有', 1=>'iOS', 2=>'Android', 3=>'其他')),
				'country'				=> array('title'=>'国家',		'type'=>'text',		'style'=>'width:120px;',	'description'=>'例如：China'),
				'province'				=> array('title'=>'省份',		'type'=>'text',		'style'=>'width:120px;',	'description'=>'例如：Guangdong'),
				'city'					=> array('title'=>'城市',		'type'=>'text',		'style'=>'width:120px;',	'description'=>'例如：Guangzhou'),
			),'description'=>'<br />均可为空，但不能全部为空，至少要有一个匹配信息是不为空的。<br />地区信息从大到小验证，小的可以不填，即若填写了省份信息，则国家信息也必填并且匹配，城市信息可以不填。'),
		);

		return $fields;
	}
}
// 微信个性化菜单
function weixin_robot_conditional_menu_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';
	if($action == 'edit' || $action == 'add'){
		weixin_robot_conditional_menu_edit_page();
	}else{
		weixin_robot_conditional_menu_list_page();
	}
}

function weixin_robot_conditional_menu_list_page(){
	global $wpdb, $current_admin_url, $weixin_list_table;

	$action 		= $weixin_list_table->current_action();
	$redirect_to	= wpjam_get_referer();
	$blog_id		= get_current_blog_id();

	if($action == 'duplicate'){
		if( !current_user_can( 'edit_weixin' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

	    if(!empty($_GET['id'])){
			check_admin_referer('duplicate-'.$weixin_list_table->get_singular().'-'.$_GET['id']);
			
			$weixin_menu	= weixin_robot_get_local_menu($_GET['id'],false);
			
			unset($weixin_menu['id']);
			unset($weixin_menu['menuid']);

			$wpdb->insert($wpdb->weixin_menus, $weixin_menu);

			$redirect_to	= add_query_arg( array( 'duplicated' => 'true' ), $redirect_to );

			wp_redirect($redirect_to);
		}

	}elseif($action == 'delete'){

	    if( !current_user_can( 'delete_weixin' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

		if(!empty($_GET['id'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['id']);

			$weixin_menu = weixin_robot_get_local_menu($_GET['id']);

			$wpdb->delete($wpdb->weixin_menus, array('id'=>$_GET['id']));

			if($weixin_menu['menuid']){
				$response = weixin_robot_del_conditional_menu($weixin_menu['menuid']);

				if(is_wp_error($response)){
					$redirect_to = add_query_arg( array( 'deleted' => $response->get_error_message() ), $redirect_to );
				}else{
					$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
				}
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}

			wp_redirect($redirect_to);
		}
	}
	?>
	
	<h2>个性化菜单 <a title="新增个性化菜单" class="thickbox add-new-h2" href="<?php echo $current_admin_url.'&action=add'.'&TB_iframe=true&width=780&height=500'; ?>">新增</a></h2>

	<?php

	$where		= "`type`='conditional' AND blog_id={$blog_id}";

	$limit			= $weixin_list_table->get_limit();
	$weixin_menus	= $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_menus} WHERE {$where} ORDER BY id DESC LIMIT {$limit};", ARRAY_A);
	$total			= $wpdb->get_var("SELECT FOUND_ROWS();");

	$weixin_list_table->prepare_items($weixin_menus, $total);
	$weixin_list_table->display(array('search'=>false));
}

function weixin_robot_conditional_menu_item($item){
	global $current_admin_url, $plugin_page, $weixin_list_table;

	$form_fields	= wpjam_get_form_fields();
	$matchrule		= json_decode($item['matchrule']);

	$item['matchrule'] = '';
	if($matchrule){
		foreach ($matchrule as $key => $value) {
			if($value){
				$field = ($form_fields['matchrule']['fields'][$key])?$form_fields['matchrule']['fields'][$key]:'';
				if($field){
					if($field['type'] == 'select'){
						$item['matchrule']	.= $field['title'].'：'.$field['options'][$value].'<br />';
					}else{
						$item['matchrule']	.= $field['title'].'：'.$value.'<br />';
					}
				}
			}
		}
	}

	$item['menuid'] = $item['menuid']?:'';

	$set_title = ($item['menuid'])?'查看按钮':'设置按钮';

	$item['row_actions'] = array(
		'edit'		=> '<a href="'.$current_admin_url.'&action=edit&id='.$item['id'].'&TB_iframe=true&width=780&height=540'.'" title="编辑个性化菜单" class="thickbox">编辑</a>',
		'set'		=> '<a href="'.admin_url('admin.php?page='.$plugin_page).'&tab=buttons&id='.$item['id'].'" title="'.$set_title.'">'.$set_title.'</a>',
		'duplicate'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=duplicate&id='.$item['id'], 'duplicate-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">复制</a>',
		'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['id'], 'delete-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">删除</a>',
	);

	// if($item['menuid']){	// 已经更新到微信，就不能再修改了
	// 	unset($item['row_actions']['edit']);
	// 	unset($item['row_actions']['button']);
	// }

	return $item;
}

function weixin_robot_conditional_menu_edit_page(){
	global $plugin_page, $wpdb, $current_admin_url;

	$id				= isset($_GET['id'])?$_GET['id']:'';
	$action			= isset($_GET['action'])?$_GET['action']:'';
	$blog_id		= get_current_blog_id();
	$nonce_action	= $id ? 'edit-weixin-menu-'.$id : 'add-weixin-menu';
	$form_fields	= wpjam_get_form_fields();

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');
		$name = $data['name'];
		unset($data['name']);

		$matchrule	= wpjam_json_encode($data);
		
		$type	= 'conditional';

		$data	= compact('name', 'matchrule', 'type');

		if($id){
			$weixin_menu = weixin_robot_get_local_menu($id);
			if($weixin_menu['menuid']){
				unset($data['matchrule']);
			}
			if($wpdb->update($wpdb->weixin_menus,$data,array('id'=>$id))){
				wpjam_admin_add_error('修改成功');
			}else{
				wpjam_admin_add_error('未修改');
			}
		}else{
			$data['blog_id']	= $blog_id;
			if($wpdb->insert($wpdb->weixin_menus,$data)){
				wpjam_admin_add_error('添加成功');
			}else{
				wpjam_admin_add_error('添加失败：'.$wpdb->last_error,'error');
			}
		}
	}

	if($id && ($weixin_menu = weixin_robot_get_local_menu($id))){
		$menuid 	= $weixin_menu['menuid'];
		$matchrule	= $weixin_menu['matchrule'];

		$form_fields['name']['value']	= $weixin_menu['name'];

		$matchrule_fields	= $form_fields['matchrule']['fields'];
		foreach ($matchrule as $key => $value) {
			$matchrule_fields[$key]['value']	= $value;
		}

		if($menuid){
			$form_fields['menuid']['value']	= $menuid;
			foreach ($matchrule_fields as $key => $value) {
				$matchrule_fields[$key]['type'] = 'view';
			}	
		}else{
			unset($form_fields['menuid']);
		}

		$form_fields['matchrule']['fields'] = $matchrule_fields;
	}else{
		unset($form_fields['menuid']);
	}

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&id='.$id;
	$action_text	= $id?'编辑':'新增';
	?>

	<h2><?php echo $action_text;?>个性化菜单</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>

	<?php
}

function weixin_robot_get_menu_counts($start_timestamp, $end_timestamp){
	global $wpdb;
	$where 	= "CreateTime > {$start_timestamp} AND CreateTime < {$end_timestamp}";
	$sql 	= "SELECT EventKey as label, count(*) as count FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType = 'event' AND Event in('CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin') AND EventKey !='' GROUP BY EventKey ORDER BY count DESC;";
	
	$counts = $wpdb->get_results($sql,OBJECT_K);

	$sql	= "SELECT count(*) as total FROM {$wpdb->weixin_messages} WHERE {$where} AND MsgType = 'event' AND Event in('CLICK','VIEW','scancode_push','scancode_waitmsg','location_select','pic_sysphoto','pic_photo_or_album','pic_weixin') AND EventKey !='';";
	$total = $wpdb->get_var($sql);

	$new_counts = array('total'=>$total);

	foreach ($counts as $key => $count) {
		$new_counts[$key]	= $count->count;
	}

	return $new_counts;
}

// 获取自定义菜单类型
function weixin_robot_get_menu_type_list(){
	return array(
		'main'				=> '主菜单（含有子菜单）', 
		'click'				=> '点击推事件', 
		'view'				=> '跳转URL',
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
}

function weixin_robot_get_local_menu($id='',$decode=true){
	global $wpdb;

	$blog_id	= get_current_blog_id();

	if($id){
		$sql = "SELECT * FROM {$wpdb->weixin_menus} WHERE id={$id} AND blog_id={$blog_id}";
	}else{
		$sql = "SELECT * FROM {$wpdb->weixin_menus} WHERE `type`='menu' AND blog_id={$blog_id}";
	}

	$weixin_menu = $wpdb->get_row($sql, ARRAY_A);

	if($weixin_menu && $decode){
		$weixin_menu['button']		= ($weixin_menu['button'])?json_decode($weixin_menu['button'],true):array();
		$weixin_menu['matchrule']	= ($weixin_menu['matchrule'])?json_decode($weixin_menu['matchrule'],true):array();
	}

	return $weixin_menu;
}

// 获取菜单
function weixin_robot_get_menu($show_message=true){
	global $wpdb;

	$blog_id	= get_current_blog_id();
	$url		= 'https://api.weixin.qq.com/cgi-bin/menu/get';

	$response = weixin_robot_remote_request($url);

	if(is_wp_error($response)){
		if($show_message){
			wpjam_admin_add_error($response->get_error_code().'：'. $response->get_error_message(), 'error');	
		}
		return ;
	}else{
		if($show_message){
			wpjam_admin_add_error('成功获取微信自定义菜单！');
		}
	}

	if(isset($response['menu']['button'])){
		$type	= 'menu';
		$button = wpjam_json_encode($response['menu']['button']);
		$menuid	= isset($response['menu']['menuid'])?$response['menu']['menuid']:0;

		if($wpdb->query("SELECT id FROM {$wpdb->weixin_menus} WHERE `type`='menu' AND blog_id={$blog_id}")){
			$wpdb->update($wpdb->weixin_menus, compact('button','menuid'), compact('type','blog_id'));
		}else{
			$wpdb->insert($wpdb->weixin_menus, compact('button','menuid','blog_id','type'));
		}
	}

	if(isset($response['conditionalmenu'])){
		$type = 'conditional';
		foreach ($response['conditionalmenu'] as $conditionalmenu) {
			$button 	= wpjam_json_encode($conditionalmenu['button']);
			$matchrule	= wpjam_json_encode($conditionalmenu['matchrule']);
			$menuid		= $conditionalmenu['menuid'];

			if($wpdb->query("SELECT id FROM {$wpdb->weixin_menus} WHERE menuid={$menuid} AND blog_id={$blog_id}")){
				$wpdb->update($wpdb->weixin_menus, compact('button','type','matchrule'), compact('menuid','blog_id'));
			}else{
				$wpdb->insert($wpdb->weixin_menus, compact('button','menuid','type','blog_id','matchrule'));
			}
		}
	}
}

// 删除菜单以及所有个性化菜单，尽量不要使用
function weixin_robot_delete_menu(){
	$url	= 'https://api.weixin.qq.com/cgi-bin/menu/delete';
	return weixin_robot_remote_request($url);
}

// 创建菜单
function weixin_robot_create_menu($button){
	$url	= 'https://api.weixin.qq.com/cgi-bin/menu/create';
	$data	= wpjam_json_encode(array('button'=>$button));
	return weixin_robot_remote_request($url, 'post', $data);
}

// 创建个性化菜单
function weixin_robot_add_conditional_menu($button, $match_rule){
	$url	= 'https://api.weixin.qq.com/cgi-bin/menu/addconditional';
	$data	= wpjam_json_encode(array('button'=>$button, 'matchrule'=>$match_rule));
	return weixin_robot_remote_request($url, 'post', $data);
}

// 删除个性化菜单
function weixin_robot_del_conditional_menu($menuid){
	$url	= 'https://api.weixin.qq.com/cgi-bin/menu/delconditional';
	$data	= json_encode(array('menuid'=>$menuid));
	return weixin_robot_remote_request($url, 'post', $data);
}

// 测试个性化菜单匹配结果
function weixin_robot_try_match_menu($user_id){
	$url	= 'https://api.weixin.qq.com/cgi-bin/menu/trymatch';
	$data	= wpjam_json_encode(array('user_id'=>$user_id));
	return weixin_robot_remote_request($url, 'post', $data);
}