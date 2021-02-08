<?php
// 内置回复
function weixin_robot_builtin_reply($keyword)
{
    // 前缀匹配，只支持2个字
    $prefix_keyword = mb_substr($keyword, 0, 2);

    $weixin_builtin_replies        = weixin_robot_get_builtin_replies('full');
    $weixin_builtin_replies_prefix = weixin_robot_get_builtin_replies('prefix');

    if ($weixin_builtin_replies && isset($weixin_builtin_replies[$keyword])) {
        $weixin_builtin_reply = $weixin_builtin_replies[$keyword];
    } elseif ($weixin_builtin_replies_prefix && isset($weixin_builtin_replies_prefix[$prefix_keyword])) {
        $weixin_builtin_reply = $weixin_builtin_replies_prefix[$prefix_keyword];
    } else {
        return false;
    }

    call_user_func($weixin_builtin_reply['function'], $keyword);

    return true;
}

function weixin_robot_start_context_reply($keyword)
{
    wp_cache_set($weixin_openid, $keyword, 'context_keyword', 3600);
}

// 获取内置回复列表
function weixin_robot_get_builtin_replies($type = 'all')
{

    $weixin_builtin_replies = get_transient('weixin_builtin_replies');

    $type = (trim($type)) ? trim($type) : 'all';

    if (false === $weixin_builtin_replies) {
        $weixin_builtin_replies = array();

        // $weixin_builtin_replies['[default]']    = array(
        //     'type'        =>'full',
        //     'reply'        =>'没有匹配时回复',
        //     'function'    =>'weixin_robot_not_found_reply'
        // );

        foreach (array(
            '[voice]',
            '[location]',
            '[image]',
            '[link]',
            '[video]',
            '[shortvideo]',
        ) as $keyword) {
            $weixin_builtin_replies[$keyword] = array(
                'type'     => 'full',
                'reply'    => '默认回复',
                'function' => 'weixin_robot_default_reply',
            );
        }

        foreach (array(
            '[view]',
            '[scancode_push]',
            '[scancode_waitmsg]',
            '[location_select]',
            '[pic_sysphoto]',
            '[pic_photo_or_album]',
            '[pic_weixin]',
            '[templatesendjobfinish]',

            '[kf_create_session]',
            '[kf_close_session]',
            '[kf_switch_session]',

            '[user_get_card]',
            '[user_del_card]',
            '[card_pass_check]',
            '[card_not_pass_check]',
            '[user_view_card]',
            '[user_enter_session_from_card]',
            '[card_sku_remind]',
            '[user_consume_card]',
            '[submit_membercard_user_info]',

            '[masssendjobfinish]',
            '[templatesendjobfinish]',

            '[poi_check_notify]',
            '[wificonnected]',
            '[shakearoundusershake]',

        ) as $keyword) {
            $weixin_builtin_replies[$keyword] = array(
                'type'     => 'full',
                'reply'    => '空字符串回复',
                'function' => 'weixin_robot_empty_string_reply',
            );
        }

        if (WEIXIN_TYPE == 4) {
            $weixin_builtin_replies['event-location'] = array(
                'type'     => 'full',
                'reply'    => '获取用户地理位置',
                'function' => 'weixin_robot_location_event_reply',
            );
        }

        $weixin_builtin_replies['subscribe'] = array(
            'type'     => 'full',
            'reply'    => '用户订阅',
            'function' => 'weixin_robot_subscribe_reply',
        );

        $weixin_builtin_replies['unsubscribe'] = array(
            'type'     => 'full',
            'reply'    => '用户取消订阅',
            'function' => 'weixin_robot_unsubscribe_reply',
        );

        $weixin_builtin_replies['scan'] = array(
            'type'     => 'full',
            'reply'    => '扫描带参数二维码',
            'function' => 'weixin_robot_scan_reply',
        );

        foreach (array(
            '[qualification_verify_success]',
            '[qualification_verify_fail]',
            '[naming_verify_success]',
            '[naming_verify_fail]',
            '[annual_renew]',
            '[verify_expired]',
        ) as $keyword) {
            $weixin_builtin_replies[$keyword] = array(
                'type'     => 'full',
                'reply'    => '微信认证回复',
                'function' => 'weixin_robot_verify_reply',
            );
        }

        // foreach (array( 'hi', 'h', 'help', '帮助', '您好', '你好') as $keyword) {
        //     $weixin_builtin_replies[$keyword]    = array('type'=>'full', 'reply'=>'欢迎回复',            'function'=>'weixin_robot_welcome_reply');
        // }

        $weixin_builtin_replies = apply_filters('weixin_builtin_reply', $weixin_builtin_replies);

        set_transient('weixin_builtin_replies', $weixin_builtin_replies, 3600);
    }

    if ('all' == $type) {
        return $weixin_builtin_replies;
    } else {
        $weixin_builtin_replies_new = get_transient('weixin_builtin_replies_new');
        if (false === $weixin_builtin_replies_new) {
            $weixin_builtin_replies_new = array();
            foreach ($weixin_builtin_replies as $key => $weixin_builtin_reply) {
                $weixin_builtin_replies_new[$weixin_builtin_reply['type']][$key] = $weixin_builtin_reply;
            }
            set_transient('weixin_builtin_replies_new', $weixin_builtin_replies_new, 3600);
        }

        return isset($weixin_builtin_replies_new[$type]) ? $weixin_builtin_replies_new[$type] : array();
    }
}

