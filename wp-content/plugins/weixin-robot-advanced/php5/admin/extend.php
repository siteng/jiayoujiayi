<?php
add_filter('weixin_extend_tabs', 'weixin_robot_extend_tabs', 1);
function weixin_robot_extend_tabs($tabs){
	$tabs = array(
		'extends'	=> array('title'=>'扩展管理',		'function'=>'weixin_robot_extends_page'),
//		'token'		=> array('title'=>'接口权限',		'function'=>'weixin_robot_access_token_page'),
//		'campaign'	=> array('title'=>'第三方授权',	'function'=>'weixin_robot_campaigns_page'),
		'short_url'	=> array('title'=>'链接缩短',		'function'=>'weixin_robot_short_url_page'),
		'ip_list'	=> array('title'=>'微信IP列表',	'function'=>'weixin_robot_ip_list_page'),
		'clear'		=> array('title'=>'数据清理',		'function'=>'weixin_robot_clear_page'),
	);

	if(WEIXIN_TYPE < 4){
		unset($tabs['short_url']);
		unset($tabs['campaign']);
	}
	return $tabs;
}

function weixin_robot_extends_page(){
	wpjam_option_page('weixin-robot-extends', array('page_title'=>'<h2>扩展管理</h2>'));
}

function weixin_robot_campaigns_page(){
	wpjam_option_page('weixin-robot-campaigns', array('page_title'=>'<h2>第三方 OAuth 2.0 授权</h2>'));
}

function weixin_robot_get_extends_option_sections(){

	$extend_fields		= array();
	$weixin_extend_dir	= WEIXIN_ROBOT_PLUGIN_DIR.'/extends';
	
	if (is_dir($weixin_extend_dir)) { // 已激活的优先
		if($weixin_extends = get_option('weixin-robot-extends')){
			foreach ($weixin_extends as $weixin_extend_file => $value) {
				if($value){
					if(is_file($weixin_extend_dir.'/'.$weixin_extend_file) && $data = get_plugin_data($weixin_extend_dir.'/'.$weixin_extend_file)){
						$extend_fields[$weixin_extend_file] = array('title'=>$data['Name'],	'type'=>'checkbox',	'description'=>$data['Description']);
					}
				}
			}
		}

		if ($weixin_extend_handle = opendir($weixin_extend_dir)) {   
			while (($weixin_extend_file = readdir($weixin_extend_handle)) !== false) {
				if ($weixin_extend_file!="." && $weixin_extend_file!=".." && is_file($weixin_extend_dir.'/'.$weixin_extend_file) && empty($weixin_extends[$weixin_extend_file])) {
					if(pathinfo($weixin_extend_file, PATHINFO_EXTENSION) == 'php'){
						if(($data = get_plugin_data($weixin_extend_dir.'/'.$weixin_extend_file)) && $data['Name']){
							$extend_fields[$weixin_extend_file] = array('title'=>$data['Name'],	'type'=>'checkbox',	'description'=>$data['Description']);
						}
					}
				}
			}   
			closedir($weixin_extend_handle);   
		}   
	}

	return  array( 'extends' => array('title'=>'', 'fields'=>$extend_fields));
}

function weixin_robot_get_campaigns_option_sections(){
	return array(
		'campaigns' => array(
			'title'		=> '', 
			'summary'	=> '为了服务号的 OAuth 2.0 的第三方认证接口不会被滥用，请输入第三方活动的域名，无需 http://',
			'fields'	=> array(
				'hosts'	=> array('title'=> '第三方活动域名', 'type'=> 'mu-text')
			)
		)
	);
}

add_action('weixin-robot-extends_field_validate', 'weixin_robot_extends_field_validate');
function weixin_robot_extends_field_validate($weixin_robot_extends){
	weixin_robot_delete_transient_cache(false);
	return $weixin_robot_extends;
}

add_action('weixin-robot-extends_option_page', 'weixin_robot_extends_option_page');
function weixin_robot_extends_option_page(){
	if(!empty($_GET['settings-updated'])){
		do_action('weixin_extends_updated');	
	}
}

function weixin_robot_ip_list_page(){
	echo "<h2>微信服务器的IP列表</h2>";
	echo "<p>";
	$ip_list = weixin_robot_get_callback_ip();
	if(is_wp_error($ip_list)){
		echo $ip_list->get_error_message();
	}else{
		echo implode('<br />', $ip_list);
	}
	echo '</p>';
}

function weixin_robot_short_url_page(){
	global $current_admin_url;

	echo '<h2>长链接转短链接</h2>';

	$form_fields = array(
		'long_url'		=> array('title'=>'', 'type'=>'textarea', 'style'=>'max-width:640px;', 'value'=>'', 'description'=>'请输入需要转换的长链接，支持http://、https://、weixin://wxpay 格式的url'),
	);

	$nonce_action	= 'long2short';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data		= wpjam_get_form_post($form_fields, $nonce_action);
		$long_url	= $data['long_url'];
		
		$form_fields['long_url']['value'] = $long_url;

		$short_url	= weixin_robot_get_short_url($long_url);
		if(is_wp_error($short_url)){
			wpjam_admin_add_error($short_url->get_error_message(),'error');
		}else{
			wpjam_admin_add_error('短链接为： '.$short_url);
		}
	}

	wpjam_form($form_fields, $current_admin_url, $nonce_action, '缩短'); 

}

