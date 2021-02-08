<?php
/**
 * 1.第三方回复加密消息给公众平台；
 * 2.第三方收到公众平台发送的消息，验证消息的安全性，并对消息进行解密。
 */
class WEIXIN_Reply{
	private $appid;
	private $crypt;
	private $token;

	private $message 	= null;
	private $keyword	= '';
	private $openid		= '';
	private $tpl		= '';
	private $response	= '';

	public function __construct($appid, $token, $encodingAESKey=''){
		$this->appid	= $appid;
		$this->token	= $token;
		
		if($encodingAESKey){
			$key	= base64_decode($encodingAESKey."=");

			$this->crypt	= new WPJAM_Crypt([
				'method'		=> 'aes-256-cbc', 
				'options'		=> OPENSSL_ZERO_PADDING, 
				'block_size'	=> 32, 
				'key'			=> $key, 
				'iv'			=> substr($key, 0, 16),
			]);
		}
	}

	public function response_msg(){
		if($msg_input = file_get_contents('php://input')){
			$msg_input	= $this->decrypt($msg_input);

			if(is_wp_error($msg_input)){
				return $msg_input;
			}

			$msg_input	= wpjam_strip_control_characters($msg_input);	// 去掉控制字符

			libxml_disable_entity_loader(true);
			$message	= @simplexml_load_string($msg_input, 'SimpleXMLElement', LIBXML_NOCDATA);

			if(!$message){
				return new WP_Error('empty_weixin_message', '微信消息XML为空');
			}

			// $message	= (array)$message;
			// $message	= map_deep($message,'strval');

			$this->message	= $message = json_decode(json_encode((array)$message), true);

			$type = strtolower(trim($message['MsgType']));

			if($type == 'text'){ 				// 文本消息
				$keyword = strtolower(trim($message['Content']));
				if($keyword == '【收到不支持的消息类型，暂无法显示】'){
					$keyword	= '[emotion]';
				}
			}elseif($type == 'event'){			// 事件消息
				$event		= strtolower(trim($message['Event']));
				if($event == 'click'){			// 点击事件
					$keyword	= strtolower(trim($message['EventKey']));
				}elseif($event == 'subscribe' || $event == 'unsubscribe' || $event == 'scan') { 	// 订阅事件，取消订阅事件，已关注用户扫描带参数二维码
					$keyword	= $event;
				}elseif($event == 'location'){		// 高级接口，用户自动提交地理位置事件。
					$keyword	= 'event-location';
				}else{
					$keyword	= '['.$event.']';	// 其他消息，统一处理成关键字为 [$event] ，后面再做处理。
				}
			}elseif($type=='voice'){
				if(!empty($message['Recognition']) && trim($message['Recognition'])){	// 如果支持语言识别，识别之后的文字作为关键字
					$keyword = strtolower(trim(str_replace(['！','？','。'], '', $message['Recognition'])));
				}else{
					$keyword = '[voice]';
				}
			}else{		// 其他消息，统一处理成关键字为 [$type] ，后面再做处理。
				$keyword = '['.$type.']';
			}

			$this->keyword	= $keyword;
			$this->openid	= wpjam_get_parameter('openid');
			$this->tpl		= "
			<ToUserName><![CDATA[".$message['FromUserName']."]]></ToUserName>
			<FromUserName><![CDATA[".$message['ToUserName']."]]></FromUserName>
			<CreateTime>".time()."</CreateTime>
			";
		}

		$this->context_reply()	|| 
		$this->custom_reply()	|| 
		$this->builtin_reply()	|| 
		$this->query_reply();
	}

	public function verify_msg(){
		$timestamp	= wpjam_get_parameter('timestamp');
		$nonce		= wpjam_get_parameter('nonce');
		$signature	= wpjam_get_parameter('signature');

		if(WPJAM_Crypt::generate_weixin_signature($this->token, $timestamp, $nonce) == $signature){
			if($echostr = wpjam_get_parameter('echostr')){
				return $echostr;
			}else{
				return true;
			}
		}else{
			return false;
		}
	}

