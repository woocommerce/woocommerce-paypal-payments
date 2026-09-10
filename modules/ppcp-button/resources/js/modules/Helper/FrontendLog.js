/**
 * Reports a frontend event to the console and the WooCommerce log.
 *
 * Some events reach no console anyone can read, so the browser posts what it
 * saw to the server as well.
 */

/**
 * @param {Error} error - The caught error.
 * @return {string} Reportable text.
 */
export function describeError( error ) {
	const message = error?.message || 'unknown error';

	return error?.status ? `HTTP ${ error.status } ${ message }` : message;
}

/**
 * Records one report, in the console and in the server log.
 *
 * Never throws, never rejects, and is not awaited.
 *
 * @param {Object} ajax     - The `frontend_log` entry of the script config.
 * @param {string} tag      - Groups this frontend's lines in the shared log.
 * @param {string} event    - What happened, as a stable slug.
 * @param {string} [detail] - Named facts about the report.
 * @param {string} [level]  - The level to record it at.
 * @return {Promise<void>}
 */
export async function logFrontendEvent(
	ajax,
	tag,
	event,
	detail = '',
	level = 'error'
) {
	try {
		const write =
			'error' === level || 'warning' === level
				? // eslint-disable-next-line no-console
				  console.error
				: // eslint-disable-next-line no-console
				  console.info;

		write( `[PPCP ${ tag }] ${ event }`, detail );

		if ( ! ajax?.endpoint ) {
			return;
		}

		await fetch( ajax.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			// Callers redirect straight afterwards, which cancels a plain fetch.
			keepalive: true,
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				nonce: ajax.nonce,
				tag,
				event,
				message: String( detail ),
				level,
			} ),
		} );
	} catch ( error ) {
		// Reporting a failure must not cause one.
	}
}
