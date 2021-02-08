<?php
$weixin_openid	= weixin_robot_get_user_openid();
$response = compact('weixin_openid');
wpjam_send_json($response);