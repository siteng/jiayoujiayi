<?php
class WPJAM_JSON{
	private $json;
	private $args;

	public function __construct($json, $args){
		$this->json	= $json;
		$this->args	= $args;
	}

	public function __get($key){
		return $this->args[$key] ?? null;;
	}

	public function response(){
		if($this->quota){
			$today	= date('Y-m-d', current_time('timestamp'));
			$times	= (int)wp_cache_get($this->json.':'.$today, 'wpjam_api_times');

			if($times < $this->quota){
				wp_cache_set($this->json.':'.$today, $times+1, 'wpjam_api_times', DAY_IN_SECONDS);
			}else{
				wpjam_send_json(['errcode'=>'api_exceed_quota', 'errmsg'=>'API 调用次数超限']);
			}
		}

		if($this->token){
			$token	= apply_filters('wpjam_json_token', null, $this->args, $this->json);

			if(is_wp_error($token)){
				wpjam_send_json($token);
			}else{
				wpjam_send_json(['access_token'=>$token, 'expires_in'=>7200]);
			}
		}elseif($this->grant){
			$result	= apply_filters('wpjam_json_grant', null, $this->args, $this->json);
			
			if(is_wp_error($result)){
				wpjam_send_json($result);
			}
		}

		$response	= ['errcode'=>0];

		$response['current_user']	= $this->get_current_user();

		if($_SERVER['REQUEST_METHOD'] != 'POST'){
			$response['page_title']		= $this->page_title ?? '';
			$response['share_title']	= $this->share_title ?? '';
			$response['share_image']	= $this->share_image ? wpjam_get_thumbnail($this->share_image, '500x400') : '';
		}

		if($this->modules){
			foreach($this->modules as $module){
				$this->parse_module($module, $response);
			}
		}elseif($this->callback){
			if(is_callable($this->callback)){
				$result	= call_user_func($this->callback, $this->args, $this->json);

				if(is_wp_error($result)){
					wpjam_send_json($result);
				}elseif(is_array($result)){
					$response += $result;
				}
			}
		}elseif($this->template){
			if(is_file($this->template)){
				$result	= include $this->template;

				if(is_wp_error($result)){
					wpjam_send_json($result);
				}elseif(is_array($result)){
					$response += $result;
				}
			}
		}else{
			$response	+= $this->args;
		}

		wpjam_send_json(apply_filters('wpjam_json', $response, $this->args, $this->json));
	}

	public function parse_module($module, &$response){
		if(empty($module['args'])){
			return;
		}

		$module_type	= $module['type'] ?? '';
		$module_args	= $module['args'];

		if(!is_array($module_args)){
			$module_args = wpjam_parse_shortcode_attr(stripslashes_deep($module_args), 'module');
		}

		if($module_type == 'post_type'){
			$this->parse_post_type_module($module_args, $response);
		}elseif($module_type == 'taxonomy'){
			$this->parse_taxonomy_module($module_args, $response);
		}elseif($module_type == 'setting'){
			$this->parse_setting_module($module_args, $response);
		}elseif($module_type == 'media'){
			$this->parse_media_module($module_args, $response);
		}elseif($module_type == 'other'){
			$response	= array_merge($response, $module_args);
		}else{
			if(!empty($module_args['callback'])){
				if(is_callable($module_args['callback'])){
					$result	= call_user_func($module_args['callback'], $module_args);

					if(is_wp_error($result)){
						wpjam_send_json($result);
					}elseif(is_array($result)){
						$response += $result;
					}
				}
			}elseif(!empty($module_args['template'])){
				if(is_file($module_args['template'])){
					include $module_args['template'];
				}
			}
		}
	}

	public function parse_post_type_module($module_args, &$response){
		$module_action	= $module_args['action'] ?? '';

		if(empty($module_action)){
			wpjam_send_json(['errcode'=>'empty_action',	'errmsg'=>'没有设置 action']);
		}

		$output	= $module_args['output'] ?? '';

		global $wp, $wpjam_query_vars;	// 两个 post 模块的时候干扰。。。。

		if(empty($wpjam_query_vars)){
			$wpjam_query_vars	= $wp->query_vars; 
		}else{
			$wp->query_vars		= $wpjam_query_vars;
		}

		$post_type	= $module_args['post_type'] ?? wpjam_get_parameter('post_type');
		$args		= $module_args;

		if(in_array($module_action, ['list', 'get'])){
			$post_template	= WPJAM_BASIC_PLUGIN_DIR.'api/post.'.$module_action.'.php';
		}elseif($module_action == 'upload'){
			$post_template	= WPJAM_BASIC_PLUGIN_DIR.'api/media.'.$module_action.'.php';
		}else{
			$post_template	= $module_args['template'] ?? '';
		}

		if($post_template && is_file($post_template)){
			include $post_template;
		}
	}

