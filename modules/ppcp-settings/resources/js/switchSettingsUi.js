document.addEventListener( 'DOMContentLoaded', () => {
	const config = ppcpSwitchSettingsUi;
	const button = document.querySelector(
		'.button.button-settings-switch-ui'
	);
	const link = document.querySelector( 'a.settings-switch-ui' );

	if ( typeof config === 'undefined' || ( ! button && ! link ) ) {
		return;
	}

	const handleClick = ( event ) => {
		event.preventDefault();

		const confirmed = confirm( config.confirmMessage );

		if ( ! confirmed ) {
			return;
		}

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
	};

	if ( button ) {
		button.addEventListener( 'click', handleClick );
	}

	if ( link ) {
		link.addEventListener( 'click', handleClick );
	}
} );
