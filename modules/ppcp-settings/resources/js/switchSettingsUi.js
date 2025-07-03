document.addEventListener( 'DOMContentLoaded', () => {
	const config = ppcpSwitchSettingsUi;
	const button = document.querySelector(
		'.button.button-settings-switch-ui'
	);

	if ( ! typeof config || ! button ) {
		return;
	}

	button.addEventListener( 'click', () => {
		fetch( config.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				nonce: config.nonce,
			} ),
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}
				return response.json();
			} )
			.then( ( data ) => {
				window.location.reload();
			} )
			.catch( ( error ) => {
				console.error( 'Error:', error );
			} );
	} );
} );
