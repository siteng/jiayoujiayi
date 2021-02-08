<?php
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/setting.php');            // 设置
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/custom-replies.php');    // 自定义回复

if (WEIXIN_TYPE >= 2) {
    include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/menu.php');            // 自定义菜单
}

if (WEIXIN_TYPE == 4) {
    include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/qrcode.php');        // 渠道管理，带参数二维码
}

if (WEIXIN_TYPE >= 3) {
    include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/masssend.php');        // 群发功能
    include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/material.php');        // 素材管理
}

include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/users.php');                // 用户管理
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/user-stats.php');        // 用户统计
// include(WEIXIN_ROBOT_PLUGIN_DIR.'admin/user-group.php');		// 用户分组
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/user-tag.php');            // 用户标签
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/user.php');                // 用户详情
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/messages.php');            // 消息管理
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/customservice.php');        // 客服管理
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/message-stats.php');        // 消息统计
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/dashboard.php');            // 数据预览
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/extend.php');            // 扩展管理和数据检测
include(WEIXIN_ROBOT_PLUGIN_DIR . 'admin/table.php');                // 数据库

weixin_robot_include_extends($adimn = true);                        // 加载扩展

// 后台菜单
add_filter('wpjam_pages', 'weixin_robot_admin_pages');
function weixin_robot_admin_pages($wpjam_pages)
{

    $base_menu = 'weixin-robot';
    $weixin_menu_icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCwgMCwgNDAwLCA0MDAiPgogIDxnIGlkPSJMYXllciAxIj4KICAgIDxwYXRoIGQ9Ik0xMjAuNjAxLDQ2LjU3NiBDOS4yNDEsNjYuNDY2IC0yNy44NzksMTkyLjI4MSA2MC43LDI0OS44NjkgQzY1LjU1NywyNTIuOTkxIDY1LjU1NywyNTIuNjQ1IDU4LjI3MSwyNzQuMzg1IEw1Mi4wMjcsMjkzLjAwMiBMNzQuNDYxLDI4MC45NzYgTDk2Ljg5NSwyNjguOTUgTDEwOC44MDYsMjcxLjg0MSBDMTIxLjI5NCwyNzQuOTYzIDEzNy4yNTMsMjc3LjE2IDE0Ny44OTEsMjc3LjE2IEwxNTQuMjUyLDI3Ny4xNiBMMTUyLjA1NCwyNjguNzE4IEMxMzQuNTkzLDIwNC40MjMgMTk0Ljk1NiwxNDAuNzA2IDI3My40NzUsMTQwLjcwNiBMMjg0LjExNCwxNDAuNzA2IEwyODEuOTE3LDEzMy4wNzQgQzI2NC42ODYsNzIuODI2IDE5MS45NSwzMy44NTYgMTIwLjYwMSw0Ni41NzYgeiBNMTEwLjg4NywxMDIuODkyIEMxMjIuNjgyLDExMC44NzIgMTIzLjM3NiwxMjguMTAyIDExMi4wNDMsMTM1LjUwMyBDOTMuNjU3LDE0Ny41MjkgNzIuMTQ4LDEyNi4zNjcgODQuNTIxLDEwOC4zMjcgQzg5Ljk1NiwxMDAuMjMzIDEwMy4wMjQsOTcuNTczIDExMC44ODcsMTAyLjg5MiB6IE0yMDUuNzExLDEwMi44OTIgQzIyNS4xMzgsMTE1Ljk2IDIxMC41NjgsMTQ2LjE0MSAxODguODI3LDEzNy44MTUgQzE3My4xMDEsMTMxLjgwMiAxNzEuMjUsMTEwLjE3OCAxODUuOTM2LDEwMi40MyBDMTkxLjcxOCw5OS4zMDggMjAwLjczOCw5OS41MzkgMjA1LjcxMSwxMDIuODkyIHogTTI0OC42MTMsMTUwLjUzNiBDMTkzLjQ1MywxNjAuNTk2IDE1NS4xNzcsMjAyLjQ1NyAxNTcuMzc0LDI1MC41NjMgQzE2MC4yNjUsMzE0Ljk3NCAyMzUuNzc3LDM1OS4zNzkgMzA4LjI4MiwzMzkuNDg5IEwzMTYuODM5LDMzNy4xNzYgTDMzNC44NzksMzQ2Ljg5IEMzNDQuODI0LDM1Mi4zMjUgMzUzLjE1LDM1Ni4yNTcgMzUzLjM4MSwzNTUuNzk0IEMzNTMuNjEzLDM1NS4yMTYgMzUxLjY0NywzNDguMjc4IDM0OS4xMDMsMzQwLjI5OSBDMzQzLjMyMSwzMjIuNDkgMzQzLjIwNSwzMjMuNzYyIDM1MC45NTMsMzE4LjIxMiBDNDM4LjE0NCwyNTUuNjUxIDM2MS41OTIsMTMwLjA2OCAyNDguNjEzLDE1MC41MzYgeiBNMjQ2LjQxNiwyMDIuNDU3IEMyNTEuMjcyLDIwNS42OTUgMjUzLjgxNiwyMTMuNzkgMjUxLjczNSwyMTkuNjg4IEMyNDcuMzQxLDIzMi4yOTIgMjI4LjQ5MiwyMzMuMjE3IDIyMy40MDMsMjIxLjA3NSBDMjE3LjYyMSwyMDcuMDgzIDIzMy41OCwxOTQuMTMxIDI0Ni40MTYsMjAyLjQ1NyB6IE0zMjMuNjYyLDIwMy44NDUgQzMzMS4yOTQsMjExLjEzIDMzMC4wMjIsMjIzLjUwNCAzMjEuMTE4LDIyOC4xMjkgQzMwNy40NzMsMjM1LjA2NyAyOTMuMTM0LDIyMS4xOTEgMzAwLjE4OCwyMDcuODkyIEMzMDQuODEzLDE5OS4zMzUgMzE2LjcyNCwxOTcuMjU0IDMyMy42NjIsMjAzLjg0NSB6IE0yMjAuNDMsMzI4Ljc3MiIgZmlsbD0iI2ZmZiIvPgogIDwvZz4KICA8ZGVmcy8+Cjwvc3ZnPg==';
    $weixin_robot_name = apply_filters('weixin_robot_name', '微信管理');

    // 微信管理菜单
    $subs = array();

    $subs[$base_menu] = array('menu_title' => '设置', 'function' => 'option');
    $subs[$base_menu . '-replies'] = array('menu_title' => '自定义回复', 'function' => 'tab');

    if (WEIXIN_TYPE >= 2) {
        $subs[$base_menu . '-menu'] = array('menu_title' => '自定义菜单', 'function' => 'tab');
    }

    if (WEIXIN_TYPE >= 3) {

        $subs[$base_menu . '-masssend'] = array('menu_title' => '群发消息', 'function' => 'tab');
        $subs[$base_menu . '-material'] = array('menu_title' => '素材管理', 'function' => 'tab');


        if (WEIXIN_TYPE == 4) {
            $subs[$base_menu . '-qrcode'] = array('menu_title' => '渠道管理');
        }

        $subs[$base_menu . '-users'] = array('menu_title' => '用户管理', 'function' => 'tab');

        if (!empty($_GET['openid']) || (isset($_GET['page']) && $_GET['page'] == $base_menu . '-user')) {
            $subs[$base_menu . '-user'] = array('menu_title' => '用户详情');
        }

        $subs[$base_menu . '-messages'] = array('menu_title' => '消息管理');

//        if (weixin_robot_get_setting('weixin_dkf')) {
//            $subs[$base_menu . '-customservice'] = array('menu_title' => '客服管理');
//        }
    }

    $subs = apply_filters('weixin_sub_pages', $subs);

    $subs[$base_menu . '-extend'] = array('menu_title' => '扩展管理', 'function' => 'tab');

    foreach ($subs as $menu_slug => $sub) {
        $subs[$menu_slug]['capability'] = 'view_weixin';

        if ($menu_slug == 'weixin-robot-extend') {
            $subs[$menu_slug]['capability'] = 'manage_options';
        }
    }

    $wpjam_pages[$base_menu] = array(
        'menu_title' => $weixin_robot_name,
        'function' => 'option',
        'icon' => $weixin_menu_icon,
        'capability' => 'view_weixin',
        'position' => '2.1.1',
        'subs' => $subs
    );


    // 微果酱统计菜单
    $subs = array();

    $subs[$base_menu . '-stats'] = array('menu_title' => '数据预览', 'function' => 'dashboard');
    $subs[$base_menu . '-users-stats'] = array('menu_title' => '用户统计分析', 'function' => 'tab');
    if (WEIXIN_TYPE >= 2) $subs[$base_menu . '-menu-stats'] = array('menu_title' => '自定义菜单统计', 'function' => 'tab');
    if (WEIXIN_TYPE == 4) $subs[$base_menu . '-qrcode-stats'] = array('menu_title' => '渠道统计分析');
    $subs[$base_menu . '-messages-stats'] = array('menu_title' => '消息统计分析', 'function' => 'tab');

    $subs = apply_filters('weixin_stats_sub_pages', $subs);

    foreach ($subs as $menu_slug => $sub) {
        $subs[$menu_slug]['capability'] = 'view_weixin';
    }

    $wpjam_pages[$base_menu . '-stats'] = array(
        'menu_title' => '微信统计',
        'function' => 'dashboard',
        'icon' => 'dashicons-chart-pie',
        'capability' => 'view_weixin',
        'position' => '2.1.2',
        'subs' => $subs
    );

    if (!weixin_robot_check_domain()) {
        unset($wpjam_pages[$base_menu . '-stats']);
        unset($wpjam_pages[$base_menu]['subs']);
        $wpjam_pages[$base_menu]['function'] = 'weixin_robot_verify_page';
    }

    return $wpjam_pages;
}

