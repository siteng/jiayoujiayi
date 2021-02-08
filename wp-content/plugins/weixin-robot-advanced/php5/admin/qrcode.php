<?php 
add_filter('weixin-robot-qrcode-stats_fields', 'weixin_robot_qrcode_fields');
function weixin_robot_qrcode_fields($fields){
	return array(
		'name'		=> array('title'=>'名称',	'type'=>'text',		'show_admin_column'=>true,	'required',	'description'=>'二维码名称无实际用途，仅用于更加容易区分。'),
		'scene'		=> array('title'=>'场景 ID',	'type'=>'number',	'show_admin_column'=>true,	'min'=>'1',	'max'=>'100000',	'required',	'description'=>'临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）'),
		'type'		=> array('title'=>'类型',	'type'=>'select',	'show_admin_column'=>true,	'options'=> weixin_robot_get_qrcode_types()),
		'expire'	=> array('title'=>'过期时间',	'type'=>'text',		'show_admin_column'=>true,	'description'=> '二维码有效时间，以秒为单位。最大不超过1800'),
	); 
}

function weixin_robot_qrcode_stats_page_load(){
	weixin_robot_qrcode_page_load();
}
function weixin_robot_qrcode_page_load(){
	global $weixin_list_table, $plugin_page, $current_tab;

	$columns	= array(
		'cb'		=> 'checkbox',
		'ticket'	=> '二维码',
	);

	$sortable_columns = array('scene'=>'scene');

	$per_page	= array();

	if($plugin_page == 'weixin-robot-qrcode-stats'){
		unset($columns['cb']);
		unset($columns['ticket']);
		unset($columns['type']);
		unset($columns['expire']);

		$columns['qrscene_count'] 	= '关注';
		$columns['scene_count']		= '扫描';

		$sortable_columns['qrscene_count']	= 'qrscene_count';
		$sortable_columns['scene_count']	= 'scene_count';

		$per_page	= array(
			'label'		=> '项',
			'default'	=> 100, 
			'option'	=> 'weixin_qrcode_stats_per_page' 
		);

		$bulk_actions	= array();
		$actions_column	= '';
		$views			= '';
	}elseif($plugin_page == 'weixin-robot-qrcode'){
		$bulk_actions	=  array('delete'	=> '删除');
		$actions_column	= 'name';
		$views			= 'weixin_robot_qrcodes_views';

		$per_page	= array(
			'label'		=> '项',
			'default'	=> 20, 
			'option'	=> 'weixin_qrcodes_per_page' 
		);
	}

	$style = 'th.column-name{width:30%;}';

	$list_table_args = array(
		'plural'			=> 'weixin-qrcodes',
		'singular' 			=> 'weixin-qrcode',
		'columns'			=> $columns,
		'sortable_columns'	=> $sortable_columns,
		'actions_column'	=> $actions_column,
		'item_callback'		=> 'weixin_robot_qrcode_item',
		'bulk_actions'		=> $bulk_actions,
		'per_page'			=> $per_page,
		'views'				=> $views,
		'style'				=> $style,
	);

	$weixin_list_table = wpjam_list_table($list_table_args);
}

function weixin_robot_qrcode_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';

	if($action == 'edit' || $action == 'add' ){
		weixin_robot_qrcode_edit_page();
	}else{
		weixin_robot_qrcode_list_page();
	}
}

