/**
 * Reading the overrides a surface hands a bridge.
 *
 * @package
 */

/**
 * Whether the surface has torn this render down while it was awaiting.
 *
 * @param {Object} overrides - The overrides passed to the bridge.
 * @return {boolean} False whenever the surface does not answer.
 */
export function renderIsObsolete( overrides ) {
	if ( ! overrides?.isObsolete ) {
		return false;
	}

	return Boolean( overrides.isObsolete() );
}