function weixin_robot_get_default_reply_keywords()
{
    $default_reply_keywords = array(
        '[subscribe]'      => array('title' => '用户关注时', 'value' => '欢迎关注！'),
        '[event-location]' => array('title' => '进入服务号', 'value' => '欢迎再次进来！'),
        '[default]'        => array('title' => '没有匹配时', 'value' => '抱歉，没有找到相关的文章，要不你更换一下关键字，可能就有结果了哦 :-)'),
        '[too-long]'       => array('title' => '文本太长时', 'value' => '你输入的关键字太长了，系统没法处理了，请等待公众账号管理员到微信后台回复你吧。'),
        '[voice]'          => array('title' => '发送语音', 'value' => ''),
        '[location]'       => array('title' => '发送位置', 'value' => ''),
        '[image]'          => array('title' => '发送图片', 'value' => ''),
        '[link]'           => array('title' => '发送链接', 'value' => '已经收到你分享的信息，感谢分享。'),
        '[video]'          => array('title' => '发送视频', 'value' => '已经收到你分享的信息，感谢分享。'),
        '[shortvideo]'     => array('title' => '发送短视频', 'value' => '已经收到你分享的信息，感谢分享。'),
    );

    return $default_reply_keywords;
}

// 默认回复
function weixin_robot_default_reply($keyword)
{
    if (weixin_robot_custom_reply($keyword)) {
        return true;
    }

    global $wechatObj;

    $default_reply_keywords = weixin_robot_get_default_reply_keywords();
    $reply                  = isset($default_reply_keywords[$keyword]) ? $default_reply_keywords[$keyword]['value'] : '';
    $wechatObj->textReply($reply);
}

// 订阅回复
function weixin_robot_subscribe_reply($keyword)
{
    global $wechatObj;
    if ($openid = $wechatObj->get_weixin_openid()) {
        $weixin_user = array('subscribe' => 1);
        weixin_robot_update_user_subscribe($openid, $weixin_user);

        $postObj                  = $wechatObj->get_postObj();
        $subscribe_custom_keyword = '[subscribe]';
        if (WEIXIN_TYPE == 4 && !empty($postObj->EventKey)) {
            // 如果是认证服务号，并且是带参数二维码
            $scene                    = str_replace('qrscene_', '', $postObj->EventKey);
            $subscribe_custom_keyword = '[subscribe_' . $scene . ']';
            weixin_robot_qrcode_subscibe($openid, $scene);
            // do_action('weixin_subscribe', $openid, $scene);
        }
    }
    if (weixin_robot_custom_reply($subscribe_custom_keyword) == false) {
        weixin_robot_default_reply('[subscribe]');
        $wechatObj->set_response('subscribe');
    }
}

