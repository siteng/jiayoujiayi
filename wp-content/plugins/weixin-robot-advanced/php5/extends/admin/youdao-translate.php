<?php

add_filter('weixin_response_types','wpjam_weixin_translate_response_types');
function wpjam_weixin_translate_response_types($response_types){
    $response_types['translate']                    = '有道翻译';
    return $response_types;
}

add_filter('weixin_setting','wpjam_weixin_translate_fields',11);
function wpjam_weixin_translate_fields($sections){
    $youdao_translate_fields = array(
        'youdao_translate_api_key'          => array('title'=>'有道翻译API Key',    'type'=>'text',     'description'=>'点击<a href="http://fanyi.youdao.com/openapi?path=data-mode">这里</a>申请有道翻译API！'),
        'youdao_translate_key_from'         => array('title'=>'有道翻译KEY FROM',   'type'=>'text',     'description'=>'申请有道翻译API的时候同时填写并获得KEY FROM'),
        // 'youdao_translate_default_reply'    => array('title'=>'默认翻译回复',        'type'=>'textarea', 'description'=>'用户只发送翻译两个词时候的默认回复'    )
    );
    $sections['youdao_translate'] = array('title'=>'有道翻译', 'callback'=>'', 'fields'=>$youdao_translate_fields);
    unset($sections['default_reply']['fields']['weixin_default_translate']);
    return $sections;
}