	public function parse_taxonomy_module($module_args, &$response){
		$taxonomy	= $module_args['taxonomy'] ?? '';

		if(empty($taxonomy)){
			wpjam_send_json(['errcode'=>'empty_taxonomy',	'errmsg'=>'自定义分类未设置']);
		}

		$tax_obj	= get_taxonomy($taxonomy);

		if(empty($tax_obj)){
			wpjam_send_json(['errcode'=>'invalid_taxonomy',	'errmsg'=>'无效的自定义分类']);
		}

		$args	= $module_args;

		if(isset($args['mapping'])){
			$mapping	= wp_parse_args($args['mapping']);

			if($mapping && is_array($mapping)){
				foreach ($mapping as $key => $get) {
					if($value = wpjam_get_parameter($get)){
						$args[$key]	= $value;
					}
				}
			}

			unset($args['mapping']);
		}

		if(isset($args['number'])){
			$number	= $args['number'];
			unset($args['number']);
		}else{
			$number	= 0;
		}

		if(isset($args['output'])){
			$output	= $args['output'];
			unset($args['output']);
		}else{
			$output	= $taxonomy.'s';
		}

		if(isset($args['max_depth'])){
			$max_depth	= $args['max_depth'];
			unset($args['max_depth']);
		}else{
			$max_depth	= $tax_obj->levels ?? -1;
		}

		if($terms = wpjam_get_terms($args, $max_depth)){
			if($number){
				$paged	= $args['paged'] ?? 1;
				$offset	= $number * ($paged-1);

				$response['current_page']	= (int)$paged;
				$response['total_pages']	= ceil(count($terms)/$number);
				$terms = array_slice($terms, $offset, $number);
			}

			$response[$output]	= array_values($terms);
		}else{
			$response[$output]	= [];
		}

		foreach (['page_title', 'share_title'] as $title_key) {
			if(empty($response[$title_key])){
				$response[$title_key]	= $tax_obj->label;
			}
		}
	}

	public function parse_media_module($module_args, &$response){
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$media_id	= $module_args['media'] ?? 'media';
		$output		= $module_args['output'] ?? 'url';

		if(!isset($_FILES[$media_id])){
			wpjam_send_json(['errcode'=>'empty_media',	'errmsg'=>'媒体流不能为空！']);
		}

		$upload_file	= wp_handle_upload($_FILES[$media_id], ['test_form'=>false]);

		if(isset($upload_file['error'])){
			wpjam_send_json(['errcode'=>'upload_error',	'errmsg'=>$upload_file['error']]);
		}

		$response[$output]	= $upload_file['url'];
	}

	public function parse_setting_module($module_args, &$response){
		if(empty($module_args['option_name'])){
			wpjam_send_json(['errcode'=>'empty_option_name', 'errmsg'=>'option_name 不能为空']);
		}

		$option_name	= $module_args['option_name'] ?? '';
		$setting_name	= $module_args['setting_name'] ?? ($module_args['setting'] ?? '');
		$output			= $module_args['output'] ?? '';

		if($setting_name){
			$output	= $output ?: $setting_name; 
			$value	= wpjam_get_setting($option_name, $setting_name);
		}else{
			$output	= $output ?: $option_name;
			$value	= wpjam_get_option($option_name);
		}

		$value	= apply_filters('wpjam_setting_json', $value, $option_name, $setting_name);

		if(is_wp_error($value)){
			wpjam_send_json($value);
		}

		$response[$output]	= $value;
	}

	private function get_current_user(){
		$wpjam_user	= wpjam_get_current_user();

		if(is_wp_error($wpjam_user)){
			if($this->auth){
				wpjam_send_json($wpjam_user);
			}else{
				$wpjam_user	= null;
			}
		}elseif(is_null($wpjam_user)){
			if($this->auth){
				wpjam_send_json(['errcode'=>'bad_authentication',	'errmsg'=>'无权限']);
			}
		}

		return $wpjam_user;
	}
}

class WPJAM_API{
	protected static $apis	= [];
	protected static $json	= '';

	public static function register($json, $args){
		if(isset(self::$apis[$json])){
			trigger_error('API 「'.$json.'」已经注册。');
		}

		self::$apis[$json]	= new WPJAM_JSON($json, apply_filters('wpjam_register_api_args', $args, $json));
	}