// 取消订阅回复
function weixin_robot_unsubscribe_reply($keyword)
{
    global $wechatObj;

    if ($openid = $wechatObj->get_weixin_openid()) {
        $weixin_user = array('subscribe' => 0, 'unsubscribe_time' => time());
        weixin_robot_update_user_subscribe($openid, $weixin_user);
    }

    // $wechatObj->textReply('你怎么忍心取消对我的订阅？');
    echo ' ';
    // $wechatObj->set_response('unsubscribe');
}

// 带参数二维码扫描回复
function weixin_robot_scan_reply($keyword)
{
    global $wechatObj;

    $postObj = $wechatObj->get_postObj();

    $scan_custom_keyword      = '[scan]';
    $subscribe_custom_keyword = '[subscribe]';
    if (WEIXIN_TYPE == 4 && !empty($postObj->EventKey)) {
        $scene                    = $postObj->EventKey;
        $openid                   = $wechatObj->get_weixin_openid();
        $scan_custom_keyword      = '[scan_' . $scene . ']';
        $subscribe_custom_keyword = '[subscribe_' . $scene . ']';
        weixin_robot_qrcode_subscibe($openid, $scene, 'scan');
        // do_action('weixin_scan', $openid, $scene, 'scan');
    }

    if (weixin_robot_custom_reply($scan_custom_keyword) == false &&
        weixin_robot_custom_reply($subscribe_custom_keyword) == false &&
        weixin_robot_custom_reply('[scan]') == false
    ) {
        weixin_robot_default_reply('[subscribe]');
    }

    // $wechatObj->set_response('scan');
}

// 服务号高级接口用户自动上传地理位置时的回复
function weixin_robot_location_event_reply($keyword)
{
    global $wechatObj, $wpdb;

    $weixin_openid    = $wechatObj->get_weixin_openid();
    $last_enter_reply = wp_cache_get($weixin_openid, 'weixin_enter_reply');
    $last_enter_reply = ($last_enter_reply) ? $last_enter_reply : 0;

    if (current_time('timestamp') - $last_enter_reply > apply_filters('weixin_enter_time', HOUR_IN_SECONDS * 8)) {
        weixin_robot_default_reply('[event-location]');
        wp_cache_set($weixin_openid, current_time('timestamp'), 'weixin_enter_reply', HOUR_IN_SECONDS);
    }
}

function weixin_robot_empty_string_reply()
{
    echo ' ';
}

function weixin_robot_verify_reply($keyword)
{
    global $wechatObj;

    $postObj = $wechatObj->get_postObj();

    if ('[qualification_verify_success]' == $keyword || '[naming_verify_success]' == $keyword || '[annual_renew]' == $keyword || '[verify_expired]' == $keyword) {
        $time = (string) $postObj->ExpiredTime;
        $time = get_date_from_gmt(date('Y-m-d H:i:s', $time));
        $type = 'updated';

        if ('[qualification_verify_success]' == $keyword) {
            $notice = '资质认证成功，你已经获取了接口权限，下次认证时间：' . $time . '！';
        } elseif ('[naming_verify_success]' == $keyword) {
            $notice = '名称认证成功，下次认证时间：' . $time . '！';
        } elseif ('[annual_renew]' == $keyword) {
            $notice = '你的账号需要年审了，到期时间：' . $time . '！';
        } elseif ('[verify_expired]' == $keyword) {
            $notice = '你的账号认证过期了，过期时间：' . $time . '！';
            $type   = 'error';
        }
    } else {
        $time   = (string) $postObj->FailTime;
        $time   = get_date_from_gmt(date('Y-m-d H:i:s', $time));
        $reason = (string) $postObj->FailReason;
        $type   = 'error';

        if ('[qualification_verify_fail]' == $keyword) {
            $notice = '资质认证失败，时间：' . $time . '，原因：' . $reason . '！';
        } elseif ('[naming_verify_fail]' == $keyword) {
            $notice = '名称认证失败，时间：' . $time . '，原因：' . $reason . '！';
        }
    }

    wpjam_add_admin_notice(array(
        'type'   => $type,
        'notice' => $notice,
    ));

    echo ' ';
}
