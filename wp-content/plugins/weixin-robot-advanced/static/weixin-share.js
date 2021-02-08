weixin_share.desc	= weixin_share.desc || weixin_share.link;

/*微信 JS SDK 封装*/
wx.config({
	debug:		weixin_share.debug,
	appId: 		weixin_share.appid,
	timestamp:	weixin_share.timestamp,
	nonceStr:	weixin_share.nonce_str,
	signature:	weixin_share.signature,
	jsApiList:	weixin_share.jsApiList
});

wx.ready(function(){
	wx.updateAppMessageShareData({
		title:	weixin_share.title,
		desc:	weixin_share.desc,
		link: 	weixin_share.link,
		imgUrl:	weixin_share.img,
		success: function(res){
			console.log(res);
		}
	});

	wx.updateTimelineShareData({
		title:	weixin_share.title,
		link: 	weixin_share.link,
		imgUrl:	weixin_share.img,
		success: function(res){
			console.log(res);
		}
	});
});

wx.error(function(res){
});
