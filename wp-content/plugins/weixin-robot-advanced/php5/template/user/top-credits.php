<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo '积分排行榜';?></title>
<meta name="HandheldFriendly" content="True">
<meta name="MobileOptimized" content="320">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php 
add_filter('weixin_hide_option_menu', '__return_true');

// add_filter('weixin_share_desc', 'weixin_robot_share_desc');
function weixin_robot_share_desc($desc){
	return '积分排名最高的30名！';
}

add_action('wp_head', 'weixin_robot_card_head', 0);
function weixin_robot_card_head(){
	remove_all_actions('wp_enqueue_scripts');
	weixin_robot_enqueue_scripts();
	wp_enqueue_style('weui');
	wp_enqueue_script('jquery');
}
wp_head();
?>
</head>
<body>
		
<div class="content">

<?php

global $wpdb;

$weixin_users = $wpdb->get_results("SELECT * FROM $wpdb->weixin_users WHERE subscribe = 1 ORDER BY credit DESC limit 0,30 ", ARRAY_A);

?>

<div class="weui_panel weui_panel_access">
    <div class="weui_panel_hd">积分排行榜</div>
    <div class="weui_panel_bd">
    <?php foreach ($weixin_users as $index => $weixin_user) { 
    	$weixin_openid		= $weixin_user['openid'];

		$continue_checkins	= weixin_robot_get_continue_checkins($weixin_openid);
		$total_checkins		= weixin_robot_get_total_checkins($weixin_openid);
		$badges				= weixin_robot_get_user_badges($weixin_openid);

		// if($badges	= weixin_robot_get_user_badges($weixin_openid)){
		// 	$badge_titles = array();
		// 	foreach ($badges as $badge) {
		// 			$badge_titles[]	= $badge['title'];
		// 	}
		// 	$badge_titles	= implode(', ', $badge_titles);
		// }

		// $i++;
	?>
        <a href="javascript:void(0);" class="weui_media_box weui_media_appmsg">
            <div class="weui_media_hd">
                <img class="weui_media_appmsg_thumb" src="<?php echo $weixin_user['headimgurl'];?>" alt="<?php echo $weixin_user['nickname']; ?>">
            </div>
            <div class="weui_media_bd">
                <h4 class="weui_media_title"><?php echo $index+1; ?>. <?php echo $weixin_user['nickname']; ?></h4>
                <p class="weui_media_desc">
                	<strong><?php echo $weixin_user['credit'];?></strong>积分，连续签到了<?php echo $continue_checkins;?>天，最近30天累计签到了<?php echo $total_checkins;?>次，获得了<?php echo count($badges); ?>个徽章。
                </p>
            </div>
        </a>
    <?php } ?>  
    </div>
</div>


</div>
<?php wp_footer(); ?>
</body>
</html>