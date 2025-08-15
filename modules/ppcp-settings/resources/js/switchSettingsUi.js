document.addEventListener( 'DOMContentLoaded', () => {
	const config = ppcpSwitchSettingsUi;
	const button = document.querySelector(
		'.button.button-settings-switch-ui'
	);
	const link = document.querySelector(
		'.ppcp-notice-wrapper:not(.inline) a.settings-switch-ui'
	);

	console.log( 'Config:', config );
	console.log( 'Button found:', button );
	console.log( 'Link found:', link );
	console.log(
		'All links with settings-switch-ui:',
		document.querySelectorAll( '.settings-switch-ui' )
	);

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

	document.addEventListener( 'click', ( event ) => {
		const linkElement = event.target.closest( 'a.settings-switch-ui' );
		if ( linkElement ) {
			handleClick( event );
		}
	} );
} );
