<?php add_filter('weixin_title', create_function('','return "积分规则";'));?>
<?php include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/user/header.php'); ?>

<ul>
	<li>签到：				<?php echo weixin_robot_get_setting('weixin_checkin_credit');?>分</li>
	<li>发送文章给好友：		<?php echo weixin_robot_get_setting('weixin_SendAppMessage_credit');?>分</li>
	<li>分享文章到朋友圈：		<?php echo weixin_robot_get_setting('weixin_ShareTimeline_credit');?>分</li>
	<li>每天最多：			<?php echo weixin_robot_get_setting('weixin_day_credit_limit');?>分</li>
</ul>

<?php include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/user/footer.php'); ?>