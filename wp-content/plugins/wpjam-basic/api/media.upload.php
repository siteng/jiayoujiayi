<?php
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$media_id	= $module_args['media'] ?? 'media';
$output		= $module_args['output'] ?? 'url';

if (!isset($_FILES[$media_id])) {
	wpjam_send_json(['errcode'=>'empty_media',	'errmsg'=>'媒体流不能为空！']);
}

$post_id		= (int)wpjam_get_parameter('post_id',	['method'=>'POST', 'default'=>0]);
$attachment_id	= media_handle_upload($media_id, $post_id);

if(is_wp_error($attachment_id)){
	wpjam_send_json($attachment_id);
}

$response[$output]	= wp_get_attachment_url($attachment_id);