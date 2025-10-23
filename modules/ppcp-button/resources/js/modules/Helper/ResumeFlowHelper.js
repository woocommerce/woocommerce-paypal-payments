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
