<?php
class wechatCallback {
	private $postObj		= '';
	private $fromUsername	= '';
	private $toUsername		= '';
	private $response		= '';
	private $is_encrypt		= false;
	private $wxBizMsgCrypt	= '';

	public function __construct(){
		$encodingAESKey		= weixin_robot_get_setting('weixin_encodingAESKey');
		$this->is_encrypt	= (weixin_robot_get_setting('weixin_message_mode')>1 && $encodingAESKey && empty($_GET['debug']))?true:false;
		
		if($this->is_encrypt){
			include(WEIXIN_ROBOT_PLUGIN_DIR.'reply/wxBizMsgCrypt.php');	//加密接口
			$this->wxBizMsgCrypt = new WXBizMsgCrypt();
		}
	}

	public function valid(){

		if(isset($_GET['debug'])){
			$this->checkSignature();
			$this->responseMsg();
		}else{

			if($this->checkSignature()){
				if(isset($_GET["echostr"])){
					echo $_GET["echostr"];
					exit;	
				}else{
					$this->responseMsg();
				}
			}
		}
	}

	public function responseMsg(){
		$postStr	= file_get_contents('php://input');

		$keyword = '';

		if (isset($_GET['debug']) || !empty($postStr)){	
			if(isset($_GET['debug'])){
				$this->fromUsername = $this->toUsername = '';
				$keyword = strtolower(trim($_GET['t']));
			}else{
				$postStr	= $this->decryptMsg($postStr);
				$postStr	= wpjam_strip_control_characters($postStr);

				// file_put_contents(WP_CONTENT_DIR.'/debug/test.log',var_export($postStr,true),FILE_APPEND);

				$postObj	= @simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

				if(!$postObj){
					echo ' ';
					exit;
				}

				// $blog_id = get_current_blog_id();
				// if($blog_id == 42){
					// file_put_contents(WP_CONTENT_DIR.'/debug/test.log',var_export($postObj,true),FILE_APPEND);
				// }

				$this->postObj		= $postObj;
				$this->fromUsername	= (string)$postObj->FromUserName;
				$this->toUsername	= (string)$postObj->ToUserName;
				$msgType = strtolower(trim($postObj->MsgType));

				if($msgType == 'text'){ 				// 文本消息
					$keyword = strtolower(trim($postObj->Content));
				}elseif($msgType == 'event'){			// 事件消息
					$event		= strtolower(trim($postObj->Event));
					$eventKey	= strtolower(trim($postObj->EventKey));
					if($event == 'click'){			// 点击事件
						$keyword = $eventKey;
						// $blog_id = get_current_blog_id();
						// if($blog_id == 26){
						// 	file_put_contents(WP_CONTENT_DIR.'/debug/aaa.log',var_export($postObj,true),FILE_APPEND);
						// }
					}elseif($event == 'subscribe') { 	// 订阅事件
						$keyword = 'subscribe';
					}elseif($event == 'unsubscribe') {	// 取消订阅事件
						$keyword = 'unsubscribe';
					}elseif($event == 'scan') {			// 已关注用户扫描带参数二维码
						$keyword = 'scan';
					}elseif($event == 'location'){		// 高级接口，用户自动提交地理位置事件。
						$keyword = 'event-location';
					}else{
						$keyword = '['.$event.']';		// 其他消息，统一处理成关键字为 [$event] ，后面再做处理。
					}
				}elseif($msgType=='voice'){
					if(isset($postObj->Recognition) && trim($postObj->Recognition)){	// 如果支持语言识别，识别之后的文字作为关键字
						$keyword = strtolower(trim(str_replace('！', '', $postObj->Recognition)));
					}else{
						$keyword = '[voice]';
					}
				}else{		// 其他消息，统一处理成关键字为 [$msgType] ，后面再做处理。
					$keyword = '['.$msgType.']';
				}


			}
			do_action('weixin_reply', $keyword);	// 自定义回复
			do_action('weixin_robot', $this);		// 数据统计
			if(isset($_GET['debug']) && $_GET['debug']='log'){
				wpjam_debug();
			}
		}else {
			echo "";
		}
		exit;
	}

	private function encryptMsg($msg){
		if($this->is_encrypt == false) return $msg;

		$timestamp	= isset($_GET["timestamp"])?$_GET["timestamp"]:'';
		$nonce 		= isset($_GET["nonce"])?$_GET["nonce"]:'';

		$encrypt_msg	= $this->wxBizMsgCrypt->encryptMsg($msg, $timestamp, $nonce);

		if(is_wp_error($encrypt_msg)){
			trigger_error($encrypt_msg->get_error_message());
			echo ' ';
			exit;
		}else{
			return $encrypt_msg;
		}
	}

	private function decryptMsg($msg){
		if($this->is_encrypt == false) return $msg;

		$timestamp		= isset($_GET["timestamp"])?$_GET["timestamp"]:'';
		$nonce 			= isset($_GET["nonce"])?$_GET["nonce"]:'';
		$msg_signature 	= isset($_GET["msg_signature"])?$_GET["msg_signature"]:'';

		$decrypt_msg = $this->wxBizMsgCrypt->decryptMsg($msg_signature, $timestamp, $nonce, $msg);

		if(is_wp_error($decrypt_msg)){
			trigger_error($decrypt_msg->get_error_message()."\n".var_export($msg,true));
			echo ' ';
			exit;
		}else{
			return $decrypt_msg;
		}
	}

