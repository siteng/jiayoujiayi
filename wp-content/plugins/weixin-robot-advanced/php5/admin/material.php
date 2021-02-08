<?php
function weixin_robot_material_tabs($tabs){
	global $plugin_page;
	if($plugin_page != 'weixin-robot-material')
		return;
	
	$material_count = weixin_robot_get_material_count();

	foreach (weixin_robot_get_material_type_list() as $type => $name) {
		$tabs[$type]	= array(
			'title'		=> $name.' <small>('.$material_count[$type.'_count'].')</small>', 
			'function'	=> 'weixin_robot_material_page'
		);
	}

//	$tabs['fetch']	= array(
//		'title'		=> '一键转载<small></small>',
//		'function'	=> 'weixin_robot_material_fetch_page'
//	);
//
//	$tabs['combine']	= array(
//		'title'		=> '合并图文<small></small>',
//		'function'	=> 'weixin_robot_material_combine_page'
//	);

	if(isset($_GET['tab']) && $_GET['tab']=='edit'){
		$tabs['edit']	= array(
			'title'		=> '编辑图文<small></small>',
			'function'	=> 'weixin_robot_material_edit_page'
		);
	}

	return $tabs;
}

function weixin_robot_material_page_load($page){
	global $weixin_list_table, $current_tab;

	if($current_tab == 'combine'){
		return;
	}elseif($current_tab == 'news'){
		$columns		= array(
			'cb'			=> 'checkbox',
			'content'		=> '内容',
			'media_id'		=> 'Media ID',
			'update_time'	=> '最后更新时间',
		);
//		$bulk_actions 	= array('delete' => '删除','combine'=>'合并');
		$bulk_actions 	= array('delete' => '删除');
		$per_page		= 10;
	}else{
		$columns		= array(
			'cb'			=> 'checkbox',
			'name'			=> '名称',
			'media_id'		=> 'Media ID',
			'update_time'	=> '最后更新时间',
		);
		$bulk_actions 	= array('delete' => '删除');
		$per_page		= 20;
	}

	$style	= 'th.column-update_time{width:90px;}';

	$weixin_list_table = wpjam_list_table( array(
		'plural'			=> 'weixin-materials',
		'singular' 			=> 'weixin-material',
		'item_callback'		=> 'weixin_robot_material_item',
		'columns'			=> $columns,
		'bulk_actions'		=> $bulk_actions,
		'actions_column'	=> 'media_id',
		'per_page'			=> $per_page,
		'style'				=> $style,
	) );
}

function weixin_robot_material_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';
	if($action == 'edit' || $action == 'add' || $action == 'set' ){
		weixin_robot_material_edit_page();
	}else{
		weixin_robot_material_list_page();
	}
}

