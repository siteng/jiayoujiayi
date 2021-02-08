<?php
abstract class WPJAM_Model{
	protected $data	= [];

	public function __construct(array $data=[]){
		$this->data	= $data;
	}

	public function __get($key){
		return $this->get_data($key);
	}

	public function __set($key, $value){
		$this->set_data($key, $value);
	}

	public function __isset($key){
		return isset($this->data[$key]);
	}

	public function __unset($key){
		unset($this->data[$key]);
	}

	public function __call($func, $args) {
		if(strpos($func, 'get_') === 0){
			$key	= str_replace('get_', '', $func);

			return $this->get_data($key);
		}elseif(strpos($func, 'set_') === 0){
			$key	= str_replace('set_', '', $func);

			return $this->set_data($key, $args[0]);
		}
	}

	public function to_array(){
		return $this->data;
	}

	public function save($data=[]){
		if($data){
			$this->data = array_merge($this->data, $data);
		}

		$primary_key	= self::get_primary_key();

		$id	= $this->data[$primary_key] ?? null;

		if($id){
			$result	= static::update($id, $this->data);
		}else{
			$result	= $id = static::insert($this->data);
		}

		if(!is_wp_error($result)){
			$this->data	= static::get($id);
		}

		return $result;
	}

	private function get_data($key=''){
		if($key){
			return $this->data[$key] ?? null;
		}else{
			return $this->data;
		}
	}

	private function set_data($key, $value){
		if(self::get_primary_key() == $key){
			trigger_error('不能修改主键的值');
			wp_die('不能修改主键的值');
		}

		$this->data[$key]	= $value;

		return $this;
	}

	public static function find($id){
		return static::get_instance($id);
	}

	public static function get_instance($id){
		if($id && ($data = static::get($id))){
			return new static($data);
		}else{
			return null;
		}
	}

	public static function get_handler(){
		return static::$handler;
	}

	public static function set_handler($handler){
		static::$handler	= $handler;
	}

	public static function Query($args=[]){
		if($args){
			return new WPJAM_Query(static::get_handler(), $args);
		}else{
			return static::get_handler();
		}
	}

	public static function get_last_changed(){
		return static::get_handler()->get_last_changed();
	}

	public static function get_cache_group(){
		return static::get_handler()->get_cache_group();
	}

	public static function get_cache_key($key){
		$cache_prefix	= static::get_handler()->get_cache_prefix();
		return $cache_prefix ? $cache_prefix.':'.$key : $key;
	}

	public static function cache_get($key){
		return wp_cache_get(self::get_cache_key($key), self::get_cache_group());
	}

	public static function cache_set($key, $data, $cache_time=DAY_IN_SECONDS){
		return wp_cache_set(self::get_cache_key($key), $data, self::get_cache_group(), $cache_time);
	}

	public static function cache_add($key, $data, $cache_time=DAY_IN_SECONDS){
		return wp_cache_add(self::get_cache_key($key), $data, self::get_cache_group(), $cache_time);
	}

	public static function cache_delete($key){
		return wp_cache_delete(self::get_cache_key($key), self::get_cache_group());
	}

	public static function get_list_cache(){
		return new WPJAM_listCache(self::get_cache_group());
	}

	public static function get($id){
		return static::get_handler()->get($id);
	}

	public static function get_by($field, $value, $order='ASC'){
		return static::get_handler()->get_by($field, $value, $order);
	}

	public static function get_one_by($field, $value, $order='ASC'){
		$items = static::get_by($field, $value, $order);
		return $items ? current($items) : [];
	}

	public static function get_ids($ids){
		return static::get_by_ids($ids);
	}

	public static function get_by_ids($ids){
		return static::get_handler()->get_by_ids($ids);
	}

	public static function update_caches($values){
		return static::get_handler()->update_caches($values);
	}

	public static function get_all(){
		return static::get_handler()->get_results();
	}

	public static function insert($data){
		return static::get_handler()->insert($data);
	}

	public static function insert_multi($datas){
		return static::get_handler()->insert_multi($datas);
	}

	public static function update($id, $data){
		return static::get_handler()->update($id, $data);
	}

	public static function delete($id){
		return static::get_handler()->delete($id);
	}

	public static function move($id, $data){
		return static::get_handler()->move($id, $data);
	}

	public static function delete_by($field, $value){
		return static::get_handler()->delete(array($field=>$value));
	}

	public static function delete_multi($ids){
		if(method_exists(static::get_handler(), 'delete_multi')){
			return static::get_handler()->delete_multi($ids);
		}elseif($ids){
			foreach($ids as $id){
				$result	= static::get_handler()->delete($id);
				if(is_wp_error($result)){
					return $result;
				}
			}

			return $result;
		}
	}

	public static function get_primary_key(){
		return static::get_handler()->get_primary_key();
	}

	public static function query_items($limit, $offset){
		if(method_exists(static::get_handler(), 'query_items')){
			return static::get_handler()->query_items($limit, $offset);
		}
	}

	public static function list($limit, $offset){
		// _deprecated_function(__METHOD__, 'WPJAM Basic 3.7', 'WPJAM_Model::query_items');
		if(method_exists(static::get_handler(), 'query_items')){
			return static::get_handler()->query_items($limit, $offset);
		}
	}

