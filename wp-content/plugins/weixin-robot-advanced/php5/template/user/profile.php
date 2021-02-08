<?php include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/user/header.php'); 

global $wpdb;
$weixin_openid 	= weixin_robot_get_user_openid();
$weixin_user 	= weixin_robot_get_user($weixin_openid);
$weixin_user 	= weixin_robot_get_user_detail($weixin_user);

if($weixin_user){ 
?>
	<?php if(WEIXIN_TYPE >= 3){?>
	<?php if($weixin_user['headimgurl']){?><a href="<?php echo str_replace('/64', '/0', $weixin_user['headimgurl']);?>" target="_blank"><img src="<?php echo str_replace('/64', '/132',$weixin_user['headimgurl']);?>" class="avatar" style="float:right; margin:0 0 10px 10px" /></a><?php } ?>
	<?php } ?>
	<h3>详细资料</h3>
	<table cellspacing="0" cellpadding="6" width="98%">
		<tbody>
			<?php wp_nonce_field('weixin_robot','weixin_robot_user_nonce'); ?>
			<?php do_action('weixin_user_show_detail', $weixin_openid); ?>
			<?php if(WEIXIN_TYPE >= 3){?>
			<tr> <th>昵称（微信）</th>	<td><?php echo $weixin_user['nickname'];?></td> </tr>
			<tr> <th>性别（微信）</th>	<td><?php echo $weixin_user['sex'];?></td> </tr>
			<tr> <th>订阅时间</th>	<td><?php echo $weixin_user['subscribe_time']; ?></td> </tr>
			<tr> <th>地址（微信）</th>	<td><?php echo $weixin_user['address'];?></td> </tr>
			<?php } ?>
			<tr> <th>地址（IP）</th>	<td><?php echo $weixin_user['ip_address'];?></td> </tr>
			<?php if(weixin_robot_get_setting('weixin_credit')){ ?>
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
<?php
}
?>

<h3>操作</h3>

<ul class="buttons">
	<li><a href="<?php echo home_url('/weixin/user-credit-history/')?>" class=button>积分历史</a></li>
	<li><a href="<?php echo home_url('/weixin/user-credit-rules/')?>" class=button>积分规则</a></li>
	<li><a href="<?php echo home_url('/weixin/user-top-credits/')?>" class=button>积分排行榜</a></li>
	<li><a href="<?php echo home_url('/weixin/user-top-checkin/')?>" class=button>签到排行榜</a></li>
</ul>

<?php include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/user/footer.php'); ?>