function weixin_robot_material_list_page(){
	global $weixin_list_table,$current_tab;

	$type_list		= weixin_robot_get_material_type_list();

	$action			= $weixin_list_table->current_action();
	$redirect_to	= wpjam_get_referer();

	if($action){
		$media_id	= isset($_GET['id'])?$_GET['id']:'';
	}

	if($action == 'delete'){

		if( !current_user_can( 'delete_weixin_material' )){
			ob_clean();
			wp_die('无权限');
		}

		if($media_id){
			
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$media_id);

			$response = weixin_robot_del_material($media_id);

			if(is_wp_error($response)){
				$redirect_to = add_query_arg( array( 'deleted' => $media_id.'：'.$response->get_error_code().'-'.$response->get_error_message() ), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}

			wp_redirect($redirect_to);
		}elseif (!empty($_GET['ids'])) {
			check_admin_referer('bulk-'.$weixin_list_table->get_plural());

			$error = false;
			foreach ($_GET['ids'] as $media_id) {
				$response = weixin_robot_del_material($media_id);
				if(is_wp_error($response)){
					$error = true;
					wpjam_admin_add_error($id.'：'.$response->get_error_code().'-'.$response->get_error_message(),'error');
				}
			}
			if($error == false){
				wpjam_admin_add_error('删除成功');
			}
		}
	}elseif($action == 'recache'){

		if($media_id){
			check_admin_referer('recache-'.$weixin_list_table->get_singular().'-'.$media_id);
			wp_cache_delete($media_id, 'weixin_material');
			$redirect_to = add_query_arg( array( 'updated' => 'true' ), $redirect_to );	
			wp_redirect($redirect_to);
		}
	}elseif($action == 'duplicate'){

		if( !current_user_can( 'edit_weixin' )){
			ob_clean();
			wp_die('无权限');
		}
		
		if($media_id){

			check_admin_referer('duplicate-'.$weixin_list_table->get_singular().'-'.$media_id);
			$articles	= weixin_robot_get_material($media_id, 'news',  true);
			$response	= weixin_robot_add_news_material($articles);
			
			if(is_wp_error($response)){
				$redirect_to = add_query_arg( array( 'duplicated' => urlencode($response->get_error_code().'-'.$response->get_error_message())), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'duplicated' => 'true' ), $redirect_to );
			}

			wp_redirect($redirect_to);
		}
	}elseif($action == 'retina'){
		if( !current_user_can( 'edit_weixin' )){
			ob_clean();
			wp_die('无权限');
		}

		if($media_id){
			check_admin_referer('retina-'.$weixin_list_table->get_singular().'-'.$media_id);
			$articles	= weixin_robot_get_material($media_id, 'news', true);
			
			foreach ($articles as $index => $news_item) {
				$news_item['content'] = preg_replace_callback('/<img.*?data-src=[\'"](.*?)[\'"].*?>/i','weixin_robot_material_replace',$news_item['content']);
				weixin_robot_update_news_material($media_id, $index, $news_item);
			}

			$redirect_to = add_query_arg( array( 'updated' => 'true' ), $redirect_to );
			wp_redirect($redirect_to);
		}
	}elseif($action == 'combine'){
		if( !current_user_can( 'edit_weixin' )){
			ob_clean();
			wp_die('无权限');
		}

		if (!empty($_GET['ids'])) {
			check_admin_referer('bulk-'.$weixin_list_table->get_plural());

			$new_material	= array();
			$error = false;
			foreach ($_GET['ids'] as $media_id) {
				$material = weixin_robot_get_material($media_id, 'news', true);
				if(is_wp_error($material)){
					$error = true;
					wpjam_admin_add_error($id.'：'.$response->get_error_code().'-'.$response->get_error_message(),'error');
					break;
				}

				$new_material	= array_merge($new_material, $material);
			}


			if($error == false){
				if($new_material){
					$response	= weixin_robot_add_news_material($new_material);
					if(is_wp_error($response)){
						wpjam_admin_add_error($response->get_error_message(), 'error');
					}else{
						wpjam_admin_add_error('合并成功');
					}
				}
			}
		}
	}


	if($current_tab == 'image' && isset($_FILES['file'])){
		$result	= wp_handle_upload($_FILES['file'], array('test_form' => false));
		if(empty($result['error'])){
			$media		= $result['file'];
			$form_data	= array(
				'filename'		=>basename($result['url']),
				'content-type'	=>$result['type'], 
				'filelength'	=>filesize($media) 
			);

			$response	= weixin_robot_add_material($media);
			unlink($media);
			if(is_wp_error($response)){
				wpjam_admin_add_error($response->get_error_code().'：'.$response->get_error_message());
			}else{
				wpjam_admin_add_error('图片新增成功');
			}
		}
	}
	?>

	<h2>
	<?php echo $type_list[$current_tab]; ?>素材
	<?php if($current_tab == 'image'){ ?>
	<form action="#" method="post" enctype="multipart/form-data" name="new-image" id="new-image" style="display:inline-block;position:relative;">
		<input id="file" type="file" name="file" style="filter:alpha(opacity=0);position:absolute;opacity:0;width:40px;height:24px" hidefocus>  
		<a href="#" class="add-new-h2" style="position:static;">新增</a>
	</form>
	<script type="text/javascript">
	jQuery(function($){
		$('body').on('change', '#file', function(){
			if($('#file').val()){
				$('#new-image').submit();
			}
		});
	});
	function show_wx_img(url) {
		var frameid = 'frameimg' + Math.random();
		window.img = '<img id="img"  style="max-width:100%" src=\'' + url + '?' + Math.random() + '\' /><script>window.onload = function() { parent.document.getElementById(\'' + frameid + '\').height = document.getElementById(\'img\').height+\'px\'; }<' + '/script>';
		document.write('<iframe id="' + frameid + '" src="javascript:parent.img;" width="100%" frameBorder="0" scrolling="no"></iframe>');
		}
	</script>
	<?php } ?>
	</h2>

	<?php if($current_tab == 'news'){ ?>
	<p>因为需要将图文中的每张图片下载到本地，所以第一次加载会有点慢！</p>
	<script type="text/javascript">
	jQuery(function($){
		// var data_src = '';
		// jQuery('.img_container').each(function(){
		// 	data_src = 'http://read.html5.qq.com/image?src=forum&q=5&r=0&imgflag=7&imageUrl='+$(this).data('src');
		// 	$(this).css("backgroundImage",'url('+data_src+')');
		// });
	});
	</script>
	<link rel="stylesheet" type="text/css" href="<?php echo WEIXIN_ROBOT_PLUGIN_URL.'/template/static/news-items.css'?>">
	<?php } ?>
	
	<?php
	
	$offset 	= $weixin_list_table->get_offset();

	if($material = weixin_robot_batch_get_material($current_tab, $offset)){
		if(isset($material['item'])){
			$weixin_list_table->prepare_items($material['item'], $material['total_count']);
			$weixin_list_table->display(array('search'=>false));
		}
	}
}