	private function context_reply(){
		$type	= $this->message['MsgType'];

		if($type != 'text') {
			if($type == 'event'){
				$event	= strtolower($this->message['Event']);
				
				if($event == 'view' || $event == 'click'){
					$this->delete_context_reply();
				}
			}

			return false;
		}

		$context_keyword	= $this->get_context_reply();

		if($context_keyword === false){
			return false;
		}

		if(is_callable($context_keyword)){
			trigger_error('上下文旧方法：'. $context_keyword);

			$this->set_context_reply($context_keyword);

			call_user_func($context_keyword, $this->keyword);

			return true;
		}elseif(is_string($context_keyword)){
			$builtin_reply	= WEIXIN_ReplySetting::get_builtin_reply($context_keyword);

			if($builtin_reply && isset($builtin_reply['callback'])){
				$this->set_context_reply($builtin_reply['keyword']);	// 每次使用，自动续命 60 秒

				$result	= call_user_func($builtin_reply['callback'], $this->keyword, $this);

				if(isset($builtin_reply['response'])){
					$this->set_response($builtin_reply['response']);
				}

				if(is_wp_error($result)){
					trigger_error(var_export($result, true));
					return false;
				}elseif($result === false){
					return false;
				}else{
					return true;
				}
			}
		}else{
			return false;
		}	
	}

	public function get_context_key(){
		return 'context_reply:'.$this->appid.':'.$this->openid;
	}

	public function set_context_reply($keyword, $expire_in=600){
		return WEIXIN_ReplySetting::cache_set($this->get_context_key(), $keyword, $expire_in);
	}

	public function get_context_reply(){
		return WEIXIN_ReplySetting::cache_get($this->get_context_key());
	}

	public function delete_context_reply(){
		return WEIXIN_ReplySetting::cache_delete($this->get_context_key());
	}

	public function custom_reply($keyword=''){
		$keyword	= $keyword ?: $this->keyword;

		$custom_reply	= WEIXIN_ReplySetting::get_custom_reply($keyword);

		if(empty($custom_reply)){
			return false;
		}

		$reply	= str_replace("\r\n", "\n", maybe_unserialize($custom_reply['reply']));
		$type	= $custom_reply['type'];

		if($type == 'text'){		// 文本回复
			$this->set_response('custom-text');
			$this->text_reply($reply);
		}elseif($type == 'img'){	// 文章图文回复
			$post_ids	= explode(',', $reply);
			$this->wp_query_reply([
				'post__in'		=> $post_ids,
				'orderby'		=> 'post__in',
				'posts_per_page'=> count($post_ids), 
				'post_type'		=> 'any'
			]);
			$this->set_response('custom-img');
		}elseif($type == 'img2'){	// 自定义图文回复
			$items	= '';

			if(is_array($reply)){
				$items .= $this->get_item($reply['title'], $reply['description'], $reply['pic_url'], $reply['url']);
			}else{
				$lines	= explode("\n", $reply);
				
				if(isset($lines[0]) && isset($lines[1]) && isset($lines[2]) && isset($lines[3])){
					$items .= $this->get_item($lines[0], $lines[1], $lines[2], $lines[3]);
				}else{
					trigger_error($keyword."\n".$reply."\n".'自定义图文不完整');
					return false;
				}
			}

			$this->news_reply($items);
			$this->set_response('custom-img2');
		}elseif($type == 'news'){	// 素材图文回复
			$material	= weixin()->get_material($reply, 'news');
			if(is_wp_error($material)){
				if($material->get_error_code() == '40007'){
					WEIXIN_ReplySetting::update($custom_reply['id'],['status'=>0]);
				}
				
				$this->text_reply('素材图文错误：'.$material->get_error_code().' '.$material->get_error_message());
			}else{
				$items	= '';
				$count	= 0;
				foreach ($material as $news_item) {
					$items	.= $this->get_item($news_item['title'], $news_item['digest'], $news_item['thumb_url'], $news_item['url']);

					$count++;

					if($this->message['MsgType'] != 'event'){
						break;
					}
				}

				$this->news_reply($items, $count);
				$this->set_response('custom-news');
			}
		}elseif($type == '3rd'){	// 第三方回复
			$this->third_reply($reply);
		}elseif($type == 'dkf'){	// 多客服
			$this->transfer_customer_service_reply($reply);
			$this->set_response('dkf');
		}elseif($type == 'image'){	// 图片回复
			$this->image_reply($reply);
			$this->set_response('custom-image');
		}elseif($type == 'voice'){	// 语音回复
			$this->set_response('custom-voice');
			$this->voice_reply($reply);
		}elseif($type == 'music'){	// 音乐回复
		 	$this->set_response('custom-music');
			$raw_items 		= explode("\n", $reply);
			$title 			= $raw_items[0] ?? '';
			$description	= $raw_items[1] ?? '';
			$music_url		= $raw_items[2] ?? '';
			$hq_music_url	= $raw_items[3] ?? '';
			$thumb_media_id	= $raw_items[4] ?? '';
			$this->music_reply($title, $description, $music_url, $hq_music_url, $thumb_media_id);
		}elseif($type == 'video'){	// 视频回复
			$this->set_response('custom-video');
			$raw_items 	= explode("\n", $reply);
			$MediaId	= $raw_items[0];
			$title 		= $raw_items[1] ?? '';
			$description= $raw_items[2] ?? '';
			$this->video_reply($MediaId, $title, $description);
		}elseif($type == 'function'){	// 函数回复
			if(is_callable($reply)){
				call_user_func($reply, $keyword, $this);	
			}else{
				echo ' ';
			}
		}elseif($type == 'wxcard'){
			$this->set_response('wxcard');
			$raw_items 	= explode("\n", $reply);
			$card_id	= isset($raw_items[0])?$raw_items[0]:'';
			$outer_id	= isset($raw_items[1])?$raw_items[1]:'';
			$code		= isset($raw_items[2])?$raw_items[2]:'';
			$openid		= isset($raw_items[3])?$raw_items[3]:'';

			$card_ext	= weixin_robot_generate_card_ext(compact('card_id','outer_id','code','openid'));
			$wxcard		= compact('card_id','card_ext');

			$response 	= weixin()->send_custom_message([
				'touser'	=>$this->openid,
				'msgtype'	=>'wxcard',
				'wxcard'	=>compact('card_id','card_ext')
			]);

			echo ' ';
		}

		return true;
	}	