	public static function item_callback($item){
		if(method_exists(static::get_handler(), 'item_callback')){
			return static::get_handler()->item_callback($item);
		}else{
			return $item;
		}
	}

	public static function get_searchable_fields(){
		if(method_exists(static::get_handler(), 'get_searchable_fields')){
			return static::get_handler()->get_searchable_fields(); 
		}else{
			return [];
		}
	}

	public static function get_filterable_fields(){
		if(method_exists(static::get_handler(), 'get_filterable_fields')){
			return static::get_handler()->get_filterable_fields(); 
		}else{
			return [];
		}
	}

	// 下面函数不建议使用
	// public static function views(){}

	public static function get_by_cache_keys($values){
		_deprecated_function(__METHOD__, 'WPJAM Basic 4.4', 'WPJAM_Model::update_caches');
		return static::update_caches($values);
	}

	public static function find_by($field, $value, $order='ASC'){
		_deprecated_function(__METHOD__, 'WPJAM Basic 4.4', 'WPJAM_Model::get_by');
		return static::get_handler()->find_by($field, $value, $order);
	}

	public static function find_one($id){
		_deprecated_function(__METHOD__, 'WPJAM Basic 4.4', 'WPJAM_Model::get');
		return static::get_handler()->find_one($id);
	}

	public static function find_one_by($field, $value){
		_deprecated_function(__METHOD__, 'WPJAM Basic 4.4', 'WPJAM_Model::get_one_by');
		return static::get_handler()->find_one_by($field, $value);
	}
}

class WPJAM_Query{
	public $request;
	public $query_vars;
	public $datas;
	public $max_num_pages	= 0;
	public $found_rows 		= 0;
	public $next_first 		= 0;
	public $next_cursor 	= 0;
	public $handler;

	public function __construct($handler, $query='') {

		$this->handler	= $handler;

		if(!empty($query)){
			$this->query($query);
		}
	}

	public function query($query){
		$this->query_vars = wp_parse_args( $query, array(
			'first'		=> null,
			'cursor'	=> null,
			'orderby'	=> null,
			'order'		=> 'DESC',
			'number'	=> 50,
			'search'	=> '',
			'offset'	=> null
		));

		$orderby 	= $this->query_vars['orderby']?:'id';
		$cache_it	= $orderby == 'rand' ? false : true;

		if($cache_it){
			$last_changed	= $this->handler->get_last_changed();
			$cache_group	= $this->handler->get_cache_group();
			$cache_prefix	= $this->handler->get_cache_prefix();
			$key			= md5(maybe_serialize($this->query_vars));
			$cache_key		= 'wpjam_query:'.$key.':'.$last_changed;
			$cache_key		= $cache_prefix ? $cache_prefix.':'.$cache_key : $cache_key;

			$result			= wp_cache_get($cache_key, $cache_group);
		}else{
			$result			= false;
		}

		if($result === false){
			$found_rows	= false;

			foreach ($this->query_vars as $key => $value) {
				if($value === null){
					continue;
				}

				if($key == 'number'){
					if($value != -1){
						$this->handler->limit($value);
						$found_rows	= true;
					}
				}elseif($key == 'offset'){
					$this->handler->offset($value);
					$found_rows	= true;
				}elseif($key == 'orderby'){
					$this->handler->order_by($value);
				}elseif($key == 'order'){
					$this->handler->order($value);
				}elseif($key == 'first'){
					$this->handler->where_gt($orderby, $value);
				}elseif($key == 'cursor'){
					if($value > 0){
						$field = $this->query_vars['orderby']??'id';
						$this->handler->where_lt($orderby, $value);
					}
				}elseif($key == 'search'){
					$this->handler->search($value);
				}elseif(strpos($key, '__in_set')){
					$this->handler->find_in_set($value, str_replace('__in_set', '', $key));
				}elseif(strpos($key, '__in')){
					$this->handler->where_in(str_replace('__in', '', $key), $value);
				}elseif(strpos($key, '__not_in')){
					$this->handler->where_not_in(str_replace('__not_in', '', $key), $value);
				}else{
					$this->handler->where($key, $value);
				}
			}

			$result	= [
				'datas'		=> $this->handler->get_results(),
				'request'	=> $this->handler->get_request()
			];

			if($found_rows){
				$result['found_rows']	= $this->handler->find_total();
			}else{
				$result['found_rows']	= count($result['datas']);
			}

			if($cache_it){
				wp_cache_set($cache_key, $result, $cache_group, DAY_IN_SECONDS);
			}
		}

		$this->datas		= $result['datas'];
		$this->request		= $result['request'];
		$this->found_rows	= $result['found_rows'];

		if ($this->found_rows && $this->query_vars['number'] && $this->query_vars['number'] != -1){
			$this->max_num_pages = ceil($this->found_rows / $this->query_vars['number']);

			if($this->query_vars['offset'] === null){
				if($this->found_rows > $this->query_vars['number']){
					$this->next_cursor	= (int)$this->datas[count($this->datas)-1][$orderby];
				}
			}
		}

		return $this->datas;
	}
}