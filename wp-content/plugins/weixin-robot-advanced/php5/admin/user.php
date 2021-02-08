<?php 
function weixin_robot_user_tabs($tabs){
	return array(
		'detail'	=> array('title'=>'详细资料',	'function'=>'weixin_robot_user_detail_page'),
		'subscribe'	=> array('title'=>'订阅历史',	'function'=>'weixin_robot_user_subscribe_page'),
		'messages'	=> array('title'=>'消息记录',	'function'=>'weixin_robot_messages_page'),
	);
}

// 用户详细信息后台页面
function weixin_robot_user_page(){
	global $plugin_page, $current_tab;
	$openid	= isset($_GET['openid'])?$_GET['openid']:'';
	$action 		= isset($_GET['action'])?$_GET['action']:'';

	if(!$openid) return;

	if($action == 'update'){
		$weixin_user	= weixin_robot_get_user($openid, array('force'=>true));
	}else{
		$weixin_user	= weixin_robot_get_user($openid);
	}

	$weixin_user	= weixin_robot_get_user_detail($weixin_user);
	
	if(!$weixin_user) return;

	?>
	<div class="wrap">
	<h1>
		<?php if($weixin_user['headimgurl']){ ?><img src="<?php echo $weixin_user['headimgurl'];?>" style="float: left; padding: 2px 4px; width: 24px;" /> <?php } ?><?php echo $weixin_user['nickname']; ?>
		<?php if($weixin_user['subscribe']){ ?>
			<a href="<?php echo wpjam_get_current_page_url().'&action=update'; ?>" class="button">更新</a> 
		<?php }?>
	</h1>
	</div>

	<?php

	wpjam_admin_tab_page(array('openid'=>$openid));
}

