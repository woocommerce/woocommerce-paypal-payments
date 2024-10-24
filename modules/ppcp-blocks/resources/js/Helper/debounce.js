export const debounce = ( callback, delayMs ) => {
	const state = {
		timeoutId: null,
		args: null,
	};

	/**
	 * Cancels any pending debounced execution.
	 * @return {boolean} True if a pending execution was cancelled, false otherwise.
	 */
	const cancel = () => {
		if ( ! state.timeoutId ) {
			return false;
		}

		window.clearTimeout( state.timeoutId );
		state.timeoutId = null;
		state.args = null;
		return true;
	};

	/**
	 * Immediately executes the debounced function if there's a pending execution.
	 * @return {void}
	 */
	const flush = () => {
		const args = state.args;

		// If there's nothing pending, return early.
		if ( ! cancel() ) {
			return;
		}

		callback.apply( null, args || [] );
	};

	const debouncedFunc = ( ...args ) => {
		cancel();
		state.args = args;
		state.timeoutId = window.setTimeout( flush, delayMs );
	};

	// Attach utility methods
	debouncedFunc.cancel = cancel;
	debouncedFunc.flush = flush;

	return debouncedFunc;
};