	public function builtin_reply($keyword=''){
		$keyword	= $keyword ?: $this->keyword;

		$builtin_reply	= WEIXIN_ReplySetting::get_builtin_reply($keyword);

		if(empty($builtin_reply)){
			return false;
		}

		if(isset($builtin_reply['callback']) && is_callable($builtin_reply['callback'])){
			return call_user_func($builtin_reply['callback'], $keyword, $this);
		}elseif(isset($builtin_reply['method'])){
			return call_user_func([$this, $builtin_reply['method']], $keyword);
		}elseif(isset($builtin_reply['function'])){
			trigger_error('使用 function 的 builtin_reply：'.$keyword);
			call_user_func($builtin_reply['function'], $keyword);
		}else{
			echo ' ';
		}
		
		return true;
	}

	public function query_reply($keyword=''){
		$keyword	= $keyword ?: $this->keyword;

		if(apply_filters('weixin_custom_keyword', false, $keyword)){
			return true;
		}

		if($queries = WEIXIN_ReplySetting::get_queries()){
			foreach ($queries as $name => $callback) {
				if(call_user_func($callback, $keyword, $this)){
					return true;
				}
			}
		}

		// 检测关键字是不是太长了
		$keyword_length	= mb_strwidth(preg_replace('/[\x00-\x7F]/','',$keyword),'utf-8')+str_word_count($keyword)*2;
		$allow_length	= weixin_get_setting('weixin_keyword_length');

		if($keyword_length > $allow_length){
			return $this->too_long_reply();
		}

		if(weixin_get_setting('weixin_3rd_search')){ // 如果使用第三方搜索，跳转到第三方
			return $this->third_reply();
		}
		
		if(weixin_get_setting('weixin_search')){	// 如果支持搜索日志
			// 搜索日志
			$message	= $this->get_message();
			if($this->wp_query_reply(['s'=>$message['Content']])){
				return true;
			}else{
				return $this->not_found_reply($keyword);
			}
		}else{
			return $this->not_found_reply($keyword);
		}
	}

	public function default_reply($keyword){
		if(!$this->custom_reply($keyword)) {
			$this->text_reply(WEIXIN_ReplySetting::get_default_reply($keyword));
		}

		return true;
	}

	public function subscribe_reply($keyword, $reply_required=true){
		WEIXIN_User::subscribe($this->openid);
		

		$subscribe_keyword = '[subscribe]';

		if(weixin_get_type() == 4 && !empty($this->message['EventKey'])){	// 如果是认证服务号，并且是带参数二维码
			$scene	= str_replace('qrscene_','',$this->message['EventKey']);

			$subscribe_keyword = '[subscribe_'.$scene.']';
			
			do_action('weixin_user_subscribe', $this->openid, $scene);
		}
		
		if($reply_required){
			if($this->custom_reply($subscribe_keyword) == false && $this->builtin_reply($subscribe_keyword) == false){
				$this->default_reply('[subscribe]');
				$this->set_response('subscribe');
			}
		}

		return true;
	}

