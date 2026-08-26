class ResumeFlowHelper {
	static PAYPAL_PARAMS = [
		'onApprove',
		'token',
		'PayerID',
		'payerID',
		'button_session_id',
		'billingToken',
		'orderID',
		'switch_initiated_time',
		'onCancel',
		'onError',
	];

	static cleanHashParams() {
		if ( ! window.location.hash ) {
			return;
		}

		const hashString = window.location.hash.substring( 1 );
		const params = hashString.split( '&' );

		const cleanedParams = params.filter( ( param ) => {
			const paramName = param.split( '=' )[ 0 ];
			return ! this.PAYPAL_PARAMS.includes( paramName );
		} );

		if ( cleanedParams.length > 0 ) {
			const newHash = '#' + cleanedParams.join( '&' );
			window.history.replaceState(
				null,
				'',
				window.location.pathname + window.location.search + newHash
			);
		} else {
			window.history.replaceState(
				null,
				'',
				window.location.pathname + window.location.search
			);
		}
	}

	/**
	 * Whether the PayPal order was created by this document.
	 *
	 * Reset on every page load by virtue of being module state.
	 */
	static orderCreatedInDocument = false;

	/**
	 * Records that this document created the PayPal order.
	 */
	static markOrderCreated() {
		ResumeFlowHelper.orderCreatedInDocument = true;
	}

	static AUTO_SUBMIT_KEY = 'ppcpSubmitAfterReturn';

	/**
	 * Remembers, across the reload that follows a return from PayPal, that the
	 * buyer already asked to pay and the restored checkout should submit itself.
	 *
	 * sessionStorage rather than a URL param so the SDK cannot mistake it for one
	 * of its own, and it dies with the tab. Guarded because private browsing can
	 * refuse storage outright, in which case the buyer simply confirms by hand.
	 */
	static markSubmitAfterReturn() {
		try {
			window.sessionStorage.setItem(
				ResumeFlowHelper.AUTO_SUBMIT_KEY,
				'1'
			);
		} catch ( error ) {
			// Storage unavailable; fall back to a manual confirmation.
		}
	}

	/**
	 * Whether approval is happening in a different document than the one that
	 * created the order — AppSwitch and redirect flows both replace it.
	 *
	 * Deliberately not derived from the URL: the SDK strips its own token and
	 * PayerID params before invoking onApprove, so by then location.search is
	 * empty and any check against it reports a false negative. Whether the
	 * document was replaced is the condition that actually matters, since that
	 * is what discards everything the buyer typed.
	 *
	 * @return {boolean} True when the buyer is coming back from PayPal.
	 */
	static isReturnFromPayPal() {
		return ! ResumeFlowHelper.orderCreatedInDocument;
	}

	/**
	 * The current URL with every PayPal param stripped from both the query
	 * string and the hash.
	 *
	 * Reloading with the params still present would let the SDK treat the fresh
	 * page as another return and approve again, in a loop.
	 *
	 * @return {string} The cleaned URL.
	 */
	static urlWithoutPayPalParams() {
		const url = new URL( window.location.href );

		this.PAYPAL_PARAMS.forEach( ( param ) =>
			url.searchParams.delete( param )
		);

		if ( url.hash ) {
			const kept = url.hash
				.substring( 1 )
				.split( '&' )
				.filter(
					( param ) =>
						! this.PAYPAL_PARAMS.includes( param.split( '=' )[ 0 ] )
				);

			url.hash = kept.length ? kept.join( '&' ) : '';
		}

		return url.toString();
	}

	static isResumeFlow() {
		if ( ! window.location.hash ) {
			return false;
		}

		const hashString = window.location.hash.substring( 1 );
		const params = hashString.split( '&' );

		return params.some( ( param ) => {
			const paramName = param.split( '=' )[ 0 ];
			return paramName === 'switch_initiated_time';
		} );
	}

	static reloadButtonsIfRequired( buttonWrapper ) {
		if ( this.isResumeFlow() ) {
			this.cleanHashParams();
			jQuery( buttonWrapper ).trigger( 'ppcp-reload-buttons' );
		}
	}
}

export default ResumeFlowHelper;
