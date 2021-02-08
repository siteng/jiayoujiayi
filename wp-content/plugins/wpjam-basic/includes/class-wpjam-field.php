<?php
class WPJAM_Field{
	private static $del_item_button		= ' <a href="javascript:;" class="button del-item">删除</a> ';
	private	static $del_item_icon		= ' <a href="javascript:;" class="del-item dashicons dashicons-no-alt"></a>';
	private	static $del_img_icon		= ' <a href="javascript:;" class="del-img dashicons dashicons-no-alt"></a>';
	private	static $sortable_dashicons	= ' <span class="dashicons dashicons-menu"></span>';
	private	static $dismiss_dashicons	= ' <span class="dashicons dashicons-dismiss"></span>';

	private static $field_tmpls	= [];

	public  static function fields_callback($fields, $args=[]){
		$output			= '';
		$fields_type	= $args['fields_type'] ?? 'table';

		$args['show_if_keys']	= self::get_show_if_keys($fields);

		foreach($fields as $key => $field){
			if(isset($field['show_admin_column']) && ($field['show_admin_column'] === 'only')){
				continue;
			}

			$field['key']	= $key;
			$field['name']	= $field['name'] ?? $key;
			$field_id		= $field['id'] = $field['id'] ?? $key;
			$field_title	= $field['title'] = $field['title'] ?? '';

			if($field['type'] == 'fieldset'){
				$field_html		= '<legend class="screen-reader-text"><span>'.$field_title.'</span></legend>';

				if(!empty($field['fields'])){
					$fieldset_type	= $field['fieldset_type'] ?? 'single';

					foreach ($field['fields'] as $sub_key => &$sub_field){
						if($sub_field['type'] == 'fieldset'){
							wp_die('fieldset 不允许内嵌 fieldset');
						}

						$sub_field['name']	= $sub_field['name'] ?? $sub_key;

						if($fieldset_type == 'array'){
							$sub_key	= $key.'_'.$sub_key;

							$sub_field['name']	= $field['name'].self::generate_sub_field_name($sub_field['name']);
						}

						$sub_id	= $sub_field['id'] ?? $sub_key;

						$sub_field['key']	= $sub_key;
						$sub_field['id']	= $sub_id;

						$sub_html	= self::field_callback($sub_field, $args);

						if($sub_field['type'] == 'hidden'){
							$field_html	.= $sub_html;
						}else{
							$wrap_attr	= self::parse_wrap_attr($sub_field, ['sub-field']);
							$sub_title	= $sub_field['title'] ?? '';
							$sub_title	= $sub_title ? '<label class="sub-field-label" for="'.$sub_id.'">'.$sub_title.'</label>' : '';

							$field_html	.= '<div '.$wrap_attr.' id="div_'.$sub_id.'">'.$sub_title.'<div class="sub-field-detail">'.$sub_html.'</div>'.'</div>';
						}
					}

					unset($sub_field);
				}
			}else{
				$field_html	= self::field_callback($field, $args);

				if($field['type'] == 'hidden'){
					$output	.= $field_html;
					continue;
				}

				if($field_title){
					$field_title	= '<label for="'.$key.'">'.$field_title.'</label>';
				}
			}

			$wrap_class	= [];

			if(!empty($args['wrap_class'])){
				$wrap_class[]	= $args['wrap_class'];
			}

			$wrap_attr	= self::parse_wrap_attr($field, $wrap_class);

			if($fields_type == 'div'){
				$output	.= '<div '.$wrap_attr.' id="div_'.$field_id.'">'.$field_title.$field_html.'</div>';
			}elseif($fields_type == 'list' || $fields_type == 'li'){
				$output	.= '<li '.$wrap_attr.' id="li_'.$field_id.'">'.$field_title.$field_html.'</li>';
			}elseif($fields_type == 'tr' || $fields_type == 'table'){
				$field_html	= $field_title ? '<th scope="row">'.$field_title.'</th><td>'.$field_html.'</td>' : '<td colspan="2">'.$field_html.'</td>';
				$output	.= '<tr '.$wrap_attr.' valign="top" '.'id="tr_'.$field_id.'">'.$field_html.'</tr>';
			}else{
				$output	.= $field_title.$field_html;
			}
		}

		if($fields_type == 'list'){
			$output	= '<ul>'.$output.'</ul>';
		}elseif($fields_type == 'table'){
			$output	= '<table class="form-table" cellspacing="0"><tbody>'.$output.'</tbody></table>';
		}

		if(wp_doing_ajax()){ 
			$output	.= self::get_field_tmpls();
		}

		if(!isset($args['echo']) || $args['echo']){
			echo $output;
		}else{
			return $output;
		}
	}

