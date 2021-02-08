<?php
function weixin_robot_replies_tabs($tabs){
	return array(
		'custom'	=> array('title'=>'自定义回复',	'function'=>'weixin_robot_custom_reply_page'),
		'default'	=> array('title'=>'默认回复',		'function'=>'weixin_robot_default_reply_page'),
		'third'		=> array('title'=>'第三方平台',	'function'=>'weixin_robot_third_reply_page'),
		'builtin'	=> array('title'=>'内置回复',		'function'=>'weixin_robot_builtin_reply_page'),
	);
}

function weixin_robot_replies_fields($fields){
	global $current_tab;
	if($current_tab == 'custom' || $current_tab == 'default' ){

		$reply_matches	= weixin_robot_get_custom_reply_matches();
		$reply_types	= weixin_robot_get_custom_reply_types();
		
		$fields = array(
			'title'		=> array('title'=>'类型',	'type'=>'text',		'value'=>'',				'show_admin_column'=>true,	'description'=>'多个关键字请用<strong>英文逗号</strong>区分开，如：<code>七牛, qiniu, 七牛云存储, 七牛镜像存储</code>'),
			'keyword'	=> array('title'=>'关键字',	'type'=>'text',		'value'=>'',				'show_admin_column'=>true,	'description'=>'多个关键字请用<strong>英文逗号</strong>区分开，如：<code>七牛, qiniu, 七牛云存储, 七牛镜像存储</code>'),
			'match'		=> array('title'=>'匹配方式',	'type'=>'radio',	'options'=>$reply_matches,	'show_admin_column'=>true,	'description'=>' '),
			'type'		=> array('title'=>'回复类型',	'type'=>'select',	'options'=>$reply_types,	'show_admin_column'=>true),
			'reply'		=> array('title'=>'回复内容',	'type'=>'textarea',	'description'=>' ',			'show_admin_column'=>true),
			'status'	=> array('title'=>'状态',	'type'=>'checkbox',	'value'=>1,					'description'=>'激活')
		);

		if($current_tab == 'custom'){
			unset($fields['title']);
		}

		return $fields;
	}
}

function weixin_robot_replies_page_load(){
	global $weixin_list_table, $current_tab;

	if($current_tab == 'custom' || $current_tab == 'default') {

		$per_page	= array(
			'label'		=> '项',
			'default'	=> 20, 
			'option'	=> 'weixin_custom_replies_per_page' 
		);	

		$style = '
		th.column-title{width:126px;}
		th.column-keyword{width:126px;}
		th.column-match{width:70px;}
		th.column-type{width:84px;}
		th.column-status{width:56px;}
		';

		if($current_tab == 'default'){
			$style	.='	.tablenav{display:none;}';
		}

		$args = array(
			'plural'			=> 'weixin-custom-replies',
			'singular' 			=> 'weixin-custom-reply',
			'item_callback'		=> 'weixin_robot_custom_reply_item',
			'columns'			=> array('cb' => 'checkbox'),
			// 'sortable_columns'	=> array('time'=>'time'),
			'actions_column'	=> 'keyword',
			'bulk_actions'		=> array('delete' => '删除'),
			'per_page'			=> $per_page,
			'views'				=> 'weixin_robot_custom_replies_views',
			'style'				=> $style,
		);

		if($current_tab == 'default'){
			unset($args['columns']);
			unset($args['bulk_actions']);
			unset($args['views']);
		}

		$weixin_list_table = wpjam_list_table($args);
	}elseif ($current_tab == 'builtin') {
		$columns		= array(
			'keywords'	=> '关键字',
			'type'		=> '匹配方式',
			'reply'		=> '描述',
			'function'	=> '处理函数'
		);

		$style = '
		th.column-keywords {width:40%;}
		th.column-function {width:24%;}
		.tablenav{display:none;}
		';

		$weixin_list_table = wpjam_list_table( array(
			'plural'		=> 'weixin-custom-builtins',
			'singular' 		=> 'weixin-custom-builtin',
			'columns'		=> $columns,
			'style'			=> $style,
		) );
	}
}

