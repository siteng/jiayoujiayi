<?php

/**
 * WordPress基础配置文件。
 *
 * 这个文件被安装程序用于自动生成wp-config.php配置文件，
 * 您可以不使用网站，您需要手动复制这个文件，
 * 并重命名为“wp-config.php”，然后填入相关信息。
 *
 * 本文件包含以下配置选项：
 *
 * * MySQL设置
 * * 密钥
 * * 数据库表名前缀
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/zh-cn:%E7%BC%96%E8%BE%91_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL 设置 - 具体信息来自您正在使用的主机 ** //
/** WordPress数据库的名称 */
define('DB_NAME', 'sjiayi');

/** MySQL数据库用户名 */
define('DB_USER', 'sjiayi');

/** MySQL数据库密码 */
define('DB_PASSWORD', '891228xx.');

/** MySQL主机 */
define('DB_HOST', 'localhost');

/** 创建数据表时默认的文字编码 */
define('DB_CHARSET', 'utf8mb4');

/** 数据库整理类型。如不确定请勿更改 */
define('DB_COLLATE', '');

/**#@+
 * 身份认证密钥与盐。
 *
 * 修改为任意独一无二的字串！
 * 或者直接访问{@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org密钥生成服务}
 * 任何修改都会导致所有cookies失效，所有用户将必须重新登录。
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '2&r1t{TVT}%`?D(!0YriT8%Y5TbU*ju#V7K_KVZ$LFb2Nf}n~=FFLKw*cpDcWfTv');
define('SECURE_AUTH_KEY',  ']-esi3nRJE_^K?-8NM,}~1:Q1>6u/3*vVB]YvN{.49C|S3ome@:ZUd3l4C,X &A+');
define('LOGGED_IN_KEY',    '5z-zpF) )zsl!SdwIknaX!NE5l}Zw&8m(Y(s3JeZA7Q[Du[FD@l0r2)WG(oyEIWY');
define('NONCE_KEY',        'dDn{K>L1fX57Zsd]gCw0^:tZTPJ5?gz`WZ~bal}T7kxP2m)q3~#&V>Lg{D{,y7Rm');
define('AUTH_SALT',        'l&G#K2ui1:7}dtDOSrJl-&SQ$F1v0E?FVq&dB[DUO&6.dUM*R;6I-kX56A?h[PA0');
define('SECURE_AUTH_SALT', 'nncw2A_frT3.`b&uLfyY6wrWBgaw|x%;QDPZJ;WI[i9*nl6i}cQ%5x$`I?rBl.BW');
define('LOGGED_IN_SALT',   'IP6Db;1xuktSg76^b_C.W3+{d[yL`eL^w^2RI+=0oYF1=#9%{iKg%M>4#KADBKH6');
define('NONCE_SALT',       '~qq:;JG6gncT_=VV/xO[#Ul.H;+#Lt+d-!ZmMFKUfXHEcKrh]9fIz(=#u9n:-KZ$');

/**#@-*/

/**
 * WordPress数据表前缀。
 *
 * 如果您有在同一数据库内安装多个WordPress的需求，请为每个WordPress设置
 * 不同的数据表前缀。前缀名只能为数字、字母加下划线。
 */
$table_prefix  = 'jiayi_';

/**
 * 开发者专用：WordPress调试模式。
 *
 * 将这个值改为true，WordPress将显示所有用于开发的提示。
 * 强烈建议插件开发者在开发环境中启用WP_DEBUG。
 *
 * 要获取其他能用于调试的信息，请访问Codex。
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* 好了！请不要再继续编辑。请保存本文件。使用愉快！ */

/** WordPress目录的绝对路径。 */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** 设置WordPress变量和包含文件。 */
require_once(ABSPATH . 'wp-settings.php');
/**设置WorePress缓存**/
/**设置SMTP发信**/

