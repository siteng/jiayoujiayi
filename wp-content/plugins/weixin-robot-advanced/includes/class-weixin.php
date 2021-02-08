<?php
wp_cache_add_global_groups('weixin');

class WEIXIN{
	private $appid;
	private $secret;

	public function __construct($appid, $secret){
		$this->appid	= $appid;
		$this->secret	= trim($secret);
	}

	// 用户
	public function get_user_info($openid){
		if($this->cache_get('user_lock:'.$openid) !== false){
			return false;
		}
		
		$this->cache_set('user_lock:'.$openid, true, 1);	// 1 秒的内存锁，防止重复远程请求微信用户资料

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/user/info?openid='.urlencode($openid));
	}

	public function batch_get_user_info($openids, $lang='zh_CN'){
		$user_list	= array_map(function($openid) use($lang){ return ['openid'=>$openid, 'lang'=>$lang]; }, $openids);

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/user/info/batchget', [
			'method'	=> 'POST',
			'body'		=> compact('user_list')
		]);
	}

	public function get_user_list($next_openid){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/user/get?next_openid='.$next_openid);
	}

	public function update_user_remark($openid, $remark){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/user/info/updateremark', [
			'method'=> 'POST',
			'body'	=> compact('openid', 'remark')
		]);
	}


	// 网页授权
	public function get_oauth_access_token($code){
		$oauth_access_token	= $this->cache_get('oauth_access_token:'.$code);

		if($oauth_access_token === false) {
			$oauth_access_token	= $this->http_request('https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->appid.'&secret='.$this->secret.'&code='.$code.'&grant_type=authorization_code', ['need_access_token'=>false]);

			if(is_wp_error($oauth_access_token)){
				return $oauth_access_token;
			}

			$oauth_access_token['expires_in']	= $oauth_access_token['expires_in'] + time() - 600;

			$openid	= $oauth_access_token['openid'];

			$this->cache_set('oauth_access_token:'.$code,   $oauth_access_token, MINUTE_IN_SECONDS*5);	// 防止同个 code 多次请求
			$this->cache_set('oauth_access_token:'.$openid, $oauth_access_token, DAY_IN_SECONDS*29);	// refresh token 有限期为30天
		}

		return $oauth_access_token;
	}

	public function get_oauth_access_token_by_openid($openid){
		$oauth_access_token	= $this->cache_get('oauth_access_token:'.$openid);

		if(!$oauth_access_token){
			return new WP_Error('empty_oauth_access_token', '服务器缓存的 oauth_access_token 失效！');
		}

		if($oauth_access_token['expires_in'] > time()){	// 内存中有效
			return $oauth_access_token;
		}

		if(empty($oauth_access_token['refresh_token'])){
			return new WP_Error('empty_oauth_access_token', '服务器缓存的 oauth_access_token 失效！');
		}

		return $this->refresh_oauth_access_token($oauth_access_token['refresh_token']);
	}

	public function refresh_oauth_access_token($refresh_token){
		$oauth_access_token	=  $this->http_request('https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$this->appid.'&grant_type=refresh_token&refresh_token='.$refresh_token, ['need_access_token'=>false]);

		if(is_wp_error($oauth_access_token)){
			return $oauth_access_token;
		}

		$openid	= $oauth_access_token['openid'];
		
		$this->cache_set('oauth_access_token:'.$openid, $oauth_access_token, DAY_IN_SECONDS*29);	// refresh token 有限期为30天

		return $oauth_access_token;
	}

	public function get_oauth_userinfo($openid, $access_token){
		return  $this->http_request('https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN', ['need_access_token'=>false]);
	}

	public function get_oauth_redirect_url($scope='snsapi_base', $redirect_uri=''){
		return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->appid.'&redirect_uri='.urlencode($redirect_uri).'&response_type=code&scope='.$scope.'&state='.wp_create_nonce($scope).'&connect_redirect=1#wechat_redirect';
	}


	// 1. 最多100个标签
	// 2. 用户最多打10个标签
	// 3. 3个系统默认保留的标签不能修改
	// 4. 粉丝数超过10w的标签不能删除
	// 5. 批量为用户打标签每次最多50个用户，取消打标签也是

	// 因为获取用户详细资料的接口已有标签信息，所以获取用户标签接口无意义

	// 微信 batchget 用户资料里面的 tagid 列表是错的，==> 微信已经修正成对的，

	public function get_tags($force=false){

		$user_tags	= $this->cache_get('user_tags');

		if($user_tags === false || $force){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/get');

			if(is_wp_error($response)){
				return $response;
			}

			$user_tags = $response['tags'];

			if($user_tags){
				$user_tag_ids	= array_column($user_tags, 'id');
				$user_tags		= array_combine($user_tag_ids, $user_tags);
			}

			$this->cache_set('user_tags', $user_tags, DAY_IN_SECONDS);
		}

		return $user_tags;
	}

	public function create_tag($name){
		$this->cache_delete('user_tags');

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/create', [
			'method'	=> 'POST',
			'body'		=> ['tag'=>compact('name')],
		]);
	}

	public function update_tag($id, $name){
		$this->cache_delete('user_tags');

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/update', [
			'method'	=> 'POST',
			'body'		=> ['tag'=>['id'=>intval($id),'name'=>$name]],
		]);
	}

	public function delete_tag($id){
		$this->cache_delete('user_tags');

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/delete', [
			'method'	=> 'POST',
			'body'		=> ['tag'=>['id'=>intval($id)]],
		]);
	}

	public function batch_tagging($openid_list, $tagid){
		$this->cache_delete('user_tags');

		if(is_string($openid_list)){
			$openid_list = [$openid_list];
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging', [
			'method'	=> 'POST',
			'body'		=> compact('openid_list','tagid'),
		]);
	}

	public function batch_untagging($openid_list, $tagid){
		$this->cache_delete('user_tags');

		if(is_string($openid_list)){
			$openid_list = [$openid_list];
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/members/batchuntagging', [
			'method'	=> 'POST',
			'body'		=> compact('openid_list','tagid'),
		]);
	}

	public function get_tag_users($tagid, $next_openid=''){
		if(empty($next_openid)){
			$tag_users	= $this->cache_get('tag_users:'.$tagid);
		}

		if($next_openid || $tag_users === false){
			$tag_users	= $this->http_request('https://api.weixin.qq.com/cgi-bin/user/tag/get', [
				'method'	=> 'POST',
				'body'		=> compact('tagid', 'next_openid'),
			]);

			if(empty($next_openid)){
				$this->cache_set('tag_users:'.$tagid, $tag_users, MINUTE_IN_SECONDS);
			}
		}

		return $tag_users;
	}


	// 用户黑名单
	public function get_blacklist($next_openid=''){
		if(empty($next_openid)){
			$blacklist	= $this->cache_get('blacklist');
		}

		if($next_openid || $blacklist === false){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/members/getblacklist', [
				'method'	=> 'POST',
				'body'		=> compact('next_openid'),
			]);

			if(is_wp_error($response)){
				return $response;
			}

			if($response['total']){
				$blacklist	= $response['data']['openid'];

				if($response['total'] > $response['count']){
					$next_openid	= $response['next_openid'];
					// 继续获取，以后再写，谁TM有一万个黑名单用户的时候，我一定帮他写。
				}

				if($next_openid == ''){
					$this->cache_set('blacklist', $blacklist, HOUR_IN_SECONDS);
				}
			} else {
				$this->cache_set('blacklist', [], HOUR_IN_SECONDS);
			}
		}

		return $blacklist;
	}

	public function batch_blacklist($openid_list){
		$this->cache_delete('blacklist');

		if(is_string($openid_list)){
			$openid_list = [$openid_list];
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/members/batchblacklist', [
			'method'	=> 'POST',
			'body'		=> compact('openid_list'),
		]);
	}

	public function batch_unblacklist($openid_list){
		$this->cache_delete('blacklist');

		if(is_string($openid_list)){
			$openid_list = [$openid_list];
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/tags/members/batchunblacklist', [
			'method'	=> 'POST',
			'body'		=> compact('openid_list'),
		]);
	}


	// 菜单
	public function get_menu(){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/menu/get');
	}

	public function delete_menu(){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/menu/delete');
	}

	public function create_menu($button){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/menu/create', [
			'method'	=> 'POST',
			'body'		=> compact('button'),
		]);
	}

	public function add_conditional_menu($button, $matchrule){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/menu/addconditional', [
			'method'	=> 'POST',
			'body'		=> compact('button','matchrule'),
		]);
	}

	public function del_conditional_menu($menuid){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/menu/delconditional', [
			'method'	=> 'POST',
			'body'		=> compact('menuid'),
		]);
	}

	public function try_match_menu($user_id){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/menu/trymatch', [
			'method'	=> 'POST',
			'body'		=> compact('user_id'),
		]);
	}


	// 临时素材
	public function upload_media($media, $type='image'){
		$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/media/upload?type='.$type, [
			'method'	=> 'file',
			'body'		=> ['media'=> new CURLFile($media,'', basename($media))],
		]);

		if(is_wp_error($response)){
			return $response;
		}

		if($response['type'] == 'thumb'){
			return $response['thumb_media_id'];
		}else{
			return $response['media_id'];
		}
	}

	public function upload_news_media($articles){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/media/uploadnews', [
			'method'	=> 'POST',
			'body'		=> $articles
		]);
	}

	public function upload_img_media($media, $args=[]){	//上传图片获取微信图片链接
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/media/uploadimg', [
			'method'	=> 'file',
			'body'		=> ['media'=> new CURLFile($media,$args['filetype'],$args['filename'])],
		]);
	}

	public function get_media($media_id, $type='image'){
		if($type=='image'){
			$media_dir	= substr($media_id, 0, 1).'/'.substr($media_id, 1, 1);
			if(!is_dir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.$this->appid.'/'.'media/'.$media_dir)){
				mkdir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.$this->appid.'/'.'media/'.$media_dir, 0777, true);
			}

			$media_file	= WEIXIN_ROBOT_PLUGIN_TEMP_DIR.$this->appid.'/'.'media/'.$media_dir.'/'.$media_id.'.jpg';
			$media_url	= WEIXIN_ROBOT_PLUGIN_TEMP_URL.$this->appid.'/'.'media/'.$media_dir.'/'.$media_id.'.jpg';

			if(!file_exists($media_file)){
				$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/media/get?media_id='.$media_id, [
					'stream'			=>true, 
					'filename'			=>$media_file,
					'need_json_decode'	=>false
				]);

				if(is_wp_error($response)){
					return $response;
				}
			}

			return $media_url;
		}
	}

	public function get_media_download_url($media_id){
		$response = $this->get_access_token();

		if(is_wp_error($response)){
			return $response;
		}

		return 'https://api.weixin.qq.com/cgi-bin/media/get?media_id='.$media_id.'&access_token='.$response['access_token'];
	}


	// 永久素材
	public function get_material($media_id, $type='image', $force=false){
		$url	= 'https://api.weixin.qq.com/cgi-bin/material/get_material';
		$body	= compact('media_id');

		if($type=='image' || $type=='thumb'){

			$media_dir	= substr($media_id, 0, 1).'/'.substr($media_id, 1, 1);
			if(!is_dir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.$this->appid.'/'.'material/'.$media_dir)){
				mkdir(WEIXIN_ROBOT_PLUGIN_TEMP_DIR.$this->appid.'/'.'material/'.$media_dir, 0777, true);
			}

			$media_file	= WEIXIN_ROBOT_PLUGIN_TEMP_DIR.$this->appid.'/'.'material/'.$media_dir.'/'.$media_id.'.jpg';
			$media_url	= WEIXIN_ROBOT_PLUGIN_TEMP_URL.$this->appid.'/'.'material/'.$media_dir.'/'.$media_id.'.jpg';

			if(!file_exists($media_file) || $force){
				$response	= $this->http_request($url, [
					'method'			=> 'POST',
					'body'				=> $body,
					'stream'			=> true, 
					'filename'			=> $media_file,
					'need_json_decode'	=> false
				]);

				if(is_wp_error($response)){
					if($response->get_error_code() == '40007'){	//  invalid media_id
						$im = imagecreatetruecolor(120, 20);
						$text_color = imagecolorallocate($im, 233, 14, 91);
						imagestring($im, 1, 5, 5,  'invalid media_id', $text_color);

						imagejpeg($im, $media_file, 100 ); // 存空图片，防止重复请求
					}
					return $response;
				}	
			}

			return $media_url;
		}elseif($type == 'news'){
			$material	= $this->cache_get('material:'.$media_id);
			if($material === false || $force){
				$response	= $this->http_request($url, [
					'method'	=> 'POST',
					'body'		=> $body
				]);

				if(is_wp_error($response)){
					return $response;
				}

				$material	= $response['news_item'];
				$this->cache_set('material:'.$media_id, $material, DAY_IN_SECONDS);
			}
			return $material;
		}elseif($type == 'video'){
			$response	= $this->http_request($url, [
				'method'	=> 'POST',
				'body'		=> $body
			]);

			return $response;
		}
	}

	public function del_material($media_id){
		$this->cache_delete('material'.$media_id);

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/material/del_material', [
			'method'	=> 'POST',
			'body'		=> compact('media_id')
		]);
	}

	public function add_news_material($articles){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/material/add_news', [
			'method'	=> 'POST',
			'body'		=> compact('articles')
		]);
	}

	public function update_news_material($media_id, $index, $articles){
		$this->cache_delete('material'.$media_id);
		
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/material/update_news', [
			'method'	=> 'POST',
			'body'		=> compact('media_id', 'index', 'articles')
		]);
	}

	public function add_material($media, $type='image', $args=[]){
		$args	= wp_parse_args($args, [
			'description'	=> '',
			'filename'		=> '',
			'filetype'		=> '',
		]);

		$body	= ['type'=>$type];

		$filename 		= $args['filename'] ?: basename($media);
		$body['media']	= new CURLFile($media, $args['filetype'], $filename);

		if($args['description']){
			$body['description']= wpjam_json_encode($args['description']);
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/material/add_material', [
			'method'	=> 'file',
			'body'		=> $body
		]);
	}

	public function add_material_by_remote_image($image_url){
		$file	= download_url($image_url);

		if(is_wp_error($file)){
			return $file;
		}

		$filetype	= wp_get_image_mime($file);
		$filename	= md5($image_url).'.'.(explode('/', $filetype)[1]);

		$response	= weixin()->add_material($file, 'image', compact('filetype', 'filename'));

		unlink($file);

		return $response;
	}

	public function batch_get_material($type='news', $offset=0, $count=20){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/material/batchget_material', [
			'method'	=> 'post',
			'body'		=> compact("type", "offset", "count")
		]);
	}

	public function get_material_count(){
		$material_count  = $this->cache_get('material_count');

		if($material_count === false){

			$material_count = $this->http_request('https://api.weixin.qq.com/cgi-bin/material/get_materialcount');

			if(is_wp_error($material_count)){
				return $material_count;
			}

			$this->cache_set('weixin_material_count', $material_count, 60);
		}

		return $material_count;
	}

	// 消息
	public function sendall_mass_message($tag_id, $msgtype='text', $content='', $send_ignore_reprint=1){
		$data	= $this->get_message_send_data($msgtype, $content);

		if($tag_id == 'all'){
			$data['filter']	= ['is_to_all'=>true];
		}else{
			$data['filter']	= ['tag_id'=>$tag_id, 'is_to_all'=>false];
		}

		$data['send_ignore_reprint']	= (int)$send_ignore_reprint;

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/message/mass/sendall', [
			'method'	=> 'post',
			'body'		=> $data
		]);
	}

	public function send_mass_message($touser, $msgtype='text', $content=''){
		$data	= $this->get_message_send_data($msgtype, $content);

		$data['touser']	= $touser;

		$blacklist	= $this->get_blacklist();

		if($blacklist && in_array($touser, $blacklist)){
			return new WP_Error('blacklist_openid', '无法发送黑名单中用户。');
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/message/mass/send', [
			'method'	=> 'post',
			'body'		=> $data
		]);
	}

	public function preview_mass_message($towxname, $msgtype='text', $content=''){
		$data	= $this->get_message_send_data($msgtype, $content);
		$data['towxname']	= $towxname;

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/message/mass/preview', [
			'method'	=> 'post',
			'body'		=> $data
		]);
	}

	public function send_custom_message($data=[]){
		$data	= wp_parse_args($data, [
			'touser'	=> '',
			'msgtype'	=> 'text',
		]);

		$blacklist	= $this->get_blacklist();
		$openid		= $data['touser'];

		if($blacklist && in_array($openid, $blacklist)){
			return new WP_Error('blacklist_openid', '无法发送黑名单中用户。');
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/message/custom/send', [
			'method'	=> 'post',
			'body'		=> $data
		]);
	}

	public function send_template_message($data=[]){
		$data	= wp_parse_args($data, [
			'touser'			=> '',
			'template_id'		=> '',
			'url'				=> '',
			'miniprogram'		=> [],
			'data'				=> [],
		]);

		$blacklist	= $this->get_blacklist();
		$openid		= $data['touser'];

		if($blacklist && in_array($openid, $blacklist)){
			return new WP_Error('blacklist_openid', '无法发送黑名单中用户。');
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/message/template/send', [
			'method'	=> 'post',
			'body'		=> $data
		]);
	}

	public function get_message_send_data($msgtype='text', $content='', $title='', $description=''){
		$data 				= [];
		$data['msgtype']	= $msgtype;

		if($msgtype == 'text'){
			$data['text']	= ['content'=>$content];
		}elseif(in_array($msgtype, ['voice', 'image', 'mpnews', 'mpvideo'])){
			$data[$msgtype]	= ['media_id'=>$content];
		}elseif($msgtype == 'video'){
			$data['video']	= ['media_id'=>$content, 'title'=>$title, 'description'=>$description];
		}elseif($msgtype == 'news'){
			$data['news']	= ['articles'=>$content];
		}elseif($msgtype == 'miniprogrampage'){
			$data['miniprogrampage']	= $content;
		}elseif($msgtype == 'wxcard'){
			$data['wxcard']	= $content;
		}

		return $data;
	}


	// 模板消息
	public function get_template_industry(){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/template/get_industry');
	}

	public function set_template_industry($industry_id1,$industry_id2){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/template/api_set_industry', [
			'method'	=> 'POST',
			'body'		=> compact('industry_id1', 'industry_id2')
		]);
	}

	public function add_template($template_id_short){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/template/api_add_template', [
			'method'	=> 'POST',
			'body'		=> compact('template_id_short')
		]);
	}

	public function get_all_private_templates(){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/template/get_all_private_template');
	}

	public function del_private_template($template_id){
		return $this->http_request('https://api.weixin.qq.com/cgi-bin/template/del_private_template', [
			'method'	=> 'POST',
			'body'		=> compact('template_id')
		]);
	}
	

	// Ticket
	public function get_js_api_ticket(){
		return $this->get_ticket('jsapi');;
	}

	public function get_jsapi_ticket(){
		return $this->get_ticket('jsapi');;
	}

	public function get_wx_card_ticket(){
		return $this->get_ticket('wx_card');
	}

	private function get_ticket($type){
		$response = $this->cache_get($type.'_ticket');

		if($response == false){

			$response = $this->http_request('https://api.weixin.qq.com/cgi-bin/ticket/getticket?type='.$type);

			if(is_wp_error($response)){
				return false;
			}

			$response['expires_in']	= time()+$response['expires_in']-600;

			$this->cache_set($type.'_ticket', $response, $response['expires_in']);
		}

		return $response;
	}

	// 带参数的二维码
	public function create_qrcode($action_name='QR_LIMIT_SCENE', $scene='', $expire_seconds=2592000){
		$data = compact('action_name');

		if($action_name == 'QR_LIMIT_SCENE'){
			$data['action_info']	= ['scene'=>['scene_id'=>intval($scene)]];
		}elseif($action_name == 'QR_LIMIT_STR_SCENE'){
			$data['action_info']	= ['scene'=>['scene_str'=>$scene]];
		}elseif($action_name == 'QR_SCENE'){
			$data['action_info']	= ['scene'=>['scene_id'=>intval($scene)]];;
			$data['expire_seconds']	= intval($expire_seconds);
		}elseif($action_name == 'QR_STR_SCENE'){
			$data['action_info']	= ['scene'=>['scene_str'=>$scene]];
			$data['expire_seconds']	= intval($expire_seconds);
		}

		return $this->http_request('https://api.weixin.qq.com/cgi-bin/qrcode/create', [
			'method'	=> 'POST',
			'body'		=> $data
		]);
	}

	
	// 客服接口
	public function add_customservice_kf_account($data){
		$this->cache_delete('kf_list');

		return $this->http_request('https://api.weixin.qq.com/customservice/kfaccount/add', [
			'method'	=> 'POST',
			'body'		=> $data
		]);
	}

	public function update_customservice_kf_account($data){
		$this->cache_delete('kf_list');

		return $this->http_request('https://api.weixin.qq.com/customservice/kfaccount/update', [
			'method'	=> 'POST',
			'body'		=> $data
		]);
	}

	public function delete_customservice_kf_account($kf_account){
		$this->cache_delete('kf_list');

		return $this->http_request('https://api.weixin.qq.com/customservice/kfaccount/del?kf_account='.urldecode($kf_account));
	}

	public function invite_customservice_kf_account_worker($kf_account, $invite_wx){
		$this->cache_delete('kf_list');

		return $this->http_request('https://api.weixin.qq.com/customservice/kfaccount/inviteworker', [
			'method'	=> 'POST',
			'body'		=> compact('kf_account','invite_wx')
		]);
	}

	public function upload_customservice_kf_account_headimg($kf_account, $media){
		$this->cache_delete('kf_list');

		return	$this->http_request('https://api.weixin.qq.com/customservice/kfaccount/uploadheadimg?kf_account='.urldecode($kf_account), [
			'method'	=> 'file',
			'body'		=> ['media'=> curl_file_create($media)],
		]);
	}

	public function get_customservice_kf_list(){
		$kf_list	= $this->cache_get('kf_list');
		if($kf_list === false){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/customservice/getkflist');
			if(is_wp_error($response)){
				$this->cache_set('kf_list', [], 60);
				return $response;
			}else{
				$kf_list = $response['kf_list'];
				$this->cache_set('kf_list', $kf_list, 3600);
			}
		}

		return $kf_list;
	}

	public function get_customservice_online_kf_list(){
		$online_kf_list = $this->cache_get('online_kf_list');
		if($online_kf_list === false){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/customservice/getonlinekflist');
			if(is_wp_error($response)){
				$this->cache_set('online_kf_list', [], 30);
				return $response;
			}else{
				$online_kf_list = $response['kf_online_list'];
				$this->cache_set('online_kf_list', $online_kf_list, 30);
			}
		}

		return $online_kf_list;
	}

	public function create_customservice_kf_session($kf_account, $openid, $text=''){
		return $this->http_request('https://api.weixin.qq.com/customservice/kfsession/create', [
			'method'	=> 'POST',
			'body'		=> compact('kf_account', 'openid', 'text')
		]);
	}

	public function close_customservice_kf_session($kf_account, $openid, $text=''){
		return $this->http_request('https://api.weixin.qq.com/customservice/kfsession/close', [
			'method'	=> 'POST',
			'body'		=> compact('kf_account', 'openid', 'text')
		]);
		
	}

	public function get_customservice_kf_session($openid){
		return $this->http_request('https://api.weixin.qq.com/customservice/kfsession/getsession?openid='.$openid);
	}

	public function get_customservice_kf_session_list($kf_account){
		$response	= $this->http_request('https://api.weixin.qq.com/customservice/kfsession/getsessionlist?kf_account='.$kf_account);

		if(is_wp_error($response)){
			return $response;
		}

		return $response['sessionlist'];
	}

	public function get_customservice_kf_wait_case_session_list($kf_account){
		return $this->http_request('https://api.weixin.qq.com/customservice/kfsession/getwaitcase');
	}

	public function get_customservice_msg_record($starttime, $endtime, $pageindex=1, $pagesize=50){
		return $this->http_request('https://api.weixin.qq.com/customservice/msgrecord/getrecord', [
			'method'	=> 'POST',
			'body'		=> compact('starttime','endtime','pagesize','pageindex')
		]);
	}


	// 数据接口
	public function get_article_total($begin_date, $end_date){	
		return $this->http_request('https://api.weixin.qq.com/datacube/getarticletotal', [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}

	public function get_article_summary($begin_date, $end_date){
		return $this->http_request('https://api.weixin.qq.com/datacube/getarticlesummary', [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}


	public function get_interface_summary($begin_date, $end_date, $type='day'){
		if($type == 'day'){
			$url		= 'https://api.weixin.qq.com/datacube/getinterfacesummary';		
		}else{
			$url		= 'https://api.weixin.qq.com/datacube/getinterfacesummaryhour';
			$end_date	= $begin_date;
		}

		return $this->http_request($url, [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}

	public function get_up_stream_msg($begin_date, $end_date, $type='day'){
		$urls	= [
			'day'	=> 'https://api.weixin.qq.com/datacube/getupstreammsg',
			'hour'	=> 'https://api.weixin.qq.com/datacube/getupstreammsghour',
			'week'	=> 'https://api.weixin.qq.com/datacube/getupstreammsgweek',
			'month'	=> 'https://api.weixin.qq.com/datacube/getupstreammsgmonth'
		];

		return $this->http_request($urls[$type], [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}


	public function get_up_stream_msg_dist($begin_date, $end_date, $type='day'){
		$urls	= [
			'day'	=> 'https://api.weixin.qq.com/datacube/getupstreammsgdist',
			'hour'	=> 'https://api.weixin.qq.com/datacube/getupstreammsgdisthour',
			'week'	=> 'https://api.weixin.qq.com/datacube/getupstreammsgdistweek',
			'month'	=> 'https://api.weixin.qq.com/datacube/getupstreammsgdistmonth'
		];

		return $this->http_request($urls[$type], [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}

	public function get_user_read( $begin_date, $end_date='', $type = 'day'){
		if($type == 'day'){
			$url 	= 'https://api.weixin.qq.com/datacube/getuserread';
		}else{
			$url 	= 'https://api.weixin.qq.com/datacube/getuserreadhour';
			$end_date	= $begin_date;
		}

		return $this->http_request($url, [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}

	public function get_user_share( $begin_date, $end_date='', $type = 'day'){
		if($type == 'day'){
			$url 	= 'https://api.weixin.qq.com/datacube/getusershare';
		}else{
			$url 	= 'https://api.weixin.qq.com/datacube/getusersharehour';
			$end_date	= $begin_date;
		}

		return $this->http_request($url, [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}

	public function get_user_summary($begin_date, $end_date){
		return $this->http_request('https://api.weixin.qq.com/datacube/getusersummary', [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}

	public function get_user_cumulate($begin_date, $end_date){
		return $this->http_request('https://api.weixin.qq.com/datacube/getusercumulate', [
			'method'	=> 'POST',
			'body'		=> compact('begin_date','end_date')
		]);
	}


	// 微信商品库接口
	// https://mp.weixin.qq.com/cgi-bin/announce?action=getannouncement&key=11533749572M9ODP&version=1&lang=zh_CN&platform=2
	public function add_scan_product($data){
		return $this->http_request('https://api.weixin.qq.com/scan/product/v2/add', [
			'method'	=> 'POST',
			'body'		=> $data
		]);
	}

	public function query_scan_product_status($status_ticket){
		return $this->http_request('https://api.weixin.qq.com/scan/product/v2/status', [
			'method'	=> 'POST',
			'body'		=> compact('status_ticket')
		]);
	}

	public function get_scan_product_info($pid){
		return $this->http_request('https://api.weixin.qq.com/scan/product/v2/getinfo', [
			'method'	=> 'POST',
			'body'		=> ['product' => ['pid' => strval($pid)]]
		]);
	}

	public function get_scan_product_info_by_page($page_context, $page_num=1, $page_size=10){
		return $this->http_request('https://api.weixin.qq.com/scan/product/v2/getinfobypage', [
			'method'	=> 'POST',
			'body'		=> compact('page_context', 'page_num', 'page_size')
		]);
	}


	// 
	public function get_current_autoreply_info(){
		return  $this->http_request('https://api.weixin.qq.com/cgi-bin/get_current_autoreply_info');
	}

	public function get_current_selfmenu_info(){
		return  $this->http_request('https://api.weixin.qq.com/cgi-bin/get_current_selfmenu_info');
	}

	public function clear_quota(){
		$last_clear_quota = $this->cache_get('clear_quota');

		if($last_clear_quota === false){
			$this->cache_set('clear_quota', true, HOUR_IN_SECONDS);
			
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/clear_quota', [
				'method'	=> 'POST',
				'body'		=> ['appid'=>$this->appid]
			]);

			return $response;
		}else{
			return new WP_Error('-1', '一小时内你刚刚清理过');
		}
	}

	public function check_callback($action='all',$check_operator='DEFAULT'){
		$response	= $this->cache_get('check_callback:'.$action.':'.$check_operator);

		if($response === false){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/callback/check', [
				'method'	=> 'POST',
				'body'		=> compact('action', 'check_operator')
			]);

			if(is_wp_error($response)){
				return $response;
			}

			$this->cache_set('check_callback:'.$action.':'.$check_operator, $response, MINUTE_IN_SECONDS*5);
		}

		return $response;
	}

	// 获取获取微信服务器IP地址
	public function get_callback_ip(){
		$ip_list	= $this->cache_get('callback_ip');

		if($ip_list === false){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/getcallbackip');

			if(is_wp_error($response)){
				return $response;
			}

			$ip_list = $response['ip_list'];
			$this->cache_set('callback_ip', $ip_list, DAY_IN_SECONDS);
		}
		return $ip_list;
	}

	// 获取微信API接口 IP地址
	public function get_api_domain_ip(){
		$ip_list	= $this->cache_get('api_domain_ip');

		if($ip_list === false){
			$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/get_api_domain_ip');

			if(is_wp_error($response)){
				return $response;
			}

			$ip_list = $response['ip_list'];
			$this->cache_set('api_domain_ip', $ip_list, DAY_IN_SECONDS);
		}
		return $ip_list;
	}

	// 获取微信短连接
	public function shorturl($long_url){
		$response	= $this->http_request('https://api.weixin.qq.com/cgi-bin/shorturl', [
			'method'	=> 'POST',
			'body'		=> ['action'=>'long2short', 'long_url'=>$long_url]
		]);

		if(is_wp_error($response)){
			return $response;
		}

		return $response['short_url'];
	}

	// 语义查询
	public function semantic_search($query, $category, $uid='', $location=[]){
		$appid 	= $this->appid;

		extract(wp_parse_args( $location, [
			'latitude'	=> '',
			'longitude'	=> '',
			'city'		=> '',
			'region'	=> ''
		]));

		$response	= $this->http_request('https://api.weixin.qq.com/semantic/semproxy/search', [
			'method'	=> 'POST',
			'body'		=> compact('query', 'category', 'appid', 'uid', 'latitude', 'longitude', 'city', 'region')
		]);

		if(is_wp_error($response)){
			return $response;
		}

		return $response['semantic'];
	}

	public function get_access_token($force=false){
		if(wpjam_doing_debug() == 'sql'){
			return new WP_Error('memcached_disabled', '未启用 Memcached。');
		}
		
		if(!$force){
			if($disabled = weixin_get_setting('api_disabled')){
				return new WP_Error($disabled['errcode'], $disabled['errmsg']);
			}
		}

		$response = $this->cache_get('access_token');

		if ($response === false || $force) {
			$response = $this->http_request("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->secret, ['need_access_token'=>false]);

			if(is_wp_error($response)){
				if(!$force){
					$errcode	= $response->get_error_code(); 

					if($errcode == '40164'){
						$errmsg	= '未把服务器IP填入微信公众号IP白名单，请仔细检查后重试。';
					}elseif($errcode == '40125' || $errcode == '40001'){
						$errmsg	= '公众号密钥错误，请到公众号后台获取重新输入。';
					}else{
						$errmsg	= '';
					}

					if($errmsg){
						weixin_update_setting('api_disabled', compact('errcode', 'errmsg'), $this->appid);
						wpjam_add_admin_notice(['type'=>'error', 'notice'=>$errmsg]);

						return new WP_Error($errcode, $errmsg);
					}
				}

				return $response;
			}

			$response['expires_in']	= time()+$response['expires_in']-600;

			$this->cache_set('access_token', $response, $response['expires_in']);

			// set_transient('weixin_access_token', $response, $response['expires_in']);
		}

		return $response;
	}

	private function http_request($url, $args=[]){
		$args = wp_parse_args( $args, [
			'need_access_token'	=> true,
			'need_json_encode'	=> true,
			'timeout'			=> 5,
		]);

		if($args['need_access_token']){
			$response = $this->get_access_token();
			if(is_wp_error($response)){
				return $response;
			}else{
				$url = add_query_arg(['access_token'=>$response['access_token']], $url);
				$url = str_replace('%40', '@', $url);  
			}
		}

		unset($args['need_access_token']);

		$response =  WPJAM_API::http_request($url, $args);

		if(is_wp_error($response)){

			$errcode	= $response->get_error_code();

			if($errcode == '40001' || $errcode == '40014' || $errcode == '42001'){
				// 40001 获取access_token时AppSecret错误，或者access_token无效。请开发者认真比对AppSecret的正确性，或查看是否正在为恰当的公众号调用接口
				// 40014 不合法的access_token，请开发者认真比对access_token的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口
				// 42001 access_token超时，请检查access_token的有效期，请参考基础支持-获取access_token中，对access_token的详细机制说明
				$this->cache_delete('access_token');
			}elseif($errcode == '50002'){	
				// 50002 用户受限，可能是违规后接口被封禁
				// $robot_option = wpjam_get_option('weixin-robot');
				// $robot_option['weixin_type'] = -1;
				// update_option('weixin-robot', $robot_option);
			}
		}

		return $response;
	}

	public function cache_get($key){
		return wp_cache_get($this->appid.':'.$key, 'weixin');
	}

	public function cache_set($key, $data, $cache_time=DAY_IN_SECONDS){
		return wp_cache_set($this->appid.':'.$key, $data, 'weixin', $cache_time);
	}

	public function cache_delete($key){
		return wp_cache_delete($this->appid.':'.$key, 'weixin');
	}
}