// 默认回复 tab
function weixin_robot_third_reply_page(){
	wpjam_option_page('weixin-robot',array('page_type'=>'default'));
}

function weixin_robot_default_reply_page(){
	global $wpdb,$weixin_list_table;

	$weixin_custom_keywords = weixin_robot_get_custom_keywords();

	/*这里是兼容性代码，把设置里面的默认回复迁移到自定义回复中，以后会慢慢删除*/

	// $old_default_reply_keywords = array(
	// 	'weixin_welcome'				=> '[subscribe]',
	// 	'weixin_enter'					=> '[event-location]',
	// 	'weixin_not_found'				=> '[default]',
	// 	'weixin_keyword_too_long'		=> '[too-long]',
	// 	'weixin_default_voice'			=> '[voice]',
	// 	'weixin_default_location'		=> '[location]',
	// 	'weixin_default_image'			=> '[image]',
	// 	'weixin_default_link'			=> '[link]',
	// );

	// $weixin_robot = get_option('weixin-robot');

	// $update_required	= false;

	// if($weixin_robot){
	// 	foreach ($old_default_reply_keywords as $key => $keyword) {
	// 		if(isset($weixin_robot[$key])){
	// 			if($weixin_robot[$key] && empty($weixin_custom_keywords[$keyword])){
	// 				$wpdb->insert($wpdb->weixin_custom_replies, array(
	// 					'keyword'	=> $keyword,
	// 					'match'		=> 'full',
	// 					'type'		=> 'text',
	// 					'reply'		=> $weixin_robot[$key],
	// 					'time'		=> time()
	// 				));
	// 			}
	// 			unset($weixin_robot[$key]);

	// 			$update_required = true;
	// 		}
	// 	}

	// 	if($update_required){
	// 		weixin_robot_delete_custom_repies_transient_cache();
	// 		update_option('weixin-robot', $weixin_robot);
	// 	}
	// };

	/*这里是兼容性代码，把设置里面的默认回复迁移到自定义回复中，以后会慢慢删除*/
		
	$default_replies = weixin_robot_get_default_reply_keywords();
	if(WEIXIN_TYPE < 4){
		unset($default_replies['[event-location]']);
	}

	echo '<link rel="stylesheet" type="text/css" href="'.WEIXIN_ROBOT_PLUGIN_URL.'/template/static/news-items.css">';
	echo '<h2>默认回复</h2>';

	$total					= count($default_replies);

	$weixin_custom_replies	= array();

	foreach ($default_replies as $keyword => $default_reply) {
		if(isset($weixin_custom_keywords[$keyword])){
			$weixin_custom_replies[$keyword]			= (array)$weixin_custom_keywords[$keyword][0];
			$weixin_custom_replies[$keyword]['title']	= $default_reply['title'];
			$weixin_custom_replies[$keyword]['keyword']	= $keyword;
		}else{
			$weixin_custom_replies[$keyword] = array(
				'id'=>'-1',
				'keyword'	=> $keyword,
				'title'		=> $default_reply['title'],
				'match'		=> 'full',
				'type'		=> 'text',
				'reply'		=> $default_reply['value']
			);
		}
	}

	$weixin_list_table->prepare_items($weixin_custom_replies, $total);
	$weixin_list_table->display(array('search'=>false));
}

// 内置回复 tab
function weixin_robot_builtin_reply_page(){
	$matches = weixin_robot_get_custom_reply_matches();

	$weixin_builtin_replies = weixin_robot_get_builtin_replies(); 
	$weixin_builtin_replies_new = array();

	foreach($weixin_builtin_replies as $keyword => $weixin_builtin_reply){
		$function = $weixin_builtin_reply['function'];
		$keywords = isset($weixin_builtin_replies_new[$function]['keywords'])?$weixin_builtin_replies_new[$function]['keywords'].', ':'';
		$weixin_builtin_replies_new[$function]['keywords']		= $keywords.$keyword;
		$weixin_builtin_replies_new[$function]['type'] 			= $matches[$weixin_builtin_reply['type']];
		$weixin_builtin_replies_new[$function]['reply'] 		= $weixin_builtin_reply['reply'];
		$weixin_builtin_replies_new[$function]['function'] 		= $function;
	}

	echo '<h2>插件或者扩展内置回复列表</h2>';
	
	global $weixin_list_table;

	$weixin_list_table->prepare_items($weixin_builtin_replies_new);
	$weixin_list_table->display();
}

