<!doctype html>
<?php $weixin_title = apply_filters('weixin_title', '用户中心' ); ?>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $weixin_title;?></title>
<meta name="HandheldFriendly" content="True">
<meta name="MobileOptimized" content="320">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel='stylesheet' id='new-smileys-css'  href='<?php echo WEIXIN_ROBOT_PLUGIN_URL.'/template/user/style.css'?>' type='text/css' media='all' />
<?php 
add_filter('weixin_hide_option_menu', '__return_true');
add_filter('weixin_hide_toolbar', '__return_true');
weixin_robot_enqueue_scripts(); 
wp_print_head_scripts();
do_action('weixin_head');
?>
</head>
<body>
		
<div class="content">

<h2><?php echo $weixin_title;?></h2>