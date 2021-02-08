<?php
class WPJAM_Basic{
	use WPJAM_Setting_Trait;

	private $extends	= [];

	private function __construct(){
		$this->init('wpjam-basic', true);

		$this->extends	= get_option('wpjam-extends');
		$this->extends	= $this->extends ? array_filter($this->extends) : [];

		if(is_multisite()){
			$sitewide_extends	= get_site_option('wpjam-extends');
			$sitewide_extends	= $sitewide_extends ? array_filter($sitewide_extends) : [];

			if($sitewide_extends){
				$this->extends	= array_merge($this->extends, $sitewide_extends);
			}
		}
	}

	public function get_setting($name){
		$value	= $this->settings[$name] ?? null;

		if($value){
			if($name == 'disable_rest_api'){
				return !empty($settings['disable_post_embed']) && !empty($settings['disable_block_editor']);
			}elseif($name == 'disable_xml_rpc'){
				return !empty($settings['disable_block_editor']);
			}
		}

		return $value;
	}

	public function get_extends(){
		return $this->extends;
	}

	public function has_extend($extend){
		$extend	= rtrim($extend, '.php').'.php';

		return isset($this->extends[$extend]);
	}

	public function get_default_settings(){
		return [
			'disable_revision'			=> 1,
			'disable_trackbacks'		=> 1,
			'disable_emoji'				=> 1,
			'disable_texturize'			=> 1,
			'disable_privacy'			=> 1,

			'remove_head_links'			=> 1,
			'remove_capital_P_dangit'	=> 1,

			'admin_footer'				=> '<span id="footer-thankyou">感谢使用<a href="https://cn.wordpress.org/" target="_blank">WordPress</a>进行创作。</span> | <a href="http://wpjam.com/" title="WordPress JAM" target="_blank">WordPress JAM</a>'
		];
	}

	private static $sub_pages	= [];

	public static function add_sub_page($sub_slug, $args=[]){
		self::$sub_pages[$sub_slug]	= $args;
	}

	public function add_menu_pages(){
		$subs	= [];

		$subs['wpjam-basic']	= ['menu_title'=>'优化设置',	'function'=>'option',	'load_callback'=>[$this, 'load_basic_page']];

		$verified	= WPJAM_Verify::verify();

		if(!$verified){
			$subs['wpjam-verify']	= ['menu_title'=>'扩展管理',	'page_title'=>'验证 WPJAM',	'function'=>'form',	'form_name'=>'verify_wpjam',	'load_callback'=>['WPJAM_Verify', 'page_action']];
		}else{
			$subs		+= self::$sub_pages;
			$subs		= apply_filters('wpjam_basic_sub_pages', $subs);

			$summary	= '系统信息扩展让你在后台就能够快速实时查看当前的系统状态，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-basic-service-status/" target="_blank">系统信息扩展</a>。';
			$capability	= is_multisite() ? 'manage_sites' : 'manage_options';

			$subs['server-status']	= ['menu_title'=>'系统信息',		'function'=>'tab',	'summary'=>$summary,	'capability'=> $capability,	'page_file'=>__DIR__.'/server-status.php'];
			$subs['dashicons']		= ['menu_title'=>'Dashicons',	'function'=>[$this, 'dashicons_page']];
			$subs['wpjam-extends']	= ['menu_title'=>'扩展管理',		'function'=>'option',	'load_callback'	=> [$this, 'load_extends_page']];

			if($verified !== 'verified'){
				$subs['wpjam-basic-topics']	= ['menu_title'=>'讨论组',		'function'=>'tab',	'page_file'=>__DIR__.'/wpjam-topics.php'];
				$subs['wpjam-about']		= ['menu_title'=>'关于WPJAM',	'function'=>[$this, 'about_page']];
			}
		}

		$basic_menu	= [
			'menu_title'	=> 'WPJAM',
			'icon'			=> 'dashicons-performance',
			'position'		=> '58.99',
			'function'		=> 'option',
			'subs'			=> $subs
		];

		if(is_multisite() && is_network_admin()){
			$basic_menu['network']	= true;
		}

		wpjam_add_menu_page('wpjam-basic', $basic_menu);
	}

	public function add_separator(){
		$GLOBALS['menu']['58.88']	= ['',	'read',	'separator'.'58.88', '', 'wp-menu-separator'];
	}