// 自定义回复
function weixin_robot_custom_reply_page(){
	$action = isset($_GET['action'])?$_GET['action']:'';

	if($action == 'edit' || $action == 'add' || $action == 'set' ){
		weixin_robot_custom_reply_edit_page();
	}else{
		weixin_robot_custom_reply_list_page();
	}
}

// 自定义回复列表
function weixin_robot_custom_reply_list_page(){
	global $wpdb, $current_admin_url, $weixin_list_table;

	$action 		= $weixin_list_table->current_action();
	$redirect_to	= wpjam_get_referer();

	if($action == 'duplicate'){
		if( !current_user_can( 'manage_options' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

	    if(!empty($_GET['id'])){
			check_admin_referer('duplicate-'.$weixin_list_table->get_singular().'-'.$_GET['id']);
			$weixin_custom_reply = (array)weixin_robot_get_custom_reply($_GET['id']);
			unset($weixin_custom_reply['id']);
			$wpdb->insert($wpdb->weixin_custom_replies, $weixin_custom_reply);

			$redirect_to = add_query_arg( array( 'duplicated' => 'true' ), $redirect_to );

			wp_redirect($redirect_to);
		}

	}elseif($action == 'delete'){

	    if( !current_user_can( 'delete_weixin' )){
	    	ob_clean();
	        wp_die('无权限');
	    }

		if(!empty($_GET['id'])){
			check_admin_referer('delete-'.$weixin_list_table->get_singular().'-'.$_GET['id']);

			$delete = weixin_robot_delete_custom_reply($_GET['id']);

			if(is_wp_error($delete)){
				$redirect_to = add_query_arg( array( 'deleted' => $delete->get_error_message() ), $redirect_to );
			}else{
				$redirect_to = add_query_arg( array( 'deleted' => 'true' ), $redirect_to );
			}

			wp_redirect($redirect_to);

		}elseif (!empty($_GET['ids'])) {
			check_admin_referer('bulk-'.$weixin_list_table->get_plural());

			$error = false;
			foreach ($_GET['ids'] as $id) {
				$response = weixin_robot_delete_custom_reply($id);
				if(is_wp_error($response)){
					$error = true;
					wpjam_admin_add_error($id.'：'.$response->get_error_code().'-'.$response->get_error_message(),'error');
				}
			}
			if($error == false){
				wpjam_admin_add_error('删除成功');
			}
		}
		weixin_robot_delete_custom_repies_transient_cache();
	}
	?>
	<link rel="stylesheet" type="text/css" href="<?php echo WEIXIN_ROBOT_PLUGIN_URL.'/template/static/news-items.css'?>">
	
	<h2>自定义回复列表 <a title="新增自定义回复" class="thickbox add-new-h2" href="<?php echo $current_admin_url.'&action=add'.'&TB_iframe=true&width=780&height=500'; ?>">新增</a></h2>

	<?php

	$status			= isset($_GET['status']) ? $_GET['status'] : 1;
	$type			= isset($_GET['type']) ? $_GET['type'] : '';
	$search_term	= isset($_GET['s']) ? $_GET['s'] : '';

	$where			= "1=1";

	$where		.= " AND `status`='{$status}'";

	if($type){
		$where		.= " AND `type`='{$type}'";
	}

	if($search_term){
		$where		.= " AND (keyword like '%{$search_term}%' OR reply LIKE '%{$search_term}%')";
	}

	$limit					= $weixin_list_table->get_limit();
	$weixin_custom_replies 	= $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->weixin_custom_replies} WHERE {$where} ORDER BY id DESC LIMIT {$limit};", ARRAY_A);
	$total					= $wpdb->get_var("SELECT FOUND_ROWS();");

	$weixin_list_table->prepare_items($weixin_custom_replies, $total);
	$weixin_list_table->display();
}

function weixin_robot_custom_reply_item($item){
	global $current_admin_url, $current_tab, $weixin_list_table;

	$item			= (array)$item;

	$type			= $item['type'];
	$item['name']	= $item['keyword'];
	
	if( $type == '3rd'){
		$item['reply']	= weixin_robot_get_setting('weixin_3rd_'.$item['reply']);
	}elseif( $type == 'img' ){
		$reply_post_ids	= explode(',', $item['reply']);
		$item['reply']	= '';

		$count			= count($reply_post_ids);
		$i				= 1;

		if($reply_post_ids){
			foreach ($reply_post_ids as $reply_post_id) {
				if($reply_post_id){

					$reply_post = get_post($reply_post_id);
					if($reply_post){

						$post_thumbnail	= ($i == 1)? wpjam_get_post_thumbnail_src($reply_post, array(640,320)):wpjam_get_post_thumbnail_src($reply_post, array(80,80));
						$item_div_class	= ($i == 1)? 'big':'small'; 
						$item_a_class	= ($i == $count)?'noborder':''; 
						$item_excerpt	= ($count == 1)?'<p>'.get_post_excerpt($reply_post).'</p>':'';

						if(!$weixin_url = get_post_meta( $reply_post_id, 'weixin_url', true )){
							$weixin_url = get_permalink( $reply_post_id);
						}

						$item['reply'] .= '
						<a class="'.$item_a_class.'" target="_blank" href="'.$weixin_url.'">
							<div class="img_container '.$item_div_class.'" style="background-image:url('.$post_thumbnail.');">
								<h3>'.$reply_post->post_title.'</h3>
							</div>
							'.$item_excerpt.'
						</a>';

						$i++;
					}
				}
			}
			$item['reply']	= '<div class="reply_item">'.$item['reply'].'</div>';
		}
	}elseif( $type == 'img2' ){

		$raw_reply		= str_replace("\r\n", "\n", $item['reply']);
		$raw_items		= explode("\n\n", $raw_reply);
		$item['reply']	= '';

		if($raw_items){
			$count		= count($raw_items);
			$i			= 1;

			foreach ($raw_items as $raw_item ) {
				$lines = explode("\n", $raw_item);

				$item_div_class	= ($i == 1)? 'big':'small'; 
				$item_a_class	= ($i == $count)?'noborder':''; 

				$item_title		= isset($lines[0])?$lines[0]:'';
				$item_excerpt	= isset($lines[1])?$lines[1]:'';
				$item_img		= isset($lines[2])?$lines[2]:'';
				$item_url		= isset($lines[3])?$lines[3]:'';

				if($count == 1){
					$item_a_class	= 'noborder';
					$item_excerpt	= '<p>'.$item_excerpt.'</p>';
				}else{
					$item_excerpt	= '';
				}

				$item['reply'] .= '
				<a class="'.$item_a_class.'" target="_blank" href="'.$item_url.'">
					<div class="img_container '.$item_div_class.'" style="background-image:url('.$item_img.');">
						<h3>'.$item_title.'</h3>
					</div>
					'.$item_excerpt.'
				</a>';

				$i++;
			}
		}
		$item['reply']	= '<div class="reply_item">'.$item['reply'].'</div>';
	}elseif($type == 'news'){
		$material	= weixin_robot_get_material($item['reply'], 'news');
		if(is_wp_error($material)){
			$item['reply'] = $material->get_error_code().' '.$material->get_error_message();
		}else{
			$item['reply']	= '';
			$i 			= 1;
			$count		= count($material);

			foreach ($material as $news_item) {

				$item_div_class	= ($i == 1)? 'big':'small'; 
				$item_a_class	= ($i == $count)?'noborder':''; 
				$item_excerpt	= ($count == 1)?'<p>'.$news_item['digest'].'</p>':'';

				$thumb	= weixin_robot_get_material($news_item['thumb_media_id'], 'thumb');
				$thumb	= is_wp_error($thumb)?'':$thumb;

				$item['reply']   .= '
				<a class="'.$item_a_class.'" target="_blank" href="'.$news_item['url'] .'">
				<div class="img_container '.$item_div_class.'" style="background-image:url('.$thumb.');">
					<h3>'.$news_item['title'].'</h3>
				</div>
				'.$item_excerpt.'
				</a>';
				
				$i++;
			}
			$item['reply'] 	= '<div class="reply_item">'.$item['reply'].'</div>';
		}

	// }elseif($type == 'image'){
	// 	$image	= weixin_robot_get_material($item['reply'], 'image');
	// 	var_dump($image);
	// 	$image	= is_wp_error($image)?'':$image;
	// 	$item['reply']	= '<img src="'.$image.'" style="max-width:200px;max-height:200px;" />';
	}else{
		$item['reply']	= wpautop($item['reply']);
	}

	if($current_tab == 'default'){
		$custom_reply_url = str_replace('default', 'custom', $current_admin_url);

		$item['row_actions'] = array(
			'set'		=> weixin_robot_get_keyword_set_html($item['keyword'],'设置'),
		);
		// $item['keyword']	= $item['title'];
	}else{
		$item['row_actions'] = array(
			'edit'		=> '<a href="'.esc_url($current_admin_url.'&action=edit&id='.$item['id'].'&TB_iframe=true&width=780&height=540').'" title="编辑自定义回复" class="thickbox">编辑</a>',
			// 'duplicate'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=duplicate&id='.$item['id'], 'duplicate-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">复制</a>',
			'duplicate'	=> '<a href="'.esc_url($current_admin_url.'&action=add&duplicate_id='.$item['id'].'&TB_iframe=true&width=780&height=540').'" title="新建自定义回复" class="thickbox">复制</a>',
			'delete'	=> '<a href="'.esc_url(wp_nonce_url($current_admin_url.'&action=delete&id='.$item['id'], 'delete-'.$weixin_list_table->get_singular().'-'.$item['id'])).'">删除</a>',
		);
	}

	return $item;
}

function weixin_robot_custom_replies_views($views){
	global $wpdb, $current_admin_url;
	
	$reply_types	= weixin_robot_get_custom_reply_types();
	
	$type			= isset($_GET['type']) ? $_GET['type'] : '';
	$status			= isset($_GET['status']) ? $_GET['status'] : 1;

	$total			= $wpdb->get_var("SELECT count(*) FROM {$wpdb->weixin_custom_replies} WHERE `status`=1");
	$status_0		= $wpdb->get_var("SELECT count(*) FROM {$wpdb->weixin_custom_replies} WHERE `status`=0");
	$counts 		= $wpdb->get_results("SELECT COUNT( * ) AS count, `type` FROM {$wpdb->weixin_custom_replies} WHERE `status`=1 GROUP BY `type` ORDER BY count DESC ");

	$views	= array();

	$class = empty($type) ? 'class="current"':'';
	$views['all'] = '<a href="'.$current_admin_url.'" '.$class.'>全部<span class="count">（'.$total.'）</span></a>';

	foreach ($counts as $count) { 
		$class = ($type == $count->type) ? 'class="current"':'';
		$reply_type = isset($reply_types[$count->type])?$reply_types[$count->type]:$count->type;
		$views[$count->type] = '<a href="'.$current_admin_url.'&type='.$count->type.'" '.$class.'>'.$reply_type.'<span class="count">（'.$count->count.'）</span></a>';
	}

	$class = empty($status) ? 'class="current"':'';
	$views['status-0']		= '<a href="'.$current_admin_url.'&status=0" '.$class.'>未激活<span class="count">（'.$status_0.'）</span></a>';

	return $views;
}

// 自定义回复添加页面
function weixin_robot_custom_reply_edit_page(){
	global $plugin_page, $wpdb, $current_admin_url;

	$id				= isset($_GET['id'])?$_GET['id']:'';
	$duplicate_id	= isset($_GET['duplicate_id'])?$_GET['duplicate_id']:'';
	$action			= isset($_GET['action'])?$_GET['action']:'';

	$keyword		= isset($_GET['keyword'])?trim($_GET['keyword']):'';
	
	if($keyword){

		if( ( $weixin_custom_keywords = weixin_robot_get_custom_keywords() ) && ( isset( $weixin_custom_keywords[$keyword] ) ) ) {
			$weixin_custom_replies	= $weixin_custom_keywords[$keyword];
			$weixin_custom_reply	= $weixin_custom_replies[0];
			$keyword				= $weixin_custom_reply->keyword;
			$id						= $weixin_custom_reply->id;
		}elseif( ( $weixin_default_replies = weixin_robot_get_default_reply_keywords() ) && (isset($weixin_default_replies[$keyword])) ){
			$weixin_default_reply	= $weixin_default_replies[$keyword]['value'];
		}elseif( ( $weixin_builtin_replies	= weixin_robot_get_builtin_replies() ) && ( isset($weixin_builtin_replies[$keyword]) ) ) {
			$weixin_builtin_reply	= $weixin_builtin_replies[$keyword];
		}
	}

	$nonce_action		= $id ? 'edit-weixin-custom-reply-'.$id : 'add-weixin-custom-reply';

	$reply_types		= weixin_robot_get_custom_reply_types();

	$third_options	= array();
	foreach (array(1,2,3) as $i) {
		// if(weixin_robot_get_setting('weixin_3rd_'.$i) && weixin_robot_get_setting('weixin_3rd_url_'.$i) && weixin_robot_get_setting('weixin_3rd_token_'.$i)){
		if(weixin_robot_get_setting('weixin_3rd_'.$i) && weixin_robot_get_setting('weixin_3rd_url_'.$i)){
			$third_options[$i] = weixin_robot_get_setting('weixin_3rd_'.$i);
		}
	}

	if(!$third_options){
		unset($reply_types['3rd']);
	}

	$kf_options = array();
	if(WEIXIN_TYPE >= 3 && weixin_robot_get_setting('weixin_dkf')){
		if($weixin_kf_list 	= weixin_robot_customservice_get_kf_list()){
			$kf_options	= array(''=>' ');
			foreach ($weixin_kf_list as $weixin_kf_account) {
				$kf_options[$weixin_kf_account['kf_account']] = $weixin_kf_account['kf_nick'];
			}
		}
	}

	if(!weixin_robot_get_setting('weixin_search')){
		unset($reply_types['img']);
	}

	$reply_descriptions	= weixin_robot_get_reply_descriptions();
	$match_descriptions	= array(
		'full'		=> ' ',
		'prefix'	=> '前缀匹配方式只支持匹配前两个中文字或者字母。',
		'fuzzy'		=> '模糊匹配效率比较低，如无必要请不要大量使用',
	);

	$form_fields = wpjam_get_form_fields();

	$form_fields['type']['options']		= $reply_types;
	$form_fields['keyword']['value']	= $keyword;

	if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

		$data = wpjam_get_form_post($form_fields, $nonce_action, 'edit_weixin');

		if($id && $old_custom_reply = weixin_robot_get_custom_reply($id)){
			if($wpdb->update($wpdb->weixin_custom_replies,$data,array('id'=>$id))){
				wpjam_admin_add_error('修改成功');
				do_action('edited_weixin_custom_reply', $id, $old_custom_reply);
			}else{
				wpjam_admin_add_error('未修改');
			}
		}else{
			$data['time']	= current_time('mysql');
			if($wpdb->insert($wpdb->weixin_custom_replies,$data)){
				wpjam_admin_add_error('添加成功');
				do_action('added_weixin_custom_reply', $wpdb->insert_id);
			}else{
				wpjam_admin_add_error('添加失败：'.$wpdb->last_error,'error');
			}
		}

		weixin_robot_delete_custom_repies_transient_cache();
	}

	if(($id && $weixin_custom_reply = weixin_robot_get_custom_reply($id)) || ($duplicate_id && $weixin_custom_reply = weixin_robot_get_custom_reply($duplicate_id))){
		foreach ($form_fields as $key => $form_field) {
			$form_fields[$key]['value']	= isset($weixin_custom_reply->$key)?$weixin_custom_reply->$key:'';
		}

		$type	= $weixin_custom_reply->type;
		$match	= $weixin_custom_reply->match;

		$form_fields['reply']['description'] 	= isset($reply_descriptions[$type])?$reply_descriptions[$type]:' ';
		$form_fields['match']['description']	= $match_descriptions[$match];
	}elseif($keyword && isset($weixin_builtin_reply)){
		$type	= 'function';
		$match	= $weixin_builtin_reply['type'];

		$form_fields['keyword']['value']	= $keyword;
		$form_fields['match']['value']		= $match;
		$form_fields['type']['value']		= $type;
		$form_fields['reply']['value']		= $weixin_builtin_reply['function'];
		$form_fields['status']['value']		= 1;

		$form_fields['reply']['description'] 	= isset($reply_descriptions[$type])?$reply_descriptions[$type]:' ';
		$form_fields['match']['description']	= $match_descriptions[$match];
	}elseif($keyword && isset($weixin_default_reply)){
		$type	= 'text';
		$match	= 'full';

		$form_fields['keyword']['value']	= $keyword;
		$form_fields['match']['value']		= $match;
		$form_fields['type']['value']		= $type;
		$form_fields['reply']['value']		= $weixin_default_reply;
		$form_fields['status']['value']		= 1;

		$form_fields['reply']['description'] 	= $reply_descriptions[$type];
		$form_fields['match']['description']	= $match_descriptions[$match];
	}else{
		$type	= isset($_GET['type'])?trim($_GET['type']):'text';	

		$form_fields['reply']['description'] 	= $reply_descriptions[$type];	
		$form_fields['reply']['value']			= isset($_GET['reply'])?trim($_GET['reply']):'';	
		$form_fields['type']['value']			= $type;	
	}

	$reply_field 		= $form_fields['reply'];
	$reply_field['key']	= 'reply';

	$third_reply_field 	= $reply_field;

	$third_reply_field['type']		= 'select';
	$third_reply_field['options']	= $third_options;

	$kf_reply_field 	= $reply_field;

	$kf_reply_field['type']			= 'select';
	$kf_reply_field['options']		= $kf_options;

	$form_url		= ($action == 'add')?$current_admin_url.'&action=add':$current_admin_url.'&action=edit&id='.$id;
	$action_text	= $id?'编辑':'新增';
	?>

	<h1><?php echo $action_text;?>自定义回复</h1>

	<?php wpjam_form($form_fields, $form_url, $nonce_action, $action_text); ?>

	<script type="text/javascript">
	jQuery(function($){
		var tr_reply 			= '<th scope="row"><label for="reply">回复内容</label></th><td>'+<?php echo wpjam_json_encode(wpjam_get_field_html($reply_field)); ?>+'</td>';
		var tr_3rd_reply 		= '<th scope="row"><label for="reply">选择平台</label></th><td><?php echo str_replace("'",'"',wpjam_get_field_html($third_reply_field)); ?></td>';
		var tr_kf_reply 		= '<th scope="row"><label for="reply">选择客服</label></th><td><?php echo str_replace("'",'"',wpjam_get_field_html($kf_reply_field)); ?></td>';
		var reply_descriptions	= $.parseJSON('<?php echo wpjam_json_encode($reply_descriptions);?>');
		var match_descriptions	= $.parseJSON('<?php echo wpjam_json_encode($match_descriptions, JSON_UNESCAPED_UNICODE);?>');
		var reply_description	= '';
		var match_description	= '';

		$("select#type").change(function(){
			var selected = $("select#type").val();
			if(selected == '3rd'){
				$('#tr_reply').html(tr_3rd_reply);
			}else if(selected == 'dkf'){
				$('#tr_reply').html(tr_kf_reply);
			}else{
				$('#tr_reply').html(tr_reply);
			}
			$('#tr_reply p').html(reply_descriptions[selected]);
		});

		$('input[type=radio]').change(function(){
			var selected = $('input[type=radio]:checked').val();
			$('#tr_match p').html(match_descriptions[selected]);
		});
		
		<?php if( $type == '3rd' ) {?>
		$('#tr_reply').html(tr_3rd_reply);
		<?php }?>

		<?php if( $type == 'dkf' ) {?>
		$('#tr_reply').html(tr_kf_reply);
		<?php }?>
	});
	</script> 
	<?php
}