	private function checkSignature(){
		$signature	= isset($_GET["signature"])?$_GET["signature"]:'';
		$timestamp	= isset($_GET["timestamp"])?$_GET["timestamp"]:'';
		$nonce 		= isset($_GET["nonce"])?$_GET["nonce"]:'';
		$token		= weixin_robot_get_setting('weixin_token');

		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr,SORT_STRING);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);
		
		if($tmpStr == $signature){
			return true;
		}else{
			return false;
		}
	}

	private function strReplace($str){
		if($weixin_openid = $this->fromUsername){
			$query_id = weixin_robot_get_user_query_id($weixin_openid);	
			return str_replace(array("\r\n", '[openid]', '[query_id]'), array("\n", $weixin_openid, $query_id), $str);
		}
		return $str;
	}

	public function get_item($title, $description, $picUrl, $url){
		if(!$description) $description = $title;

		return
		'
		<item>
			<Title><![CDATA['.html_entity_decode($title, ENT_QUOTES, "utf-8" ).']]></Title>
			<Description><![CDATA['.html_entity_decode($description, ENT_QUOTES, "utf-8" ).']]></Description>
			<PicUrl><![CDATA['.$picUrl.']]></PicUrl>
			<Url><![CDATA['.$this->strReplace($url).']]></Url>
		</item>
		';
	}

	public function get_fromUsername(){ // 微信的 USER OpenID
		return $this->fromUsername;
	}

	public function get_weixin_openid(){ // 微信的 USER OpenID
		return $this->fromUsername;
	}

	public function get_response(){
		return $this->response;
	}

	public function get_msgType(){
		$postObj = $this->get_postObj();
		return isset($postObj->MsgType)?strtolower(trim($postObj->MsgType)):'text';
	}

	public function get_postObj(){
		return $this->postObj;
	}

	public function set_response($response, $force=false){
		if(!$this->response || $force){
			$this->response = $response;		
		}
	}

	private function get_basicTpl(){
		return "
				<ToUserName><![CDATA[".$this->fromUsername."]]></ToUserName>
				<FromUserName><![CDATA[".$this->toUsername."]]></FromUserName>
				<CreateTime>".current_time('timestamp',1)."</CreateTime>
		";
	}
	public function get_textTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[text]]></MsgType>
				<Content><![CDATA[%s]]></Content>
			</xml>
		";
	}

	public function textReply($text){
		if(trim($text)){
			$msg = sprintf($this->get_textTpl(), $this->strReplace($text));
			echo $this->encryptMsg($msg);
		}else{
			echo ' ';	// 回复空字符串
		}
	}

	public function get_picTpl(){
		return  "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[news]]></MsgType>
				<Content><![CDATA[]]></Content>
				<ArticleCount>%d</ArticleCount>
				<Articles>
				%s
				</Articles>
			</xml>
		";
	}

	public function picReply($count, $items){
		$msg = sprintf($this->get_picTpl(), $count, $items);
		echo $this->encryptMsg($msg);
	}

	public function get_imageTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[image]]></MsgType>
				<Image>
				<MediaId><![CDATA[%s]]></MediaId>
				</Image>
			</xml>
		";
	}

	public function imageReply($image){
		$msg = sprintf($this->get_imageTpl(), $image);
		echo $this->encryptMsg($msg);
	}

	public function get_voiceTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[voice]]></MsgType>
				<Voice>
				<MediaId><![CDATA[%s]]></MediaId>
				</Voice>
			</xml>
		";
	}

	public function voiceReply($voice){
		$msg = sprintf($this->get_voiceTpl(), $voice);
		echo $this->encryptMsg($msg);
	}

	public function get_videoTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[video]]></MsgType>
				<Video>
				<MediaId><![CDATA[%s]]></MediaId>
				<Title><![CDATA[%s]]></Title>
				<Description><![CDATA[%s]]></Description>
				</Video>
			</xml>
		";
	}

	public function videoReply($video, $title='', $description=''){
		$msg = sprintf($this->get_videoTpl(), $video, $title, $description);
		echo $this->encryptMsg($msg);
	}

	public function get_musicTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[music]]></MsgType>
				<Music>
				<Title><![CDATA[%s]]></Title>
				<Description><![CDATA[%s]]></Description>
				<MusicUrl><![CDATA[%s]]></MusicUrl>
				<HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
				<ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
				</Music>
			</xml>
		";
	}

	public function musicReply($title='', $description='', $music_url='', $hq_music_url='', $thumb_media_id=''){
		$msg = sprintf($this->get_musicTpl(), $title, $description, $music_url, $hq_music_url, $thumb_media_id);
		echo $this->encryptMsg($msg);
	}

	public function transferCustomerServiceReply($KfAccount=''){
		if($KfAccount){
			$msg = "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[transfer_customer_service]]></MsgType>
				<TransInfo>
			        <KfAccount>".$KfAccount."</KfAccount>
			    </TransInfo>
			</xml>
			";
		}else{
			$msg = "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[transfer_customer_service]]></MsgType>
			</xml>
			";
		}

		echo $this->encryptMsg($msg);
	}
}