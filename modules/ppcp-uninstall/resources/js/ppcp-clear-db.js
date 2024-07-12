document.addEventListener( 'DOMContentLoaded', () => {
	const config = PayPalCommerceGatewayClearDb;
	if ( ! typeof config ) {
		return;
	}

	const clearDbConfig = config.clearDb;

	document
		.querySelector( clearDbConfig.button )
		?.addEventListener( 'click', function () {
			const isConfirmed = confirm( clearDbConfig.confirmationMessage );
			if ( ! isConfirmed ) {
				return;
			}

			const clearButton = document.querySelector( clearDbConfig.button );

			clearButton.setAttribute( 'disabled', 'disabled' );
			fetch( clearDbConfig.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: clearDbConfig.nonce,
				} ),
			} )
				.then( ( res ) => {
					return res.json();
				} )
				.then( ( data ) => {
					if ( ! data.success ) {
						jQuery( clearDbConfig.failureMessage ).insertAfter(
							clearButton
						);
						setTimeout(
							() =>
								jQuery(
									clearDbConfig.messageSelector
								).remove(),
							3000
						);
						clearButton.removeAttribute( 'disabled' );
						throw Error( data.data.message );
					}

					jQuery( clearDbConfig.successMessage ).insertAfter(
						clearButton
					);
					setTimeout(
						() => jQuery( clearDbConfig.messageSelector ).remove(),
						3000
					);
					clearButton.removeAttribute( 'disabled' );
					window.location.replace( clearDbConfig.redirectUrl );
				} );
		} );
} );