	public static function unregister($json){
		unset(self::$apis[$json]);
	}

	public static function get_apis(){
		return self::$apis;
	}

	public static function get_api($json){
		if(self::$apis && !empty(self::$apis[$json])){
			return self::$apis[$json];
		}else{
			return [];
		}
	}

	public static function get_filter_name($name='', $type=''){
		$filter	= str_replace('-', '_', $name);
		$filter	= str_replace('wpjam_', '', $filter);

		return 'wpjam_'.$filter.'_'.$type;
	}

	public static function is_json_request(){
		if(get_option('permalink_structure')){
			if(preg_match("/\/api\/(.*)\.json/", $_SERVER['REQUEST_URI'])){ 
				return true;
			}
		}else{
			if(isset($_GET['module']) && $_GET['module'] == 'json'){
				return true;
			}
		}

		return false;
	}

	public static function json_redirect($action){
		if(!wpjam_doing_debug()){ 
			self::send_origin_headers();

			if(wp_is_jsonp_request()){
				@header('Content-Type: application/javascript; charset='.get_option('blog_charset'));
			}else{
				@header('Content-Type: application/json; charset='.get_option('blog_charset'));
			}
		}

		if(strpos($action, 'mag.') !== 0){
			return;
		}

		self::$json	= $json	= str_replace(['mag.','/'], ['','.'], $action);

		do_action('wpjam_api', $json);

		if($json_obj = self::get_api($json)){
			$json_obj->response();
		}else{
			wpjam_send_json(['errcode'=>'api_not_defined',	'errmsg'=>'接口未定义！']);
		}
	}

	protected static function send_origin_headers(){
		header('X-Content-Type-Options: nosniff');

		if($origin	= get_http_origin()){
			// Requests from file:// and data: URLs send "Origin: null"
			if('null' !== $origin){
				$origin	= esc_url_raw($origin);
			}

			@header('Access-Control-Allow-Origin: ' . $origin);
			@header('Access-Control-Allow-Methods: GET, POST');
			@header('Access-Control-Allow-Credentials: true');
			@header('Access-Control-Allow-Headers: Authorization, Content-Type');
			@header('Vary: Origin');

			if('OPTIONS' === $_SERVER['REQUEST_METHOD']){
				exit;
			}
		}

		if('OPTIONS' === $_SERVER['REQUEST_METHOD']){
			status_header(403);
			exit;
		}
	}

	public static function get_json(){
		return self::$json;
	}

	public static function method_allow($method, $send=true){
		if($_SERVER['REQUEST_METHOD'] != $method){
			$wp_error = new WP_Error('method_not_allow', '接口不支持 '.$_SERVER['REQUEST_METHOD'].' 方法，请使用 '.$method.' 方法！');
			if($send){
				self::send_json($wp_error);
			}else{
				return $wp_error;
			}
		}else{
			return true;
		}
	}

	private static function get_post_input(){
		static $post_input;
		if(!isset($post_input)){
			$post_input	= file_get_contents('php://input');
			// trigger_error(var_export($post_input,true));
			if(is_string($post_input)){
				$post_input	= @self::json_decode($post_input);
			}
		}

		return $post_input;
	}

