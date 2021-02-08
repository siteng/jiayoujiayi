		<footer class="footer text-center"> 
			<div class="container">
				<div class="row">
					<div class="col-md-12">
						<?php if ( has_nav_menu('footer_menu') ) { wp_nav_menu( array( 'theme_location' => 'footer_menu', 'depth' => 1, 'container' => 'div', 'container_class' => 'footer-more-list text-center mb-3 d-none d-sm-block', 'menu_class' => null, 'container_id' => null) ); } ?>
						<div class="text-center text-muted">
							<div class="copyright text-flink">
								<ul>
									<li>友情链接：</li><?php wp_list_bookmarks('title_li=&categorize=0'); ?>
								</ul>
							</div>
							<hr />
							<div class="copyright">								
								<small>© 2016-2019 <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>. All Rights Reserved.</small>
							</div>
                   <div class="miitbeian mt-2">
                    <?php if( dobby_option('footer_icp_num') ) {?>
                      <small class="mx-1 text-muted"><a href="http://www.miitbeian.gov.cn/" rel="external nofollow" target="_blank"><?php echo dobby_option( 'footer_icp_num' ); ?></a></small>
                      <?php } if( dobby_option('footer_gov_num') ) {?>
                      <small class="mx-1 text-muted"><a href="<?php echo dobby_option( 'footer_gov_link' ); ?>" rel="external nofollow" target="_blank"><i></i><?php echo dobby_option( 'footer_gov_num' ); ?></a></small>
					   <?php }?>
                 	</div>
						</div>
					</div>
				</div>
			</div>
		</footer>
		<div class="gotop-box">
		   <a href="#" class="gotop-btn"><i class="dobby v3-packup"></i></a>
		</div>
        <?php wp_footer(); ?>
	</body>
</html>
<!--本页面执行<?php echo get_num_queries(); ?>次查询<?php timer_stop(3); ?>秒。服务器已勉强运行<?php 
	$uptime = trim(file_get_contents('/proc/uptime'));
	$uptime	= explode(' ', $uptime);
	echo time2string($uptime[0]+3474000);
?>-->