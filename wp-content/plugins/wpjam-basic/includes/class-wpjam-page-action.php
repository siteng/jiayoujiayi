<?php
class WPJAM_Page_Action{
	private $args	= '';

	private static $instances = [];

	public static function register($name, $args){
		if(isset(self::$instances[$name])){
			trigger_error('Page Action 「'.$name.'」已经注册。');
		}

		if(is_wp_error($args)){
			self::$instances[$name]	= $args;
		}elseif(!isset($args['screen_id']) || $args['screen_id'] == get_current_screen()->id){
			self::$instances[$name] = new self($name, apply_filters('wpjam_register_page_action_args', $args, $name));
		}
	}

	public static function unregister($name){
		unset(self::$instances[$name]);
	}

	public static function get_instance($name){
		return self::$instances[$name] ?? null;
	}

	public static function ajax_response(){
		$action	= wpjam_get_parameter('page_action',	['method'=>'POST']);
		$nonce	= wpjam_get_parameter('_ajax_nonce',	['method'=>'POST']);

		if(!wp_verify_nonce($nonce, $GLOBALS['plugin_page'].'-'.$action)){
			wpjam_send_json(['errcode'=>'invalid_nonce',	'errmsg'=>'非法操作']);
		}

		$action_type	= wpjam_get_parameter('page_action_type',	['method'=>'POST', 'sanitize_callback'=>'sanitize_key']);

		if($instance = self::get_instance($action)){
			if($action_type == 'form'){
				$form		= $instance->get_form();
				$response	= is_wp_error($form) ? $form : ['form'=>$form];
			}else{
				$response	= $instance->callback($action);
			}

			wpjam_send_json($response);
		}else{
			do_action_deprecated('wpjam_page_action', [$action, $action_type], 'WPJAM Basic 4.6');

			$ajax_response	= wpjam_get_filter_name($GLOBALS['plugin_page'], 'ajax_response');
			$ajax_response	= apply_filters_deprecated('wpjam_page_ajax_response', [$ajax_response, $GLOBALS['plugin_page'], $action, $action_type], 'WPJAM Basic 4.6');

			if(is_callable($ajax_response)){
				$result	= call_user_func($ajax_response, $action);
				$result	= (is_wp_error($result) || is_array($result)) ? $result : [];
			}else{
				$result	= new WP_Error('invalid_ajax_response', '无效的回调函数');
			}

			wpjam_send_json($result);
		}	
	}

	public static function ajax_query(){
		$data_type	= wpjam_get_parameter('data_type',	['method'=>'POST']);
		$query_args	= wpjam_get_parameter('query_args',	['method'=>'POST']);

		if($data_type == 'post_type'){
			$query_args['posts_per_page']	= $query_args['posts_per_page'] ?? 10;
			$query_args['post_status']		= $query_args['post_status'] ?? 'publish';

			$query	= wpjam_query($query_args);
			$posts	= array_map(function($post){ return wpjam_get_post($post->ID); }, $query->posts);

			wpjam_send_json(['datas'=>$posts]);
		}elseif($data_type == 'taxonomy'){
			$query_args['number']		= $query_args['number'] ?? 10;
			$query_args['hide_empty']	= $query_args['hide_empty'] ?? 0;
			
			$terms	= wpjam_get_terms($query_args, -1);

			wpjam_send_json(['datas'=>$terms]);
		}elseif($data_type == 'model'){
			$model	= $query_args['model'];

			unset($query_args['model']);
			unset($query_args['label_key']);
			unset($query_args['id_key']);

			$query_args['number']	= $query_args['number'] ?? 10;
			
			$query	= $model::Query($query_args);

			wpjam_send_json(['datas'=>$query->datas]);
		}
	}

	public static function ajax_button($args){
		$args	= wp_parse_args($args, [
			'action'		=> '',
			'data'			=> [],
			'direct'		=> '',
			'confirm'		=> '',
			'tb_width'		=> 0,
			'tb_height'		=> 0,
			'button_text'	=> '保存',
			'page_title'	=> '',
			'tag'			=> 'a',
			'nonce'			=> '',
			'class'			=> 'button-primary large',
			'style'			=> ''
		]);

		$action	= $args['action'];

		if(empty($action)){
			return '';
		}

		$page_title = $args['page_title'] ?: $args['button_text'];

		$attr	= ' title="'.esc_attr($page_title).'" id="wpjam_button_'.$action.'"';

		if($args['tag'] == 'a'){
			$attr	.= 'href="javascript:;"';
		}

		$datas	= [];

		if(empty($args['nonce'])){
			$datas['nonce']	= wp_create_nonce($GLOBALS['plugin_page'].'-'.$action);
		}else{
			$datas['nonce']	= $args['nonce'];
		}

		$datas['action']	= $action;
		$datas['data']		= $args['data'] ? http_build_query($args['data']) : '';
		$datas['title']		= $page_title;
		
		$datas	+= wp_array_slice_assoc($args, ['direct', 'confirm', 'tb_width', 'tb_height']);
		$datas	= array_filter($datas);

		foreach ($datas as $data_key=>$data_value) {
			$attr	.= ' data-'.$data_key.'="'.$data_value.'"';
		}

		if($args['style']){
			$attr	.= ' style="'.$args['style'].'"';
		}
		
		$class	= 'wpjam-button';
		$class	.= $args['class'] ? ' '.$args['class'] : '';
		$attr	.= ' class="'.$class.'"';
		
		return '<'.$args['tag'].$attr.'>'.$args['button_text'].'</'.$args['tag'].'>';
	}

