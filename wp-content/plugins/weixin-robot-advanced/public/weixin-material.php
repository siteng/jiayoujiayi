<?php
class WEIXIN_Material{
	use WEIXIN_Trait;

	public static $type = '';

	public static function set_type($type){
		self::$type	= $type; 
	}

	public static function get($media_id){

		$item = weixin()->get_material($media_id, self::$type);

		if(is_wp_error($item)){
			return $item;
		}

		if(self::$type == 'news'){
			return [
				'update_time'	=> time(),
				'media_id'		=> $media_id,
				'content'		=> ['news_item'=>$item]
			];
		}else{
			return $item;
		}
	}

	public static function insert($data){
		if(self::$type == 'news'){
			$post_ids	= wpjam_get_data_parameter('post_ids');

			if(empty($post_ids)){
				return new WP_Error('empty_posts', '未选择文章');
			}

			$articles	= [];

			$posts	= WPJAM_Post::update_caches($post_ids);

			add_filter('wpjam_thumbnail_args', function($args){
				return $args+['webp'=>0];
			});

			remove_filter('the_content',	['WPJAM_CDN', 'content_images'], 5);

			foreach($post_ids as $post_id){
				if($post = get_post($post_id)){
					if($thumb_url = wpjam_get_post_thumbnail_url($post, 'full')){
						$response	= weixin()->add_material_by_remote_image($thumb_url);

						if(is_wp_error($response)){
							return $response;
						}

						$author		= get_userdata($post->post_author);

						$content	= get_the_content('', false, $post);
						$content	= apply_filters('the_content', $content);
						$content	= str_replace(']]>', ']]&gt;', $content);

						$articles[]	= [
							'title'					=> html_entity_decode(get_the_title($post)),
							'digest'				=> '',//html_entity_decode(get_the_excerpt($post)),
							'content'				=> $content,
							'author'				=> $author ? $author->display_name : '',
							'thumb_media_id'		=> $response['media_id'],
							'content_source_url'	=> get_permalink($post),
							'show_cover_pic'		=> 0,
							'need_open_comment'		=> 1,
							'only_fans_can_comment'	=> 1,
						];
					}
				}
			}

			$response = weixin()->add_news_material($articles);

			if(is_wp_error($response)){
				return $response;
			}

			return $response['media_id'];
		}else{
			return weixin()->add_material($data, self::$type);
		}
	}

	public static function update($id, $data){
		return weixin()->update_news_material($media_id, $data['index'], $data['articles']);
	}

	public static function delete($media_id){
		return weixin()->del_material($media_id);
	}

	public static function bulk_combine($media_ids){
		if(empty($media_ids)){
			return;
		}

		$new_articles	= [];

		foreach ($media_ids as $media_id) {
			$articles	= self::prepare_for_add($media_id);

			if(is_wp_error($articles)){
				return $articles;
			}

			$new_articles	= array_merge($new_articles, $articles);
		}


		$result	= weixin()->add_news_material($new_articles);

		if(is_wp_error($result)){
			return $result;
		}

		return $result['media_id'];
	}

	public static function reply($media_id, $data){
		$reply_data			= [
			'keyword'	=> $data['keyword'],
			'match'		=> $data['match']??'full',
			'type'		=> self::$type,
			self::$type	=> maybe_serialize($data[self::$type]),
			'status'	=> 1
		];

		return WEIXIN_AdminReplySetting::set($reply_data);
	}

	// 后台 list table 显示
	public static function query_items($limit, $offset){
		$material = weixin()->batch_get_material(self::$type, $offset, $limit);

		if(is_wp_error($material)){
			return $material;
		}else{
			if(isset($material['item'])){
				$items	= $material['item'];
				$total	= $material['total_count'];
			}else{
				$items	= [];
				$total	= 0;
			}
		}

		return compact('items', 'total');
	}

