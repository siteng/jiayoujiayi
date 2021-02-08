<?php
wp_cache_add_global_groups('weixin_replies');

class WEIXIN_ReplySetting extends WPJAM_Model{
	use WEIXIN_Trait;
	private static $builtin_replies	= [];
	private static $queries			= [];

	public static function register_builtin_reply($keyword, $args){
		self::$builtin_replies[$keyword]	= wp_parse_args($args, ['type'=>'full', 'keyword'=>$keyword]);
	}

	public static function register_query($name, $callback){
		self::$queries[$name]	= $callback;
	}

	public static function get_queries(){
		return self::$queries;
	}

	public static function get_custom_reply($keyword){
		foreach(['full', 'prefix', 'fuzzy'] as $match){
			$custom_replies	= self::get_custom_replies($match);

			if(empty($custom_replies)){
				continue;
			}

			if($match == 'full'){
				if(isset($custom_replies[$keyword])){
					$custom_reply	= $custom_replies[$keyword];
					break;
				}
			}elseif($match == 'prefix'){
				$prefix_keyword = mb_substr($keyword, 0, 2);	// 前缀匹配，只支持2个字

				if(isset($custom_replies[$prefix_keyword])){
					$custom_reply	= $custom_replies[$prefix_keyword];
					break;
				}
			}elseif($match == 'fuzzy'){
				if(preg_match('/'.implode('|', array_keys($custom_replies)).'/', $keyword, $matches)){
					$fuzzy_keyword	= $matches[0];
					$custom_reply	= $custom_replies[$fuzzy_keyword];
					break;
				}
			}
		}

		if(isset($custom_reply)){
			$rand_key	= array_rand($custom_reply, 1);
			return $custom_reply[$rand_key];
		}else{
			return false;
		}
	}

	public static function get_builtin_reply($keyword){
		foreach(['full', 'prefix'] as $match){
			$builtin_replies	= self::get_builtin_replies($match);

			if(empty($builtin_replies)){
				continue;
			}

			if($match == 'full'){
				if(isset($builtin_replies[$keyword])){
					$builtin_reply	= $builtin_replies[$keyword];
					break;
				}
			}elseif($match == 'prefix'){
				$prefix_keyword = mb_substr($keyword, 0, 2);	// 前缀匹配，只支持2个字
				
				if(isset($builtin_replies[$prefix_keyword])){
					$builtin_reply	= $builtin_replies[$prefix_keyword];
					break;
				}
			}
		}

		return $builtin_reply ?? false;
	}

	public static function get_default_reply($keyword){
		$defaut_replies	= self::get_default_replies(); 
		return isset($defaut_replies[$keyword]) ? $defaut_replies[$keyword]['value'] : '';
	}

	protected static function get_custom_replies($match=null){
		$custom_replies_original	= self::get_by('appid', static::get_appid());
		
		$custom_replies = []; 
		if($custom_replies_original){
			foreach ($custom_replies_original as $custom_reply ) {

				if($custom_reply['status'] != 1){
					continue;
				}

				if($match && $custom_reply['match'] != $match){
					continue;
				}
				
				$key = strtolower(trim($custom_reply['keyword']));
				if(strpos($key,',')){
					foreach (explode(',', $key) as $new_key) {
						$new_key = strtolower(trim($new_key));
						if($new_key !== ''){
							$custom_replies[$new_key][] = $custom_reply;
						}
					}
				}else{
					$custom_replies[$key][] = $custom_reply;
				}
			}
		}

		if($match == 'full'){
			$builtin_replies	= self::get_builtin_replies($match);

			foreach (['[too-long]','[default]'] as $keyword) {	// 将这两个作为函数回复写入到自定义回复中
				if(isset($custom_replies[$keyword])){
					continue;
				}

				if(isset($builtin_replies[$keyword])){
					$custom_reply = [];

					$custom_reply['keyword']	= $keyword;
					$custom_reply['reply']		= $builtin_replies[$keyword]['function'];
					$custom_reply['type']		= 'function';

					$custom_replies[$keyword][]	= $custom_reply;
				}
			}
		}

		// 按照键的长度降序排序
		uksort($custom_replies, function ($v, $w){
			return (mb_strwidth($v) <=> mb_strwidth($w));
		});

		return $custom_replies;
	}