function weixin_robot_qrcode_list_page(){
	global $current_admin_url, $wpdb, $weixin_list_table;

	$action			= $weixin_list_table->current_action();
	$redirect_to	= wpjam_get_referer();

	if($action == 'delete'){
		if( !current_user_can( 'delete_weixin' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

		if(!empty($_GET['id'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['id']);

			if($wpdb->delete($wpdb->weixin_qrcodes, array('id' => $_GET['id']))){
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => $wpdb->last_error ), $redirect_to );
			}
			
			wp_redirect($redirect_to);
		}elseif (isset($_GET['ids'])) {
			check_admin_referer('bulk-'.$weixin_list_table->get_plural());

			$error = false;
			foreach ($_GET['ids'] as $id) {
				if(!$wpdb->delete($wpdb->weixin_qrcodes, array('id' => $id))){
					$error = true;
					wpjam_admin_add_error($id.'删除失败：'.$wpdb->last_error,'error');
				}
			}
			if($error == false){
				wpjam_admin_add_error('删除成功');
			}
		}
	}
	?>

	<h1>带参数二维码<a title="新增带参数二维码" class="thickbox add-new-h2" href="<?php echo $current_admin_url.'&action=add&TB_iframe=true&width=780&height=390'; ?>">新增</a></h1>

	<?php weixin_robot_qrcode_stats_page(); ?>
	<?php
}

function weixin_robot_qrcode_stats_page(){
	global $wpdb, $plugin_page, $weixin_list_table;

	$type			= isset($_GET['type']) ? $_GET['type'] : '';
	$search_term	= isset($_GET['s']) ? $_GET['s'] : '';

	$where			= "1=1";
	if($type){
		$where		.= " AND `type`='{$type}'";
	}

	$orderby		= isset($_GET['orderby'])?$_GET['orderby']:'';
	$order 			= isset($_GET['order'])?$_GET['order']:'desc';


	$sql_orderby	= ' id DESC';
	if($orderby){
		$sql_orderby	= "{$orderby}+0 {$order}";
	}

	if($search_term){
		$where	.= " AND name like '%{$search_term}%'";
	}

	$limit	= $weixin_list_table->get_limit();

	$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_qrcodes} WHERE {$where} ORDER BY {$sql_orderby} LIMIT {$limit};";

	if($plugin_page == 'weixin-robot-qrcode'){
		$weixin_qrcodes = $wpdb->get_results($sql, ARRAY_A);
		$total 			= $wpdb->get_var("SELECT FOUND_ROWS();");
		$show_search	= true;
	}elseif($plugin_page == 'weixin-robot-qrcode-stats'){
		global $qrscene_counts, $scene_counts, $wpjam_stats_labels;
		echo '<h2>渠道统计分析</h2>'; 
		wpjam_stats_header();

		extract($wpjam_stats_labels);

		$stats_where = "CreateTime > {$wpjam_start_timestamp} AND CreateTime < {$wpjam_end_timestamp}";

		if($orderby == 'scene_count'){
			$sql = "SELECT SQL_CALC_FOUND_ROWS wq.*, wm.scene_count FROM {$wpdb->weixin_qrcodes} wq LEFT JOIN (SELECT EventKey, count(*) as scene_count FROM {$wpdb->weixin_messages} WHERE {$stats_where} AND MsgType = 'event' AND Event = 'SCAN' AND EventKey!='' GROUP BY EventKey) wm ON wq.scene = wm.EventKey  WHERE {$where} ORDER BY {$sql_orderby} LIMIT {$limit};";
		}elseif($orderby == 'qrscene_count'){
			$sql = "SELECT SQL_CALC_FOUND_ROWS wq.*, wm.qrscene_count FROM {$wpdb->weixin_qrcodes} wq LEFT JOIN (SELECT EventKey, count(*) as qrscene_count FROM {$wpdb->weixin_messages} WHERE {$stats_where} AND MsgType = 'event' AND Event = 'subscribe' AND EventKey!='' GROUP BY EventKey) wm ON CONCAT('qrscene_',wq.scene) = wm.EventKey WHERE {$where} ORDER BY {$sql_orderby} LIMIT {$limit};";
		}

		$weixin_qrcodes = $wpdb->get_results($sql, ARRAY_A);
		$total 			= $wpdb->get_var("SELECT FOUND_ROWS();");
		$show_search	= false;

		if($weixin_qrcodes){
			
			$scenes		= array();
			$qrscenes	= array();
			foreach ($weixin_qrcodes as $weixin_qrcode) {
				$scenes[]	= $weixin_qrcode['scene'];
				$qrscenes[]	= 'qrscene_'.$weixin_qrcode['scene'];
			}

			$scene_counts = $wpdb->get_results("SELECT EventKey, count(*) as count FROM {$wpdb->weixin_messages} WHERE {$stats_where} AND MsgType = 'event' AND Event = 'SCAN' AND EventKey in(".implode(',', $scenes).") GROUP BY EventKey", OBJECT_K);

			$qrscene_counts = $wpdb->get_results("SELECT EventKey, count(*) as count FROM {$wpdb->weixin_messages} WHERE {$stats_where} AND MsgType = 'event' AND Event = 'subscribe' AND EventKey in('".implode("','", $qrscenes)."') GROUP BY EventKey",OBJECT_K);
		}
	};

	$weixin_list_table->prepare_items($weixin_qrcodes, $total);
	$weixin_list_table->display(array('search'=>$show_search));
}