	private static function field_callback($field, $args=[]){
		if(empty($args['is_add'])){
			$field['value']	= self::get_field_value($field, $args);
		}

		if(!empty($args['name'])){
			$field['name']	= $args['name'].self::generate_sub_field_name($field['name']);
		}

		if(!empty($args['show_if_keys']) && in_array($field['key'], $args['show_if_keys'])){
			$field['show_if_key']	= true;
		}

		return self::get_field_html($field);
	}

	public  static function get_field_value($field, $args=[]){
		$default	= null;

		if(isset($field['value_callback'])){
			if(is_callable($field['value_callback'])){
				$value_callback	= $field['value_callback'];
			}
		}elseif(isset($field['value'])){
			if(is_admin()){
				$default	= $field['value'];
			}
		}

		if(!is_admin()){
			$default	= $field['default'] ?? null;
		}

		if(in_array($field['type'], ['view', 'br','hr']) && !is_null($default) && empty($value_callback)){
			return $default;
		}

		$value	= null;
		$data	= $args['data'] ?? [];
		$name	= $field['name'] ?? $field['key'];
		$id		= $args['id'] ?? 0;

		if(preg_match('/\[([^\]]*)\]/', $name)){
			$name_arr	= wp_parse_args($name);
			$name		= current(array_keys($name_arr));
		}else{
			$name_arr	= [];
		}

		if(!empty($value_callback)){
			$value	= call_user_func($value_callback, $name, $id);
		}else{
			if($data && isset($data[$name])){
				$value	= $data[$name];
			}elseif(!empty($args['value_callback'])){
				if(is_callable($args['value_callback'])){
					$value	= call_user_func($args['value_callback'], $name, $id);
				}
			}
		}

		if($name_arr){
			$name_arr	= current(array_values($name_arr));

			do{
				$sub_name	= current(array_keys($name_arr));
				$name_arr	= current(array_values($name_arr));
				$value		= $value[$sub_name] ?? null;
			}while ($name_arr && $value);
		}

		if(is_null($value)){
			return $default;
		}

		return $value;
	}

