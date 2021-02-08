<?php
$weixin_appid = weixin_get_appid();

if(empty($weixin_appid)){
	wpjam_send_json(['errcode'=>'empty_appid', 'errmsg'=>'公众号 appid 为空']);
}

$weixin_js_api_ticket	= weixin()->get_js_api_ticket();

if(is_wp_error($weixin_js_api_ticket)){
	wpjam_send_json($weixin_js_api_ticket);
}

$js_api_ticket	= $weixin_js_api_ticket['ticket'];

$url		= $_REQUEST['url'];
$timestamp	= time();
$nonceStr	= wp_generate_password(16, false);
$signature	= sha1("jsapi_ticket=$js_api_ticket&noncestr=$nonceStr&timestamp=$timestamp&url=$url");

wpjam_send_json([
	'appId'		=> weixin_get_appid(),
	'url'		=> $url,
	'timestamp'	=> $timestamp,
	'nonceStr'	=> $nonceStr,
	'signature'	=> $signature,
]);