	// 带参数二维码扫描回复
	public function scan_reply($keyword, $reply_required=true){
		$scan_keyword		= '[scan]';
		$subscribe_keyword	= '[subscribe]';
		
		if(weixin_get_type() == 4 && !empty($this->message['EventKey'])){
			$scene	= $this->message['EventKey'];
			$scan_keyword		= '[scan_'.$scene.']';
			$subscribe_keyword	= '[subscribe_'.$scene.']';

			do_action('weixin_user_scan', $this->openid, $scene);
		}

		if($reply_required){
			if(	$this->custom_reply($scan_keyword) == false && 
				$this->custom_reply($subscribe_keyword) == false && 
				$this->builtin_reply($scan_keyword) == false && 
				$this->builtin_reply($subscribe_keyword) == false && 
				$this->custom_reply('[scan]') == false
			){
				$this->default_reply('[subscribe]');
			}
		}

		return true;
	}

	// 取消订阅回复
	public function unsubscribe_reply($keyword){
		WEIXIN_User::unsubscribe($this->openid);
		echo ' ';

		return true;
	}

	// 服务号高级接口用户自动上传地理位置时的回复
	private function location_event_reply($keyword){
		$last_enter_reply	= WEIXIN_ReplySetting::cache_get('enter_reply:'.$this->appid.':'.$this->openid);
		$last_enter_reply	= ($last_enter_reply)?$last_enter_reply:0;

		if(time() - $last_enter_reply > apply_filters('weixin_enter_time',HOUR_IN_SECONDS*8))  {
			$this->default_reply('[event-location]');
			WEIXIN_ReplySetting::cache_set('enter_reply:'.$this->appid.':'.$this->openid, time(), HOUR_IN_SECONDS);
		}

		return true;
	}

	private function verify_reply($keyword){
		$message = $this->message;

		if($keyword == '[qualification_verify_success]' || $keyword == '[naming_verify_success]' || $keyword == '[annual_renew]' || $keyword == '[verify_expired]'){
			$time	= (string)$message['ExpiredTime'];
			$time	= get_date_from_gmt(date('Y-m-d H:i:s',$time));

			if($keyword == '[qualification_verify_success]'){
				$type 	= 'success';
				$notice	= '资质认证成功，你已经获取了接口权限，下次认证时间：'.$time.'！';	
			}elseif($keyword == '[naming_verify_success]'){
				$type 	= 'success';
				$notice	= '名称认证成功，下次认证时间：'.$time.'！';	
			}elseif($keyword == '[annual_renew]'){
				$type 	= 'warning';
				$notice	= '你的账号需要年审了，到期时间：'.$time.'！';	
			}elseif($keyword == '[verify_expired]'){
				$type 	= 'error';
				$notice	= '你的账号认证过期了，过期时间：'.$time.'！';
				$type 	= 'error';
			}
		}else{
			$time	= (string)$message['FailTime'];
			$time	= get_date_from_gmt(date('Y-m-d H:i:s',$time));
			$reason	= (string)$message['FailReason'];
			$type = 'error';

			if($keyword == '[qualification_verify_fail]'){
				$type 	= 'error';
				$notice	= '资质认证失败，时间：'.$time.'，原因：'.$reason.'！';	
			}elseif($keyword == '[naming_verify_fail]'){
				$type 	= 'error';
				$notice	= '名称认证失败，时间：'.$time.'，原因：'.$reason.'！';	
			}
		}

		WPJAM_Notice::add(['type'=>$type, 'notice'=>$notice]);

		echo ' ';

		return true;
	}

	// 找不到内容时回复
	public function not_found_reply($keyword){
		$this->default_reply('[default]');

		if($this->get_response() != 'third' && $this->get_response() != 'function' ){
			$this->set_response('not-found');
		}

		return true;
	}

	// 关键字太长回复
	public function too_long_reply(){
		$this->default_reply('[too-long]');
		
		if($this->get_response() != '3rd' && $this->get_response() != 'function' ){
			$this->set_response('too-long');
		}

		return true;
	}