	public function load_basic_page(){
		$disabled_fields	= [
			'disable_revision'		=> [
				'title'			=>'屏蔽文章修订',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-post-revision/">屏蔽文章修订功能，精简 Posts 表数据。</a>'
			],
			'disable_trackbacks'	=> [
				'title'			=>'屏蔽Trackbacks',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/bye-bye-trackbacks/">彻底关闭Trackbacks，防止垃圾留言。</a>'
			],
			'disable_emoji'			=> [
				'title'			=>'屏蔽Emoji图片',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/diable-emoji/">屏蔽 Emoji 功能，直接使用支持Emoji文字。</a>'
			],
			'disable_texturize'		=> [
				'title'			=>'屏蔽字符转码',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-wptexturize/">屏蔽字符换成格式化的 HTML 实体功能。</a>'
			],
			'disable_feed'		=> [
				'title'			=>'屏蔽站点Feed',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-feed/">屏蔽站点Feed，防止文章快速被采集。</a>'
			],
			'disable_admin_email_check'		=> [
				'title'			=>'屏蔽邮箱验证',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-site-admin-email-check/">屏蔽站点管理员邮箱验证功能。</a>'
			],
			'disable_privacy'		=> [
				'title'			=>'屏蔽后台隐私',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/wordpress-remove-gdpr-pages/">移除后台适应欧洲通用数据保护条例而生成相关的页面</a>。'
			],
			'disable_autoembed'		=> [
				'title'			=>'屏蔽Auto Embeds',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-auto-embeds-in-wordpress/">禁用 Auto Embeds 功能，加快页面解析速度。</a>'
			],
			'disable_post_embed'	=> [
				'title'			=>'屏蔽文章Embed',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-wordpress-post-embed/">屏蔽可嵌入其他 WordPress 文章的Embed功能</a>。'
			],
			'disable_block_editor'	=> [
				'title'			=>'屏蔽Gutenberg',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-gutenberg/">屏蔽Gutenberg编辑器，换回经典编辑器</a>。'
			],
			'disable_xml_rpc'		=> [
				'title'			=>'屏蔽XML-RPC',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-xml-rpc/">关闭XML-RPC功能，只在后台发布文章</a>。'
			],
			'disable_rest_api'		=> [
				'title'			=>'屏蔽REST API',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/disable-wordpress-rest-api/">屏蔽REST API功能</a>。WPJAM 出品的小程序没有使用该功能。'
			],
		];

		$speed_fields		= [
			'google_fonts_fieldset'		=> [
				'title'			=>'Google字体加速',
				'type'			=>'fieldset',
				'fields'		=>[
					'google_fonts'	=> [
						'title'			=>'',
						'type'			=>'select',
						'options'		=>[''=>'默认Google提供的服务', 'ustc'=>'中科大Google字体加速服务','custom'=>'自定义Google字体镜像']
					],
					'googleapis_fonts'	=> [
						'title'			=>'',
						'type'			=>'text',
						'show_if'		=>['key'=>'google_fonts','value'=>'custom'],
						'placeholder'	=>'请输入 fonts.googleapis.com 镜像地址',
					],
					'googleapis_ajax'	=> [
						'title'			=>'',
						'type'			=>'text',
						'show_if'		=>['key'=>'google_fonts','value'=>'custom'],
						'placeholder'	=>'请输入 ajax.googleapis.com 镜像地址'
					],
					'googleusercontent_themes'	=> [
						'title'			=>'',
						'type'			=>'text',
						'show_if'		=>['key'=>'google_fonts','value'=>'custom'],
						'placeholder'	=>'请输入 themes.googleusercontent.com 镜像地址'
					],
					'gstatic_fonts'	=> [
						'title'			=>'',
						'type'			=>'text',
						'show_if'		=>['key'=>'google_fonts','value'=>'custom'],
						'placeholder'	=>'请输入 fonts.gstatic.com 镜像地址'
					],
					'disable_google_fonts_4_block_editor'	=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/wordpress-disable-google-font-for-gutenberg/">禁止古腾堡编辑器加载 Google 字体</a>。'
					],
				]
			],
			'gravatar_fieldset'		=> [
				'title'			=>'Gravatar加速',
				'type'			=>'fieldset',
				'fields'		=>[
					'gravatar'				=> [
						'title'			=>'',
						'type'			=>'select',
						'options'		=>[''=>'Gravatar默认服务器', 'v2ex'=>'v2ex镜像加速服务', 'custom'=>'自定义镜像加速服务']
					],
					'gravatar_custom'	=> [
						'title'			=>'',
						'type'			=>'text',
						'show_if'		=>['key'=>'gravatar','value'=>'custom'],
						'placeholder'	=>'请输入 Gravatar 镜像加速服务地址'
					],
				]
			],
			'excerpt_fieldset'		=> [
				'title'			=>'文章摘要优化',
				'type'			=>'fieldset',
				'fields'		=>[
					'excerpt_optimization'	=> [
						'title'			=>'未设摘要：',
						'type'			=>'select',
						'options'		=>[0=>'WordPress 默认方式截取',1=>'按照中文最优方式截取',2=>'直接不显示摘要']
					],
					'excerpt_length'		=> [
						'title'			=>'摘要长度：',
						'type'			=>'number',
						'value'			=>200,
						'description'	=>'<br />中文最优方式是指：<a target="_blank" href="https://blog.wpjam.com/m/get_post_excerpt/">按照中文2个字节，英文1个字节算法从内容中截取</a>。',
						'show_if'		=>['key'=>'excerpt_optimization', 'value'=>1]
					]
				]
			],
			'frontend_optimization'	=> [
				'title'			=>'前端页面优化',
				'type'			=>'fieldset',
				'fields'		=>[
					'locale'				=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/setup-different-admin-and-frontend-language-on-wordpress/">前台不加载语言包，节省加载语言包所需的0.1-0.5秒。</a>'
					],
					'search_optimization'	=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/redirect-to-post-if-search-results-only-returns-one-post/">当搜索结果只有一篇时直接重定向到文章</a>。'
					],
					'404_optimization'	=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/wpjam_redirect_guess_404_permalink/">改进404页面跳转到正确的页面的效率</a>。'
					],
					'remove_head_links'		=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/remove-unnecessary-code-from-wp_head/">移除页面头部中无关紧要的代码</a>。'
					],
					'remove_admin_bar'		=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/remove-wp-3-1-admin-bar/">移除工具栏和后台个人设置页面工具栏有关的选项。</a>'
					],
					'remove_capital_P_dangit'	=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/remove-capital_p_dangit/">移除WordPress大小写修正，让用户自己决定怎么写。</a>'
					],
				]
			],

			'backend_optimization'	=> [
				'title'			=>'后台界面优化',
				'type'			=>'fieldset',
				'fields'		=>[
					'remove_help_tabs'	=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/wordpress-remove-help-tabs/">移除后台界面右上角的帮助。</a>'
					],
					'remove_screen_options'	=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/wordpress-remove-screen-options/">移除后台界面右上角的选项</a>。'
					],
					'no_admin'				=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/no-admin-try/">禁止使用 admin 用户名尝试登录 WordPress</a>。'
					]
				]
			]
		];

		$taxonomy_options	= wp_list_pluck(get_taxonomies(['public'=>true,'hierarchical'=>true], 'objects'), 'label', 'name');

		$enhance_fields		= [
			'optimized_by_wpjam'	=>[
				'title'			=>'由WPJAM优化',
				'type'			=>'checkbox',
				'description'	=>'在网站底部显示：Optimized by WPJAM Basic。'
			],
			'timestamp_file_name'	=> [
				'title'			=>'上传图片加上时间戳',
				'type'			=>'checkbox',
				'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/add-timestamp-2-image-filename/">给上传的图片加上时间戳</a>，防止<a target="_blank" href="https://blog.wpjam.com/m/not-name-1-for-attachment/">大量的SQL查询</a>。'
			],
			'no_category_base_set'	=> [
				'title'			=>'简化分类目录链接',
				'type'			=>'fieldset',
				'fields'		=>[
					'no_category_base'		=> [
						'title'			=>'',
						'type'			=>'checkbox',
						'description'	=>'<a target="_blank" href="https://blog.wpjam.com/m/wordpress-no-category-base/">去掉分类目录链接中的 category 或者自定义分类的 %taxonomy%。</a>'
					],
					'no_category_base_for'	=> [
						'title'			=>'分类模式',
						'type'			=>'select',
						'options'		=>$taxonomy_options,
						'show_if'		=>['key'=>'no_category_base','value'=>1],
					]
				]
			],
			'x-frame-options'	=>[
				'title'			=>'Frame 嵌入支持',
				'type'			=>'select',
				'options'		=>[''=>'所有网页', 'DENY'=>'不允许其他网页嵌入本网页', 'SAMEORIGIN'=>'只能是同源域名下的网页']
			]
		];

		if($GLOBALS['wp_rewrite']->use_verbose_page_rules){
			unset($enhance_fields['no_category_base_set']['fields']);

			$enhance_fields['no_category_base_set']['type']		= 'view';
			$enhance_fields['no_category_base_set']['value']	= '你的固定链接设置不能去掉分类目录链接中的 category 或者自定义分类的 %taxonomy%，请先修改固定链接设置。';
		}

		wpjam_register_option('wpjam-basic', [
			'site_default'		=> true,
			'sanitize_callback'	=> [$this, 'sanitize_callback'],
			'sections'			=> [ 
				'disabled'	=>['title'=>'功能屏蔽',	'fields'=>$disabled_fields],
				'speed'		=>['title'=>'加速优化', 	'fields'=>$speed_fields],
				'enhance'	=>['title'=>'功能增强',	'fields'=>$enhance_fields],
			],
			'summary'			=> '优化设置让你通过关闭一些不常用的功能来加快  WordPress 的加载。<br />但是某些功能的关闭可能会引起一些操作无法执行，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-basic-optimization-setting/" target="_blank">优化设置</a>。',
		]);

		add_action('admin_head', [$this, 'basic_page_head']);
	}

	public function basic_page_head(){
		?>
		<script type="text/javascript">
		jQuery(function ($){
			function wpjam_basic_init(){
				if($('#disable_block_editor').is(':checked') && $('#disable_post_embed').is(':checked')){
					$("#disable_rest_api").attr('disabled', false);
				}else{
					$("#disable_rest_api").attr('disabled', true).attr('checked',false);
				}

				if($('#disable_block_editor').is(':checked')){
					$("#disable_xml_rpc").attr('disabled', false);
				}else{
					$("#disable_xml_rpc").attr('disabled', true).attr('checked',false);
				}
			}

			wpjam_basic_init();

			$('#disable_block_editor').on('change', wpjam_basic_init);
			$('#disable_post_embed').on('change', wpjam_basic_init);
		});
		</script>
		<?php
	}

	public function sanitize_callback($value){
		flush_rewrite_rules();

		return $value;
	}

	public function load_extends_page(){
		$fields		= [];
		$extend_dir = WPJAM_BASIC_PLUGIN_DIR.'extends';

		if(is_dir($extend_dir)) { 
			$wpjam_extends 	= wpjam_get_option('wpjam-extends');

			$file_headers	= [
				'Name'			=> 'Name',
				'URI'			=> 'URI',
				'Version'		=> 'Version',
				'Description'	=> 'Description'
			];

			if($wpjam_extends){	// 已激活的优先
				foreach ($wpjam_extends as $extend_file => $value) {
					if(!$value || !is_file($extend_dir.'/'.$extend_file)){
						continue;
					}

					$data	= get_file_data($extend_dir.'/'.$extend_file, $file_headers);

					if($data['Name']){
						$fields[$extend_file] = ['title'=>'<a href="'.$data['URI'].'" target="_blank">'.$data['Name'].'</a>', 'type'=>'checkbox', 'description'=>$data['Description']];
					}
				}
			}

			if($extend_handle = opendir($extend_dir)) {   
				while (($extend_file = readdir($extend_handle)) !== false) {
					if ($extend_file == '.' || $extend_file == '..' || !is_file($extend_dir.'/'.$extend_file) || !empty($wpjam_extends[$extend_file])){
						continue;
					}

					if(pathinfo($extend_file, PATHINFO_EXTENSION) != 'php') {
						continue;
					}

					$data	= get_file_data($extend_dir.'/'.$extend_file, $file_headers);

					if($data['Name']){
						$fields[$extend_file] = ['title'=>'<a href="'.$data['URI'].'" target="_blank">'.$data['Name'].'</a>', 'type'=>'checkbox', 'description'=>$data['Description']];
					}
				}   
				closedir($extend_handle);   
			}
		} 

		if(is_multisite() && !is_network_admin()){
			$sitewide_extends = get_site_option('wpjam-extends');

			unset($sitewide_extends['plugin_page']);

			if($sitewide_extends){
				foreach ($sitewide_extends as $extend_file => $value) {
					if($value){
						unset($fields[$extend_file]);
					}
				}
			}
		}

		$summary	= is_network_admin() ? '在管理网络激活将整个站点都会激活！' : '';

		wpjam_register_option('wpjam-extends', ['fields'=>$fields, 'summary'=>$summary, 'ajax'=>false]);
	}

	public function dashicons_page(){
		?>
		<p>Dashicons 功能列出所有的 Dashicons 以及每个 Dashicon 的名称和 HTML 代码。<br />详细介绍请查看：<a href="https://blog.wpjam.com/m/wpjam-basic-dashicons/" target="_blank">Dashicons</a>，在 WordPress 后台<a href="https://blog.wpjam.com/m/using-dashicons-in-wordpress-admin/" target="_blank">如何使用 Dashicons</a>。</p>

		<?php
		$dashicon_css_file	= fopen(ABSPATH.'/'.WPINC.'/css/dashicons.css','r') or die("Unable to open file!");

		$i	= 0;

		$dashicons_html = '';

		while(!feof($dashicon_css_file)) {
			$line	= fgets($dashicon_css_file);
			$i++;

			if($i < 32) continue;

			if($line){
				if (preg_match_all('/.dashicons-(.*?):before/i', $line, $matches)) {
					$dashicons_html .= '<p data-dashicon="dashicons-'.$matches[1][0].'"><span class="dashicons-before dashicons-'.$matches[1][0].'"></span> <br />'.$matches[1][0].'</p>'."\n";
				}elseif(preg_match_all('/\/\* (.*?) \*\//i', $line, $matches)){
					if($dashicons_html){
						echo '<div class="wpjam-dashicons">'.$dashicons_html.'</div>'.'<div class="clear"></div>';
					}
					echo '<h2>'.$matches[1][0].'</h2>'."\n";
					$dashicons_html = '';
				}
			}
		}

		echo '<div class="wpjam-dashicons">'.$dashicons_html.'</div>'.'<div class="clear"></div>';

		fclose($dashicon_css_file);
		?>
		<style type="text/css">
		h2{max-width: 800px; margin:40px 0 20px 0; padding-bottom: 20px; clear: both; border-bottom: 1px solid #ccc;}
		div.wpjam-dashicons{max-width: 800px; float: left;}
		div.wpjam-dashicons p{float: left; margin:0px 10px 10px 0; padding: 10px; width:70px; height:70px; text-align: center; cursor: pointer;}
		div.wpjam-dashicons .dashicons-before:before{font-size:32px; width: 32px; height: 32px;}
		div#TB_ajaxContent p{font-size:20px; float: left;}
		div#TB_ajaxContent .dashicons{font-size:100px; width: 100px; height: 100px;}
		</style>
		<script type="text/javascript">
		jQuery(function($){
			$('body').on('click', 'div.wpjam-dashicons p', function(){
				var dashicon = $(this).data('dashicon');
				var dashicon_html = '&lt;span class="dashicons '+dashicon+'"&gt;&lt;/span&gt;';
				$('#tb_modal').html('<p><span class="dashicons '+dashicon+'"></span></p><p style="margin-left:20px;">'+dashicon+'<br /><br />HTML：<br /><code>'+dashicon_html+'</code></p>');
				tb_show(dashicon, '#TB_inline?inlineId=tb_modal&width=700&height=200');
				tb_position();
			});
		});
		</script>
		<?php
	}

	public function get_jam_plugins(){
		$jam_plugins = get_transient('about_jam_plugins');

		if($jam_plugins === false){
			$response	= wpjam_remote_request('http://jam.wpweixin.com/api/template/get.json?id=5644');

			if(!is_wp_error($response)){
				$jam_plugins	= $response['template']['table']['content'];
				set_transient('about_jam_plugins', $jam_plugins, DAY_IN_SECONDS );
			}
		}

		return $jam_plugins;
	}

	public function about_page(){ ?>
		<div style="max-width: 900px;">
			<table id="jam_plugins" class="widefat striped">
				<tbody>
				<tr>
					<th colspan="2">
						<h2>WPJAM 插件</h2>
						<p>加入<a href="http://97866.com/s/zsxq/">「WordPress果酱」知识星球</a>即可下载：</p>
					</th>
				</tr>
				<?php foreach($this->get_jam_plugins() as $jam_plugin){ ?>
				<tr>
					<th style="width: 100px;"><p><strong><a href="<?php echo $jam_plugin['i2']; ?>"><?php echo $jam_plugin['i1']; ?></a></strong></p></th>
					<td><?php echo wpautop($jam_plugin['i3']); ?></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>

			<div class="card">
				<h2>WPJAM Basic</h2>

				<p><strong><a href="http://blog.wpjam.com/project/wpjam-basic/">WPJAM Basic</a></strong> 是 <strong><a href="http://blog.wpjam.com/">我爱水煮鱼</a></strong> 的 Denis 开发的 WordPress 插件。</p>

				<p>WPJAM Basic 除了能够优化你的 WordPress ，也是 「WordPress 果酱」团队进行 WordPress 二次开发的基础。</p>
				<p>为了方便开发，WPJAM Basic 使用了最新的 PHP 7.2 语法，所以要使用该插件，需要你的服务器的 PHP 版本是 7.2 或者更高。</p>
				<p>我们开发所有插件都需要<strong>首先安装</strong> WPJAM Basic，其他功能组件将以扩展的模式整合到 WPJAM Basic 插件一并发布。</p>
			</div>

			<div class="card">
				<h2>WPJAM 优化</h2>
				<p>网站优化首先依托于强劲的服务器支撑，这里强烈建议使用<a href="https://wpjam.com/go/aliyun/">阿里云</a>或<a href="https://wpjam.com/go/qcloud/">腾讯云</a>。</p>
				<p>更详细的 WordPress 优化请参考：<a href="https://blog.wpjam.com/article/wordpress-performance/">WordPress 性能优化：为什么我的博客比你的快</a>。</p>
				<p>我们也提供专业的 <a href="https://blog.wpjam.com/article/wordpress-optimization/">WordPress 性能优化服务</a>。</p>
			</div>
		</div>
		<style type="text/css">
			.card {max-width: 320px; float: left; margin-top:20px;}
			.card a{text-decoration: none;}
			table#jam_plugins{margin-top:20px; width: 520px; float: left; margin-right: 20px;}
			table#jam_plugins th{padding-left: 2em; }
			table#jam_plugins td{padding-right: 2em;}
			table#jam_plugins th p, table#jam_plugins td p{margin: 6px 0;}
		</style>
	<?php }
}

function wpjam_basic_get_setting($name){
	return WPJAM_Basic::get_instance()->get_setting($name);
}

function wpjam_basic_update_setting($name, $value){
	return WPJAM_Basic::get_instance()->update_setting($name, $value);
}

function wpjam_basic_delete_setting($name){
	return WPJAM_Basic::get_instance()->delete_setting($name);
}

function wpjam_basic_get_default_settings(){
	return WPJAM_Basic::get_instance()->get_default_settings();
}

function wpjam_get_extends(){
	return WPJAM_Basic::get_instance()->get_extends();
}

function wpjam_has_extend($extend){
	return WPJAM_Basic::get_instance()->has_extend($extend);
}

function wpjam_add_basic_sub_page($sub_slug, $args=[]){
	WPJAM_Basic::add_sub_page($sub_slug, $args);
}

add_action('plugins_loaded', function(){
	$template_extend_dir	= get_template_directory().'/extends';

	if(is_dir($template_extend_dir)){
		if($extend_handle = opendir($template_extend_dir)) {   
			while (($extend = readdir($extend_handle)) !== false) {
				if ($extend == '.' || $extend == '..' || is_file($template_extend_dir.'/'.$extend)) {
					continue;
				}

				if(is_file($template_extend_dir.'/'.$extend.'/'.$extend.'.php')){
					include $template_extend_dir.'/'.$extend.'/'.$extend.'.php';
				}
			}   
			closedir($extend_handle);   
		}
	}
}, 0);

add_action('wpjam_loaded',	function(){
	$instance	= WPJAM_Basic::get_instance();
	if($extends = $instance->get_extends()){
		foreach(array_keys($extends) as $extend_file){
			if(is_file(WPJAM_BASIC_PLUGIN_DIR.'extends/'.$extend_file)){
				include WPJAM_BASIC_PLUGIN_DIR.'extends/'.$extend_file;
			}
		}
	}

	if(is_admin()){
		add_filter('default_option_wpjam-basic',	[$instance, 'get_default_settings']);

		add_action('wpjam_admin_init',	[$instance, 'add_menu_pages']);
		add_action('admin_menu', 		[$instance, 'add_separator']);
	}
});