	public  static function get_field_html($field){
		foreach ($field as $attr_key => $attr_value) {
			if(is_numeric($attr_key)){
				$attr_key	= $attr_value = strtolower(trim($attr_value));
				$field[$attr_key]	= $attr_value;
			}
		}

		$field['key']	= $key = $field['key'] ?? '';
		$field['name']	= $field['name'] ?? $key;
		$field['id']	= $field['id'] ?? $key;
		$field['sep']	= $field['sep'] ?? '&emsp;';

		$field['type'] 	= $type	=  empty($field['type'])  ? 'text' : $field['type'];

		if(!isset($field['value'])){
			$field['value']	= $type == 'radio' ? null : '';
		}

		$value	= $field['value'];
		$name	= $field['name'];
		$id		= $field['id'];

		if(!isset($field['class'])){
			if($type == 'textarea'){
				$field['class']	= ['large-text'];
			}elseif($type == 'mu-text'){
				// do nothing
			}elseif(!in_array($type, ['checkbox', 'radio', 'select', 'color', 'date', 'time', 'datetime-local', 'number'])){
				$field['class']	= ['regular-text'];
			}else{
				$field['class']	= [];
			}
		}elseif($field['class']){
			if(!is_array($field['class'])){
				$field['class']	= explode(' ', $field['class']);
			}
		}else{
			$field['class']	= [];
		}

		if(in_array($type, ['mu-image','mu-file','mu-text','mu-img','mu-fields'])){
			if(!empty($field['total']) && empty($field['max_items'])){
				$field['max_items']	= $field['total'];
			}else{
				$field['max_items']	= $field['max_items'] ?? 0;
			}
		}else{
			if(isset($field['show_if_key'])){
				$field['class'][]	= 'show-if-key';
			}
		}

		if(!empty($field['description'])){
			if($type == 'checkbox' || $type == 'mu-text'){
				$description	= ' <span class="description">'.$field['description'].'</span>';
			}elseif(empty($field['class']) || !array_intersect(['large-text','regular-text'], $field['class'])){
				$description	= ' <span class="description">'.$field['description'].'</span>';
			}else{
				$description	= '<br /><span class="description">'.$field['description'].'</span>';
			}

			$field['description']	= $description;
		}else{
			$description	= $field['description'] = '';
		}

		if(!empty($field['options'])){
			if(!is_array($field['options'])){
				$field['options'] = wp_parse_args($field['options']);
			}
		}else{
			$field['options']	= [];
		}

		$field_html	= '';

		if($type == 'view' || $type == 'br'){
			if($field['options']){
				$value		= $value ?: 0;
				$field_html	= $field['options'][$value] ?? $value;
			}else{
				$field_html	= $value;
			}
		}elseif($type == 'hr'){
			$field_html	= '<hr />';
		}elseif($type == 'hidden'){
			$field_html	= self::get_input_field_html($field);
		}elseif($type == 'range'){
			$field_html	= self::get_input_field_html($field).' <span>'.$value.'</span>';
		}elseif($type == 'color'){
			$field['class'][]	= 'color';
			$field['type']		= 'text';
			$field_html			= self::get_input_field_html($field);
		}elseif($type == 'checkbox'){
			if($field['options']){
				$field['class'][]	= 'mu-checkbox';
				$field['class'][]	= 'checkbox-'.esc_attr($field['key']);
				$field['name']		= $name.'[]';

				$item_htmls	= [];

				foreach ($field['options'] as $option_value => $option_title){ 
					$checked	= ($value && is_array($value) && in_array($option_value, $value)) ? 'checked' : '';
					$item_field	= array_merge($field, ['id'=>$id.'_'.$option_value, 'value'=>$option_value, 'checked'=>$checked, 'description'=>$option_title]);

					$item_htmls[]	= self::get_input_field_html($item_field);
				}

				$field_html = '<div id="'.$id.'_options">'.implode($field['sep'], $item_htmls).'</div>'.$description;
			}else{
				$field['checked']	= $value == 1 ? 'checked' : ''; 
				$field['value']		= 1;

				$field_html	= self::get_input_field_html($field);
			}
		}elseif($type == 'radio'){
			if($field['options']){
				$value	= $value ?? current(array_keys($field['options']));

				$item_htmls	= [];

				foreach ($field['options'] as $option_value => $option_title) {
					$checked	= $option_value == $value ? 'checked' : '';
					$item_field	= array_merge($field, ['id'=>$id.'_'.$option_value, 'value'=>$option_value, 'checked'=>$checked, 'description'=>'']);

					$data_attr		= '';
					$option_title	= self::parse_option_title($option_title, $data_attr);
					$item_htmls[]	= '<label '.$data_attr.' id="label_'.$item_field['id'].'" for="'.$item_field['id'].'">'.self::get_input_field_html($item_field).$option_title.'</label>';
				}

				$field_html	= '<div id="'.$id.'_options">'.implode($field['sep'], $item_htmls).'</div>'.$description;
			}
		}elseif($type == 'select'){
			if($field['options']){
				$item_htmls	= [];

				foreach ($field['options'] as $option_value => $option_title){
					$data_attr		= '';
					$option_title	= self::parse_option_title($option_title, $data_attr);
					$item_htmls[]	= '<option '.$data_attr.' value="'.esc_attr($option_value).'" '.selected($option_value, $value, false).'>'.$option_title.'</option>';
				}

				$field['options']	= implode('', $item_htmls);
			}else{
				$field['options']	= '';
			}

			$field_html	= self::get_input_field_html($field);
		}elseif($type == 'file' || $type == 'image'){
			if(current_user_can('upload_files')){
				$item_type		= $type == 'image' ? 'image' : '';
				$item_text		= $type == 'image' ? '图片' : '文件';

				$field['class'][]	= 'wpjam-file-input';

				$item_field	= array_merge($field, ['type'=>'url', 'description'=>'']);

				$field_html	= self::get_input_field_html($item_field).' <a class="wpjam-file button" data-item_type="'.$item_type.'">选择'.$item_text.'</a>'.$description;
			}
		}elseif($type == 'img'){
			if(current_user_can('upload_files')){
				$item_type	= $field['item_type'] ?? '';
				$size		= $field['size'] ?? '400x0';

				$img_style	= '';

				if(isset($field['size'])){
					$size	= wpjam_parse_size($field['size']);

					if($size['width'] > 600 || $size['height'] > 600){
						if($size['width'] > $size['height']){
							$size['height']	= (int)(($size['height'] / $size['width']) * 600);
							$size['width']	= 600;
						}else{
							$size['width']	= (int)(($size['width'] / $size['height']) * 600);
							$size['height']	= 600;
						}
					}

					if($size['width']){
						$img_style	.= ' width:'.(int)($size['width']/2).'px;';
					}

					if($size['height']){
						$img_style	.= ' height:'.(int)($size['height']/2).'px;';
					}

					$thumb_args	= wpjam_get_thumbnail('',$size);
				}else{
					$thumb_args	= wpjam_get_thumbnail('',400);
				}

				$img_style	= $img_style ?: 'max-width:200px;';

				$div_class	= 'wpjam-img button add_media';
				$field_html	= '<span class="wp-media-buttons-icon"></span> 添加图片</button>';

				if(isset($field['disabled']) || isset($field['readonly'])){
					$div_class	= '';
					$field_html	= '';
				}

				if(!empty($value)){
					$img_url	= $item_type == 'url' ? $value : wp_get_attachment_url($value);

					if($img_url){
						$img_url	= wpjam_get_thumbnail($img_url, $size);
						$field_html	= '<img style="'.$img_style.'" src="'.$img_url.'" alt="" />';

						if(!isset($field['disabled']) && !isset($field['readonly'])){
							$div_class	= 'wpjam-img';
							$field_html	.= self::$del_img_icon;
						}
					}
				}

				$item_field	= array_merge($field, ['type'=>'hidden', 'description'=>'']);
				$field_html	= '<div data-item_type="'.$item_type.'" data-img_style="'.$img_style.'" data-thumb_args="'.$thumb_args.'" class="'.$div_class.'">'.$field_html.'</div>';
				$field_html = '<div class="wp-media-buttons wpjam-media-buttons">'.self::get_input_field_html($item_field).$field_html.'</div>'.$description;
			}
		}elseif($type == 'textarea'){
			$field['rows']	= $field['rows'] ?? 6;
			$field['cols']	= $field['cols'] ?? 50;

			$field_html = self::get_input_field_html($field);
		}elseif($type == 'editor'){
			wp_enqueue_editor();

			ob_start();
			$settings		= $field['settings'] ?? [];
			wp_editor($value, $field['id'], $settings);
			$field_style	= isset($field['style'])?' style="'.$field['style'].'"':'';
			$field_html 	= '<div'.$field_style.'>'.ob_get_contents().'</div>';
			ob_end_clean();

			$field_html		.= $description;
		}elseif($type == 'mu-img'){
			if(current_user_can('upload_files')){
				$item_type	= $field['item_type'] ?? '';
				$max_items	= $field['max_items'];

				$item_field	= array_merge($field, ['type'=>'hidden', 'name'=>$name.'[]', 'description'=>'']);

				$i	= 0;

				if($value && is_array($value)){
					foreach($value as $img){
						if(empty(trim($img))){
							continue;
						}

						$i++;

						if($max_items && $i > $max_items){
							break;
						}

						$item_field	= array_merge($item_field, ['id'=>$id.'_'.$i, 'value'=>esc_attr($img)]);

						$img_url	= ($item_type == 'url') ? $img : wp_get_attachment_url($img);
						$img_url	= wpjam_get_thumbnail($img_url, 200, 200);

						if(!isset($field['disabled']) && !isset($field['readonly'])){
							$item_htmls[]	= '<img width="100" src="'.$img_url.'" alt="">'.self::get_input_field_html($item_field).self::$del_item_icon;
						}else{
							$item_htmls[]	= '<img width="100" src="'.$img_url.'" alt="">';
						}
					}

					$field_html	= '<div class="mu-item mu-img">'.implode('</div> <div class="mu-item mu-img">', $item_htmls).'</div>';
				}

				if(!isset($field['disabled']) && !isset($field['readonly'])){
					$thumb_args	= wpjam_get_thumbnail('',[200,200]);

					$field_html	.= '<div title="按住Ctrl点击鼠标左键可以选择多张图片" class="wpjam-mu-img dashicons dashicons-plus-alt2" data-i='.($i+1).' data-id="'.$id.'" data-item_type="'.$item_type.'" data-thumb_args="'.$thumb_args.'" data-name="'.$name.'[]" data-max_items='.$max_items.'></div>';
				}

				$field_html	= '<div class="mu-imgs">'.$field_html.'</div>'.$description;
			}
		}elseif($type == 'mu-file' || $type == 'mu-image'){
			if(current_user_can('upload_files')){
				$item_type	= $type == 'mu-image' ? 'image' : '';
				$item_text	= $type == 'mu-image' ? '图片' : '文件';
				$max_items	= $field['max_items'];

				$item_field	= array_merge($field, ['type'=>'url', 'name'=>$name.'[]', 'description'=>'']);

				$i	= 0;

				if($value && is_array($value)){
					foreach($value as $file){
						if(empty(trim($file))){
							continue;
						}

						$i++;

						$item_field		= array_merge($item_field, ['id'=>$id.'_'.$i, 'value'=>esc_attr($file)]);

						if($max_items && $i >= $max_items){
							$max_reached	= true;
							break;
						}

						$item_htmls[]	= self::get_input_field_html($item_field).self::$del_item_button.self::$sortable_dashicons;
					}
				}

				if(empty($max_items) || empty($max_reached)){
					$item_field		= array_merge($item_field, ['id'=>$id.'_'.($i+1), 'value'=>'']);
				}

				$item_htmls[]	= self::get_input_field_html($item_field).' <a class="wpjam-mu-file button" data-item_type="'.$item_type.'" data-i='.$i.' data-id="'.$id.'" data-max_items='.$max_items.' data-name="'.$name.'[]" title="按住Ctrl点击鼠标左键可以选择多个'.$item_text.'">选择'.$item_text.'[多选]'.'</a>';

				$field_html		= '<div class="mu-item">'.implode('</div> <div class="mu-item">', $item_htmls).'</div>';
				$field_html		= '<div class="'.$type.'s">'.$field_html.'</div>'.$description;
			}
		}elseif($type == 'mu-text'){
			$item_type	= $field['item_type'] ?? 'text';
			$max_items	= $field['max_items'];

			$item_field	= array_merge($field, ['type'=>$item_type, 'name'=>$name.'[]', 'description'=>'']);

			$i	= 0;

			if($value && is_array($value)){
				foreach($value as $item){
					if(empty(trim($item))){
						continue;
					}

					$i++;

					$item_field	= array_merge($item_field, ['id'=>$id.'_'.$i, 'value'=>esc_attr($item)]);

					if($max_items && $i >= $max_items){
						$max_reached	= true;
						break;
					}

					$item_htmls[]	= self::get_field_html($item_field).self::$del_item_button.self::$sortable_dashicons;
				}
			}

			if(empty($max_items) || empty($max_reached)){
				$item_field	= array_merge($item_field, ['id'=>$id.'_'.($i+1), 'value'=>'']);
			}

			$item_htmls[]	= self::get_field_html($item_field).' <a class="wpjam-mu-text button" data-i='.($i+1).' data-id="'.$id.'" data-max_items='.$max_items.'">添加选项</a>';

			$field_html		= '<div class="mu-item">'.implode('</div> <div class="mu-item">', $item_htmls).'</div>';
			$field_html 	= '<div class="mu-texts">'.$field_html.'</div>'.$description;
		}elseif($type == 'mu-fields'){
			if(!empty($field['fields'])){
				$max_items	= $field['max_items'];

				$i	= 0;

				if($value && is_array($value)){
					foreach($value as $item){
						if(empty($item)){
							continue;
						}

						$i++;

						$item_html	= self::get_mu_fields_html($name, $field['fields'], $i, $item);

						if($max_items && $i >= $max_items){
							$max_reached	= true;
							break;
						}

						$item_htmls[]	= $item_html.self::$del_item_button.self::$sortable_dashicons;
					}
				}

				if(!$max_items || empty($max_reached)){
					$item_html	= self::get_mu_fields_html($name, $field['fields'], ($i+1));
				}

				$data_attr		= ' data-tmpl-id="wpjam-'.md5($name).'" data-max_items='.$max_items;

				$item_htmls[]	= $item_html.' <a class="wpjam-mu-fields button" data-i='.($i+1).$data_attr.'">添加选项</a>'; 
				$field_html		= '<div class="mu-item">'.implode('</div> <div class="mu-item">', $item_htmls).'</div>';
				$field_html		= '<div class="mu-fields" id="mu_fields_'.$id.'">'.$field_html.'</div>';

				self::$field_tmpls[md5($name)]	= '<div class="mu-item">'.self::get_mu_fields_html($name, $field['fields'], '{{ data.i }}').' <a class="wpjam-mu-fields button" data-i="{{ data.i }}" '.$data_attr.'>添加选项</a>'.'</div>';
			}
		}else{
			if(!empty($field['data_type'])){
				$field['class'][]		= 'wpjam-autocomplete';
				$field['data-data_type']= esc_attr($field['data_type']);
				$field['description']	= '';

				$query_title	= '';

				$query_args		= $field['query_args'] ?? [];

				if($query_args && !is_array($query_args)){
					$query_args	= wp_parse_args($query_args);
				}

				if($field['data_type'] == 'post_type'){
					if(!empty($field['post_type'])){
						$query_args['post_type']	= $field['post_type'];
					}

					if($value && is_numeric($value) && ($field_post = get_post($value))){
						$query_title	= $field_post->post_title ?: $field_post->ID;
					}
				}elseif($field['data_type'] == 'taxonomy'){
					if(!empty($field['taxonomy'])){
						$query_args['taxonomy']	= $field['taxonomy'];
					}

					if($value && is_numeric($value) && ($field_term = get_term($value))){
						$query_title	= $field_term->name ?: $field_term->term_id;
					}
				}elseif($field['data_type'] == 'model'){
					if(!empty($field['model'])){
						$query_args['model']	= $field['model'];
					}

					$label_key	= $query_args['label_key'] = $query_args['label_key'] ?? 'title'; 
					$id_key		= $query_args['id_key'] = $query_args['id_key'] ?? 'id';

					$model	= $query_args['model'] ?? '';

					if(empty($model) || !class_exists($model)){
						wp_die($key.' model 未定义');
					}

					if($value && ($item = $model::get($value))){
						$query_title	= $item[$label_key] ?: $item[$id_key];
					}
				}

				$field['data-query_args']	= wpjam_json_encode($query_args);

				unset($field['query_args']);

				$query_title	= $query_title ? '<span class="wpjam-query-title">'.self::$dismiss_dashicons.$query_title.'</span>' : '';
				$field_html		= self::get_input_field_html($field).$query_title;
			}else{
				$field_html		= self::get_input_field_html($field);

				if(!empty($field['list']) && $field['options']){
					$field_html	.= '<datalist id="'.$field['list'].'">';

					foreach ($field['options'] as $option_value => $option_title) {
						$field_html	.= '<option label="'.esc_attr($option_title).'" value="'.esc_attr($option_value).'" />';
					}

					$field_html	.= '</datalist>';
				}
			}
		}

		return apply_filters('wpjam_field_html', $field_html, $field);
	}