function weixin_robot_user_detail_page(){
	global $plugin_page, $current_admin_url;

	$openid			= $_GET['openid'];
	$nonce_action	= 'weixin_user'; 

	$weixin_user		= weixin_robot_get_user($openid);
	$weixin_user		= weixin_robot_get_user_detail($weixin_user);
	$weixin_user_tags 	= weixin_robot_get_tags();

	$weixin_user_tags_options = array();
	if(!is_wp_error($weixin_user_tags)){
		foreach ($weixin_user_tags as $tagid => $weixin_user_tag) {
			$weixin_user_tags_options[$tagid] = $weixin_user_tag['name'];
		}
	}

	$current_tagids = array();
	for ($i=1; $i <=3 ; $i++) { 
		if($weixin_user['tagid_'.$i]){
			$current_tagids[] = $weixin_user['tagid_'.$i];
		}
	}

	$form_fields = array(
		'tags'		=> array('title'=>'标签',	'type'=>'checkbox',	'value'=>$current_tagids,		'options'=> $weixin_user_tags_options),
		'remark'	=>array('title'=>'备注',		'type'=>'textarea',	'value'=>$weixin_user['remark'],	'rows'=>3, 'description'=>'长度必须小于30字符')
	);
	
	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$cap = apply_filters('update_weixin_user_capability','edit_weixin');
		if( !current_user_can( $cap )){
			ob_clean();
			wp_die('无权限');
		}

		$data		= wpjam_get_form_post($form_fields, $nonce_action);

		$new_tagids			= (array)$data['tags'];

		$untagging_tagids	= array_diff($current_tagids, $new_tagids);
		$tagging_tagids 	= array_diff($new_tagids, $current_tagids);

		$openid_list	= array($openid);

		if($untagging_tagids){
			foreach ($untagging_tagids as $tagid) {
				if(trim($tagid)){
					weixin_robot_batch_untagging($openid_list, $tagid);
				}
			}
		}

		if($tagging_tagids){
			foreach ($tagging_tagids as $tagid) {
				if(trim($tagid)){
					weixin_robot_batch_tagging($openid_list, $tagid);
				}
			}
		}

		$remark		= $data['remark'];
		weixin_robot_update_user_remark($openid, $remark);	

		weixin_robot_get_user($openid, array('force'=>true));

		foreach ($form_fields as $key => $form_field) {
			$form_fields[$key]['value']	= $data[$key];
		}

		wpjam_admin_add_error('修改成功');
	}

	if($weixin_user){ 
	?>
		<div class="user-profile" style="max-width:640px">
			<?php if($weixin_user['headimgurl']){?><a href="<?php echo str_replace('/64', '/0', $weixin_user['headimgurl']);?>" target="_blank"><img src="<?php echo str_replace('/64', '/132',$weixin_user['headimgurl']);?>" style="width:132px; height:132px; float:right; margin:0 0 10px 10px" /></a><?php } ?>
			<style type="text/css">
			td, th {padding: 4px 8px;} 
			td{font-weight: bold;}
			input.regular-text{width:15em; padding:4px;}
			th strong {padding:10px 0; font-size:16px; display: block;}
			input[type=checkbox]{height:16px; width: 16px;}
			</style>
			
			<script type="text/javascript">
			jQuery(function($){
				$(".form-table input:checkbox").click(function(){
					if($("input:checkbox:checked").length>3){ 
						alert('亲，最多只能选三个哟~'); 
						return false; //另刚才勾选的取消 
					}
				})
			});
			</script>
			
			<h2>详细资料</h2>
			<table class="user-profile" class="widefat" cellspacing="0" style="width:360px; float:left;background:none; clear:none; border:none; -webkit-box-shadow:none; box-shadow:none;">
				<tbody>
					<?php do_action('weixin_user_show_detail', $openid); ?>
					<tr> <th style="width:100px;">OPENID</th><td><?php echo $openid;?></td> </tr>
					<tr> <th>昵称（微信）</th>	<td><?php echo $weixin_user['nickname'];?></td> </tr>
					<tr> <th>性别（微信）</th>	<td><?php echo $weixin_user['sex'];?></td> </tr>
					<tr> <th>订阅时间</th>	<td><?php echo $weixin_user['subscribe_time']; ?></td> </tr>
					<tr> <th>地址（微信）</th>	<td><?php echo $weixin_user['address'];?></td> </tr>
					<tr> <th colspan="2"></th></tr>
					<?php if($weixin_user['ip']){?>
					<tr> <th>IP</th>	<td><?php echo $weixin_user['ip'];?></td> </tr>
					<?php } ?>
					<?php if($weixin_user['credit']){?>
					<tr> <th>积分</th>	<td><?php echo $weixin_user['credit']; ?></td></tr>
					<?php } ?>

					<?php if($weixin_user['os']){ ?>
					<tr> <th>系统</th>	<td><?php echo $weixin_user['os']; ?></td> </tr>
					<?php }?>

					<?php if($weixin_user['device']){ ?>
					<tr> <th>设备</th>	<td><?php echo $weixin_user['device']; ?></td> </tr>
					<?php } ?>
				</tbody>
			</table>

			<div style="clear:both"></div>
			<hr />
			
			<?php
			$form_url = admin_url('admin.php?page='.$plugin_page.'&openid='.$openid);
		
			wpjam_form($form_fields, $form_url, $nonce_action, '保存');
			?>
		</div>
	<?php
	}
}