	protected static function get_builtin_replies($type='all'){
		self::$builtin_replies = apply_filters('weixin_builtin_reply', self::$builtin_replies);

		return $type == 'all' ? self::$builtin_replies : wp_list_filter(self::$builtin_replies, ['type'=>$type]);
	}

	protected static function get_default_replies(){
		return [
			'[subscribe]'		=> ['title'=>'用户关注时',	'value'=>'欢迎关注！'],
			'[event-location]'	=> ['title'=>'进入服务号',	'value'=>'欢迎再次进来！'],
			'[default]'			=> ['title'=>'没有匹配时',	'value'=>'抱歉，没有找到相关的文章，要不你更换一下关键字，可能就有结果了哦 :-)'],
			'[too-long]'		=> ['title'=>'文本太长时',	'value'=>'你输入的关键字太长了，系统没法处理了，请等待公众账号管理员到微信后台回复你吧。'],
			'[emotion]'			=> ['title'=>'发送表情',		'value'=>'已经收到你的表情了！'],
			'[voice]'			=> ['title'=>'发送语音',		'value'=>''],
			'[location]'		=> ['title'=>'发送位置',		'value'=>''],
			'[image]'			=> ['title'=>'发送图片',		'value'=>''],
			'[link]'			=> ['title'=>'发送链接',		'value'=>'已经收到你分享的信息，感谢分享。'],
			'[video]'			=> ['title'=>'发送视频',		'value'=>'已经收到你分享的信息，感谢分享。'],
			'[shortvideo]'		=> ['title'=>'发送短视频',	'value'=>'已经收到你分享的信息，感谢分享。'],
		];
	}

	public static function get_table(){
		global $wpdb;
		return $wpdb->base_prefix.'weixin_replies';
	}

	protected static $handler;

	public static function get_handler(){
		if(is_null(static::$handler)){
			static::$handler = new WPJAM_DB(self::get_table(), [
				'primary_key'		=> 'id',
				'cache_key'			=> 'appid',
				'cache_group'		=> 'weixin_replies',
				'field_types'		=> ['id'=>'%d'],
				'searchable_fields'	=> ['keyword', 'reply'],
				'filterable_fields'	=> ['match','type','status'],
			]);
		}
		
		return static::$handler;
	}