function weixin_robot_check_domain(){
    $domain = parse_url(home_url(), PHP_URL_HOST);
    if(get_option('wpjam_net_domain_check_56') == md5($domain.'56')){
        return true;
    }

    $weixin_user = wpjam_topic_get_weixin_user();

    if($weixin_user && $weixin_user['subscribe']){
        return true;
    }

    return false;
}

function weixin_robot_verify_page() {
    global $current_admin_url;
    $current_admin_url = admin_url('admin.php?page=weixin-robot');
    wpjam_topic_setting_page('微信机器人','<p>请使用微信扫描下面的二维码，获取验证码之后提交即可验证通过！</p>');
}

add_action('admin_init', 'weixin_robot_admin_init');
function weixin_robot_admin_init()
{
    global $plugin_page;
    if ($plugin_page == 'weixin-robot-user' && empty($_GET['openid'])) {
        wp_redirect(admin_url('admin.php?page=weixin-robot-users'));
        exit;
    }
}

// 在插件页面添加快速设置链接
add_filter('plugin_action_links_' . plugin_basename(WEIXIN_ROBOT_PLUGIN_FILE), 'weixin_robot_plugin_action_links', 10, 2);
function weixin_robot_plugin_action_links($links, $file)
{
    $links['setting'] = '<a href="' . admin_url('admin.php?page=weixin-robot') . '">设置</a>';

    if (strpos(WEIXIN_ROBOT_PLUGIN_FILE, 'weixin-robot-test')) {
        $links['info'] = '<span style="color:red; font-weight:bold;">测试版</span>';
    } else {
        $links['info'] = '<span style="color:green; font-weight:bold;">正式版</span>';
    }

    return array_reverse($links);
}

add_filter('pre_update_option_active_plugins', 'weixin_robot_set_plugin_load_late');
function weixin_robot_set_plugin_load_late($active_plugins)
{

    $weixin_plugin = plugin_basename(WEIXIN_ROBOT_PLUGIN_FILE);
    if (false !== ($plugin_key = array_search($weixin_plugin, $active_plugins))) {
        unset($active_plugins[$plugin_key]);
        $new_active_plugins = array();
        if ($active_plugins) {
            foreach ($active_plugins as $active_plugin) {
                $new_active_plugins[] = $active_plugin;
            }
        }

        $new_active_plugins[] = $weixin_plugin;

        return $new_active_plugins;
    }

    return $active_plugins;

}


add_action('delete_blog', 'weixin_robot_delete_blog');
function weixin_robot_delete_blog($blog_id)
{
    global $wpdb;
    $wpdb->delete($wpdb->weixin_menus, array('blog_id' => $blog_id));
}