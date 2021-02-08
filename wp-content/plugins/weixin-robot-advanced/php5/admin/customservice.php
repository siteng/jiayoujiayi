<?php

function weixin_robot_customservice_fields($fields){
	return array(
		'kf_account'	=> array('title'=>'客服账号',		'type'=>'text',		'description'=>'完整客服账号，格式为：账号前缀@公众号微信号'),
		'nickname'		=> array('title'=>'客服昵称',		'type'=>'text',		'description'=>'客服昵称，最长6个汉字或12个英文字符'),
		'headimg'		=> array('title'=>'客服头像',		'type'=>'image'),
		'kf_wx'			=> array('title'=>'绑定微信号',	'type'=>'text'),
	);
}

function weixin_robot_customservice_page_load(){
	global $weixin_list_table;

	$action	= isset($_GET['action'])?$_GET['action']:'';
	if($action == 'headimg'){
		return;
	}

	$columns		= array(
		'kf_account'	=> '账号',
		'kf_id'			=> 'ID',
		'nickname'		=> '昵称',
		'kf_wx'			=> '微信号',
		'kf_head'		=> '头像',
		'online_status'	=> '状态',
		// 'auto_accept'	=> '最大自动接入数',
		'accepted_case'	=> '正在接待会话数',
	);

	$style = '
	th.column-kf_id {width:70px;}
	th.column-kf_headimgurl {width:70px;}
	.tablenav{display:none;}
	';
	

	$weixin_list_table = wpjam_list_table( array(
		'plural'		=> 'weixin-customservice-kfs',
		'singular' 		=> 'weixin-customservice-kf',
		'columns'		=> $columns,
		'item_callback'	=> 'weixin_robot_customservice_kf_item',
		'actions_column'=> 'kf_account',
		'style'			=> $style,
	) );
}

function weixin_robot_customservice_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';

	if($action == 'edit' || $action == 'add' || $action == 'set' ){
		weixin_robot_customservice_kf_edit_page();
	}elseif($action == 'headimg'){
		weixin_robot_customservice_kf_edit_headimg_page();
	}else{
		weixin_robot_customservice_kf_list_page();
	}
}

function weixin_robot_customservice_kf_list_page(){
	global $wpdb, $weixin_list_table,$current_admin_url;

	$action = $weixin_list_table->current_action();

	if($action == 'delete'){

	    if( !current_user_can( 'edit_weixin' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

	    $redirect_to	= wpjam_get_referer();

		if(!empty($_GET['kf_account'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['kf_account']);

			$response = weixin_robot_customservice_delete_kf_account($_GET['kf_account']);
			if(is_wp_error($response)){
				$redirect_to = add_query_arg( array( 'deleted' => urlencode($response->get_error_message()) ), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}
		}

		delete_transient('weixin_kf_list');
		delete_transient('weixin_online_kf_list');

		wp_redirect($redirect_to);
	}

	$weixin_kf_list 		= weixin_robot_customservice_get_kf_list();
	$total					= count($weixin_kf_list);
	?>

	<h1>微信客服管理<?php if($total<99){?> <a title="新增客服" class="thickbox page-title-action" href="<?php echo $current_admin_url.'&action=add'.'&TB_iframe=true&width=780&height=400'; ?>">新增</a><?php } ?></h1>
	<p>在线状态每30秒刷新。</p>

	<?php

	$weixin_list_table->prepare_items($weixin_kf_list, $total);
	$weixin_list_table->display(array('search'=>false));
}

function weixin_robot_customservice_kf_item($item){
	global $current_admin_url, $weixin_list_table;

	$item['kf_head']	= ($item['kf_headimgurl'])?'<img src="'.$item['kf_headimgurl'].'" width="50" />':'';

	if(isset($item['kf_wx'])){

	}elseif(isset($item['invite_wx'])){
		$item['kf_wx'] = 
		'已经邀请：'.$item['invite_wx'].'<br />'.
		'过期时间：'.get_date_from_gmt(date('Y-m-d H:i:s',$item['invite_expire_time'])).'<br />'.
		'邀请状态：'.$item['invite_status'];
	}else{
		$item['kf_wx']	= '';
	}

	$item['nickname']	= $item['kf_nick'];

	$item['row_actions'] = array(
		'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&kf_account='.$item['kf_account'].'&TB_iframe=true&width=780&height=400').'" title="编辑客服" class="thickbox">编辑</a>',
		'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&kf_account='.$item['kf_account'], 'delete-'.$weixin_list_table->get_singular().'-'.$item['kf_account'])).'">删除</a>',
	);

	return $item;
}

function weixin_robot_customservice_kf_edit_page(){
	global  $wpdb, $current_admin_url;

	$weixin_kf_list = weixin_robot_customservice_get_kf_list();

	$kf_account	= isset($_GET['kf_account'])?$_GET['kf_account']:'';
	$action		= isset($_GET['action'])?$_GET['action']:'';

	$nonce_action	= $kf_account ? 'edit-weixin-customservice-'.$kf_account : 'add-weixin-customservice';

	$form_fields = wpjam_get_form_fields();

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data	= wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');

		$headimg	= $data['headimg'];
		$kf_wx		= $data['kf_wx'];
		unset($data['headimg']);
		unset($data['kf_wx']);

		if($kf_account && isset($weixin_kf_list[$kf_account])){
			$response = weixin_robot_customservice_update_kf_account($data);
			$success_msg = '修改成功';
		}else{
			$response = weixin_robot_customservice_add_kf_account($data);
			$success_msg = '添加成功';
		}

		if(is_wp_error($response)){
			wpjam_admin_add_error($response->get_error_code().'-'.$response->get_error_message(),'error');
		}else{
			$kf_account	= $data['kf_account'];
			if($headimg && ((strpos($headimg, 'http://p.qlogo.cn/dkfheadimg') === false) || (strpos($headimg, 'qpic.cn/') === false))){
				$media		= weixin_robot_download_remote_image($headimg);
				$response	= weixin_robot_customservice_upload_kf_account_headimg($kf_account, $media);
			}

			if($kf_wx){
				if($kf_account && isset($weixin_kf_list[$kf_account])){
					if($weixin_kf_list[$kf_account]['kf_wx'] != $kf_wx){
						$response	= weixin_robot_customservice_invite_kf_account_worker($kf_account, $kf_wx);
					}
				}else{
					$response	= weixin_robot_customservice_invite_kf_account_worker($kf_account, $kf_wx);
				}
			}

			wpjam_admin_add_error($success_msg);
		}

		delete_transient('weixin_kf_list');
		delete_transient('weixin_online_kf_list');
	}

	$weixin_kf_list = weixin_robot_customservice_get_kf_list();

	if($kf_account && isset($weixin_kf_list[$kf_account])){
		$form_fields['kf_account']['value']	= $kf_account;
		$form_fields['nickname']['value']	= $weixin_kf_list[$kf_account]['kf_nick'];
		$form_fields['kf_wx']['value']		= isset($weixin_kf_list[$kf_account]['kf_wx'])?$weixin_kf_list[$kf_account]['kf_wx']:'';
		$form_fields['headimg']['value']	= $weixin_kf_list[$kf_account]['kf_headimgurl'];
	}

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&kf_account='.$kf_account;
	$action_text	= $kf_account?'编辑':'新增';
	?>

	<h2><?php echo $action_text;?>客服</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>
	<?php
}