	public static function item_callback($item){
		$item['update_time'] = get_date_from_gmt(date('Y-m-d H:i:s',$item['update_time']));

		if(self::$type == 'news' ){
			if(is_array( $item['content']['news_item'] ) ){
				$content	= '';
				$i 			= 1;
				$count		= count($item['content']['news_item']);

				foreach ($item['content']['news_item'] as $news_item) {

					$item_div_class	= ($i == 1)? 'big':'small'; 
					$item_a_class	= ($i == $count)?'noborder':''; 
					$item_excerpt	= ($count == 1)?'<p>'.$news_item['digest'].'</p>':'';
					$iframe_width	= ($i == 1)? '360':'40';
					$iframe_height	= ($i == 1)? '150':'40';

					$thumb_img		='<img class="weixin_img" src="'.$news_item['thumb_url'].'" width="'.$iframe_width.'" height="'.$iframe_height.'" data-url="'.$news_item['url'].'" />';

					$content   .= '
					<a class="'.$item_a_class.'" target="_blank" href="'.$news_item['url'] .'">
					<!--<div class="img_container '.$item_div_class.'" data-src="'.$news_item['thumb_url'].'" style="background-image:url('.$news_item['thumb_url'].');">
						<h3>'.$news_item['title'].'</h3>
					</div>-->
					<div class="img_container '.$item_div_class.'">
						<h3>'.$news_item['title'].'</h3>
						'.$thumb_img.'
					</div>
					'.$item_excerpt.'
					</a>';

					$i++;
				}
				$item['content'] 	= '<div class="reply_item">'.$content.'</div>';
			}
		}elseif(self::$type == 'image' ){
			if(!empty($item['url'])){
				// $item['name']	= '<div style="max-width:200px;"><script type="text/javascript">show_wx_img(\''.str_replace('/0?','/640?',$item['url']).'\');</script><a href="'.$item['url'].'" target="_blank">'.$item['name'].'</a></div>';
				$item['name']	= '<div style="max-width:200px;"><img class="weixin_img" src="'.$item['url'].'" /><a href="'.$item['url'].'" target="_blank">'.$item['name'].'</a></div>';
			}
		}

		$item['id']	= $item['media_id'];

		if(isset($item['row_actions'])){
			if(self::$type != 'video'){
				$row_actions	= array(
					// 'masssend'	=> '<a href="'.admin_url('admin.php?page=weixin-robot-masssend&content='.$item['media_id'].'&msgtype='.self::$type).'&TB_iframe=true&width=780&height=500" title="群发消息" class="thickbox">群发消息</a>',
					// 'reply'		=> '<a href="'.admin_url('admin.php?page=weixin-robot-replies&action=add&'.self::$type.'='.$item['media_id'].'&type='.self::$type).'&TB_iframe=true&width=780&height=500" title="新增自定义回复" class="thickbox">添加到自定义回复</a>'
					);

				$item['row_actions']	= array_merge($row_actions, $item['row_actions']);
			}

			if(self::$type == 'news'){
				unset($item['row_actions']['combine']);

				// if(current_user_can('manage_sites')){
				// 	$item['row_actions']['retina']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=retina&id='.$item['media_id'], 'retina-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">一键高清图片</a>';
				// }
				// $item['row_actions']['recache']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=recache&id='.$item['media_id'], 'recache-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">更新缓存</a>';
				// $item['row_actions']['duplicate']	= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=duplicate&id='.$item['media_id'], 'duplicate-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">复制</a>';
			}
		}

		return $item;
	}

	public static function recache($media_id){
		weixin()->cache_delete('material'.$media_id);
	}

	public static function duplicate($media_id){
		$articles	= self::prepare_for_add($media_id);

		if(is_wp_error($articles)){
			return $articles;
		}

		$result	= weixin()->add_news_material($articles);

		if(is_wp_error($result)){
			return $result;
		}

		return $result['media_id'];
	}

	public static function prepare_for_add($media_id, $n=0){
		$articles	= weixin()->get_material($media_id, 'news',  true);

		if(is_wp_error($articles)){
			return $articles;
		}

		if($n && isset($articles[$n-1])){
			$articles	= [$articles[$n-1]];
		}

		foreach ($articles as &$article){
			if(empty($article['thumb_media_id'])){
				$response	= weixin()->add_material_by_remote_image($article['thumb_url']);

				if(is_wp_error($response)){
					return $response;
				}

				$article['thumb_media_id']	= $response['media_id'];
			}
		}

		return $articles;
	}

	public static function retina($media_id){
		$articles	= weixin()->get_material($media_id, 'news', true);

		foreach ($articles as $index => $news_item) {
			$news_item['content'] = preg_replace_callback('/<img.*?data-src=[\'"](.*?)[\'"].*?>/i',function($matches){
				$img_url 	= trim($matches[1]);

				if(empty($img_url)) return;

				$img_url	= str_replace('/640?', '/0?', $img_url);

				if(!preg_match('|<img.*?srcset=[\'"](.*?)[\'"].*?>|i',$matches[0],$srcset_matches)){
					return str_replace('data-src', ' data-srcset="'.$img_url.' 2x"  data-src', $matches[0]);
				}

				return $matches[0];
			},$news_item['content']);

			weixin()->update_news_material($media_id, $index, $news_item);
		}
	}

