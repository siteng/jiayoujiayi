<?php
add_filter('weixin_title', create_function('','return "积分历史";'));
include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/user/header.php'); 

$weixin_openid 	= weixin_robot_get_user_openid();
$weixin_user 	= weixin_robot_get_user($weixin_openid);

global $wpdb;
$weixin_credits	= $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->weixin_credits} WHERE weixin_openid=%s ORDER BY id DESC LIMIT 0,30;",$weixin_openid));
?>

<p>
<?php if(WEIXIN_TYPE >= 3){?>
<img src="<?php echo str_replace('/0', '/64', $weixin_user['headimgurl']);?>" alt="<?php echo $weixin_user['nickname'];?>" class="avatar" /> <strong><?php echo $weixin_user['nickname'];?></strong>，
<?php }?>
你现在共有 <strong><?php echo $weixin_user['credit']; ?></strong> 积分：
</p>

<table cellspacing="0" cellpadding="6" width="98%">
	<thead>
		<tr><th>操作</th><th width="20%">积分</th><th width="20%">新增</th></tr>
	</thead>
	<tbody>
	<?php foreach ($weixin_credits as $weixin_credit) { ?>
		<tr><td><?php echo $weixin_credit->note; ?></td><td><?php echo $weixin_credit->credit; ?></td><td><?php echo $weixin_credit->credit_change; ?></td></tr>
		<?php /*<td><?php if($weixin_credit->limit){echo '每日'.DAY_CREDIT_LIMIT.'分上限';}; ?></td> */?>
	<?php } ?>
	</tbody>
</table>

<?php include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/user/footer.php'); ?>