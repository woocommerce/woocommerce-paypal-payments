export function log( message, level = 'info' ) {
	const wpDebug = window.wc_ppcp_axo?.wp_debug;
	const endpoint = window.wc_ppcp_axo?.ajax?.frontend_logger?.endpoint;
	const loggingEnabled = window.wc_ppcp_axo?.logging_enabled;

	if ( wpDebug ) {
		switch ( level ) {
			case 'error':
				console.error( `[AXO] ${ message }` );
				break;
			case 'warn':
				console.warn( `[AXO] ${ message }` );
				break;
			default:
				console.log( `[AXO] ${ message }` );
		}
	}

	if ( ! endpoint || ! loggingEnabled ) {
		return;
	}

	fetch( endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		body: JSON.stringify( {
			nonce: window.wc_ppcp_axo.ajax.frontend_logger.nonce,
			log: {
				message,
				level,
			},
		} ),
	} );
}