	private static function get_input_field_html($field){
		$field['data-key']	= $field['key'];
		$field['class']		= $field['class'] ? implode(' ', $field['class']) : '';

		$keys	= ['type','key','title','value','default','description','options','fields','size','show_admin_column','sortable_column','taxonomies','taxonomy','settings','data_type','post_type','item_type','total','max_items','sep','wrap_class','show_if','show_if_key','sanitize_callback','validate_callback','column_callback','value_callback'];

		$field_attr	= [];

		foreach ($field as $attr_key => $attr_value) {
			if(!in_array(strtolower($attr_key), $keys)){
				if(is_object($attr_value) || is_array($attr_value)){
					trigger_error($attr_key.' '.var_export($attr_value, true).var_export($field, true));
				}elseif(is_int($attr_value) || $attr_value){
					$field_attr[]	= $attr_key.'="'.esc_attr($attr_value).'"';
				}
			}
		}

		$field_attr	= implode(' ', $field_attr);

		if($field['type'] == 'select'){
			$html	= '<select '.$field_attr.'>'.$field['options'].'</select>' .$field['description'];
		}elseif($field['type'] == 'textarea'){
			$html	= '<textarea '.$field_attr.'>'.esc_textarea($field['value']).'</textarea>'.$field['description'];
		}else{
			$html	= '<input type="'.esc_attr($field['type']).'" value="'.esc_attr($field['value']).'" '.$field_attr.' />';

			if($field['type'] != 'hidden' && $field['description']){
				$html	= '<label for="'.esc_attr($field['id']).'">'.$html.$field['description'].'</label>';
			}
		}

		return $html;
	}

