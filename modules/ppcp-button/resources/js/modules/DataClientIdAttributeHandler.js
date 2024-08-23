import { loadScript } from '@paypal/paypal-js';

const storageKey = 'ppcp-data-client-id';

const validateToken = ( token, user ) => {
	if ( ! token ) {
		return false;
	}
	if ( token.user !== user ) {
		return false;
	}
	const currentTime = new Date().getTime();
	const isExpired = currentTime >= token.expiration * 1000;
	return ! isExpired;
};

const storedTokenForUser = ( user ) => {
	const token = JSON.parse( sessionStorage.getItem( storageKey ) );
	if ( validateToken( token, user ) ) {
		return token.token;
	}
	return null;
};

const storeToken = ( token ) => {
	sessionStorage.setItem( storageKey, JSON.stringify( token ) );
};

const dataClientIdAttributeHandler = (
	scriptOptions,
	config,
	callback,
	errorCallback = null
) => {
	fetch( config.endpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		credentials: 'same-origin',
		body: JSON.stringify( {
			nonce: config.nonce,
		} ),
	} )
		.then( ( res ) => {
			return res.json();
		} )
		.then( ( data ) => {
			const isValid = validateToken( data, config.user );
			if ( ! isValid ) {
				return;
			}
			storeToken( data );

			scriptOptions[ 'data-client-token' ] = data.token;

			loadScript( scriptOptions )
				.then( ( paypal ) => {
					if ( typeof callback === 'function' ) {
						callback( paypal );
					}
				} )
				.catch( ( err ) => {
					if ( typeof errorCallback === 'function' ) {
						errorCallback( err );
					}
				} );
		} );
};

export default dataClientIdAttributeHandler;
