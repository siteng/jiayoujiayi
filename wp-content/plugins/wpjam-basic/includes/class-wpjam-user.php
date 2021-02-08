<?php
class WPJAM_User{
	private $user_id;

	private static $instances	= [];

	private function __construct($user_id){
		$this->user_id	= (int)$user_id;
	}

	public function add_role($role, $blog_id=0){
		$wp_error	= null;

		$switched	= (is_multisite() && $blog_id) ? switch_to_blog($blog_id) : false;	// 不同博客的用户角色不同
		$user		= get_userdata($this->user_id);

		if($user->roles){
			if(!in_array($role, $user->roles)){
				$wp_error	= new WP_Error('role_added', '你已有权限，如果需要更改权限，请联系管理员直接修改。');
			}
		}else{
			$user->add_role($role);
		}

		if($switched){
			restore_current_blog();
		}

		return $wp_error ?? $user;
	}

	public function update_avatarurl($avatarurl){
		if(get_user_meta($this->user_id, 'avatarurl', true) != $avatarurl){
			update_user_meta($this->user_id, 'avatarurl', $avatarurl);
		}

		return true;
	}

	public function update_nickname($nickname){
		if(get_userdata($this->user_id)->nickname != $nickname){
			self::update($this->user_id, ['nickname'=>$nickname, 'display_name'=>$nickname]);
		}

		return true;
	}

	public function get_openid($name, $appid=''){
		$meta_key	= $this->get_bind_key($name, $appid);

		return get_user_meta($this->user_id, $meta_key, true);
	}

	public function bind($name, $appid='', $openid=''){
		$meta_key	= $this->get_bind_key($name, $appid);

		if($openid){
			if($this->get_openid($name, $appid) != $openid){
				return update_user_meta($this->user_id, $meta_key, $openid);
			}else{
				return true;
			}
		}else{
			$openid	= $this->get_openid($name, $appid);

			delete_user_meta($this->user_id, $meta_key);

			return $openid;
		}
	}

	public function unbind($name, $appid=''){
		return $this->bind($name, $appid);
	}

	public function login(){
		$user	= get_userdata($this->user_id);

		wp_set_auth_cookie($this->user_id, true, is_ssl());
		wp_set_current_user($this->user_id);
		do_action('wp_login', $user->user_login, $user);
	}

	public static function insert($data){
		return wp_insert_user(wp_slash($data));
	}

	public static function update($user_id, $data){
		$data['ID'] = $user_id;

		return wp_update_user(wp_slash($data));
	}

	public static function create($args){
		$args	= wp_parse_args($args, [
			'users_can_register'	=> get_option('users_can_register'),
			'user_pass'				=> wp_generate_password(12, false),
			'user_login'			=> '',
			'user_email'			=> '',
			'nickname'				=> '',
			'avatarurl'				=> '',
			'role'					=> '',
			'blog_id'				=> 0
		]);

		if(empty($args['users_can_register'])){
			return new WP_Error('register_disabled', '系统不开放注册，请联系管理员！');
		}

		$args['user_login']	= preg_replace('/\s+/', '', sanitize_user($args['user_login'], true));

		if(empty($args['user_login'])){
			return new WP_Error('empty_user_login', '用户名不能为空。');
		}

		if(empty($args['user_email'])){
			return new WP_Error('empty_user_email', '用户邮箱不能为空。');
		}

		$register_lock	= wp_cache_get($args['user_login'].'_register_lock', 'users');

		if($register_lock !== false){
			return new WP_Error('user_register_locked', '该用户名正在注册中，请稍后再试！');
		}

		$result	= wp_cache_add($args['user_login'].'_register_lock', 1, 'users', 15);

		if($result === false){
			return new WP_Error('user_register_locked', '该用户名正在注册中1，请稍后再试！');
		}

		$userdata	= wp_array_slice_assoc($args, ['user_login', 'user_pass', 'user_email']);

		if(!empty($args['nickname'])){
			$userdata['nickname']	= $userdata['display_name']	= $args['nickname'];
		}

		$switched	= (is_multisite() && $args['blog_id']) ? switch_to_blog($args['blog_id']) : false;

		$userdata['role']	= $args['role'] ?: get_option('default_role');

		$user_id	= self::insert($userdata);

		if($switched){
			restore_current_blog();
		}

		if(is_wp_error($user_id)){
			return $user_id;
		}

		return $user_id;
	}

	public static function get_bind_key($name, $appid=''){
		return $appid ? $name.'_'.$appid : $name;
	}

	public static function get_by_openid($name, $appid='', $openid){
		$meta_key	= self::get_bind_key($name, $appid);

		if($users = get_users(['meta_key'=>$meta_key, 'meta_value'=>$openid])){
			return current($users)->ID;
		}else{
			return username_exists($openid);
		}
	}

	public static function update_meta($user_id, $meta_key, $meta_value){
		if($meta_value){
			return update_user_meta($user_id, $meta_key, wp_slash($meta_value));
		}else{
			return delete_user_meta($user_id, $meta_key);
		}
	}

	public static function get_instance($user_id){
		$user	= self::get_user($user_id);

		if(!($user instanceof WP_User)){
			return new WP_Error('user_not_exists', '用户不存在');
		}

		if(!isset($instances[$user_id])){
			$instances[$user_id]	= new self($user_id);
		}

		return $instances[$user_id];
	}

	public static function get_user($user){
		if($user && is_numeric($user)){	// 不存在情况下的缓存优化
			$user_id	= $user;
			$found		= false;
			$cache		= wp_cache_get($user_id, 'users', false, $found);

			if($found){
				return $cache ? get_userdata($user_id) : $cache;
			}else{
				$user	= get_userdata($user_id);

				if(!$user){	// 防止重复 SQL 查询。
					wp_cache_add($user_id, false, 'users', 10);
				}
			}
		}

		return $user;
	}

	public static function get_current_user(){
		return apply_filters('wpjam_current_user', null);
	}

	public static function get_current_commenter(){
		$commenter	= wp_get_current_commenter();

		if(!is_wp_error($commenter) && empty($commenter['comment_author_email'])){
			return new WP_Error('empty_comment_author', '登录之后才能操作');
		}

		return $commenter;
	}

	public static function filter_current_user($user_id){
		if(empty($user_id)){
			$wpjam_user	= self::get_current_user();

			if($wpjam_user && !is_wp_error($wpjam_user) && !empty($wpjam_user['user_id'])){
				return $wpjam_user['user_id'];
			}
		}

		return $user_id;
	}

	public static function filter_current_commenter($commenter){
		if(empty($commenter['comment_author_email'])){
			$wpjam_user	= self::get_current_user();

			if(is_wp_error($wpjam_user)){
				return $wpjam_user;
			}elseif(empty($wpjam_user) || empty($wpjam_user['user_email'])){
				return new WP_Error('bad_authentication', '无权限');
			}else{
				$commenter['comment_author_email']	= $wpjam_user['user_email'];
				$commenter['comment_author']		= $wpjam_user['nickname'];
			}
		}

		return $commenter;
	}
}