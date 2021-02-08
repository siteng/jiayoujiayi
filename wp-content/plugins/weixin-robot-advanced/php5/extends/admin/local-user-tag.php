<?php
function weixin_robot_user_tags_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';

	if($action == 'edit' || $action == 'add' ){
		weixin_robot_user_tag_edit_page();
	}else{
		weixin_robot_user_tag_list_page();
	}
}

function weixin_robot_user_tag_list_page(){
	global $wpdb, $current_admin_url, $weixin_list_table;
	?>
	<h2>用户标签管理<a title="新增标签" class="add-new-h2 thickbox" href="<?php echo $current_admin_url.'&action=add&TB_iframe=true&width=780&height=200'; ?>">新增</a></h2>	
	<?php

	$action = $weixin_list_table->current_action();

	if($action == 'delete'){
		if( !current_user_can( 'delete_weixin' )){
			ob_clean();
			wp_die('无权限');
		}

		$redirect_to = wpjam_get_referer();

		if(!empty($_GET['id'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['id']);

			$delete = weixin_robot_delete_tag(array('id'=>$_GET['id']));

			if(is_wp_error($delete)){
				$redirect_to = add_query_arg( array( 'deleted' => $delete->get_error_message() ), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}

			wp_redirect($redirect_to);

		}elseif (isset($_GET['ids'])) {
			check_admin_referer('bulk-'.$weixin_list_table->get_plural());

			$error = false;
			foreach ($_GET['ids'] as $id) {
				$response = weixin_robot_delete_tag(array('id'=>$id));
				if(is_wp_error($response)){
					$error = true;
					wpjam_admin_add_error($id.'：'.$response->get_error_code().'-'.$response->get_error_message(),'error');
				}
			}

			if($error == false){
				wpjam_admin_add_error('删除成功');
			}
		}
	}

	$search_term	= isset($_GET['s']) ? $_GET['s'] : '';

	$orderby		= isset($_GET['orderby'])?$_GET['orderby']:'';
	$order 			= isset($_GET['order'])?$_GET['order']:'desc';

	$where			= "1=1";

	if($search_term){
		$where		.= " AND name like '%{$search_term}%'";
	}

	$sql_orderby	= 'id DESC';
	if($orderby){
		$sql_orderby	= "{$orderby}+0 {$order}";
	}

	$limit				= $weixin_list_table->get_limit();
	$weixin_user_tags	= $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_user_tags} WHERE {$where} ORDER BY {$sql_orderby} LIMIT {$limit};", ARRAY_A);
	$total				= $wpdb->get_var("SELECT FOUND_ROWS();");

	$weixin_list_table->prepare_items($weixin_user_tags, $total);
	$weixin_list_table->display();
}

function weixin_robot_user_tag_item($item){
	global $current_admin_url, $weixin_list_table;

	$item['count']			= '<a href="'.admin_url('admin.php?page=weixin-robot-users&tab=list&tag_id='.$item['id']).'">'.$item['count'].'</a>';
	$item['row_actions']	= array(
		'edit'		=> '<a href="'.$current_admin_url.'&action=edit&id='.$item['id'].'&TB_iframe=true&width=780&height=200'.'" title="编辑标签" class="thickbox" >编辑</a>',
		'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['id'],'delete-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">删除</a>'
	);
	return $item;		
}

function weixin_robot_user_tag_edit_page(){
	global $wpdb, $current_admin_url;

	$action	= isset($_GET['action'])?$_GET['action']:'';
	$id		= isset($_GET['id'])?$_GET['id']:'';

	$nonce_action	= $id ? 'edit-user-tag-'.$id : 'add-user-tag';

	$form_fields = array(
		'name'		=> array('title'=>'名称',	'type'=>'text')
	); 

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');
		$name = $data['name'];

		if($id){
			$response = 	weixin_robot_update_tag($id, $name);

			if(is_wp_error($response)){
				wpjam_admin_add_error('修改失败：'.$response->get_error_message(),'error');
			}else{
				wpjam_admin_add_error('修改成功');
			}
		}else{
			$response = weixin_robot_create_tag($name);

			if(is_wp_error( $response )){
				wpjam_admin_add_error('添加失败：'.$response->get_error_message(),'error');
			}else{
				wpjam_admin_add_error('添加成功');
			}
		}
	}
	
	if($id){
		$weixin_user_tag = weixin_robot_get_tag(array('id'=>$id));
		if(!is_wp_error($weixin_user_tag)){
			$form_fields['name']['value']	= $weixin_user_tag['name'];
		}
	}

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&id='.$id;
	$action_text	= $id?'修改':'新增';

	?>
	<h2><?php echo $action_text;?>用户标签</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>
<?php
}

function weixin_robot_user_tag_import_page(){
	global $current_admin_url;
	
	$nonce_action = 'weixin_tag_import';

	$form_fields = array(
		'openids'	=> array('title'=>'Openid列表',	'type'=>'textarea',	'description'=>'每个一行！！！'),
		'tags'		=> array('title'=>'标签',	 	'type'=>'mu-text',	'description'=>''),
	);

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');

		$openids	= explode("\n", str_replace("\n\t", "\n", $data['openids']));
		$tags		= $data['tags'];

		if($openids && $tags){
			foreach ($tags as $tag) {
				foreach ($openids as $openid) {
					weixin_robot_insert_user_tag($openid, $tag, array('update_count'=>false));
				}
				weixin_robot_update_tag_count($tag);
			}
		}
		wpjam_admin_add_error('添加成功');
	}

	echo '<h2>批量标签</h2>';

	wpjam_form($form_fields, $current_admin_url, $nonce_action, '批量添加'); 
}

function weixin_robot_user_tag_merge_page(){
	global $current_admin_url, $wpdb;
	
	$nonce_action = 'weixin_tag_merge';

	$tag_lists	= $wpdb->get_col("SELECT name FROM {$wpdb->weixin_user_tags} ORDER BY count DESC;");

	$form_fields = array(
		'tag_before'	=> array('title'=>'要合并的标签',	'type'=>'text',	'options'=>$tag_lists,	'list'=>'tag_list',),
		'tag_after'		=> array('title'=>'合并到的标签',	'type'=>'text',	'options'=>$tag_lists,	'list'=>'tag_list',),
	);

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');

		$tag_before	= trim($data['tag_before']);
		$tag_after	= trim($data['tag_after']);

		$form_fields['tag_before']['value'] = $tag_before;
		$form_fields['tag_after']['value'] 	= $tag_after;

		if($tag_before && $tag_after && in_array($tag_before, $tag_lists) && in_array($tag_after, $tag_lists)){
			if($weixin_openids = weixin_robot_get_tag_users($tag_before)){
				foreach ($weixin_openids as $weixin_openid) {
					weixin_robot_insert_user_tag($weixin_openid, $tag_after, array('update_count'=>false));
				}
			}
			weixin_robot_update_tag_count($tag_before);
			weixin_robot_update_tag_count($tag_after);
			wpjam_admin_add_error('合并成功');
		}
	}

	echo '<h2>合并标签</h2>';

	wpjam_form($form_fields, $current_admin_url, $nonce_action, '合并'); 
}