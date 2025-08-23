document.addEventListener( 'DOMContentLoaded', () => {
	const config = ppcpSwitchSettingsUi;

	if ( typeof config === 'undefined' ) {
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
				window.location.href = config.settingsUrl;
			} )
			.catch( ( error ) => {
				console.error( 'Error:', error );
			} );
	};

	document.addEventListener( 'click', ( event ) => {
		if (
			event.target.closest( '.button.button-settings-switch-ui' ) ||
			event.target.closest(
				'.ppcp-notice-wrapper:not(.inline) a.settings-switch-ui'
			) ||
			event.target.closest( 'a[name="settings-switch-ui"]' ) ||
			event.target
				.closest( '.woocommerce-inbox-note__action-button' )
				?.textContent.includes( 'Switch to New Settings' )
		) {
			handleClick( event );
		}
	} );
} );