function weixin_robot_delete_custom_reply($id){
	global $wpdb;

	$custom_reply = weixin_robot_get_custom_reply($id);

	if(!$custom_reply){
		return new WP_Error('already-deleted', '已经被删除了！');
	}
	
	if(!$wpdb->delete($wpdb->weixin_custom_replies, array('id'=>$id))){
		return new WP_Error('delete-failed', $wpdb->last_error);
	}

	do_action('deleted_weixin_custom_reply', $custom_reply);

	return true;
}

function weixin_robot_get_custom_reply($id){
	global $wpdb;
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->weixin_custom_replies} WHERE id=%d", $id));
}

function weixin_robot_get_keyword_set_html($keyword, $title=''){
	$title = ($title)?$title:$keyword;
	return '<a title="设置自定义回复" class="thickbox" href="'.admin_url('admin.php?page=weixin-robot-replies&action=set&keyword='.$keyword.'&TB_iframe=true&width=780&height=540').'">'.$title.'</a>';
}

// 自定义回复匹配模式
function weixin_robot_get_custom_reply_matches(){
	return array(
		'full'		=>'完全匹配',
		'prefix'	=>'前缀匹配',
		'fuzzy'		=>'模糊匹配'
	);
}

// 自定义回复类型
function weixin_robot_get_custom_reply_types(){
	$types = array(
		'text'		=> '文本',
		'img2'		=> '自定义图文',
		'img'		=> '文章图文',
	);

	if(WEIXIN_TYPE >=3 ){
		$types['news']	= '素材图文';
		$types['image']	= '图片';
		$types['voice']	= '语音';
		// $types['video']	= '视频';
		$types['music']	= '音乐';
		if(weixin_robot_get_setting('weixin_dkf')){
			$types['dkf']	= '转到多客服';
		}
	}

	$types = apply_filters('weixin_custom_reply_types',$types);

	$types['3rd']		= '转到第三方';
	$types['function']	= '函数';

	return $types;
}

