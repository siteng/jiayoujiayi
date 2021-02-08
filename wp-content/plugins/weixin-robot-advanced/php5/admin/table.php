<?php

register_activation_hook(WEIXIN_ROBOT_PLUGIN_FILE, 'weixin_robot_activation');
function weixin_robot_activation()
{

    flush_rewrite_rules();

    $administrator = get_role('administrator');
    $administrator->add_cap('view_weixin');
    $administrator->add_cap('edit_weixin');
    $administrator->add_cap('delete_weixin');
    $administrator->add_cap('masssend_weixin');
    $administrator->add_cap('delete_weixin_material');

    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    if ($wpdb->get_var("show tables like '$wpdb->weixin_messages'") != $wpdb->weixin_messages) {
        $sql = "
		CREATE TABLE IF NOT EXISTS " . $wpdb->weixin_messages . " (
		  `id` bigint(20) NOT NULL auto_increment,
		  `MsgId` bigint(64) NOT NULL,
		  `FromUserName` varchar(30) NOT NULL,
		  `MsgType` varchar(10) NOT NULL,
		  `CreateTime` int(10) NOT NULL,
		  `Content` longtext NOT NULL,
		  `Event` varchar(255) NOT NULL,
		  `EventKey` varchar(255) NOT NULL,
		  `Title` text NOT NULL,
		  `Url` varchar(255) NOT NULL,
		  `MediaId` text NOT NULL,
		  `Response` varchar(255) NOT NULL,
		  PRIMARY KEY (`id`)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		";

        dbDelta($sql);
    }

    if ($wpdb->get_var("show tables like '{$wpdb->weixin_users}'") != $wpdb->weixin_users) {
        $sql = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_users}` (
		  `openid` varchar(30) NOT NULL,
		  `nickname` varchar(255) NOT NULL,
		  `subscribe` int(1) NOT NULL default '1',
		  `subscribe_time` int(10) NOT NULL,
		  `unsubscribe_time` int(10) NOT NULL,
		  `sex` int(1) NOT NULL,
		  `city` varchar(255) NOT NULL,
		  `country` varchar(255) NOT NULL,
		  `province` varchar(255) NOT NULL,
		  `language` varchar(255) NOT NULL,
		  `headimgurl` varchar(255) NOT NULL,
		  `groupid` int(4) NOT NULL,
		  `tagid_1` int(4) NOT NULL,
		  `tagid_2` int(4) NOT NULL,
		  `tagid_3` int(4) NOT NULL,
		  `privilege` text NOT NULL,
		  `unionid` varchar(30) NOT NULL,
		  `remark` text NOT NULL,
		  `os` varchar(32) NOT NULL,
		  `os_ver` varchar(8) NOT NULL,
		  `weixin_ver` varchar(8) NOT NULL,
		  `device` varchar(64) NOT NULL,
		  `ip` varchar(23) NOT NULL,
		  `credit` int(10) NOT NULL,
		  `exp` int(10) NOT NULL,
		  `last_update` int(10) NOT NULL,
		  PRIMARY KEY  (`openid`)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		";

        dbDelta($sql);
    }

    if ($wpdb->get_var("show tables like '{$wpdb->weixin_custom_replies}'") != $wpdb->weixin_custom_replies) {
        $sql = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_custom_replies}` (
		  `id` bigint(20) NOT NULL auto_increment,
		  `keyword` varchar(255) NOT NULL,
		  `match` varchar(10) NOT NULL default 'full',
		  `reply` text NOT NULL,
		  `status` int(1) NOT NULL default '1',
		  `time` datetime NOT NULL default '0000-00-00 00:00:00',
		  `type` varchar(10) NOT NULL default 'text',
		  PRIMARY KEY  (`id`)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		";

        dbDelta($sql);
    }

    if ($wpdb->get_var("show tables like '{$wpdb->weixin_menus}'") != $wpdb->weixin_menus) {
        $sql = "
		CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_menus}` (
		  `id` bigint(20) NOT NULL auto_increment,
		  `blog_id` bigint(20) NOT NULL,
		  `menuid` bigint(20) NOT NULL,
		  `name` varchar(255) NOT NULL,
		  `button` longtext NOT NULL,
		  `matchrule` text NOT NULL,
		  `type` varchar(15) NOT NULL,
		  PRIMARY KEY  (`id`)
		) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		";

        dbDelta($sql);
    }

    if (WEIXIN_TYPE == 4) {
        if ($wpdb->get_var("show tables like '$wpdb->weixin_qrcodes'") != $wpdb->weixin_qrcodes) {
            $sql = "
			CREATE TABLE IF NOT EXISTS  `{$wpdb->weixin_qrcodes}` (
			  `id` bigint(20) NOT NULL auto_increment,
			  `scene` varchar(64) NOT NULL,
			  `name` varchar(255) NOT NULL,
			  `type` varchar(31) NOT NULL,
			  `ticket` text NOT NULL,
			  `expire` int(10) NOT NULL,
			  PRIMARY KEY  (`id`)
			) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			";

            dbDelta($sql);
        }

        if ($wpdb->get_var("show tables like '$wpdb->weixin_subscribes'") != $wpdb->weixin_subscribes) {
            $sql = "
			CREATE TABLE IF NOT EXISTS  `{$wpdb->weixin_subscribes}` (
			  `id` bigint(20) NOT NULL auto_increment,
			  `openid` varchar(30) NOT NULL,
			  `scene` varchar(64) NOT NULL,
			  `type` varchar(16) NOT NULL,
 			  `time` int(10) NOT NULL,
			  PRIMARY KEY  (`id`)
			) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			";

            dbDelta($sql);
        }
    }

    if (isset($_GET['crm'])) {
        if ($wpdb->get_var("show tables like '{$wpdb->weixin_crm_users}'") != $wpdb->weixin_crm_users) {
            $sql = "
			CREATE TABLE IF NOT EXISTS `{$wpdb->weixin_crm_users}` (
			  `id` bigint(20) NOT NULL auto_increment,
			  `weixin_openid` varchar(30) NOT NULL,
			  `name` varchar(50) NOT NULL COMMENT '姓名',
			  `description` text NOT NULL COMMENT '描述',
			  `phone` varchar(20) NOT NULL COMMENT '电话号码',
			  `email` varchar(255) NOT NULL COMMENT '邮箱',
			  `id_card` varchar(18) NOT NULL COMMENT '身份证',
			  `registered_time` datetime NOT NULL,
			  `sex` int(1) NOT NULL,
			  `country` varchar(255) NOT NULL,
			  `province` varchar(255) NOT NULL,
			  `city` varchar(255) NOT NULL,
			  `address` text NOT NULL COMMENT '地址',
			  `language` varchar(255) NOT NULL,
			  PRIMARY KEY  (`id`),
			  UNIQUE KEY `weixin_openid` (`weixin_openid`)
			) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			";

            dbDelta($sql);
        }
    }
}

function weixin_robot_table_exist($table)
{
    global $wpdb;
    if ($wpdb->get_var("show tables like '$table'") == $table) {
        return true;
    }
    return false;
}

function weixin_robot_table_column_exist($table, $column)
{
    global $wpdb;
    if ($wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE '$column'") == $column) {
        return true;
    }
    return false;
}

function weixin_robot_table_add_column($table, $column, $data_type)
{
    global $wpdb;

    if (!weixin_robot_table_exist($wpdb->weixin_users)) {
        return;
    }

    if (weixin_robot_table_column_exist($table, $column)) {
        return;
    }

    $sql = "ALTER TABLE $table ADD COLUMN $column $data_type";
    $wpdb->query($sql);
}


function weixin_robot_update_table()
{
    global $wpdb;

    weixin_robot_table_add_column($wpdb->weixin_users, 'tagid_1', 'int(4) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'tagid_2', 'int(4) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'tagid_3', 'int(4) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'groupid', 'int(4) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'os', 'varchar(32) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'os_ver', 'varchar(8) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'weixin_ver', 'varchar(8) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'device', 'varchar(64) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'ip', 'varchar(23) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'credit', 'int(10) NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_users, 'exp', 'int(10) NOT NULL');

    weixin_robot_table_add_column($wpdb->weixin_messages, 'Title', 'text NOT NULL');
    weixin_robot_table_add_column($wpdb->weixin_messages, 'Url', 'varchar(255) NOT NULL');
}

add_filter('wpmu_drop_tables', 'weixin_robot_wpmu_drop_tables', 10, 2);
function weixin_robot_wpmu_drop_tables($tables, $blog_id)
{
    global $wpdb;
    $blog_prefix = $wpdb->get_blog_prefix($blog_id);
    foreach (weixin_robot_get_tables() as $function => $weixin_tables) {
        foreach ($weixin_tables as $weixin_table_name => $weixin_table_title) {
            $tables[] = $blog_prefix . $weixin_table_name;
        }
    }
    return $tables;
}