function weixin_robot_material_item($item){
	global $weixin_list_table,$current_admin_url,$current_tab;
	$item['update_time'] = get_date_from_gmt(date('Y-m-d H:i:s',$item['update_time']));
	
	if($current_tab == 'news' ){
		if(is_array( $item['content']['news_item'] ) ){
			$content	= '';
			$i 			= 1;
			$count		= count($item['content']['news_item']);

			foreach ($item['content']['news_item'] as $news_item) {

				$item_div_class	= ($i == 1)? 'big':'small'; 
				$item_a_class	= ($i == $count)?'noborder':''; 
				$item_excerpt	= ($count == 1)?'<p>'.$news_item['digest'].'</p>':'';

				$thumb	= weixin_robot_get_material($news_item['thumb_media_id'], 'thumb');
				$thumb	= is_wp_error($thumb)?'':$thumb;

				$content   .= '
				<a class="'.$item_a_class.'" target="_blank" href="'.$news_item['url'] .'">
				<div class="img_container '.$item_div_class.'" data-src="'.$news_item['thumb_url'].'" style="background-image:url('.$thumb.');">
					<h3>'.$news_item['title'].'</h3>
				</div>
				'.$item_excerpt.'
				</a>';
				
				$i++;
			}
			$item['content'] 	= '<div class="reply_item">'.$content.'</div>';
		}
	}elseif($current_tab == 'image' ){
		if(!empty($item['url'])){
			$item['name']	= '<div style="max-width:200px;"><script type="text/javascript">show_wx_img(\''.str_replace('/0?','/640?',$item['url']).'\');</script><a href="'.$item['url'].'" target="_blank">'.$item['name'].'</a></div>';
		}
	}

	$item['id']	= $item['media_id'];



	$item['row_actions'] = array(
		// 'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&id='.$item['media_id'].'&TB_iframe=true&width=780&height=500').'" title="编辑自定义回复" class="thickbox">编辑</a>',
		// 'duplicate'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=duplicate&id='.$item['media_id'], 'duplicate-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">复制</a>',
		// 'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['media_id'], 'delete-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">删除</a>',
	);

	if($current_tab != 'video'){
		$item['row_actions']['masssend']= '<a href="'.admin_url('admin.php?page=weixin-robot-masssend&content='.$item['media_id'].'&msgtype='.$current_tab).'&TB_iframe=true&width=780&height=500" title="群发消息" class="thickbox">群发消息</a>';	
		$item['row_actions']['reply']	= '<a href="'.admin_url('admin.php?page=weixin-robot-replies&action=add&reply='.$item['media_id'].'&type='.$current_tab).'&TB_iframe=true&width=780&height=500" title="新增自定义回复" class="thickbox">添加到自定义回复</a>';	
	}

	if($current_tab == 'news'){
		if(current_user_can('manage_sites')){
			$item['row_actions']['retina']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=retina&id='.$item['media_id'], 'retina-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">一键高清图片</a>';
		}
		$item['row_actions']['recache']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=recache&id='.$item['media_id'], 'recache-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">更新缓存</a>';
		$item['row_actions']['duplicate']	= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=duplicate&id='.$item['media_id'], 'duplicate-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">复制</a>';
	}

	$item['row_actions']['delete']		= '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['media_id'], 'delete-'.$weixin_list_table->get_singular().'-'.$item['media_id'])).'">删除</a>';
	
	return $item;
}