function weixin_robot_user_subscribe_page(){
	global $plugin_page, $current_tab, $wpdb;
	$openid	= $_GET['openid'];

	$current_page 		= isset($_GET['paged'])?$_GET['paged']:1;
	$number_per_page	= 100;
	$start_count		= ($current_page-1)*$number_per_page;
	$limit 				= $start_count.','.$number_per_page;

	$sql = "SELECT SQL_CALC_FOUND_ROWS CreateTime, Event, EventKey FROM  {$wpdb->weixin_messages} WHERE FromUserName = '{$openid}' AND Event in ('subscribe','unsubscribe') ORDER BY  CreateTime DESC LIMIT {$limit}";

	$weixin_subscribes 	= $wpdb->get_results($sql);
	$total_count		= $wpdb->get_var("SELECT FOUND_ROWS();");
	?>

	<h2>订阅历史</h2>

	<?php if($weixin_subscribes){ ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th>动作</th>
				<th>二维码</th>
				<th>时间</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach($weixin_subscribes as $weixin_subscribe){ ?>
			<tr class="<?php $alternate	= empty($alternate)?'alternate':''; echo $alternate;?>">
				<td><?php echo ($weixin_subscribe->Event == 'subscribe')?'订阅':'取消订阅';; ?></td>
				<td><?php if($weixin_subscribe->EventKey){ $qrcode = weixin_robot_get_qrcode(str_replace('qrscene_', '', $weixin_subscribe->EventKey)); if($qrcode) { echo $qrcode['name']; } } ?></td>
				<td><?php echo get_date_from_gmt(date('Y-m-d H:i:s',$weixin_subscribe->CreateTime)); ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	<?php }
}

// 获取经过处理的用户消息
function weixin_robot_get_user_detail($weixin_user, $args=array()){
	if(!$weixin_user )	return false;

	extract(wp_parse_args( $args, array('show_link'=>false, 'tab'=>'') ));

	$weixin_user 		= (array)$weixin_user;
	$openid		= $weixin_user['openid'];
	
	//if(!$weixin_user['subscribe']) return false;

	if($tab) $tab = '&tab='.$tab; 
	$weixin_user['link']= admin_url('admin.php?page=weixin-robot-user&openid='.$openid.$tab);

	$weixin_users_link	= wpjam_get_current_page_url();

	if(WEIXIN_TYPE >= 3){

		if(!$weixin_user['subscribe']){
			if(empty($weixin_user['subscribe_time'])){
				return false;
			}
			
			$weixin_user['nickname'] = '<span style="color:red; text-decoration:line-through; transform: rotate(1deg);">'.$weixin_user['nickname'].'</span>';
		}

		$weixin_user['username'] = '<a href="'.$weixin_user['link'].'">'.$weixin_user['nickname'].'</a>';
		if($weixin_user['headimgurl']) {
			$weixin_user['headimgurl'] = str_replace('/0', '/64', $weixin_user['headimgurl']);
			$weixin_user['username'] = '<a href="'.$weixin_user['link'].'"><img src="'.$weixin_user['headimgurl'].'" width="32" class="weixin-avatar" /></a>'.$weixin_user['username'];
		}

		$weixin_user['subscribe_time']	= get_date_from_gmt(date('Y-m-d H:i:s',$weixin_user['subscribe_time']));

		// $weixin_user_groups	= weixin_robot_get_groups();

		// if(!is_wp_error($weixin_user_groups) && $weixin_user['subscribe']){
		// 	// if(is_admin()){
		// 	// 	global $plugin_page;
		// 	// 	if($plugin_page != 'weixin-robot-users'){
		// 	// 		$groupid = ($weixin_user['groupid'] == -1) ? weixin_robot_get_user_group($openid,$update=true) : $weixin_user['groupid'];
		// 	// 		$weixin_user['group_name']	= isset($weixin_user_groups[$groupid])?$weixin_user_groups[$groupid]['name']:'';
		// 	// 	}else{
		// 	// 		$weixin_user['group_name']	= '';
		// 	// 	}
		// 	// }else{
		// 	// }
		// 	if(is_wp_error($weixin_user_groups)){
		// 		$weixin_user['group_name']	= '';
		// 	}else{
		// 		$groupid = ($weixin_user['groupid'] == -1) ? weixin_robot_get_user_group($openid,$update=true) : $weixin_user['groupid'];
		// 		$weixin_user['group_name']	= isset($weixin_user_groups[$groupid])?$weixin_user_groups[$groupid]['name']:'';
		// 	}
		// }else{
		// 	$weixin_user['group_name']	= '';
		// }

		$weixin_user_tags		= weixin_robot_get_tags();

		$weixin_user_sex_list	= array('1'=>'男','2'=>'女','0'=>'未知');
		
		$weixin_user_sex		= isset($weixin_user_sex_list[$weixin_user['sex']])?$weixin_user_sex_list[$weixin_user['sex']]:'未知';

		$weixin_user['tags']	= array();
		
		if($show_link){
			$weixin_user['address']	= '<a href="'.$weixin_users_link.'&country='.$weixin_user['country'].'">'.$weixin_user['country'].'</a> <a href="'.$weixin_users_link.'&province='.$weixin_user['province'].'">'.$weixin_user['province'].'</a> <a href="'.$weixin_users_link.'&city='.$weixin_user['city'].'">'.$weixin_user['city'].'</a>';
			$weixin_user['sex']		= '<a href="'.$weixin_users_link.'&sex='.$weixin_user['sex'].'">'.$weixin_user_sex.'</a>';
			for ($i=1; $i <=3 ; $i++) { 
				if($weixin_user['tagid_'.$i]){
					$weixin_user['tags'][] = '<a href="'.$weixin_users_link.'&tagid='.$weixin_user['tagid_'.$i].'">'.$weixin_user_tags[$weixin_user['tagid_'.$i]]['name'].'</a>';
				}
			}
		}else{
			$weixin_user['address']	= $weixin_user['country'].' '.$weixin_user['province'].' '.$weixin_user['city'];
			$weixin_user['sex']		= $weixin_user_sex;
			for ($i=1; $i <=3 ; $i++) { 
				if($weixin_user['tagid_'.$i]){
					$weixin_user['tags'][] = $weixin_user_tags[$weixin_user['tagid_'.$i]]['name'];
				}
			}
			
		}
		if($weixin_user['tags']){
			$weixin_user['tags'] = implode(', ', $weixin_user['tags']);
		}else{
			$weixin_user['tags'] = '';
		}
	}else{
		$weixin_user['username'] = '<a href="'.$weixin_user['link'].'">'.$weixin_user['openid'].'</a>';
	}

	// if(isset($weixin_user['ip_country']) && ( $weixin_user['ip_country'] || $weixin_user['ip_region'] || $weixin_user['ip_city'] ) ){
	// 	if($show_link){
	// 		$weixin_user['ip_address']	= '<a href="'.$weixin_users_link.'&ip_country='.$weixin_user['ip_country'].'">'.$weixin_user['ip_country'].'</a> <a href="'.$weixin_users_link.'&ip_region='.$weixin_user['ip_region'].'">'.$weixin_user['ip_region'].'</a> <a href="'.$weixin_users_link.'&ip_city='.$weixin_user['ip_city'].'">'.$weixin_user['ip_city'].'</a>';
	// 	}else{
	// 		$weixin_user['ip_address']	= $weixin_user['ip_country'].' '.$weixin_user['ip_region'].' '.$weixin_user['ip_city'];
	// 	}
	// }else{
	// 	$weixin_user['ip_address'] = '';
	// }
				
	// $weixin_user_tags = weixin_robot_get_user_tags($openid);

	// if($weixin_user_tags){
	// 	$weixin_user_tag_names = array();
	// 	foreach ($weixin_user_tags as $weixin_user_tag) {
	// 		if($show_link){
	// 			$weixin_user_tag_names[] = '<a href="'.$weixin_users_link.'&tag_id='.$weixin_user_tag['id'].'">'.$weixin_user_tag['name'].'</a>';
	// 		}else{
	// 			$weixin_user_tag_names[] = $weixin_user_tag['name'];
	// 		}
	// 	}

	// 	$weixin_user['tags'] = implode(',', $weixin_user_tag_names);
	// }else{
	// 	$weixin_user['tags'] = '';
	// }

	if(weixin_robot_get_setting('weixin_credit') && $show_link && $weixin_user['credit']>0){
		$weixin_user['credit']	= '<a href="'.$weixin_user['link'].'&tab=credit">'.$weixin_user['credit'].'</a>';
	}

	if($show_link){
		if(!empty($weixin_user['os_ver'])){ 
			$weixin_user['os_ver']	= '<a href="'.$weixin_users_link.'&os='.$weixin_user['os'].'&os_ver='.$weixin_user['os_ver'].'">'.$weixin_user['os_ver'].'</a>';
		}

		$weixin_user['os'] = '<a href="'.$weixin_users_link.'&os='.$weixin_user['os'].'">'.$weixin_user['os'].'</a>';
	}

	if(!empty($weixin_user['os_ver'])){ 
		$weixin_user['os']	.=  '（'.$weixin_user['os_ver'].'）';
	} 

//	if(!empty($weixin_user['device'])){
//		$device_array	= wpjam_get_device($weixin_user['device']);
//
//		if($device_array){
//			if($show_link){
//				$weixin_user['device']	= '<a href="'.$weixin_users_link.'&device='.$weixin_user['device'].'">'.$device_array['name'].'</a>';
//			}else{
//				$weixin_user['device']	= $device_array['name'];
//			}
//		}else{
//			if($show_link){
//				$weixin_user['device']	= '<a href="'.$weixin_users_link.'&device='.$weixin_user['device'].'">'.$weixin_user['device'].'</a>';
//			}
//		}
//	}

	if(isset($weixin_user['unsubscribe_time'])){
		$weixin_user['unsubscribe_time'] = ($weixin_user['unsubscribe_time'])?get_date_from_gmt(date('Y-m-d H:i:s',$weixin_user['unsubscribe_time'])):'';
	}

	return apply_filters('weixin_user_detail', $weixin_user, $show_link, $tab);
}