<?php
abstract class WPJAM_Item{
	private $primary_key	= 'id';
	private $primary_title	= 'ID';
	private $unique_key		= '';
	private $unique_title	= '';
	private $total			= 0;

	public function __construct($args=[]){
		$args = wp_parse_args($args, [
			'primary_key'	=> 'id',
			'primary_title'	=> 'ID',
			'unique_key'	=> '',
			'unique_title'	=> '',
			'total'			=> 0,
		]);

		$this->primary_key		= $args['primary_key'];
		$this->primary_title	= $args['primary_title'];
		$this->unique_key		= $args['unique_key'];
		$this->unique_title		= $args['unique_title'];
		$this->total			= $args['total'];
	}

	public function get_primary_key(){
		return $this->primary_key;
	}

	public function get_results(){
		return $this->parse_items($this->get_items());
	}

	public function get_items(){}

	public function update_items($items){}

	public function parse_items($items){
		if($items && is_array($items)){
			foreach ($items as $id => &$item) {
				$item[$this->primary_key]	= $id;
			}

			unset($item);
		}else{
			$items	= [];
		}

		return $items;
	}

	public function exists($value){
		$items	= $this->get_items();

		return $items ? in_array($value, array_column($items, $this->unique_key)) : false;
	}

	public function get($id){
		$items	= $this->get_items();

		return $items[$id] ?? false;
	}

	public function insert($data){
		$items	= $this->get_items();

		if($this->total && count($items) >= $this->total){
			return new WP_Error('over_total', '最多支持'.$this->total.'个');
		}

		if(in_array($this->primary_key, ['option_key', 'id'])){
			if($this->unique_key){

				if(empty($data[$this->unique_key])){
					return new WP_Error('empty_'.$this->unique_key, $this->unique_title.'不能为空');
				}

				if($this->exists($data[$this->unique_key])){
					return new WP_Error('duplicate_'.$this->unique_key, $this->unique_title.'重复');
				}
			}

			if($items){
				$ids	= array_keys($items);
				$ids	= array_map(function($id){	return (int)(str_replace('option_key_', '', $id)); }, $ids);

				$id		= max($ids);
				$id		= $id+1;
			}else{
				$id		= 1;
			}

			if($this->primary_key == 'option_key'){
				$id		= 'option_key_'.$id;
			}
		}else{
			if(empty($data[$this->primary_key])){
				return new WP_Error('empty_'.$this->primary_key, $this->primary_title.'不能为空');
			}

			$id	= $data[$this->primary_key];

			if(isset($items[$id])){
				return new WP_Error('duplicate_'.$this->primary_key, $this->primary_title.'值重复');
			}
		}

		$items[$id]	= $data;

		$this->update_items($items);

		return $id;
	}

	public function update($id, $data){
		$items	= $this->get_items();

		if(!isset($items[$id])){
			return new WP_Error('invalid_'.$this->primary_key, $this->primary_title.'为「'.$id.'」的数据的不存在');
		}

		if(in_array($this->primary_key, ['option_key', 'id'])){
			if($this->unique_key && isset($data[$this->unique_key])){
				if(empty($data[$this->unique_key])){
					return new WP_Error('empty_'.$this->unique_key, $this->unique_title.'不能为空');
				}

				if($data[$this->unique_key] != $items[$id][$this->unique_key]){
					if($this->exists($data[$this->unique_key])){
						return new WP_Error('duplicate_'.$this->unique_key, $this->unique_title.'重复');
					}
				}
			}
		}

		$data[$this->primary_key] = $id;

		$items[$id]	= wp_parse_args($data, $items[$id]);

		return $this->update_items($items);
	}

	public function delete($id){
		$items	= $this->get_items();

		if(!isset($items[$id])){
			return new WP_Error('invalid_'.$this->primary_key, $this->primary_title.'为「'.$id.'」的数据的不存在');
		}

		unset($items[$id]);

		return $this->update_items($items);
	}

