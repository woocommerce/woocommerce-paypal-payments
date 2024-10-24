export const debounce = ( callback, delayMs ) => {
	const state = {
		timeoutId: null,
		args: null,
	};

	/**
	 * Cancels any pending debounced execution.
	 */
	const cancel = () => {
		if ( state.timeoutId ) {
			window.clearTimeout( state.timeoutId );
		}

		state.timeoutId = null;
		state.args = null;
	};

	/**
	 * Immediately executes the debounced function if there's a pending execution.
	 * @return {void}
	 */
	const flush = () => {
		// If there's nothing pending, return early.
		if ( ! state.timeoutId ) {
			return;
		}

		callback.apply( null, state.args || [] );
		cancel();
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