	private static function get_mu_fields_html($name, $fields, $i, $value=[]){
		$show_if_keys	= self::get_show_if_keys($fields);

		$field_html		= '';

		foreach ($fields as $sub_key=>$sub_field) {
			if($sub_field['type'] == 'fieldset'){
				wp_die('mu-fields 不允许内嵌 fieldset');
			}elseif($sub_field['type'] == 'mu-fields'){
				wp_die('mu-fields 不允许内嵌 mu-fields');
			}

			$sub_id		= $sub_field['id'] ?? $sub_key;
			$sub_name	= $sub_field['name'] ?? $sub_key;

			if(preg_match('/\[([^\]]*)\]/', $sub_name)){
				wp_die('mu-fields 类型里面子字段不允许[]模式');
			}

			$sub_field['name']	= $name.'['.$i.']'.'['.$sub_name.']';

			if($value){
				if(!empty($value[$sub_name])){
					$sub_field['value']	= $value[$sub_name];
				}
			}

			if($show_if_keys && in_array($sub_key, $show_if_keys)){
				$sub_field['show_if_key']	= true;
			}

			if(isset($sub_field['show_if'])){
				$sub_field['show_if']['key']	.= '_'.$i;
			}

			$sub_key	.= '_'.$i;
			$sub_id		.= '_'.$i;

			$sub_field['key']		= $sub_key;
			$sub_field['id']		= $sub_id;
			$sub_field['data-i']	= $i;

			$sub_html	= self::get_field_html($sub_field);

			if($sub_field['type'] == 'hidden'){
				$field_html	.= $sub_html;
			}else{
				$wrap_attr	= self::parse_wrap_attr($sub_field, ['sub-field']);
				$sub_title	= $sub_field['title'] ?? ''; 
				$sub_title	= $sub_title ? '<label class="sub-field-label" for="'.$sub_id.'">'.$sub_title.'</label>' : '';

				$field_html	.= '<div '.$wrap_attr.'>'.$sub_title.'<div class="sub-field-detail">'.$sub_html.'</div></div>';
			}
		}

		return $field_html;
	}