function weixin_robot_get_tables(){
	$weixin_tables = array(
		'weixin_robot_activation' => array(
			'weixin_custom_replies'			=> '微信自定义回复',
			'weixin_users'					=> '微信用户',
			'weixin_user_tags'				=> '微信用户标签',
			'weixin_user_tag_relationships'	=> '微信用户标签关系',
			'weixin_crm_users'				=> '微信CRM用户',
			'weixin_messages'				=> '微信消息',
			'weixin_tokens'					=> '微信带参数二维码',
			'wp_weixin_menus'				=> '微信自定义菜单'
		)
	);

	if(WEIXIN_TYPE < 4){
		unset($weixin_tables['weixin_robot_activation']['weixin_tokens']);
	}

	if(!isset($_GET['crm'])){
		unset($weixin_tables['weixin_robot_activation']['weixin_crm_users']);	
	}
	
	return apply_filters('weixin_tables',$weixin_tables);
}

function weixin_robot_clear_page() {
	global $current_admin_url;
	$action	= isset($_GET['action'])?$_GET['action']:'';
	?>
	<h2>数据检测和清理</h2>
	<p>
		微信机器人 WordPress 插件高级版已经尽量做好了自动创建数据库和缓存的自动更新，但是还是会不可避免出现一些不可知的问题和异常。<br />
	</p>
	
	<p><a href="<?php echo $current_admin_url.'&action=table'; ?>" class="button button-primary">检查数据表</a></p>

	<?php if($action == 'table'){

		weixin_robot_update_table();

		?><ol>
		<?php foreach (weixin_robot_get_tables() as $function => $weixin_tables) {
			call_user_func($function);
			foreach ($weixin_tables as $weixin_table_name => $weixin_table_title) {
				echo '<li><strong>'.$weixin_table_title.'</strong>表已经创建</li>';	
			}	
		}

		// global $wpdb;
		// if(get_option('weixin-robot') == false && get_option('weixin-robot-basic')){
		// 	update_option( 'weixin-robot', get_option('weixin-robot-basic') );
		// 	echo "微信设置已经转移";
		// }

		// weixin_robot_insert_users_subscribe_tags();

		//$sql = "ALTER TABLE  ".$wpdb->weixin_custom_replies." DROP INDEX keyword";
		//$wpdb->query($sql);
		?>
	</ol><?php } ?>


	<p><a href="<?php echo $current_admin_url.'&action=cache' ?>" class="button button-primary">删除缓存</a></p>

	<?php if($action == 'cache'){  ?><ol>
		<?php weixin_robot_delete_transient_cache(); ?>
	</ol><?php } ?>

	<p><a href="<?php echo $current_admin_url.'&action=clear_quota' ?>" class="button button-primary">API调用次数清零</a></p>

	<?php if($action == 'clear_quota'){ ?><p>
		<?php 
		$response = weixin_robot_clear_quota();
		if(is_wp_error($response)){
			echo $response->get_error_message();
		} else{
			echo 'API调用次数已经清零';
		} 
		?>
	</p><?php } ?>

	<?php
}

function weixin_robot_delete_custom_repies_transient_cache(){
	foreach(array('weixin_custom_keywords_all','weixin_custom_keywords_full','weixin_custom_keywords_prefix','weixin_custom_keywords_fuzzy','weixin_builtin_replies','weixin_builtin_replies_new') as $cache_key){
		delete_transient($cache_key);
	}
}

function weixin_robot_delete_transient_cache($echo = true){
	$weixin_transient_caches = array(
		'自定义回复'			=> array('weixin_custom_keywords_full','weixin_custom_keywords_prefix','weixin_custom_keywords_fuzzy'),
		'内置回复'			=> array('weixin_builtin_replies','weixin_builtin_replies_new'),
		'微信 Access Token'	=> array('weixin_access_token')
	);

	$weixin_transient_caches = apply_filters('weixin_transient_caches',$weixin_transient_caches);
	foreach ($weixin_transient_caches as $name => $cache_keys) {
		foreach ($cache_keys as $cache_key) {
			delete_transient($cache_key);
		}
		if($echo){
			echo '<li><strong>'.$name.'缓存</strong>已经清除</li>';
		}
	}
}


add_filter('weixin-robot-extend_fields', 'weixin_robot_access_token_fields',99);
function weixin_robot_access_token_fields($fields){
	global $current_tab;
	if($current_tab == 'token'){
		return array(
			'token'		=> array('title'=>'Access Token',	'type'=>'text',		'show_admin_column'=>true),
			'date'		=> array('title'=>'过期时间',			'type'=>'date',		'show_admin_column'=>true,	'sortable_columns'=>'meta_value_num',	'description'=>'请根据第三方开发商的需求设置有效期，留空为永久。'),
			'remark'	=> array('title'=>'备注',			'type'=>'textarea',	'show_admin_column'=>true,	'description'=>'请输入该 Token 的用途或者其他备注！')
		); 
	}
}