function weixin_robot_material_replace($matches){
	$img_url 	= trim($matches[1]);

	if(empty($img_url)) return;

	$img_url	= str_replace('/640?', '/0?', $img_url);

	if(!preg_match('|<img.*?srcset=[\'"](.*?)[\'"].*?>|i',$matches[0],$srcset_matches)){
		return str_replace('data-src', ' data-srcset="'.$img_url.' 2x"  data-src', $matches[0]);
	}

	return $matches[0];
}

function weixin_robot_material_edit_page(){
	$media_id	= isset($_GET['media_id'])?$_GET['media_id']:'';
	$index		= isset($_GET['index'])?$_GET['index']:1;

	if(!$media_id || !$index){
		wp_die('media_id 或者 index 不能为空');
	}

	$material = weixin_robot_get_material($media_id, 'news', true);

	if(is_wp_error($material)){
		wp_die($media_id.'：'.$material->get_error_message());
	}

	if(empty($material[$index-1])){
		wp_die('第'.$index.'条图文不存在');
	}

	// wpjam_print_R($news_item);

	global $current_admin_url;

	$form_fields 	= array(
		'title'					=> array('title'=>'标题',	'type'=>'text',		),
		'content'				=> array('title'=>'内容',	'type'=>'editor',	'settings'=>array('default_ediotr'=>'quicktags')),
		'author'				=> array('title'=>'作者',	'type'=>'text'),
		'digest'				=> array('title'=>'摘要',	'type'=>'textarea',	'style'=>'max-width:640px;'),
		'content_source_url'	=> array('title'=>'原文链接',	'type'=>'url',		'style'=>'max-width:640px;',	'class'=>'large-text'),
		'thumb_media_id'		=> array('title'=>'头图',	'type'=>'text',		'style'=>'max-width:640px;',	'class'=>'large-text'),
	);

	$nonce_action = 'weixin-edit-material';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data = wpjam_get_form_post($form_fields, $nonce_action);

		$data['content']	= str_replace("\n", "", wpautop($data['content']));

		$news_item = $material[$index-1];

		$articles 	= array_merge($news_item, $data);

		$response	= weixin_robot_update_news_material($media_id, $index-1, $articles);

		if(is_wp_error($response)){
			wpjam_admin_add_error($media_id.'：'.$response->get_error_message(), 'error');			
		}else{
			wpjam_admin_add_error('更新成功');
		}

		wp_cache_delete($media_id, 'weixin_material');

		$material	= weixin_robot_get_material($media_id, 'news', true);
	}

	$news_item = $material[$index-1];

	$news_item['content']	= str_replace('</p>', "</p>\n\n", $news_item['content']);
	$news_item['content']	= str_replace("\n\n\n", "\n\n", $news_item['content']);

	foreach ($form_fields as $key => $form_field) {
		$form_fields[$key]['value'] = $news_item[$key];
	}

	$form_url = $current_admin_url.'&media_id='.$media_id.'&index='.$index;

	?>
	<h2>编辑图文</h2>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, '编辑'); ?>

	<?php
}

