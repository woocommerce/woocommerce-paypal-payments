/**
 * Writes a frontend failure to the console and to the WooCommerce log.
 *
 * Some failures reach no console anyone can read: a wallet sheet fails inside
 * native UI, and an iPhone showing Apple's "Payment not completed" leaves the
 * reason nowhere the merchant or support can see it. So the browser posts what
 * it saw to the server as well.
 *
 * Failures only, so the happy path costs no request and no config flag is needed
 * to hold the volume down. A dismissal is deliberately not reported while an
 * abort is, which is what makes the two distinguishable afterwards.
 */

// The endpoint is shared with every other module's frontend; the tag is what
// keeps their lines apart.
const LOG_TAG = 'SDK v6';

// Long enough for a PayPal payload or the opening of an HTML error page, short
// enough to keep a log line readable. The endpoint enforces the same cap on
// whatever reaches it.
const MAX_VALUE_LENGTH = 500;

/**
 * Reduces one detail value to a loggable string.
 *
 * @param {*} value - Whatever the call site had.
 * @return {string} The value as text, clamped.
 */
function loggable( value ) {
	if ( undefined === value || null === value ) {
		return '';
	}

	let text;

	if ( 'string' === typeof value ) {
		text = value;
	} else {
		try {
			text = JSON.stringify( value );
		} catch ( error ) {
			// Circular, or a getter that throws.
			text = String( value );
		}
	}

	return text.slice( 0, MAX_VALUE_LENGTH );
}

/**
 * Records one failure, in the console and in the server log.
 *
 * Never throws and never rejects: this is bookkeeping, and a shopper's payment
 * must not fail because logging it did. Not awaited by its callers either,
 * since a wallet sheet needs its answer now.
 *
 * @param {Object} config   - The wc_ppcp_sdk_v6 config object.
 * @param {string} event    - What happened, as a stable slug.
 * @param {Object} [detail] - Named facts about the failure.
 * @return {Promise<void>} Resolves once logged, or once the attempt failed.
 */
export async function logError( config, event, detail = {} ) {
	// One catch around everything, including the console line: `detail` comes
	// from a caught error, so even reading it can throw.
	try {
		// eslint-disable-next-line no-console
		console.error( `[PPCP ${ LOG_TAG }] ${ event }`, detail );

		const ajax = config?.ajax?.frontend_log;

		if ( ! ajax?.endpoint ) {
			return;
		}

		const context = {};

		Object.keys( detail ).forEach( ( key ) => {
			const value = loggable( detail[ key ] );

			if ( '' !== value ) {
				context[ key ] = value;
			}
		} );

		await fetch( ajax.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			// Nothing awaits this, and some callers redirect straight afterwards,
			// which would cancel it. The payload is far below the 64KB this costs.
			keepalive: true,
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				nonce: ajax.nonce,
				tag: LOG_TAG,
				event,
				context,
			} ),
		} );
	} catch ( error ) {
		// Bookkeeping only: the shopper's payment does not depend on it.
	}
}