	public function move($id, $data){
		$items	= $this->get_items();

		if(empty($items) || empty($items[$id])){
			return new WP_Error('key_not_exists', $id.'的值不存在');
		}

		$next	= $data['next'] ?? false;
		$prev	= $data['prev'] ?? false;

		if(!$next && !$prev){
			return new WP_Error('invalid_move', '无效移动位置');
		}

		$item	= $items[$id];
		unset($items[$id]);

		if($next){
			if(empty($items[$next])){
				return new WP_Error('key_not_exists', $next.'的值不存在');
			}

			$offset	= array_search($next, array_keys($items));

			if($offset){
				$items	= array_slice($items, 0, $offset, true) +  [$id => $item] + array_slice($items, $offset, null, true);
			}else{
				$items	= [$id => $item] + $items;
			}
		}else{
			if(empty($items[$prev])){
				return new WP_Error('key_not_exists', $prev.'的值不存在');
			}

			$offset	= array_search($prev, array_keys($items));
			$offset ++;

			if($offset){
				$items	= array_slice($items, 0, $offset, true) +  [$id => $item] + array_slice($items, $offset, null, true);
			}else{
				$items	= [$id => $item] + $items;
			}
		}

		return $this->update_items($items);
	}

	public function query_items($limit, $offset){
		$items	= $this->get_items();
		$total 	= count($items);

		return compact('items', 'total');
	}
}

class WPJAM_Option extends WPJAM_Item{
	private $option_name;
	private $primary_key;

	public function __construct($option_name, $args=[]){
		$this->option_name	= $option_name;

		if(!is_array($args)){
			$args	= ['primary_key' => $args];
		}else{
			$args	= wp_parse_args($args, ['primary_key'=>'option_key']);
		}

		parent::__construct($args);
	}

	public function get_items(){
		return $this->parse_items(get_option($this->option_name, []));
	}

	public function update_items($items){
		if($items && in_array($this->get_primary_key(), ['option_key','id'])){
			foreach ($items as &$item){
				unset($item[$this->get_primary_key()]);
			}

			unset($item);
		}
		return update_option($this->option_name, $items);
	}
}

class WPJAM_MetaItem extends WPJAM_Item{
	private $meta_type	= 'post';
	private $meta_key;

	public function __construct($meta_type, $meta_key, $args=[]){
		$this->meta_type	= $meta_type;
		$this->meta_key		= $meta_key;

		parent::__construct($args);
	}

	public function get_object_id(){
		return wpjam_get_data_parameter($this->meta_type.'_id');
	}

	public function get_items(){
		$items	= get_metadata($this->meta_type, $this->get_object_id(), $this->meta_key, true) ?: [];
		return $this->parse_items($items);
	}

	public function update_items($items){
		if($items && in_array($this->get_primary_key(), ['option_key','id'])){
			foreach ($items as &$item){
				unset($item[$this->get_primary_key()]);
				unset($item[$this->meta_type.'_id']);
			}

			unset($item);
		}

		return update_metadata($this->meta_type, $this->get_object_id(), $this->meta_key, $items);
	}
}

class WPJAM_PostContent extends WPJAM_Item{
	public function __construct($args=[]){
		parent::__construct($args);
	}

	public function get_post_id(){
		return wpjam_get_data_parameter('post_id');
	}

	public function get_items(){
		$post_id	= $this->get_post_id();
		$items		= [];
		$_post		= get_post($post_id);

		if($_post && $_post->post_content){
			$items	= maybe_unserialize($_post->post_content);
		}

		return  $this->parse_items($items);
	}

	public function update_items($items){
		if($items){
			foreach ($items as &$item){
				unset($item[$this->get_primary_key()]);
				unset($item['post_id']);
			}

			unset($item);
		}

		$post_id		= $this->get_post_id();
		$post_content	= maybe_serialize($items);
		return WPJAM_Post::update($post_id, compact('post_content'));
	}
}