function weixin_robot_get_reply_descriptions(){
	return array(
		'text'		=> '请输入要回复的文本，可以使用 a 标签。',
		'img'		=> '请输入单篇或者多篇日志的ID，并用英文逗号区分开，如：<code>123,234,345</code>，并且 ID 数量不要超过基本设置里面的返回结果最大条数。',
		'img2'		=> '请输入标题，摘要，图片链接，链接，每个一行，如果多个图文，请使用一个空行隔开。',
		'news'		=> '请输入素材图文的 Media ID，Media ID 从素材管理获取。',
		'image'		=> '请输入图片的 Media ID，Media ID 从素材管理获取。',
		'voice'		=> '请输入语音的 Media ID，Media ID 从素材管理获取。',
		'video'		=> '请输入视频的 Media ID，标题，摘要，每个一行，Media ID 从素材管理获取。',
		'music'		=> '请输入音乐的标题，描述，链接，高清连接，缩略图的 Media ID，每个一行，Media ID 从素材管理获取。',
		'function'	=> '请输入函数名，该功能仅限于程序员测试使用。',
		'dkf'		=> '请选择客服或者留空，留空系统会随机选择一个客服。',
		'3rd'		=> '请选择相应的第三方。',
		'wxcard'	=> '请输入微信卡券ID。'
	);
}