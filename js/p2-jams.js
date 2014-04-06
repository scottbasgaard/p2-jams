jQuery(function($) {

	setTimeout(function(){

	    setInterval(function(){

			$.post( p2_jams.ajaxurl, { action: 'p2_jams', security: p2_jams.ajaxnonce }, function(data) {
			
				var parsedJSON = $.parseJSON(data);
				var jamsList = $('#p2-jams');
				var dataName = 'p2-jams';
				var fadeSpeed = 2000;

				$.each(parsedJSON, function( index, value ) {
					
					var existingItem = $('li[data-'+dataName+'="'+value[1]+'"]', jamsList);
					
					if ( $(existingItem).length > 0 ) {
						
						if ( $(existingItem).html() != $(value[0]).html() ) {

							$(existingItem).delay(1000 * index).fadeOut(fadeSpeed, function() {
					       	 	$(this).remove();
								$(jamsList).append(value[0]).fadeIn(fadeSpeed);
					   	 	});
					
						}
					
					} else {
					
						$(jamsList).hide().append(value[0]).fadeIn(fadeSpeed);
					
					}

				});
				
			});
		
		}, 10000);

	}, ( 10 - new Date().getSeconds()) );

});