	public static function get_parameter($parameter, $args=[]){
		$value		= null;
		$method		= !empty($args['method']) ? strtoupper($args['method']) : 'GET';

		if($method == 'GET'){
			if(isset($_GET[$parameter])){
				$value = wp_unslash($_GET[$parameter]);
			}
		}elseif($method == 'POST'){
			if(empty($_POST)){
				$post_input	= self::get_post_input();

				if(is_array($post_input) && isset($post_input[$parameter])){
					$value = $post_input[$parameter];
				}
			}else{
				if(isset($_POST[$parameter])){
					$value = wp_unslash($_POST[$parameter]);
				}
			}
		}else{
			if(!isset($_GET[$parameter]) && empty($_POST)){
				$post_input	= self::get_post_input();

				if(is_array($post_input) && isset($post_input[$parameter])){
					$value = $post_input[$parameter];
				}
			}else{
				if(isset($_REQUEST[$parameter])){
					$value = wp_unslash($_REQUEST[$parameter]);
				}
			}
		}

		if(is_null($value) && isset($args['default'])){
			return $args['default'];
		}

		$validate_callback	= $args['validate_callback'] ?? '';

		$send	= $args['send'] ?? true;

		if($validate_callback && is_callable($validate_callback)){
			$result	= call_user_func($validate_callback, $value);

			if($result === false){
				$wp_error = new WP_Error('invalid_parameter', '非法参数：'.$parameter);

				if($send){
					self::send_json($wp_error);
				}else{
					return $wp_error;
				}
			}elseif(is_wp_error($result)){
				if($send){
					self::send_json($result);
				}else{
					return $result;
				}
			}
		}else{
			if(!empty($args['required']) && is_null($value)){
				$wp_error = new WP_Error('missing_parameter', '缺少参数：'.$parameter);

				if($send){
					self::send_json($wp_error);
				}else{
					return $wp_error;
				}
			}

			$length	= $args['length'] ?? 0;
			$length	= (int)$length;

			if($length && (mb_strlen($value) < $length)){
				$wp_error = new WP_Error('short_parameter', $parameter.' 参数长度不能少于 '.$length);

				if($send){
					self::send_json($wp_error);
				}else{
					return $wp_error;
				}
			}
		}

		$sanitize_callback	= $args['sanitize_callback'] ?? '';

		if($sanitize_callback && is_callable($sanitize_callback)){
			$value	= call_user_func($sanitize_callback, $value);
		}else{
			if(!empty($args['type']) && $args['type'] == 'int' && $value){
				$value	= (int)$value;
			}
		}

		return $value;
	}

	public static function get_data_parameter($parameter, $args=[]){
		$value		= null;

		if(isset($_GET[$parameter])){
			$value	= wp_unslash($_GET[$parameter]);
		}elseif(isset($_REQUEST['data'])){
			$data		= wp_parse_args(wp_unslash($_REQUEST['data']));
			$defaults	= !empty($_REQUEST['defaults']) ? wp_parse_args(wp_unslash($_REQUEST['defaults'])) : [];
			$data		= wpjam_array_merge($defaults, $data);

			if(isset($data[$parameter])){
				$value	= $data[$parameter];
			}
		}

		if(is_null($value) && isset($args['default'])){
			return $args['default'];
		}

		$sanitize_callback	= $args['sanitize_callback'] ?? '';

		if(is_callable($sanitize_callback)){
			$value	= call_user_func($sanitize_callback, $value);
		}

		return $value;
	}

	public static function json_encode( $data, $options=JSON_UNESCAPED_UNICODE, $depth = 512){
		return wp_json_encode($data, $options, $depth);
	}

	public static function send_json($response=[], $status_code=null){
		if(is_wp_error($response)){
			$response	= ['errcode'=>$response->get_error_code(), 'errmsg'=>$response->get_error_message()];
		}else{
			$response	= array_merge(['errcode'=>0], $response);
		}

		$result	= self::json_encode($response);

		if(!headers_sent() && !wpjam_doing_debug()){
			if(!is_null($status_code)){
				status_header($status_code);
			}

			if(wp_is_jsonp_request()){
				@header('Content-Type: application/javascript; charset=' . get_option('blog_charset'));

				$jsonp_callback	= $_GET['_jsonp'];

				$result	= '/**/' . $jsonp_callback . '(' . $result . ')';

			}else{
				@header('Content-Type: application/json; charset=' . get_option('blog_charset'));
			}
		}

		echo $result;

		exit;
	}

	public static function json_decode($json, $assoc=true, $depth=512, $options=0){
		$json	= wpjam_strip_control_characters($json);

		if(empty($json)){
			return new WP_Error('empty_json', 'JSON 内容不能为空！');
		}

		$result	= json_decode($json, $assoc, $depth, $options);

		if(is_null($result)){
			$result	= json_decode(stripslashes($json), $assoc, $depth, $options);

			if(is_null($result)){
				if(wpjam_doing_debug()){
					print_r(json_last_error());
					print_r(json_last_error_msg());
				}
				trigger_error('json_decode_error '. json_last_error_msg()."\n".var_export($json,true));
				return new WP_Error('json_decode_error', json_last_error_msg());
			}
		}

		return $result;

		// wp 5.3 不建议使用 Services_JSON
		if(is_null($result)){
			require_once( ABSPATH . WPINC . '/class-json.php');

			$wp_json	= new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
			$result		= $wp_json->decode($json); 

			if(is_null($result)){
				return new WP_Error('json_decode_error', json_last_error_msg());
			}else{
				if($assoc){
					return (array)$result;
				}else{
					return (object)$result;
				}
			}
		}else{
			return $result;
		}
	}

