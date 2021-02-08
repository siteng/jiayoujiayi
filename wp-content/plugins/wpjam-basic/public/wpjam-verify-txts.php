<?php
class WPJAM_VerifyTXT{
	private static $verify_txts	= [];

	public static function register($key, $args){
		if(isset(self::$verify_txts[$key])){
			return new WP_Error('verify_txt_registered', '该验证txt文件已被注册');
		}

		self::$verify_txts[$key]	= $args;
	}

	public static function get_name($key){
		if(isset(self::$verify_txts[$key])){
			$value	= wpjam_get_setting('wpjam_verify_txts', $key);

			if($value){
				return $value['name'] ?? '';
			}
		}

		return '';
	}

	public static function get_value($key){
		if(isset(self::$verify_txts[$key])){
			$value	= wpjam_get_setting('wpjam_verify_txts', $key);

			if($value){
				return $value['value'] ?? '';
			}
		}

		return '';
	}

	public static function set($key, $name, $value){
		if(isset(self::$verify_txts[$key])){
			wpjam_update_setting('wpjam_verify_txts', $key, compact('name', 'value'));
			return true;
		}else{
			return false;
		}
	}

	public static function get_value_by_name($name){
		if($values = wpjam_get_option('wpjam_verify_txts')){
			$name	= str_replace('.txt', '', $name).'.txt';
			foreach ($values as $key => $value) {
				if($value['name'] == $name){
					return $value['value'];
				}
			}
		}

		return '';
	}

	public static function filter_root_rewrite_rules($root_rewrite){
		if(empty($GLOBALS['wp_rewrite']->root)){
			$home_path	= parse_url(home_url());

			if(empty($home_path['path']) || '/' == $home_path['path']){
				$root_rewrite	= array_merge(['([^/]+)\.txt?$'=>'index.php?module=txt&action=$matches[1]'], $root_rewrite);
			}
		}
		
		return $root_rewrite;
	}

	public static function module($action){
		if($value	= self::get_value_by_name($action)){
			header('Content-Type: text/plain');
			echo $value;

			exit;
		}

		wp_die('错误');
	}
}

function wpjam_register_verify_txt($key, $args){
	WPJAM_VerifyTXT::register($key, $args);
}

wpjam_register_route_module('txt',	['callback'=>['WPJAM_VerifyTXT', 'module']]);

add_filter('root_rewrite_rules',	['WPJAM_VerifyTXT', 'filter_root_rewrite_rules']);