function weixin_robot_qrcode_item($item){
	global $plugin_page, $current_admin_url, $weixin_list_table;

	$scene = $item['scene'];

	$item['expire']	= ($item['type']=='QR_SCENE')?(($item['expire']-time()>0)?$item['expire']-time():'已过期'):'';
	
	if($plugin_page == 'weixin-robot-qrcode'){
		$item['ticket']	= '<img src="https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($item['ticket']).'" width="100">';
		$item['row_actions']	= array(
			'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&id='.$item['id'].'&TB_iframe=true&width=780&height=390').'" title="编辑带参数二维码" class="thickbox">编辑</a>',
			'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['id'], 'delete-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">删除</a>',
			'subscribe'	=> weixin_robot_get_keyword_set_html('[subscribe_'.$scene.']', '关注回复'),
			'scan'		=> weixin_robot_get_keyword_set_html('[scan_'.$scene.']', '扫描回复')
		);
	}else{
		global $qrscene_counts, $scene_counts;
		$item['qrscene_count']	= isset($qrscene_counts['qrscene_'.$scene])?'<a href="'.admin_url('admin.php?page=weixin-robot-users&subscribe=qrscene_'.$scene).'">'.$qrscene_counts['qrscene_'.$scene]->count.'</a>':'';
		$item['scene_count']	= isset($scene_counts[$scene])?'<a href="'.admin_url('admin.php?page=weixin-robot-users&scan='.$scene).'">'.$scene_counts[$scene]->count.'</a>':'';
	}

	return $item;
}

function weixin_robot_qrcodes_views($views){
	global $wpdb, $current_admin_url;
	
	$qrcode_types	= weixin_robot_get_qrcode_types();
	$type			= isset($_GET['type']) ? $_GET['type'] : '';
	$total 			= $wpdb->get_var("SELECT count(*) FROM {$wpdb->weixin_qrcodes}");
	$counts 		= $wpdb->get_results("SELECT COUNT( * ) AS count, `type` FROM {$wpdb->weixin_qrcodes} GROUP BY `type` ORDER BY count DESC ");

	$views	= array();

	$class = empty($type) ? 'class="current"':'';
	$views['all'] = '<a href="'.$current_admin_url.'" '.$class.'>全部<span class="count">（'.$total.'）</span></a>';

	foreach ($counts as $count) { 
		$class = ($type == $count->type) ? 'class="current"':'';
		$views[$count->type] = '<a href="'.$current_admin_url.'&type='.$count->type.'" '.$class.'>'.$qrcode_types[$count->type].'<span class="count">（'.$count->count.'）</span></a>';
	}

	return $views;
}

function weixin_robot_qrcode_edit_page(){
	global $wpdb, $current_admin_url;
	$id		= isset($_GET['id'])?$_GET['id']:'';
	$action	= isset($_GET['action'])?$_GET['action']:'';

	$nonce_action	= $id ? 'edit-qrcode-'.$id : 'add-qrcode';
	$form_fields	= wpjam_get_form_fields();

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');

		if(!$data['scene']){
			wpjam_admin_add_error('场景ID不能为空', 'error');
		}else{

			$response = weixin_robot_create_qrcode($data);

			if(is_wp_error($response)){
				wpjam_admin_add_error($response, 'error');
			}elseif($response){
				if($id){
					wpjam_admin_add_error('修改成功');
				}else{
					wpjam_admin_add_error('添加成功');
				}
			}else{
				if($id){
					wpjam_admin_add_error('未修改');
				}else{
					wpjam_admin_add_error('添加失败', 'error');
				}
			}
		}
	}
	
	if($id){
		$weixin_qrcode	= $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->weixin_qrcodes WHERE id=%d LIMIT 1",$id));

		foreach ($form_fields as $key => $form_field) {
			$form_fields[$key]['value']	= isset($weixin_qrcode->$key)?$weixin_qrcode->$key:'';
		}
		$form_fields['expire']['value']	= $weixin_qrcode->expire-time();
		$type = $weixin_qrcode->type;
	}

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&id='.$id;
	$action_text	= $id?'编辑':'新增';

	?>
	<h1><?php echo $action_text;?>带参数二维码</h1>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>

	<script type="text/javascript">
	jQuery(function(){
		jQuery('#tr_expire').hide();
	<?php if($id){?>
		jQuery('#scene').attr('readonly','readonly'); 
		<?php if($type == 'QR_SCENE' ){?>
			jQuery('#tr_expire').show();
		<?php } ?>
	<?php }?>
		jQuery("select#type").change(function(){
			var selected = jQuery("select#type").val();

			if(selected == 'QR_LIMIT_SCENE'){
				jQuery('#tr_expire').hide();
			}else if(selected == 'QR_SCENE'){
				jQuery('#tr_expire').show();
			}
		});
	});
	</script> 
	<?php
}

function weixin_robot_get_qrcode_types(){
	return  array(
		'QR_LIMIT_SCENE'	=> '永久二维码',
		'QR_SCENE'			=> '临时二维码'
	);
}