	public static function create_table(){
		global $wpdb;

		$table = self::get_table();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		if($wpdb->get_var("show tables like '".$table."'") != $table) {
			$sql = "
			CREATE TABLE IF NOT EXISTS {$table} (
				`id` bigint(20) NOT NULL auto_increment,
				`appid` varchar(32) NOT NULL,
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

			$wpdb->query("ALTER TABLE `{$table}`
				ADD KEY `match` (`match`),
				ADD KEY `status` (`status`),
				ADD KEY `type` (`type`);");
		}
	}
}

class WEIXIN_Advanced{
	//按照时间排序
	public static function new_posts_reply($keyword, $weixin_reply){
		return $weixin_reply->wp_query_reply([]);
	}

	//随机排序
	public static function rand_posts_reply($keyword, $weixin_reply){
		return $weixin_reply->wp_query_reply(['orderby'=>'rand']);
	}

	//按照浏览排序
	public static function hot_posts_reply($keyword, $weixin_reply, $date=0){
		$query_args	= [
			'meta_key'	=>'views',
			'orderby'	=>'meta_value_num',
		];

		if($date){
			$query_args['date_query']	= [
				'after'	=> date('Y-m-d', current_time() - $date * DAY_IN_SECONDS)
			];
		}

		return $weixin_reply->wp_query_reply($query_args);
	}

	//按照留言数排序
	public static function comment_posts_reply($keyword, $weixin_reply, $date=0){
		global $weixin_reply;

		$query_args	= [
			'orderby'	=>'comment_count',
		];

		if($date){
			$query_args['date_query']	= [
				'after'	=> date('Y-m-d', current_time() - $date * DAY_IN_SECONDS)
			];
		}

		return $weixin_reply->wp_query_reply($query_args);
	}

	//7天内最热
	public static function hot_7_posts_reply($keyword, $weixin_reply){
		return self::hot_posts_reply($keyword, $weixin_reply, 7);
	}

	//30天内最热
	public static function hot_30_posts_reply($keyword, $weixin_reply){
		return self::hot_posts_reply($keyword, $weixin_reply, 30);
	}

	//7天内留言最多 
	public static function comment_7_posts_reply($keyword, $weixin_reply){
		return self::comment_posts_reply($keyword, $weixin_reply, 7);
	}

	//30天内留言最多
	public static function comment_30_posts_reply($keyword){
		return self::comment_posts_reply($keyword, $weixin_reply, 30);
	}
}

class WEIXIN_AdminReplySetting extends WEIXIN_ReplySetting{
	public static $tab = 'custom';

	public static function set_tab($tab){
		self::$tab	= $tab;
	}

	public static function get_primary_key(){
		return self::$tab == 'default' ? 'key' : 'id';
	}

	public static function get($id){
		if(self::$tab == 'default'){
			$default_replies	= parent::get_default_replies();

			$keyword			= '['.$id.']';
			$data				= self::get_by_keyword($keyword);
			$data['key']		= $id;
			$data['title']		= $default_replies[$keyword]['title'];
		}else{
			$data	= parent::get($id);
		
			if($data){
				$type	= $data['type'] ?? 'text';
				$data[$type]	= maybe_unserialize($data['reply']);
			}
		}

		$type	= $data['type'] ?? '';

		if($type == 'img'){
			if($data['img']){
				$data['img']	= explode(',', $data['img']);
				$data['img']	= $data['img'][0];
			}
		}elseif($type == 'img2'){
			if($data['img2'] && !is_array($data['img2'])){
				$lines = explode("\n", $data['img2']);
				$data['img2']	= [];
				$data['img2']['title']			= $lines[0];
				$data['img2']['description']	= $lines[1];
				$data['img2']['pic_url']		= $lines[2];
				$data['img2']['url']			= $lines[3];
			}
		}

		return $data;
	}

	public static function insert($data){
		$data	= self::sanitize($data);

		$data['time']	= time();
		$data['appid']	= static::get_appid();
		
		return parent::insert($data);
	}	

	public static function update($id, $data){
		return parent::update($id, self::sanitize($data));
	}

	public static function sanitize($data){
		$type	= $data['type'] ?? 'text';

		$data['match']	= $data['match'] ?? 'full';
		$data['reply']	= maybe_serialize($data[$type]);

		return array_diff_key($data, self::get_descriptions());
	}

	public static function set(){
		$args_num	= func_num_args();
		$args		= func_get_args();

		if($args_num == 2){
			$data	= $args[1];
		}else{
			$data	= $args[0];
		}

		$id			= 0;
		$keyword	= $data['keyword'] ?? '';
		if($keyword){
			$reply	= self::get_by_keyword($keyword);

			if($reply && $reply['type'] == $data['type'] && $reply[$reply['type']] == $data[$data['type']]){ // 没更新，就算了
				return true;
			}

			$id		= $reply['id']??0;
		}

		if($id){
			return self::update($id, $data);
		}else{
			return self::insert($data);
		}
	}

	public static function get_by_keyword($keyword){
		$custom_replies		= parent::get_custom_replies();
		$default_replies	= parent::get_default_replies();
		$builtin_replies	= parent::get_builtin_replies();

		$data = [];

		if($custom_replies  && isset($custom_replies[$keyword])) {
			$data		= $custom_replies[$keyword][0];
			$reply_type	= $data['type']??'text';

			$data[$reply_type]	= maybe_unserialize($data['reply']);
		}elseif($builtin_replies && isset($builtin_replies[$keyword])){
			$callback	= '';

			if(isset($builtin_replies[$keyword]['callback'])){
				$callback		= $builtin_replies[$keyword]['callback'];
			}elseif(isset($builtin_replies[$keyword]['function'])){
				$callback		= $builtin_replies[$keyword]['function'];
			}

			$reply_type			= $callback ? 'function' : 'text';
			$data['keyword']	= $keyword; 
			$data['type']		= $reply_type; 
			$data['match']		= $builtin_replies[$keyword]['type']; 
			$data['status']		= 1;
			$data['reply']		= $data[$reply_type]	= $callback;
		}elseif($default_replies && isset($default_replies[$keyword])){
			$data['keyword']	= $keyword; 
			$data['type']		= 'text';
			$data['match']		= 'full';
			$data['status']		= 1;
			$data['reply']		= $data['text']		= $default_replies[$keyword]['value'];
		}

		return $data;
	}

	public static function views(){
		if(self::$tab != 'custom'){
			return [];
		}

		$types		= self::get_types();
		$matches	= self::get_matches();
		
		$_type		= $_REQUEST['type'] ?? '';
		$_match		= $_REQUEST['type'] ?? '';
		$_status	= $_REQUEST['status'] ?? 1;
		
		$views	= [];

		$class	= (empty($type) && empty($_match) && $_status) ? 'current':'';
		$total	= self::Query()->where('appid', static::get_appid())->where('status', 1)->get_var('count(*)');

		$views['all']	= wpjam_get_list_table_filter_link(['type'=>0,'status'=>1], '全部<span class="count">（'.$total.'）</span>', $class);

		$counts	= self::Query()->where('appid', static::get_appid())->where('status', 1)->group_by('match')->order_by('count')->get_results('COUNT( * ) AS count, `match`');
		foreach($counts as list('match'=>$match, 'count'=>$count)){ 
			$class	= $_match == $match ? 'current':'';

			if(isset($matches[$match])){
				$views[$match] = wpjam_get_list_table_filter_link(['match'=>$match,'status'=>1], $matches[$match].'<span class="count">（'.$count.'）', $class);
			}
		}

		$counts	= self::Query()->where('appid', static::get_appid())->where('status', 1)->group_by('type')->order_by('count')->get_results('COUNT( * ) AS count, `type`');
		foreach($counts as list('type'=>$type, 'count'=>$count)){
			$class	= $_type == $type ? 'current':'';

			if(isset($types[$type])){
				$views[$type] = wpjam_get_list_table_filter_link(['type'=>$type,'status'=>1], $types[$type].'<span class="count">（'.$count.'）', $class);
			}
		}

		$class		= empty($_status) ? 'current':'';
		$status_0 	= self::Query()->where('appid', static::get_appid())->where('status', 0)->get_var('count(*)');

		$views['status-0']	= wpjam_get_list_table_filter_link(['type'=>0,'status'=>0], '未激活<span class="count">（'.$status_0.'）</span>', $class);

		return $views;
	}

	public static function query_items($limit, $offset){
		if(self::$tab == 'custom'){
			$status	= $_REQUEST['status'] ?? 1;
			$type	= $_REQUEST['type'] ?? null;
			$type	= $type	?: null;
			self::Query()->where('appid', static::get_appid())->where('status', $status)->where('type', $type);
			return parent::list($limit, $offset);
		}elseif(self::$tab == 'default'){
			$items	= parent::get_default_replies();

			if(weixin_get_type() < 4){
				unset($items['[event-location]']);
			}

			array_walk($items, function(&$item, $key){
				$item['keyword']	= $key;
				$item['key']		= str_replace(['[',']'], '', $key);
			});
			
			$total = count($items);

			return compact('items', 'total');
		}elseif(self::$tab == 'builtin'){
			$builtin_replies = parent::get_builtin_replies(); 
			$items = [];

			foreach($builtin_replies as $keyword => $builtin_reply){
				if(isset($builtin_reply['callback'])){
					$function	= $builtin_reply['callback'];
				}elseif(isset($builtin_reply['function'])){
					$function	= $builtin_reply['function'];
				}elseif(!empty($builtin_reply['method'])){
					$function	= 'WEIXIN_Reply::'.$builtin_reply['method'];
				}else{
					$function	= '';
				}

				if(is_array($function)){
					$function	= implode('::', $function);
				}

				$keywords = isset($items[$function]['keywords'])?$items[$function]['keywords'].', ':'';

				$items[$function]['id']			= $function;
				$items[$function]['keywords']	= $keywords.$keyword;
				$items[$function]['type'] 		= $builtin_reply['type'];
				$items[$function]['reply'] 		= $builtin_reply['reply'];
				$items[$function]['function'] 	= $function;
			}

			$total = count($items);

			return compact('items', 'total');
		}
	}

	public static function item_callback($item){
		if(self::$tab == 'builtin'){
			return $item;
		}elseif(self::$tab == 'default'){
			$data	= self::get_by_keyword($item['keyword']);
			$item	= wp_parse_args($item, $data);
		}

		$type	= $item['type'];
		
		if($type == '3rd'){
			$weixin_setting	= weixin_get_setting();
			$item['reply']	= $weixin_setting['weixin_3rd_'.$item['reply']];
		}elseif($type == 'img'){
			$reply_post_ids	= explode(',', $item['reply']);
			$item['reply']	= '';

			$count			= count($reply_post_ids);
			$i				= 1;

			if($reply_post_ids){
				foreach ($reply_post_ids as $reply_post_id) {
					if($reply_post_id){

						$reply_post = get_post($reply_post_id);
						if($reply_post){

							$item_img	= ($i == 1)? wpjam_get_post_thumbnail_url($reply_post, [640,320]):wpjam_get_post_thumbnail_url($reply_post, [80,80]);
							$item_div_class	= ($i == 1)? 'big':'small'; 
							$item_a_class	= ($i == $count)?'noborder':''; 
							$item_excerpt	= ($count == 1)? wpautop(get_the_excerpt($reply_post)) : '';
							$iframe_width	= ($i == 1)? '320':'40'; 
							$iframe_height	= ($i == 1)? '160':'40'; 

							if(!$weixin_url = get_post_meta( $reply_post_id, 'weixin_url', true )){
								$weixin_url = get_permalink( $reply_post_id);
							}

							if(strpos($item_img, 'https://mmbiz.') !== false || strpos($item_img, 'http://mmbiz.') !== false){
								$thumb_img		='<img class="weixin_img" src="'.$news_item['thumb_url'].'" width="'.$iframe_width.'" height="'.$iframe_height.'" data-url="'.$news_item['url'].'" />';

								$item['reply'] .= '
								<a class="'.$item_a_class.'" target="_blank" href="'.$weixin_url.'">
									<div class="img_container '.$item_div_class.'">
										<h3>'.$reply_post->post_title.'</h3>
										<img class="weixin_img" src="'.$item_img.'" width="'.$iframe_width.'" height="'.$iframe_height.'" data-url="'.$weixin_url.'" />
									</div>
									'.$item_excerpt.'
								</a>';
							}else{
								$item['reply'] .= '
								<a class="'.$item_a_class.'" target="_blank" href="'.$weixin_url.'">
									<div class="img_container '.$item_div_class.'" style="background-image:url('.$item_img.');">
										<h3>'.$reply_post->post_title.'</h3>
									</div>
									'.$item_excerpt.'
								</a>';
							}

							break;

							$i++;
						}
					}
				}
				$item['reply']	= '<div class="reply_item">'.$item['reply'].'</div>';
			}
		}elseif($type == 'img2'){		
			$raw_reply		= str_replace("\r\n", "\n", maybe_unserialize($item['reply']));
			if(is_array($raw_reply)){
				$item_title		= $raw_reply['title'] ?? '';
				$item_excerpt	= $raw_reply['description'] ?? '';
				$item_img		= $raw_reply['pic_url'] ?? '';
				$item_url		= $raw_reply['url'] ?? '';
			}else{
				$lines = explode("\n", $raw_reply);
	
				$item_title		= $lines[0] ?? '';
				$item_excerpt	= $lines[1] ?? '';
				$item_img		= $lines[2] ?? '';
				$item_url		= $lines[3] ?? '';
			}
			
			$item_div_class	= 'big'; 
			$item_a_class	= 'noborder';
			$iframe_width	= '360'; 
			$iframe_height	= '150'; 

			$item_a_class	= 'noborder';

			if(strpos($item_img, 'https://mmbiz.') !== false || strpos($item_img, 'http://mmbiz.') !== false){
				$item['reply'] = '
				<a class="'.$item_a_class.'" target="_blank" href="'.$item_url.'">
					<div class="img_container '.$item_div_class.'">
						<h3>'.$item_title.'</h3>
						<img class="weixin_img" src="'.$item_img.'" width="'.$iframe_width.'" height="'.$iframe_height.'" data-url="'.$item_url.'" />
					</div>
					<p>'.$item_excerpt.'</p>
				</a>';
			}else{
				$item['reply'] = '
				<a class="'.$item_a_class.'" target="_blank" href="'.$item_url.'">
					<div class="img_container '.$item_div_class.'" style="background-image:url('.$item_img.');">
						<h3>'.$item_title.'</h3>
					</div>
					<p>'.$item_excerpt.'</p>
				</a>';
			}

			$item['reply']	= '<div class="reply_item">'.$item['reply'].'</div>';
		}elseif($type == 'news'){
			if(weixin_get_type() >= 3){
				$material	= weixin()->get_material($item['reply'], 'news');
				if(is_wp_error($material)){
					if($material->get_error_code() == '40007'){
						self::update($item['id'], ['status'=>0]);	
					}
					
					$item['reply'] = $material->get_error_code().' '.$material->get_error_message();
				}else{
					$count	= count($material);
					$i		= 1;
					$item['reply']	= '';
					foreach ($material as $news_item) {

						$item_div_class	= ($i == 1)? 'big':'small'; 
						$item_a_class	= ($i == $count)?'noborder':''; 
						$item_excerpt	= ($count == 1)?'<p>'.$news_item['digest'].'</p>':'';
						$iframe_width	= ($i == 1)? '360':'40'; 
						$iframe_height	= ($i == 1)? '150':'40';

						$item['reply']   .= '
						<a class="'.$item_a_class.'" target="_blank" href="'.$news_item['url'] .'">
						<div class="img_container '.$item_div_class.'">
							<h3>'.$news_item['title'].'</h3>
							<img class="weixin_img" src="'.$news_item['thumb_url'].'" width="'.$iframe_width.'" height="'.$iframe_height.'" data-url="'.$news_item['url'].'" />
						</div>
						'.$item_excerpt.'
						</a>';

						// break;
						
						$i++;
					}
					$item['reply'] 	= '<div class="reply_item">'.$item['reply'].'</div>';
				}
			}
		}elseif($type == 'image'){
			if(weixin_get_type() >= 3){
				$image	= weixin()->get_material($item['reply'], 'image');
				if(!is_wp_error($image)){
					$item['reply']	= '<a href="'.$image.'" target="_blank"><img src="'.$image.'" style="max-width:200px;" /></a>';
				}
			}
		}elseif($type == 'function'){
			if(is_array($item['reply'])){
				$item['reply']	= wpautop($item['reply'][0].'::'.$item['reply'][1]);
				unset($item['row_actions']);
			}else{
				$item['reply']	= wpautop($item['reply']);
			}
		}else{
			$item['reply']	= wpautop($item['reply']);
		}

		return $item;
	}

	public static function get_actions(){
		if(self::$tab == 'default'){
			return ['set'	=> ['title'=>'设置']];
		}elseif(self::$tab == 'builtin'){
			return [];
		}else{
			return [
				'add'		=> ['title'=>'新建'],
				'edit'		=> ['title'=>'编辑'],
				'duplicate'	=> ['title'=>'复制'],
				'delete'	=> ['title'=>'删除',	'direct'=>true,	'bulk'=>true, 'confirm'=>true],
			];
		}
	}

	public static function get_fields($key='', $id=0, $type_key='type'){
		$matches	= self::get_matches();
		$types		= self::get_types();

		if(self::$tab == 'builtin'){
			return [
				'keywords'	=> ['title'=>'关键字',	'type'=>'view',	'show_admin_column'=>true],
				'type'		=> ['title'=>'匹配方式',	'type'=>'view',	'show_admin_column'=>true,	'options'=>$matches],
				'reply'		=> ['title'=>'描述',		'type'=>'view',	'show_admin_column'=>true],
				'function'	=> ['title'=>'处理函数',	'type'=>'view',	'show_admin_column'=>true]
			];
		}

		$weixin_setting	= weixin_get_setting();

		$third_options	= [];
		foreach ([1,2,3] as $i) {
			if(!empty($weixin_setting['weixin_3rd_'.$i]) && !empty($weixin_setting['weixin_3rd_url_'.$i])){
				$third_options[$i] = $weixin_setting['weixin_3rd_'.$i];
			}
		}

		if(!$third_options){
			unset($types['3rd']);
		}

		$kf_options = [];
		if(weixin_get_type() >= 3 && !empty($weixin_setting['weixin_dkf'])){
			if($weixin_kf_list 	= weixin()->get_customservice_kf_list()){
				$kf_options	= [''=>' '];
				foreach ($weixin_kf_list as $weixin_kf_account) {
					$kf_options[$weixin_kf_account['kf_account']] = $weixin_kf_account['kf_nick'];
				}
			}
		}

		if(empty($weixin_setting['weixin_search'])){
			unset($types['img']);
		}

		$match_description	= '<p>前缀匹配支持匹配前两个中文字或字母。<br />模糊匹配效率比较低，请不要大量使用。</p>';

		$fields	= [
			'keyword'	=> ['title'=>'关键字',		'type'=>'text',		'show_admin_column'=>true,	'description'=>'多个关键字请用<strong>英文逗号</strong>分开'],
			'match'		=> ['title'=>'匹配方式',		'type'=>'radio',	'show_admin_column'=>true,	'options'=>$matches,	'description'=>$match_description],
			$type_key	=> ['title'=>'回复类型',		'type'=>'select',	'show_admin_column'=>true,	'options'=>$types],
			'reply'		=> ['title'=>'回复内容',		'type'=>'textarea',	'show_admin_column'=>'only'],
			'text'		=> ['title'=>'文本内容',		'type'=>'textarea',	'show_if'=>['key'=>$type_key, 'value'=>'text']],
			'img2'		=> ['title'=>'自定义图文',	'type'=>'fieldset',	'show_if'=>['key'=>$type_key, 'value'=>'img2'],	'fieldset_type'=>'array',	'fields'=>[
				'title'			=> ['title'=>'标题',	'type'=>'text'],
				'description'	=> ['title'=>'摘要',	'type'=>'textarea',	'rows'=>3],
				'pic_url'		=> ['title'=>'图片',	'type'=>'image'],
				'url'			=> ['title'=>'链接',	'type'=>'url'],
			]],
			'img'		=> ['title'=>'文章图文',		'type'=>'number',	'show_if'=>['key'=>$type_key, 'value'=>'img']],
			'news'		=> ['title'=>'素材图文',		'type'=>'text',		'show_if'=>['key'=>$type_key, 'value'=>'news'],		'class'=>'large-text'],
			'image'		=> ['title'=>'图片',			'type'=>'text',		'show_if'=>['key'=>$type_key, 'value'=>'image'],		'class'=>'large-text'],
			'voice'		=> ['title'=>'语音',			'type'=>'text',		'show_if'=>['key'=>$type_key, 'value'=>'voice'],		'class'=>'large-text'],
			'music'		=> ['title'=>'音乐',			'type'=>'textarea',	'show_if'=>['key'=>$type_key, 'value'=>'music']],
			'dkf'		=> ['title'=>'转到多客服',	'type'=>'select',	'show_if'=>['key'=>$type_key, 'value'=>'dkf'],	'options'=>$kf_options],
			'3rd'		=> ['title'=>'转到第三方',	'type'=>'select',	'show_if'=>['key'=>$type_key, 'value'=>'3rd'],	'options'=>$third_options],
			'wxcard'	=> ['title'=>'微信卡券id',	'type'=>'text',		'show_if'=>['key'=>$type_key, 'value'=>'wxcard'],		'class'=>'large-text'],
			'function'	=> ['title'=>'函数',			'type'=>'text',		'show_if'=>['key'=>$type_key, 'value'=>'function'],		'class'=>'large-text'],
			'status'	=> ['title'=>'状态',			'type'=>'checkbox',	'description'=>'激活',	'value'=>1]
		];

		foreach(self::get_descriptions() as $key => $description){
			if(!isset($types[$key])){
				unset($fields[$key]);
			}elseif($description){
				$fields[$key]['placeholder']	= $description;
			}
		}

		if(self::$tab == 'default'){
			$fields	= ['title'=>['title'=>'类型', 'type'=>'view', 'show_admin_column'=>true]]+$fields;

			$fields['keyword']['type']	= 'hidden';
			unset($fields['match']);
		}

		return $fields;
	}

	public static function get_descriptions(){
		return [
			'text'		=> '请输入要回复的文本，可以使用 a 标签实现链接跳转。',
			'img'		=> '请输入文章ID。',
			'img2'		=> '',
			'news'		=> '请输入图文的 Media ID，Media ID 从素材管理获取。',
			'image'		=> '请输入图片的 Media ID，Media ID 从素材管理获取。',
			'voice'		=> '请输入语音的 Media ID，Media ID 从素材管理获取。',
			'video'		=> '请输入视频的 Media ID，标题，摘要，每个一行，Media ID 从素材管理获取。',
			'music'		=> '请输入音乐的标题，描述，链接，高清连接，缩略图的 Media ID，每个一行，Media ID 从素材管理获取。',
			'function'	=> '请输入函数名，该功能仅限于程序员测试使用。',
			'dkf'		=> '',
			'3rd'		=> '',
			'wxcard'	=> '请输入微信卡券ID。'
		];
	}

	public static function get_types($all=false){
		$types = [
			'text'	=> '文本',
			'img2'	=> '自定义图文',
			'img'	=> '文章图文',
		];

		if(weixin_get_type() >=3 || $all){
			$types['news']	= '素材图文';
			$types['image']	= '图片';
			$types['voice']	= '语音';
			// $types['video']	= '视频';
			$types['music']	= '音乐';

			$weixin_setting = weixin_get_setting();
			if(!empty($weixin_setting['weixin_dkf']) || $all){
				$types['dkf']	= '转到多客服';
			}
		}

		$types['3rd']		= '转到第三方';
		$types['function']	= '函数';

		return $types;
	}

	public static function get_matches(){
		return [
			'full'		=>'完全匹配',
			'prefix'	=>'前缀匹配',
			'fuzzy'		=>'模糊匹配'
		];
	}

	public static function get_filterable_fields(){
		if(self::$tab != 'custom'){
			return [];
		}
		
		return parent::get_filterable_fields();
	}
}