	private static function parse_option_title($option_title, &$data_attr){
		$attr	= $class	= [];

		if(is_array($option_title)){
			foreach ($option_title as $k => $v) {
				if($k == 'show_if'){
					if($show_if_data = self::parse_show_if($v, $class)){
						$attr[]	= $show_if_data;
					}
				}elseif($k == 'class'){
					$class	= array_merge($class, explode(' ', $v));
				}elseif($k != 'title' && !is_array($v)){
					$attr[]	= 'data-'.$k.'="'.esc_attr($v).'"';
				}
			}

			$option_title	= $option_title['title'];
		}

		if($class){
			$attr[]	= 'class="'.implode(' ', $class).'"';
		}

		$data_attr	= $attr ? implode(' ', $attr) : '';

		return $option_title;
	}

	public static function parse_wrap_attr($field, $class=[]){
		$attr	= [];

		if(!empty($field['wrap_class'])){
			$class[]	= $field['wrap_class'];
		}

		if(isset($field['show_if'])){
			if($show_if_data = self::parse_show_if($field['show_if'], $class)){
				$attr[]	= $show_if_data;
			}
		}

		$attr[]	= $class ? 'class="'.implode(' ', $class).'"' : '';

		return $attr ? implode(' ', $attr) : '';
	}

	private static function parse_show_if($show_if, &$class=[]){
		if(empty($show_if['key'])){
			return '';
		}

		if(empty($show_if['compare'])){
			$show_if['compare']	= '=';
		}else{
			$show_if['compare']	= strtoupper($show_if['compare']);

			if($show_if['compare'] == 'ITEM'){
				return '';
			}
		}

		if(in_array($show_if['compare'], ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])){
			if(!is_array($show_if['value'])){
				$show_if['value']	= preg_split('/[,\s]+/', $show_if['value']);
			}

			if(count($show_if['value']) == 1){
				$show_if['value']	= current($show_if['value']);
				$show_if['compare']	= in_array($show_if['compare'], ['IN', 'BETWEEN']) ? '=' : '!=';
			}
		}else{
			$show_if['value']	= trim($show_if['value']);
		}