	public static function ajax_fetch_material(){
		$mp_url	= wpjam_get_data_parameter('mp_url');

		if(empty($mp_url)){
			return new WP_Error('emoty_mp_url','输入图文链接不能为空');
		}

		$article = weixin_parse_mp_article($mp_url);

		if(is_wp_error($article)){
			return $article;
		}

		$response	= weixin()->add_material_by_remote_image($article['thumb_url']);

		if(is_wp_error($response)){
			return $response;
		}

		$media_id	= $response['media_id'];

		$article['thumb_media_id']			= $media_id;
		$article['show_cover_pic']			= 0;
		$article['need_open_comment']		= 1;
		$article['only_fans_can_comment']	= 1;
		$article['content']					= strip_tags($article['content'],'<p><img><br><span><section><strong><iframe><blockquote>');

		unset($article['thumb_url']);

		$result	= weixin()->add_news_material([$article]);

		if(is_wp_error($result)){
			return $result;
		}

		return ['errmsg'=>'一键转载成功，<a href="'.admin_url('admin.php?page=weixin-material').'">请点击这里查看。</a>'];
	}

	public static function ajax_combine_material(){
		$medias			= wpjam_get_data_parameter('medias');
		$new_articles	= [];

		if($medias){
			foreach($medias as $media){
				if($media_id = trim($media['media_id'])){
					$n 			= trim($media['n']);
					$articles	= self::prepare_for_add($media_id, $n);

					if(is_wp_error($articles)){
						return $articles;
					}

					$new_articles	= array_merge($new_articles, $articles);
				}
			}
		}

		if($new_articles){
			$response	= weixin()->add_news_material($new_articles);

			if(is_wp_error($response)){
				return $response;
			}else{
				return ['errmsg'=>'合并成功，<a href="'.admin_url('admin.php?page=weixin-material').'">请点击这里查看。</a>'];
			}
		}else{
			return new WP_Error('empty_medias', '你没有输入任何素材ID！');
		}
	}

	public static function get_actions(){
		if(self::$type == 'news'){
			return [
				'add'		=> ['title'=>'新增'],
				'reply'		=> ['title'=>'添加到自定义回复',	'update_row'=>false],
				'recache'	=> ['title'=>'更新缓存',	'direct'=>true],
				'combine'	=> ['title'=>'合并',		'direct'=>true, 'bulk'=>true,	'response'=>'add'],
				'duplicate'	=> ['title'=>'复制',		'direct'=>true],
				'delete'	=> ['title'=>'删除',		'direct'=>true,	'confirm'=>true,	'bulk'=>true],
			];
		}else{
			return	[
				'reply'		=> ['title'=>'添加到自定义回复',	'update_row'=>false],
				'delete'	=> ['title'=>'删除',		'direct'=>true,	'confirm'=>true,	'bulk'=>true],
			];
		}
	}

	public static function get_fields($action_key='', $media_id=''){
		if($action_key == 'reply'){
			$fields		= WEIXIN_AdminReplySetting::get_fields();

			$fields['type']['value']		= self::$type;
			$fields['type']['type']			= 'view';
			$fields[self::$type]['value']	= $media_id;

			foreach (WEIXIN_AdminReplySetting::get_types() as $key => $type) {
				if($key != self::$type){
					unset($fields[$key]);
				}
			}

			unset($fields['status']);

			return $fields;
		}elseif($action_key == 'add'){
			return [
				'post_ids'	=> ['title'=>'选择文章',	'type'=>'mu-text',	'data_type'=>'post_type',	'post_type'=>'any',	'max_items'=>3,	'placeholder'=>'输入文章ID或者关键字进行搜索...']
			];
		}else{
			if(self::$type == 'news'){
				return [
					'content'		=> ['title'=>'内容',			'type'=>'text',	'show_admin_column'=>true],
					'media_id'		=> ['title'=>'Media ID',	'type'=>'text',	'show_admin_column'=>true],
					'update_time'	=> ['title'=>'最后更新时间',	'type'=>'text',	'show_admin_column'=>true]
				];
			}else{
				return [
					'name'			=> ['title'=>'内容',			'type'=>'text',	'show_admin_column'=>true],
					'media_id'		=> ['title'=>'Media ID',	'type'=>'text',	'show_admin_column'=>true],
					'update_time'	=> ['title'=>'最后更新时间',	'type'=>'text',	'show_admin_column'=>true]
				];
			}
		}
	}