	// 文章搜索回复
	public function wp_query_reply($args=''){
		// 获取除 page 和 attachmet 之外的所有日志类型
		$post_types	= get_post_types(['exclude_from_search'=>false]);

		unset($post_types['page']);
		unset($post_types['attachment']);

		$args	= wp_parse_args($args, [
			'ignore_sticky_posts'	=> true,
			'posts_per_page'		=> 5,
			'post_status'			=> 'publish',
			'post_type'				=> $post_types
		]);

		$weixin_text_search	= weixin_get_setting('weixin_text_search');
		$weixin_search_url	= weixin_get_setting('weixin_search_url');

		if(!empty($args['s']) && $weixin_search_url){
			$search_term	= $args['s'];

			if($term_id = term_exists($search_term)){
				if($term = get_term($term_id)){
					unset($args['s']);

					$this->set_response('query');

					if($weixin_text_search){
						$this->text_reply('<a href="'.get_term_link($term).'">'.'『'.$search_term.'』已找到，点击查看</a>。');
					}else{
						$thumb	= wpjam_get_term_thumbnail_url(null, [120,120]);
						if(empty($thumb)){
							$thumb	= wpjam_get_default_thumbnail_url([120,120]);
						}
						$this->news_reply($this->get_item($term->name, $term->description, $thumb, get_term_link($term)));	
					}

					return true;
				}
			}

			$search_url		= get_search_link($search_term);
		}

		$msgtype	= $this->message['MsgType'];

		global $wp_the_query;

		$args	= apply_filters('weixin_query', $args);

		$wp_the_query->query($args);

		$items	= '';
		
		if($wp_the_query->have_posts()){
			$found_posts	= $wp_the_query->found_posts;

			if($msgtype == 'event' && $found_posts > 1){
				$size	= [720, 300];
				$count	= $found_posts > 5 ? 5 : $found_posts;
			}else{
				$size	= [120,120];
				$count	= 1;
			}

			while ($wp_the_query->have_posts()){
				$wp_the_query->the_post();

				if($msgtype != 'event' && $found_posts > 1 && !empty($search_url)){
					if($weixin_text_search){
						$this->set_response('query');
						$this->text_reply('<a href="'.$search_url.'">『'.$search_term.'』已找到，点击查看</a>。');
						return true;
					}

					$url	= $search_url;	
				}else{
					$url	= get_permalink();
				}

				if(strpos($url, '?') === false){
					$url	.= '?';
				}

				$title		= wp_strip_all_tags(get_the_title(), true); 

				if(!$weixin_text_search || $msgtype == 'event'){
					$excerpt	= get_the_excerpt();
					$thumb		= wpjam_get_post_thumbnail_url(null, $size);
					$size		= [120,120];

					$items		= $items.$this->get_item($title, $excerpt, $thumb, $url);
				}

				if($msgtype != 'event'){
					break;
				}
			}

			$this->set_response('query');

			if($weixin_text_search && $msgtype != 'event'){
				$this->text_reply('点击查看<a href="'.$url.'">『'.$title.'』</a>。');
			}else{
				$this->news_reply($items, $count);
			}

			return true;
		}

		return false;
	}

	public function third_reply($no=1){
		$third_cache	= weixin_get_setting('weixin_3rd_cache_'.$no);
		$third_url		= weixin_get_setting('weixin_3rd_url_'.$no);

		$type	= $this->message['MsgType'];
		$third_response = false;

		if($type == 'text'){
			$keyword	= strval($this->message['Content']);

			if($keyword && $third_cache){
				$third_response	= WEIXIN_ReplySetting::cache_get('third_reply:'.$this->appid.':'.$keyword);;
			}
		}

		if($third_response === false){
			$third_url	= add_query_arg($_GET,$third_url);
			$args		= [ 
				'headers' 	=> ['Content-Type'=>'text/xml', 'Accept-Encoding'=>''],
				'body'		=> file_get_contents('php://input'),
				'need_json_decode'	=> false
			];

			$third_response	= wpjam_remote_request($third_url, $args);

			if(is_wp_error($third_response)){
				$third_response = ' ';
			}else{
				if(($type == 'text') && $keyword && $third_cache){
					WEIXIN_ReplySetting::cache_set('third_reply:'.$this->appid.':'.$keyword, $third_response, $third_cache);
				}
			}
		}

		echo $third_response;
		$this->set_response('3rd');

		return true;
	}

