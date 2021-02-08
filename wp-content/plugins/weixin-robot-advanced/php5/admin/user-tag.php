<?php
// 用户标签管理
function weixin_robot_user_tags_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';

	if($action == 'edit' || $action == 'add' ){
		weixin_robot_user_tag_edit_page();
	}else{
		weixin_robot_user_tag_list_page();
	}
}

function weixin_robot_user_tag_list_page(){
	global $current_admin_url, $weixin_list_table;
	?>
	
	<h2>用户标签管理 <a title="新增标签" class="page-title-action thickbox" href="<?php echo $current_admin_url.'&action=add&TB_iframe=true&width=780&height=200'; ?>">新增</a></h2>

	<?php 

	$action	= $weixin_list_table->current_action();

	if($action == 'delete'){
		if( !current_user_can( 'delete_weixin' )){
			ob_clean();
			wp_die('无权限');
		}

		$redirect_to = wpjam_get_referer();

		if(!empty($_GET['id'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['id']);

			$delete = weixin_robot_delete_tag($_GET['id']);
			
			if(is_wp_error($delete)){
				$redirect_to = add_query_arg( array( 'deleted' => $delete->get_error_message() ), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}

			wp_redirect($redirect_to);
		}
	}

	$action		= isset($_GET['action'])?$_GET['action']:'';
	$orderby	= isset($_GET['orderby'])?$_GET['orderby']:'';
	$order 		= isset($_GET['order'])?$_GET['order']:'desc';

	$weixin_user_tags = weixin_robot_get_tags();

	if(is_wp_error($weixin_user_tags)){
		wpjam_admin_add_error($weixin_user_tags->get_error_code().'：'. $weixin_user_tags->get_error_message(),'error');
	}

	if($orderby){
		$order = ($order == 'desc')? 'DESC': 'ASC';
		$weixin_user_tags = wp_list_sort($weixin_user_tags, $orderby, $order);
	}

	
	if(!is_wp_error($weixin_user_tags)){
		$weixin_list_table->prepare_items($weixin_user_tags);
		$weixin_list_table->display();
	}
}

function weixin_robot_user_tag_item($item){
	global $current_admin_url,$weixin_list_table;

	$item['count']	= '<a href="'.admin_url('admin.php?page=weixin-robot-users&tab=list&tagid='.$item['id']).'">'.$item['count'].'</a>';
	
	if($item['id'] > 99){
		$item['row_actions'] = array(
			'edit'	=> '<a href="'.$current_admin_url.'&action=edit&id='.$item['id'].'&TB_iframe=true&width=780&height=200'.'" title="编辑标签" class="thickbox">编辑</a>',
			'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['id'],'delete-'.$weixin_list_table->get_singular().'-'.$item['id'])).'" title="删除标签">删除</a>'
		);
	}else{
		$item['row_actions'] = array();
	}

	return $item;
}

function weixin_robot_user_tag_edit_page(){
	global $plugin_page, $current_admin_url;

	$action = isset($_GET['action'])?$_GET['action']:'';
	$id 	= isset($_GET['id'])?$_GET['id']:'';

	$nonce_action	= $id ? 'edit-user-tag-'.$id : 'add-user-tag';

	$form_fields = array(
		'name'	=> array('title'=>'标签名字',	'type'=>'text',	'description'=>'30个字符以内')
	);

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');
		$name = $data['name'];

		if($id){
			$response = weixin_robot_update_tag($id, $name);

			if(is_wp_error($response)){
				wpjam_admin_add_error('修改失败：'.$response->get_error_code().'：'. $response->get_error_message() ,'error');
			}else{
				wpjam_admin_add_error('修改成功');
			}
		}else{
			$response = weixin_robot_create_tag($name);

			if(is_wp_error( $response )){
				wpjam_admin_add_error('添加失败：'.$response->get_error_code().'：'. $response->get_error_message(),'error');
			}else{
				wpjam_admin_add_error('添加成功');
			}
		}
	}

	if($id && $weixin_user_tags = weixin_robot_get_tags()){
		$weixin_user_tag	= $weixin_user_tags[$id];
		$form_fields['name']['value']	= $weixin_user_tag['name'];
	}

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&id='.$id;
	$action_text	= $id?'修改':'新增';
	?>

	<h2><?php echo $action_text;?>用户标签</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>

	<?php 
}