	public static function extra_tablenav($which){
		if(self::$type == 'image' && $which == 'top'){ ?>
			<input id="new_image" type="file" name="image" style="filter:alpha(opacity=0);position:absolute;opacity:0;width:80px;height:34px; margin:-5px 0;" hidefocus>  
			<a href="#" class="page-title-action button-primary" style="position:static;">上传图片</a>
			<script type="text/javascript">
			jQuery(function($){
				$('body').on('change', '#new_image', function(){
					if($('#new_image').val()){
						$('form#list_table_form')
						.attr('enctype', 'multipart/form-data')
						.attr('encoding', 'multipart/form-data')	// for ie
						.submit();
					}
				});
			});
			</script>
		<?php }
	}

	public static function load_list_table_page($current_tab){
		include WEIXIN_ROBOT_PLUGIN_DIR.'public/weixin-reply.php';

		WEIXIN_Material::set_type($current_tab);

		wpjam_register_list_table('weixin-material', [
			'title'				=> wpjam_get_current_tab_setting('name').'素材',
			'singular'			=> 'weixin-material',
			'plural'			=> 'weixin-materials',
			'primary_column'	=> 'media_id',
			'primary_key'		=> 'media_id',
			'model'				=> 'WEIXIN_Material',
			'per_page'			=> $current_tab == 'news' ? 10 : 20,
		]);

		add_action('admin_enqueue_scripts', function(){
			wp_enqueue_style('weixin-news-items',	WEIXIN_ROBOT_PLUGIN_URL.'static/news-items.css');
			wp_add_inline_style('weixin-news-items', "\n".'th.column-update_time{width:90px;}');
		});
	}

	public static function load_fetch_page(){
		wpjam_register_page_action('weixin_fetch_material', [
			'submit_text'	=> '转载',
			'summary'		=> '*内容中的视频和投票信息无法转载',
			'fields'		=> ['mp_url'=>['title'=>'',	'type'=>'url',	'placeholder'=>'请输入要转载的图文链接',	'style'=>'width:640px;',	'required']],
			'callback'		=> ['WEIXIN_Material', 'ajax_fetch_material']
		]);
	}

	public static function load_combine_page(){
		wpjam_register_page_action('weixin_combine_material', [
			'submit_text'	=> '合并',
			'fields'		=>[
				'medias'	=> ['title'=>'',	'type'=>'mu-fields',	'fields'=>[
					'media_id'	=>['title'=>'Media_id',	'type'=>'text',		'placeholder'=>'请输入图文素材的 Media ID'],
					'n'			=>['title'=>'第几条',	'type'=>'number',	'description'=>'不填则为全部']
				]]
			],
			'callback'		=> ['WEIXIN_Material', 'ajax_combine_material']
		]);
	}
}

$material_count = weixin()->get_material_count();

if(is_wp_error($material_count)){
	wp_die($material_count->get_error_message());
}

foreach(['news'=>'图文', 'image'=>'图片',	'voice'=>'语音',	'video'=>'视频'] as $type => $name){
	wpjam_register_plugin_page_tab($type,	[
		'name'			=>$name,
		'title'			=>$name.' <small>('.$material_count[$type.'_count'].')</small>',
		'function'		=>'list',
		'load_callback'	=>['WEIXIN_Material', 'load_list_table_page']
	]);
}

wpjam_register_plugin_page_tab('fetch',		[
	'title'			=>'一键转载',
	'function'		=>'form',
	'form_name'		=>'weixin_fetch_material',
	'load_callback'	=>['WEIXIN_Material', 'load_fetch_page']
]);

wpjam_register_plugin_page_tab('combine',	[
	'title'			=>'合并图文',
	'function'		=>'form',
	'form_name'		=>'weixin_combine_material',
	'load_callback'	=>['WEIXIN_Material', 'load_combine_page']
]);

if(isset($_FILES['image'])){
	if(!current_user_can('manage_options')){
		wp_die('无权限！');
	}

	$media	= $_FILES['image'];

	if(empty($media['tmp_name'])){
		wp_die('请上传导入文件');
	}

	if($media['error']){
		wp_die('导入文件异常'.$media['error']);
	}

	$response	= weixin()->add_material($media['tmp_name'], 'image', ['filename'=>$media['name'], 'filetype'=>$media['type']]);

	if(is_wp_error($response)){
		wpjam_admin_add_error($response->get_error_code().'：'.$response->get_error_message());
	}else{
		wpjam_admin_add_error('图片新增成功');
	}
}