		$class[]	= 'show-if-'.$show_if['key'];

		unset($show_if['key']);

		return 'data-show_if=\''.wpjam_json_encode($show_if).'\'';;
	}

	private static function get_show_if_keys($fields){
		$show_if_keys	= [];

		foreach ($fields as $key => $field){
			if(isset($field['show_if']) && !empty($field['show_if']['key'])){
				$show_if_keys[]	= $field['show_if']['key'];
			}

			if($field['type'] == 'fieldset' && !empty($field['fields'])){
				$show_if_keys	= array_merge($show_if_keys, self::get_show_if_keys($field['fields']));
			}
		}

		return array_unique($show_if_keys);
	}

	private static function generate_sub_field_name($name){
		if(preg_match('/\[([^\]]*)\]/', $name)){
			$name_arr	= wp_parse_args($name);
			$name		= '';

			do{
				$name		.='['.current(array_keys($name_arr)).']';
				$name_arr	= current(array_values($name_arr));
			}while ($name_arr);

			return $name;
		}else{
			return '['.$name.']';
		}
	}

	public  static function get_field_tmpls(){
		if(!wp_doing_ajax()){
			self::$field_tmpls	+= [
				'img'		=> '<img style="{{ data.img_style }}" src="{{ data.img_url }}{{ data.thumb_args }}" alt="" />'.self::$del_img_icon,
				'mu-img'	=> '<div class="mu-item mu-img"><img width="100" src="{{ data.img_url }}{{ data.thumb_args }}"><input type="hidden" name="{{ data.name }}" id="{{ data.id }}_{{ data.i }}" value="{{ data.img_value }}" />'.self::$del_item_icon.'</div>',
				'mu-file'	=> '<div class="mu-item"><input type="url" name="{{ data.name }}" id="{{ data.id }}_{{ data.i }}" class="regular-text" value="{{ data.img_url }}" /> '.self::$del_item_button.self::$sortable_dashicons.'</div>'
			]; 
		}

		$output = '';

		if(self::$field_tmpls){ 
			foreach (self::$field_tmpls as $tmpl_id => $field_tmpl) {
				$output .= "\n".'<script type="text/html" id="tmpl-wpjam-'.$tmpl_id.'">'."\n";
				$output .=  $field_tmpl."\n";
				$output .=  '</script>'."\n";
			}

			self::$field_tmpls	= [];
		}

		return $output;
	}

	public  static function fields_validate($fields, $values=null){
		$data = [];

		foreach ($fields as $key => $field) {
			if($field['type'] == 'fieldset'){
				if(empty($field['fields'])){
					continue;
				}

				if(!empty($field['fieldset_type']) && $field['fieldset_type'] == 'array'){
					$name	= $field['name'] ?? $key;

					array_walk($field['fields'], function(&$sub_field, $sub_key) use($name){
						$sub_field['name']	= $sub_field['name'] ?? $sub_key;
						$sub_field['name']	= $name.self::generate_sub_field_name($sub_field['name']);
					});
				}

				$data	= wpjam_array_merge($data, self::fields_validate($field['fields'], $values));
			}else{
				$name	= $field['name'] ?? $key;

				if(preg_match('/\[([^\]]*)\]/', $name)){
					$name_arr	= wp_parse_args($name);
					$name		= current(array_keys($name_arr));

					if(isset($values)){
						$value	= $values[$name] ?? null;
					}else{
						$value	= wpjam_get_parameter($name, ['method'=>'POST']);
					}

					$name_arr		= current(array_values($name_arr));
					$sub_name_arr	= [];

					do{
						$sub_name	= current(array_keys($name_arr));
						$name_arr	= current(array_values($name_arr));

						if(isset($value) && isset($value[$sub_name])){
							$value	= $value[$sub_name];
						}else{
							$value	= null;
						}

						array_unshift($sub_name_arr, $sub_name);
					}while($name_arr && $value);

					$value	= self::field_validate($field, $value);

					if(is_wp_error($value)){
						return $value;
					}

					if($value !== false){
						foreach($sub_name_arr as $sub_name) {
							$value	= [$sub_name => $value];
						}

						$data	= wpjam_array_merge($data, [$name=>$value]);
					}
				}else{
					if(isset($values)){
						$value	= $values[$name] ?? null;
					}else{
						$value	= wpjam_get_parameter($name, ['method'=>'POST']);
					}

					$value	= self::field_validate($field, $value);

					if(is_wp_error($value)){
						return $value;
					}

					if($value !== false){
						$data[$name]	= $value;
					}
				}
			}
		}

		return $data;
	}

	private static function field_validate($field, $value){
		$type	= $field['type'] ?? 'text';

		if(in_array($type, ['view', 'br','hr'])){
			return false;
		}elseif($type == 'checkbox'){
			if(is_null($value)){
				return 0;
			}
		}

		if(!empty($field['readonly']) || !empty($field['disabled'])){
			return false;
		}

		if(isset($field['show_admin_column']) && ($field['show_admin_column'] === 'only')){
			return false;
		}

		if(is_null($value)){
			return $value;
		}

		$validate_callback	= $field['validate_callback'] ?? '';

		if($validate_callback && is_callable($validate_callback)){
			$result	= call_user_func($validate_callback, $value);

			if($result === false){
				return new WP_Error('invalid_value', $field['title'].'的值无效');
			}elseif(is_wp_error($result)){
				return $result;
			}
		}

		if(in_array($type, ['mu-image','mu-file','mu-text','mu-img'])){
			if(!is_array($value)){
				$value	= null;
			}else{
				$value	= array_filter($value);
			}
		}elseif($type == 'mu-fields'){
			if(!is_array($value)){
				$value	= null;
			}else{
				foreach($value as $i => &$v){
					foreach($v as $sub_key => $sub_value) {
						if(is_array($sub_value)){
							$v[$sub_key]	= array_filter($sub_value);
						}
					}

					if(empty(array_filter($v))){
						unset($value[$i]);
					}
				}

				unset($v);

				$value	= array_values($value);
			}
		}elseif($type == 'number'){
			if(!empty($field['step']) && ($field['step'] == 'any' || strpos($field['step'], '.'))){
				$value	= (float)$value;
			}else{
				$value	= (int)$value;
			}
		}else{
			if($value && !is_array($value)){
				$value	= trim($value);
			}

			if($type == 'textarea'){
				$value	= $value ? str_replace("\r\n", "\n",$value) : $value;
			}
		}

		$sanitize_callback	= $field['sanitize_callback'] ?? '';

		if($sanitize_callback && is_callable($sanitize_callback)){
			$value	= call_user_func($sanitize_callback, $value);
		}

		return $value;
	}

	public  static function validate_fields_value($fields, $values=[]){
		return self::fields_validate($fields, $values);
	}
}

class WPJAM_Form extends WPJAM_Field{
	public static function form_validate($fields, $nonce_action='', $capability='manage_options'){
		check_admin_referer($nonce_action);

		if(!current_user_can($capability)){
			ob_clean();
			wp_die('无权限');
		}

		return self::fields_validate($fields);
	}

	public static function form_callback($fields, $form_url, $nonce_action='', $submit_text=''){
		echo '<form method="post" action="'.$form_url.'" enctype="multipart/form-data" id="form">';

		echo self::fields_callback($fields);

		wp_nonce_field($nonce_action);
		wp_original_referer_field(true, 'previous');

		if($submit_text!==false){ 
			submit_button($submit_text);
		}

		echo '</form>';
	}
}