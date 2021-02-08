<?php 
add_action('weixin_head','wpjam_weixin_head');
function wpjam_weixin_head(){
?>
<style type="text/css">
	th,td {width:24%; padding-right: 4px; vertical-align: middle;}
</style>
<?php
}

include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/header.php'); 

global $wpdb;
$weixin_openid 	= weixin_robot_get_user_openid();
$weixin_user 	= weixin_robot_get_user($weixin_openid);

if($weixin_user){ 

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		if ( !wp_verify_nonce($_POST['weixin_user_edit_nonce'],'weixin_user') ){
			ob_clean();
			wp_die('非法操作');
		}

		$name			= stripslashes( trim( $_POST['name'] ));
		$phone			= stripslashes( trim( $_POST['phone'] ));
		$description	= stripslashes( trim( $_POST['description'] ));
		$weixin			= stripslashes( trim( $_POST['weixin'] ));
		$province		= stripslashes( trim( $_POST['province'] ));
		$city			= stripslashes( trim( $_POST['city'] ));
		$length			= stripslashes( trim( $_POST['length'] ));

		if($_FILES['avatar']['name']){

			if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
			
			$uploadedfile = $_FILES['avatar'];
			$upload_file = wp_handle_upload( $uploadedfile, array( 'test_form' => false ) );
			if ( $upload_file ) {
			    $avatar = $upload_file['url'];
			}
		}

		$data = compact('name','phone','description','weixin','province','city','length','weixin_openid');

		if($avatar){
			$data['avatar'] = $avatar;
		}

		$weixin_crm_user	= weixin_robot_crm_get_user($weixin_openid);

		if($weixin_crm_user){
			weixin_robot_crm_update_user($data);
			$success_msg = '修改成功';
		}else{
			weixin_robot_crm_insert_user($data); 
			$success_msg = '提交成功';
		}
	}

	$weixin_crm_user	= weixin_robot_crm_get_user($weixin_openid);
	if($weixin_crm_user){
		$name			= $weixin_crm_user['name'];
		$avatar			= $weixin_crm_user['avatar'];
		$phone			= $weixin_crm_user['phone'];
		$description	= $weixin_crm_user['description'];
		$weixin			= $weixin_crm_user['weixin'];
		$province		= $weixin_crm_user['province'];
		$city			= $weixin_crm_user['city'];
		$length			= $weixin_crm_user['length'];
	}

	$form_fields = array(
		'name'			=> array('title'=>'姓名',	'type'=>'text',		'value'=>$weixin_crm_user?$name:'',			'description'=>''),
		'avatar'		=> array('title'=>'头像',	'type'=>'file',		'value'=>$weixin_crm_user?$avatar:'',		'description'=>''),
		'phone'			=> array('title'=>'电话',	'type'=>'number',	'value'=>$weixin_crm_user?$phone:'',		'description'=>''),
		'description'	=> array('title'=>'简介',	'type'=>'textarea',	'value'=>$weixin_crm_user?$description:'',	'description'=>'',	'rows'=>3),
		'weixin'		=> array('title'=>'微信号',	'type'=>'text',		'value'=>$weixin_crm_user?$weixin:'',		'description'=>''),
		'province'		=> array('title'=>'省份',	'type'=>'select',	'value'=>$weixin_crm_user?$province:'',		'description'=>''),
		'city'			=> array('title'=>'城市',	'type'=>'select',	'value'=>$weixin_crm_user?$city:'',			'description'=>''),
		'length'		=> array('title'=>'从业时长',	'type'=>'select',	'value'=>$weixin_crm_user?$length:'',		'description'=>'','options'=>array('1','2','3','4','5','6','7','8','9','10','10年以上')),
	);
	?>

	<?php if(isset($success_msg)) { ?>
	<p style="color:green; font-weight:bold;"><?php echo $success_msg;?></p>
	<?php } ?>

	<form method="post" action="<?php echo home_url('?weixin_user=edit'); ?>" enctype="multipart/form-data" id="form">
		<?php wpjam_form_fields($form_fields); ?>
		<?php wp_nonce_field('weixin_user','weixin_user_edit_nonce'); ?>
		<input type="hidden" name="action" value="edit" />
		<p class="submit"><input class="button-primary" type="submit" value="编辑" /></p>
	</form>

<?php } ?>

<script type="text/javascript" src="<?php echo WEIXIN_ROBOT_PLUGIN_URL; ?>/template/js/jquery.cityselect.js"></script> 
<script  type="text/javascript">
	jQuery(".form-table").citySelect({  
		url:"<?php echo WEIXIN_ROBOT_PLUGIN_URL; ?>/template/js/city.min.js",  
		prov:"<?php echo (isset($province) ? $province : ''); ?>", //省份 
		city:"<?php echo (isset($city) ? $city : ''); ?>", //城市 
		nodata:"none" //当子集无数据时，隐藏select 
});   
</script>

<?php include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/footer.php'); ?>