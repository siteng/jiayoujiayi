<?php
/**
 * 1.第三方回复加密消息给公众平台；
 * 2.第三方收到公众平台发送的消息，验证消息的安全性，并对消息进行解密。
 */
class WXBizMsgCrypt{
	private $encodingAesKey;
	private $appId;
	private $token;
	private $mcrypt;

	/**
	 * 构造函数
	 * @param $token string 公众平台上，开发者设置的token
	 * @param $encodingAesKey string 公众平台上，开发者设置的EncodingAESKey
	 * @param $appId string 公众平台的appId
	 */
	// public function WXBizMsgCrypt($token, $encodingAesKey, $appId){
	public function __construct(){
		$this->token			= weixin_robot_get_setting('weixin_token');
		$this->encodingAesKey	= weixin_robot_get_setting('weixin_encodingAESKey');
		$this->appId			= WEIXIN_APPID;

		$key 					= base64_decode($this->encodingAesKey . "=");
		$this->mcrypt			= new WPJAM_Mcrypt($key, array('algorithm'=>MCRYPT_RIJNDAEL_128,'mode'=>MCRYPT_MODE_CBC,'iv'=>substr($key, 0, 16)));
	}

	/**
	 * 将公众平台回复用户的消息加密打包.
	 * <ol>
	 *    <li>对要发送的消息进行AES-CBC加密</li>
	 *    <li>生成安全签名</li>
	 *    <li>将消息密文和安全签名打包成xml格式</li>
	 * </ol>
	 *
	 * @param $reply_msg string 公众平台待回复用户的消息，xml格式的字符串
	 * @param $timeStamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
	 * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
	 * @param &$encryptMsg string 加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串,
	 *                      当return返回0时有效
	 *
	 * @return int 成功0，失败返回对应的错误码
	 */
	public function encryptMsg($reply_msg, $timestamp, $nonce){
		try {
			//获得16位随机字符串，填充到明文之前
			$random = wp_generate_password($length = 16, $special_chars = false);
			$text	= $random . pack("N", strlen($reply_msg)) . $reply_msg . $this->appId;
			
			//使用自定义的填充方式对明文进行补位填充
			$text = $this->encode($text);

			//加密
			$encrypt_msg = $this->mcrypt->encrypt($text);
		} catch (Exception $e) {
			//print $e;
			return new WP_Error('EncryptAESError', 'aes 加密失败');
		}

		$timestamp	= ($timestamp)?$timestamp:time();

		//生成安全签名
		$signature = $this->generateSignature($timestamp, $nonce, $encrypt_msg);
		if(is_wp_error($signature)){
			return $signature;
		}

		//生成发送的xml
		return $this->generateXML($encrypt_msg, $signature, $timestamp, $nonce);
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
	public function decryptMsg($msg_signature, $timestamp = null, $nonce, $msg){
		if (strlen($this->encodingAesKey) != 43) {
			return new WP_Error('IllegalAesKey', 'encodingAesKey 非法');
		}

		// 提取密文
		$array = $this->extractXML($msg);
		if(is_wp_error($array)){
			return $array;
		}

		$encrypt_msg	= $array[0];
		$touser_name 	= $array[1];

		$timestamp		= ($timestamp)?$timestamp:time();

		//验证安全签名
		$signature = $this->generateSignature($timestamp, $nonce, $encrypt_msg);
		if(is_wp_error($signature)){
			return $signature;
		}

		if ($signature != $msg_signature) {
			return new WP_Error('ValidateSignatureError', '签名验证错误');
		}

		try {
			$decrypted = $this->mcrypt->decrypt($encrypt_msg);
		} catch (Exception $e) {
			return new WP_Error('DecryptAESError', 'aes 解密失败');
		}

		try {
			//去除补位字符
			$result = $this->decode($decrypted);

			//去除16位随机字符串,网络字节序和AppId
			if (strlen($result) < 16)	return "";

			$content		= substr($result, 16, strlen($result));
			$len_list		= unpack("N", substr($content, 0, 4));
			$xml_len		= $len_list[1];
			$decrypt_msg	= substr($content, 4, $xml_len);
			$from_appid		= substr($content, $xml_len + 4);
		} catch (Exception $e) {
			//print $e;
			return new WP_Error('IllegalBuffer', '解密后得到的buffer非法');
		}
		if ($from_appid != $this->appId){
			return new WP_Error('ValidateAppidError', 'Appid 校验错误');
		}
		// return $xml_content;

		// if(is_wp_error($decrypted_msg)){
		// 	return $decrypted_msg;
		// }

		return $decrypt_msg;
		
	}

	/**
	 * 用SHA1算法生成安全签名
	 * @param string $token 票据
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机字符串
	 * @param string $encrypt 密文消息
	 */
	public function generateSignature($timestamp, $nonce, $encrypt_msg){
		//排序
		try {
			$array = array($encrypt_msg, $this->token, $timestamp, $nonce);
			sort($array, SORT_STRING);
			$str = implode($array);
			return sha1($str);
		} catch (Exception $e) {
			//print $e . "\n";
			return new WP_Error('ComputeSignatureError', 'sha加密生成签名失败');
		}
	}

	/**
	 * 提取出xml数据包中的加密消息
	 * @param string $xmltext 待提取的xml字符串
	 * @return string 提取出的加密消息字符串
	 */
	public function extractXML($xmltext){
		try {
			$xml = new DOMDocument();
			$xml->loadXML($xmltext);
			$array_e	= $xml->getElementsByTagName('Encrypt');
			$array_a	= $xml->getElementsByTagName('ToUserName');
			$encrypt	= $array_e->item(0)->nodeValue;
			$tousername	= $array_a->item(0)->nodeValue;
			return array($encrypt, $tousername);
		} catch (Exception $e) {
			//print $e . "\n";
			return new WP_Error('ParseXmlError', 'XML解析失败');
		}
	}

	/**
	 * 生成xml消息
	 * @param string $encrypt 加密后的消息密文
	 * @param string $signature 安全签名
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机字符串
	 */
	public function generateXML($encrypt, $signature, $timestamp, $nonce){
		$format = "<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>";
		return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
	}

	/**
	 * 对需要加密的明文进行填充补位
	 * @param $text 需要进行填充补位操作的明文
	 * @return 补齐明文字符串
	 */
	function encode($text){
		//计算需要填充的位数
		$amount_to_pad	= 32 - (strlen($text) % 32);
		$amount_to_pad	= ($amount_to_pad)?$amount_to_pad:32;
		
		//获得补位所用的字符
		$pad_chr = chr($amount_to_pad);
		
		return $text . str_repeat($pad_chr, $amount_to_pad);
	}

	/**
	 * 对解密后的明文进行补位删除
	 * @param decrypted 解密后的明文
	 * @return 删除填充补位后的明文
	 */
	function decode($text){
		$pad	= ord(substr($text, -1));
		$pad	= ($pad >= 1 && $pad <= 32)?$pad:0;
		return substr($text, 0, (strlen($text) - $pad));
	}
}

