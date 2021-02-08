<?php
if(empty($_REQUEST['callback'])){
	exit;
}

$callback	= htmlspecialchars($_REQUEST['callback']);

$weixin_js_api_ticket	= weixin()->get_js_api_ticket();

if(is_wp_error($weixin_js_api_ticket)){
	wpjam_send_json($weixin_js_api_ticket);
}

$js_api_ticket	= $weixin_js_api_ticket['ticket'];

header('Access-Control-Allow-Origin: *');
header('Content-type: application/json');

$url		= $_REQUEST['url'];
$timestamp	= time();
$nonceStr	= wp_generate_password(16, false);
$signature	= sha1("jsapi_ticket=$js_api_ticket&noncestr=$nonceStr&timestamp=$timestamp&url=$url");

$response	= [
	'errno'		=> 0,			
	'errmsg'	=> 'SUCCESS',			
	'data'		=> [
		'appId'		=> weixin_get_appid(),
		'timestamp'	=> $timestamp,
		'nonceStr'	=> $nonceStr,
		'signature'	=> $signature,
		'jsApiList'	=> ['checkJsApi', 'onMenuShareTimeline', 'onMenuShareAppMessage', 'onMenuShareQQ', 'onMenuShareWeibo', 'onMenuShareQZone'],
	],
	'time'		=> $timestamp,
	'hasFlush'	=> true,
	'format'	=> 'jsonp'
];

echo $callback . "(" . json_encode($response) . ")";

exit;