	public static function http_request($url, $args=[], $err_args=[]){
		$args = wp_parse_args($args, [
			'timeout'			=> 5,
			'method'			=> '',
			'body'				=> [],
			'headers'			=> [],
			'sslverify'			=> false,
			'blocking'			=> true,	// 如果不需要立刻知道结果，可以设置为 false
			'stream'			=> false,	// 如果是保存远程的文件，这里需要设置为 true
			'filename'			=> null,	// 设置保存下来文件的路径和名字
			'need_json_decode'	=> true,
			'need_json_encode'	=> false,
			// 'headers'		=> ['Accept-Encoding'=>'gzip;'],	//使用压缩传输数据
			// 'headers'		=> ['Accept-Encoding'=>''],
			// 'compress'		=> false,
			'decompress'		=> true,
		]);

		if(wpjam_doing_debug()){
			print_r($url);
			print_r($args);
		}

		$need_json_decode	= $args['need_json_decode'];
		$need_json_encode	= $args['need_json_encode'];

		if(!empty($args['method'])){
			$method			= strtoupper($args['method']);
		}else{
			$method			= $args['body'] ? 'POST' : 'GET';
		}

		unset($args['need_json_decode']);
		unset($args['need_json_encode']);
		unset($args['method']);

		if($method == 'GET'){
			$response = wp_remote_get($url, $args);
		}elseif($method == 'POST'){
			if($need_json_encode){
				if(is_array($args['body'])){
					$args['body']	= self::json_encode($args['body']);
				}

				if(empty($args['headers']['Content-Type'])){
					$args['headers']['Content-Type']	= 'application/json';
				}
			}

			$response	= wp_remote_post($url, $args);
		}elseif($method == 'FILE'){	// 上传文件
			$args['method']				= $args['body'] ? 'POST' : 'GET';
			$args['sslcertificates']	= $args['sslcertificates'] ?? ABSPATH.WPINC.'/certificates/ca-bundle.crt';
			$args['user-agent']			= $args['user-agent'] ?? 'WordPress';

			$wp_http_curl	= new WP_Http_Curl();
			$response		= $wp_http_curl->request($url, $args);
		}elseif($method == 'HEAD'){
			if($need_json_encode && is_array($args['body'])){
				$args['body']	= self::json_encode($args['body']);
			}

			$response = wp_remote_head($url, $args);
		}else{
			if($need_json_encode && is_array($args['body'])){
				$args['body']	= self::json_encode($args['body']);
			}

			$response = wp_remote_request($url, $args);
		}

		if(is_wp_error($response)){
			trigger_error($url."\n".$response->get_error_code().' : '.$response->get_error_message()."\n".var_export($args['body'],true));
			return $response;
		}

		if(!empty($response['response']['code']) && $response['response']['code'] != 200){
			return new WP_Error($response['response']['code'], '远程服务器错误：'.$response['response']['code'].' - '.$response['response']['message']);
		}

		if(!$args['blocking']){
			return true;
		}

		$headers	= $response['headers'];
		$response	= $response['body'];

		if(isset($headers['content-type'])){
			$content_type	= is_array($headers['content-type']) ? implode(' ', $headers['content-type']) : $headers['content-type'];	
		}else{
			$content_type	= '';
		}
		
		if($need_json_decode || ($content_type && strpos($content_type, '/json'))){
			if($args['stream']){
				$response	= file_get_contents($args['filename']);
			}

			if(empty($response)){
				trigger_error(var_export($response, true).var_export($headers, true));
			}else{
				$response	= self::json_decode($response);

				if(is_wp_error($response)){
					return $response;
				}
			}
		}

		$err_args	= wp_parse_args($err_args,  [
			'errcode'	=>'errcode',
			'errmsg'	=>'errmsg',
			'detail'	=>'detail',
			'success'	=>'0',
		]);

		if(isset($response[$err_args['errcode']]) && $response[$err_args['errcode']] != $err_args['success']){
			$errcode	= $response[$err_args['errcode']];
			$errmsg		= $response[$err_args['errmsg']] ?? '';
			$detail		= $response[$err_args['detail']] ?? '';

			if(apply_filters('wpjam_http_response_error_debug', true, $errcode, $errmsg, $detail)){
				trigger_error($url."\n".$errcode.' : '.$errmsg."\n".($detail ? var_export($detail,true)."\n" : '').var_export($args['body'],true));
			}

			return new WP_Error($errcode, $errmsg, $detail);
		}

		if(wpjam_doing_debug()){
			echo $url;
			print_r($response);
		}

		return $response;
	}
}