function weixin_robot_extend_page_load(){
	global $weixin_list_table, $plugin_page, $current_tab;

	if($current_tab == 'token'){
		$style = '
		th.column-token{width:270px;}
		.tablenav{display:none;}
		';

		$list_table_args = array(
			'plural'			=> 'weixin-tokens',
			'singular' 			=> 'weixin-token',
			'actions_column'	=> 'token',
			'style'				=> $style,
		);

		$weixin_list_table = wpjam_list_table($list_table_args);
	}
}

function weixin_robot_access_token_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';

	if($action == 'edit' || $action == 'add' ){
		weixin_robot_access_token_edit_page();
	}else{
		weixin_robot_access_token_list_page();
	}
}

function weixin_robot_access_token_list_page(){
	global $current_admin_url, $wpdb, $weixin_list_table;

	$action	= $weixin_list_table->current_action();
	$tokens	= get_option('weixin_api_access_tokens');

	if($action == 'delete'){
		if( !current_user_can( 'manage_options' )){
			ob_clean();
			wp_die('无权限');
		}

		if(!empty($_GET['token'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['token']);

			$redirect_to	= wpjam_get_referer();

			if(isset($tokens[$_GET['token']])){
				unset($tokens[$_GET['token']]);
				update_option( 'weixin_api_access_tokens', $tokens );
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}
			
			wp_redirect($redirect_to);
		}
	}
	?>

	<h2>接口权限<a title="新增 Token" class="thickbox add-new-h2" href="<?php echo $current_admin_url.'&action=add&TB_iframe=true&width=780&height=420'; ?>">新增</a></h2>
	
	<?php

	$today		= date('Y-m-d', current_time('timestamp'));
	$new_tokens	= array();

	if($tokens){
		foreach ($tokens as $token => $value) {
			$date	= $value['date'];
			if($date){
				if($date < $today ){
					$date = '<span style="color:red;">已经过期<br />'.$date.'</span>';
				}else{
					$date = '<span style="">未过期<br />'.$date.'</span>';
				}
			}else{
				$date = '<span style="color:green;">永久</span>';
			}

			$remark	= $value['remark'];
			$new_tokens[] = array(
				'token'		=> $token,
				'date'		=> $date,
				'remark'	=> $remark,
				'row_actions'	=> array(
					'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&token='.$token).'&TB_iframe=true&width=780&height=420" class="thickbox">编辑</a>',
					'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&token='.$token, 'delete-'.$weixin_list_table->get_singular().'-'.$token)).'">删除</a>',
				)
			);
		}
	}

	$weixin_list_table->prepare_items($new_tokens, count($new_tokens));
	$weixin_list_table->display(array('search'=>false));
}

function weixin_robot_access_token_edit_page(){
	global $wpdb, $current_admin_url;
	$token	= isset($_GET['token'])?$_GET['token']:'';
	$action	= isset($_GET['action'])?$_GET['action']:'';

	$nonce_action	= $token ? 'edit-token-'.$token : 'add-token';
	$form_fields	= wpjam_get_form_fields();

	$tokens	= get_option('weixin_api_access_tokens');
	$tokens	= ($tokens)?$tokens:array();

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data	= wpjam_get_form_post($form_fields, $nonce_action);
		$date	= $data['date'];
		$remark	= $data['remark'];

		if($token){
			$tokens[$token]	= compact('date', 'remark');
			wpjam_admin_add_error('修改成功');
		}else{
			$tokens[$data['token']]	= compact('date', 'remark');
			wpjam_admin_add_error('添加成功');
		}
		update_option( 'weixin_api_access_tokens', $tokens );
	}
	
	if($token){
		$form_fields['token']['value']	= $token;
		$form_fields['token']['type']	= 'view';
		$form_fields['date']['value']	= $tokens[$token]['date'];
		$form_fields['remark']['value']	= $tokens[$token]['remark'];
	}else{
		$form_fields['token']['value']	= wp_generate_password(32, false, false);
	}

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&token='.$token;
	$action_text	= $token?'编辑':'新增';

	?>
	<h2><?php echo $action_text;?> Token</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); 
}

// function weixin_robot_insert_users_subscribe_tags(){
// 	if(weixin_robot_get_setting('weixin_token2tag')){
// 		global $wpdb;
// 		$weixin_token_sunscribes = $wpdb->get_results("SELECT wmt.FromUserName,wmt.EventKey, wqt.scene, wqt.name FROM {$wpdb->weixin_messages} wmt INNER JOIN {$wpdb->weixin_tokens} wqt ON CONCAT('qrscene_',wqt.scene) =wmt.EventKey WHERE wmt.Event = 'subscribe' AND wmt.EventKey != '' GROUP BY wmt.FromUserName");

// 		foreach($weixin_token_sunscribes as $weixin_token_sunscribe){
// 			weixin_robot_insert_user_tag($weixin_token_sunscribe->FromUserName, $weixin_token_sunscribe->name);
// 		}
// 	}
// }

