/**
 * The session validates the card only on submit, which needs an existing order
 * or setup token, so the field events catch an empty or invalid card first.
 *
 * @package
 */

export const FIELD_TYPES = [ 'number', 'expiry', 'cvv' ];

const STATE_CHANGE_EVENTS = [ 'change', 'validitychange', 'empty', 'notempty' ];

/**
 * @param {Object} session - The card fields session.
 * @return {Function} A getter for the latest state the session reported.
 */
export function trackFieldState( session ) {
	let state = {};

	const handler = ( payload ) => {
		if ( payload?.data ) {
			state = payload.data;
		}
	};

	STATE_CHANGE_EVENTS.forEach( ( event ) => {
		Promise.resolve( session.on( event, handler ) ).catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.warn(
				'[PPCP SDK v6] card field event not available',
				event,
				error
			);
		} );
	} );

	return () => state;
}

/**
 * @param {Object} fieldState - The state from the trackFieldState() getter.
 * @param {Object} labels     - The config labels.
 * @return {string} The message, or an empty string when all fields are valid.
 */
export function fieldStateError( fieldState, labels ) {
	const fields = FIELD_TYPES.map( ( type ) => fieldState[ type ] ?? {} );

	// A field without an event yet is still empty.
	if ( fields.some( ( field ) => field.isEmpty ?? true ) ) {
		return labels.fields_empty;
	}
	if ( fields.some( ( field ) => ! field.isValid ) ) {
		return labels.fields_not_valid;
	}

	return '';
}
