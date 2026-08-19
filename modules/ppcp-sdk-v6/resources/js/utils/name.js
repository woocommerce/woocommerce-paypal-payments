/**
 * Name helpers shared by the surfaces that receive a single name string.
 *
 * @package
 */

/**
 * Splits a full name into first and last parts.
 *
 * The last token is the surname, so a middle name stays with the given name.
 * Multi-token surnames ("van der Berg", Hispanic double surnames) therefore
 * split wrongly. This is deliberate: the simplest rule that fits most shops,
 * rather than growing this into a culture-aware module.
 *
 * Splits on any whitespace run: no whitespace is valid inside a name segment.
 *
 * @param {string} fullName - The full name.
 * @return {[string, string]} The first and last name.
 */
export function splitFullName( fullName ) {
	const parts = fullName.trim().split( /\s+/ );
	const lastName = parts.length > 1 ? parts.pop() : '';
	return [ parts.join( ' ' ), lastName ];
}
