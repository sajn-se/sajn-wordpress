document.addEventListener( 'wpcf7submit', function ( event ) {
	var response = event.detail && event.detail.apiResponse;
	if ( response && 'mail_sent' === response.status && response.sajn_redirect ) {
		window.location.assign( response.sajn_redirect );
	}
} );
