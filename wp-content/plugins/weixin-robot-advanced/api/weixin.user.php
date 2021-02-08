<?php
$weixin_openid	= weixin_get_current_openid();

if(is_wp_error($weixin_openid)){
	wpjam_send_json($weixin_openid);
}

$weixin_user	= WEIXIN_User::parse_for_json($weixin_openid);

wpjam_send_json($weixin_user);