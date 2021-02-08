jQuery(function($){
	$.extend({
		wpjam_prepend_post_thumbnail: function(id){
			if($('#inline_'+id+' div.post_thumbnail').length){
				$('#inline_'+id).prev('strong').prepend($('#inline_'+id+' div.post_thumbnail').html());
			}
		}
	});

	$('body').on('list_table_action_success', function(event, response){
		if(response.list_action_type != 'form'){
			if(response.bulk){
				$.each(response.ids, function(index, id){
					$.wpjam_prepend_post_thumbnail(id);
				});
			}else{
				$.wpjam_prepend_post_thumbnail(response.id);
			}
		}
	});

	var wp_inline_edit_function = inlineEditPost.edit;
	
	inlineEditPost.edit = function(id){

		wp_inline_edit_function.apply(this, arguments);

		if(typeof(id) === 'object'){
			id = this.getId(id);
		}

		if(id > 0){
			var edit_row	= $('#edit-' + id);

			if($('#inline_'+id+' div.post_excerpt').length){
				var excerpt		= $('#inline_'+id+' div.post_excerpt').text();
				$(':input[name="the_excerpt"]', edit_row).val(excerpt);
			}
		}
	}
});