	private function openid_replace($str){
		if($openid = $this->openid){
			return str_replace(["\r\n", '[openid]', '[weixin_access_token]'], ["\n", $openid, WEIXIN_User::generate_access_token($openid)], $str);
		}
		return $str;
	}

	public function get_weixin_openid(){ // 微信的 USER OpenID
		return $this->openid;
	}

	public function get_openid(){
		return $this->openid;
	}

	public function set_openid($openid){
		$this->openid	= $openid;
	}

	public function get_response(){
		return $this->response;
	}

	public function set_response($response){
		$this->response = $response;
	}

	public function get_keyword($keyword){
		return $this->keyword;
	}

	public function set_keyword($keyword){
		$this->keyword = $keyword;
	}

	public function get_message(){
		return $this->message;
	}

	public function textReply($text){
		$this->text_reply($text);
	}

	public function text_reply($text){
		if(is_array($text)){
			trigger_error(var_export($text, true));
		}
		if(trim($text)){
			if($text_reply_append = weixin_get_setting('weixin_text_reply_append')){
				$text .= "\n\n".$text_reply_append;
			}

			echo $this->encrypt("
				<xml>".$this->tpl."
					<MsgType><![CDATA[text]]></MsgType>
					<Content><![CDATA[".$this->openid_replace($text)."]]></Content>
				</xml>
			");
		}else{
			echo ' ';	// 回复空字符串
		}
	}

	public function get_item($title, $description, $pic_url, $url){
		if(!$description) $description = $title;

		return
		'
		<item>
			<Title><![CDATA['.html_entity_decode($title, ENT_QUOTES, "utf-8" ).']]></Title>
			<Description><![CDATA['.html_entity_decode($description, ENT_QUOTES, "utf-8" ).']]></Description>
			<PicUrl><![CDATA['.$pic_url.']]></PicUrl>
			<Url><![CDATA['.$this->openid_replace($url).']]></Url>
		</item>
		';
	}

	public function news_reply($items, $count=1){
		echo $this->encrypt( "
			<xml>".$this->tpl."
				<MsgType><![CDATA[news]]></MsgType>
				<Content><![CDATA[]]></Content>
				<ArticleCount>".$count."</ArticleCount>
				<Articles>
				".$items."
				</Articles>
			</xml>
		");
	}

	public function image_reply($media_id){
		echo $this->encrypt("
			<xml>".$this->tpl."
				<MsgType><![CDATA[image]]></MsgType>
				<Image>
				<MediaId><![CDATA[".$media_id."]]></MediaId>
				</Image>
			</xml>
		");
	}

	public function voice_reply($media_id){
		echo $this->encrypt("
			<xml>".$this->tpl."
				<MsgType><![CDATA[voice]]></MsgType>
				<Voice>
				<MediaId><![CDATA[".$media_id."]]></MediaId>
				</Voice>
			</xml>
		");
	}

	public function video_reply($video, $title='', $description=''){
		echo $this->encrypt("
			<xml>".$this->tpl."
				<MsgType><![CDATA[video]]></MsgType>
				<Video>
				<MediaId><![CDATA[".$video."]]></MediaId>
				<Title><![CDATA[".$title."]]></Title>
				<Description><![CDATA[".$description."]]></Description>
				</Video>
			</xml>
		");
	}

	public function music_reply($title='', $description='', $music_url='', $hq_music_url='', $thumb_media_id=''){
		echo $this->encrypt("
			<xml>".$this->tpl."
				<MsgType><![CDATA[music]]></MsgType>
				<Music>
				<Title><![CDATA[".$title."]]></Title>
				<Description><![CDATA[".$description."]]></Description>
				<MusicUrl><![CDATA[".$music_url."]]></MusicUrl>
				<HQMusicUrl><![CDATA[".$hq_music_url."]]></HQMusicUrl>
				<ThumbMediaId><![CDATA[".$thumb_media_id."]]></ThumbMediaId>
				</Music>
			</xml>
		");
	}

	public function transfer_customer_service_reply($KfAccount=''){
		if($KfAccount){
			$msg = "
			<xml>".$this->tpl."
				<MsgType><![CDATA[transfer_customer_service]]></MsgType>
				<TransInfo>
			        <KfAccount>".$KfAccount."</KfAccount>
			    </TransInfo>
			</xml>
			";
		}else{
			$msg = "
			<xml>".$this->tpl."
				<MsgType><![CDATA[transfer_customer_service]]></MsgType>
			</xml>
			";
		}

		echo $this->encrypt($msg);
	}

	/**
	 * 将公众平台回复用户的消息加密打包.
	 * <ol>
	 *    <li>对要发送的消息进行AES-CBC加密</li>
	 *    <li>生成安全签名</li>
	 *    <li>将消息密文和安全签名打包成xml格式</li>
	 * </ol>
	 *
	 * @param $text string 公众平台待回复用户的消息，xml格式的字符串
	 * @param $timeStamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
	 * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
	 * @param &$encrypted string 加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串,
	 *                      当return返回0时有效
	 *
	 * @return int 成功0，失败返回对应的错误码
	 */
	public function encrypt($text){
		if(!$this->crypt){
			return $text;
		}

		try {
			$text		= $this->crypt->weixin_pad($text, $this->appid);
			$encrypted	= $this->crypt->encrypt($text);	//加密
		} catch (Exception $e) {
			return new WP_Error('encrypt_aes_failed', 'aes 加密失败');
		}

		//生成安全签名
		try {
			$signature	= $this->crypt->generate_weixin_signature($this->token, $timestamp, $nonce, $encrypted);
		} catch (Exception $e) {
			return new WP_Error('compute_signature_failed', 'sha加密生成签名失败');
		}

		//生成发送的xml
		return "
		<xml>
			<Encrypt><![CDATA[".$encrypted."]]></Encrypt>
			<MsgSignature><![CDATA[".$signature."]]></MsgSignature>
			<TimeStamp>".$timestamp."</TimeStamp>
			<Nonce><![CDATA[".$nonce."]]></Nonce>
		</xml>
		";
	}

	/**
	 * 检验消息的真实性，并且获取解密后的明文.
	 * <ol>
	 *    <li>利用收到的密文生成安全签名，进行签名验证</li>
	 *    <li>若验证通过，则提取xml中的加密消息</li>
	 *    <li>对消息进行解密</li>
	 * </ol>
	 *
	 * @param $msgSignature string 签名串，对应URL参数的msg_signature
	 * @param $timestamp string 时间戳 对应URL参数的timestamp
	 * @param $nonce string 随机串，对应URL参数的nonce
	 * @param $postData string 密文，对应POST请求的数据
	 * @param &$msg string 解密后的原文，当return返回0时有效
	 *
	 * @return int 成功0，失败返回对应的错误码
	 */
	public function decrypt($msg){
		if(!$this->crypt){
			return $msg;
		}

		if(strpos($msg, '<Encrypt>') === false){
			return new WP_Error('invaild_encrypt_xml', '非法加密 XML'); 
		}

		// 提取出xml数据包中的加密消息
		try {
			$xml = new DOMDocument();
			$xml->loadXML($msg);
			$encrypt_array	= $xml->getElementsByTagName('Encrypt');
			// $openid_array	= $xml->getElementsByTagName('ToUserName');
			$encrypted	= $encrypt_array->item(0)->nodeValue;
		} catch (Exception $e) {
			return new WP_Error('parse_xml_failed', 'XML 解析失败');
		}

		$timestamp		= wpjam_get_parameter('timestamp');
		$nonce			= wpjam_get_parameter('nonce');
		$msg_signature	= wpjam_get_parameter('msg_signature');

		//验证安全签名
		try{
			$signature = $this->crypt->generate_weixin_signature($this->token, $timestamp, $nonce, $encrypted);
		}catch (Exception $e) {
			return new WP_Error('compute_signature_failed', 'sha加密生成签名失败');
		}

		if($signature != $msg_signature){
			return new WP_Error('validate_signature_error', '签名验证错误');
		}

		try{
			$decrypted = $this->crypt->decrypt($encrypted);
		}catch(Exception $e){
			return new WP_Error('decrypt_aes_failed', 'aes 解密失败');
		}

		try{
			//去除16位随机字符串,网络字节序和AppId
			if (strlen($decrypted) < 16){
				return "";
			}

			$decrypt_msg	= $this->crypt->weixin_unpad($decrypted, $from_appid);
		}catch(Exception $e){
			return new WP_Error('illegal_buffer', '解密后得到的buffer非法');
		}

		if ($from_appid != $this->appid){
			return new WP_Error('validate_appid_error', 'Appid 校验错误');
		}

		return $decrypt_msg;
	}
}