function weixin_robot_material_combine_page(){
	global $current_admin_url;

	$form_fields 	= array(
		'media_ids'		=> array('title'=>'',	'type'=>'textarea',	'style'=>'max-width:640px;' )
	);

	$nonce_action = 'weixin-combine-material';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');
		if($media_ids = $data['media_ids']){
			$new_material = array();

			$media_ids	= explode("\n", $media_ids);
			foreach ($media_ids as $media_id) {
				$media_id	= trim($media_id).'|';
				list($media_id, $n) = explode("|", $media_id);
				$material = weixin_robot_get_material($media_id, 'news', true);
				if(is_wp_error($material)){
					wpjam_admin_add_error($media_id.'：'.$material->get_error_message(), 'error');
					$new_material = array();
					break;
				}
				if($n){
					$new_material[]	= $material[$n-1];
				}else{
					$new_material	= array_merge($new_material, $material);
				}
			}

			if($new_material){
				$response	= weixin_robot_add_news_material($new_material);
				if(is_wp_error($response)){
					wpjam_admin_add_error($response->get_error_message(), 'error');
				}else{
					wpjam_admin_add_error('合并成功');
				}
			}
		}else{
			wpjam_admin_add_error('你没有输入任何素材ID！','error');
		}
	}

	?>
	<h2>合并图文</h2>

	<p>请按照格式输入要合并的图文：</p>

	<?php wpjam_form($form_fields, $current_admin_url, $nonce_action, '合并'); ?>

	<p>
	格式为：media_id|n，|后面为空则全部，比如：<br />
	<code>MHNViNjDYTcuCtmVYnmd8-MzQpTPLJjSmEhXbtik4pM|3</code> 为：MHNViNjDYTcuCtmVYnmd8-MzQpTPLJjSmEhXbtik4pM的第三条图文<br />
	<code>wpJr2hWr0dg_K9xETG7QM1-5vdNenyYerx3ddF9qulc</code> 为：wpJr2hWr0dg_K9xETG7QM1-5vdNenyYerx3ddF9qulc的所有图文
	</p>

	<?php
}

function weixin_robot_material_fetch_page(){
	global $current_admin_url;

	$form_fields 	= array(
		'mp_url'			=> array('title'=>'图文链接',			'type'=>'url',	'class'=>'large-text' ),
		'thumb_media_id'	=> array('title'=>'头图 media_id',	'type'=>'text',	'description'=>'微信公众号开发模式只能上传5000张图片！如果已超限，请先选用一张<a href="'.admin_url('admin.php?page=weixin-robot-material&tab=image').'" target="_blank">已有图片的Media_id</a>代替，再到微信公众号后台替换！' ),
	);

	$nonce_action = 'weixin-fetch-material';

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');

		$mp_url			= $data['mp_url'];
		$thumb_media_id	= $data['thumb_media_id'];

		if($mp_url){
			$response	= weixin_robot_add_remote_article($mp_url,$thumb_media_id);
			if(is_wp_error($response)){
				wpjam_admin_add_error($response->get_error_message(), 'error');
			}else{
				$redirect_to = add_query_arg( array( 'updated' => 'true' ), admin_url('admin.php?page=weixin-robot-material'));	
				wp_redirect($redirect_to);
			}
		}else{
			wpjam_admin_add_error('你没有输入图文链接！','error');
		}
	}

	?>
	<h2>一键转载</h2>

	<?php wpjam_form($form_fields, $current_admin_url, $nonce_action, '转载'); ?>

	<p>*视频和投票无法转载</p>

	<?php
}

function weixin_robot_get_material_type_list(){
	return array(
		'news'	=> '图文',
		'image'	=> '图片',
		'voice'	=> '语音',
		'video'	=> '视频',
	);
}