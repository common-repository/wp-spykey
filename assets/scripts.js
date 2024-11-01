var loader = "<div id='loader' class='loader'>Loading...</div>";

jQuery( document ).ready(function(){
	jQuery( "#load-more-button" ).on("click", function(){
		jQuery( "#list-holder" ).append( loader );
		jQuery.post(
			ajaxurl,
			{
				'action': 'wpull_load_more_users',
				'offset': users_offset,
				'list': jQuery( this ).attr( "list-needed" )
			},
			function( response ) {
				jQuery( "#loader" ).remove();
				users_offset += 50;
				if ( response != "" ) { jQuery( "#users-container" ).append( response ); }
				else { jQuery( "#load-more-button" ).remove(); }
			}
		);
	});

	jQuery( "#export-tracked-users" ).on("click", function(){
		jQuery.post(
			ajaxurl,
			{
				'action': 'wpull_export_tracked_users',
				'data': ""
			},
			function( response ) {
				if ( response == "READY" ) { window.open( export_url ); }
				else { console.log( response ); }
			}
		);
	});
});

function showHideDetails( container ) {
	if ( jQuery( ".active" ).attr( "id" ) != "details-"+ container ) { jQuery( ".active" ).removeClass( "active" ); }

	container = "#details-"+ container;
	if ( !jQuery( container ).hasClass( "active" ) ) {
		jQuery( container ).addClass( "active" );
	} else {
		jQuery( container ).removeClass( "active" );
	}
}