	public static function ajax_form($args){
		$args	= wp_parse_args($args, [
			'data_type'		=> 'form',
			'fields_type'	=> 'table',
			'fields'		=> [],
			'data'			=> [],
			'bulk'			=> false,
			'ids'			=> [],
			'id'			=> '',
			'action'		=> '',
			'page_title'	=> '',
			'submit_text'	=> '',
			'nonce'			=> '',
			'form_id'		=> 'wpjam_form'
		]);

		$action	= $args['action'];
		$output	= '';

		$fields = $args['fields'];

		$attr	= ' method="post" action="#"';
		$attr	.= 'id="'.$args['form_id'].'"';

		$datas	= [];

		if($action){
			$datas['action']	= $action;

			if(empty($args['nonce'])){
				$datas['nonce']	= wp_create_nonce($GLOBALS['plugin_page'].'-'.$action);
			}else{
				$datas['nonce']	= $args['nonce'];
			}
		}
			
		$datas['title']		= $args['page_title'] ?: $args['submit_text'];

		if($args['bulk']){
			$datas['bulk']	= $args['bulk'];
			$datas['ids']	= $args['ids'] ? http_build_query($args['ids']) : '';
		}else{
			$datas['id']	= $args['id'];
		}

		$datas	= array_filter($datas);

		foreach ($datas as $data_key=>$data_value) {
			$attr	.= ' data-'.$data_key.'="'.$data_value.'"';
		}

		$output	.= '<div class="page-action-notice notice is-dismissible hidden"></div>';
		
		$output	.= '<form'.$attr.'>';
		
		$args['echo']	= false;

		if($fields){
			$output	.= wpjam_fields($fields, $args);
		}

		if($args['submit_text']){
			$output	.= '<p class="submit"><input type="submit" id="page_submit" class="button-primary large" value="'.$args['submit_text'].'"> <span class="spinner"></span></p>';
		}

		$output	.= '<div class="card response" style="display:none;"></div>';

		if($fields){
			$output	.= '</form>';
		}

		return $output;
	}

	private function __construct($name, $args){
		$this->args	= wp_parse_args($args, [
			'action'	=> $name,
			'direct'	=> false,
			'response'	=> $name,
			'callback'	=> '',
			'fields'	=> [],
			'summary'	=> ''
		]);
	}

	public function callback($action){
		$callback	= $this->args['callback'];

		if(empty($callback) || !is_callable($callback)){
			return new WP_Error('invalid_ajax_callback', '无效的回调函数');
		}

		$result = call_user_func($callback, $action);

		if(is_wp_error($result)){
			return $result;
		}elseif(!$result){
			return WP_Error('error_ajax_callback', '回调函数返回错误');
		}

		$response	= ['type'=>$this->args['response']];

		if(is_array($result)){
			return array_merge($result, $response);
		}

		if($result !== true){
			if($this->args['response'] == 'redirect'){
				$response['url']	= $result;
			}else{
				$response['data']	= $result;
			}
		}

		return $response;
	}

	public function get_summary(){
		return $this->args['summary'];
	}

	public function get_button($args=[]){
		return $this->ajax_button(array_merge($this->args, $args));
	}

	public function get_form($args=[]){
		$args	= array_merge($this->args, $args);

		$args['fields']	= $args['fields'] ?? [];
		$args['data']	= $args['data'] ?? [];

		if($args['fields'] && is_callable($args['fields'])){
			$args['fields']	= call_user_func($args['fields'], $args['action']);

			if(is_wp_error($args['fields'])){
				return $args['fields'];
			}
		}

		if(!empty($args['data_callback']) && is_callable($args['data_callback'])){
			$data	= call_user_func($args['data_callback'], $args['action'], $args['fields']);

			if(is_wp_error($data)){
				return $data;
			}

			$args['data']	= array_merge($args['data'], $data);
		}

		return $this->ajax_form($args);
	}
}

