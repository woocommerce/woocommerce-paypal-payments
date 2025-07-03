const initiateRedirect = ( successUrl ) => {
	/**
	 * Notice how this step initiates a redirect to a new page using a plain
	 * URL as new location. This process does not send any details about the
	 * approved order or billed customer.
	 *
	 * The redirect will start after a short delay, giving the calling method
	 * time to process the return value of the `await onApprove()` call.
	 */

	setTimeout( () => {
		window.location.href = successUrl;
	}, 200 );
};

const onApprove = ( context, errorHandler ) => {
	return ( data, actions ) => {
		const canCreateOrder =
			! context.config.vaultingEnabled || data.paymentSource !== 'venmo';

		const payload = {
			nonce: context.config.ajax.approve_order.nonce,
			order_id: data.orderID,
			funding_source: window.ppcpFundingSource,
			should_create_wc_order: canCreateOrder,
		};

		if ( canCreateOrder && data.payer ) {
			payload.payer = data.payer;
		}

		return fetch( context.config.ajax.approve_order.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( payload ),
		} )
			.then( ( res ) => {
				return res.json();
			} )
			.then( ( approveData ) => {
				if ( ! approveData.success ) {
					errorHandler.genericError();
					return actions.restart().catch( () => {
						errorHandler.genericError();
					} );
				}

				const orderReceivedUrl = approveData.data?.order_received_url;
				initiateRedirect( orderReceivedUrl || context.config.redirect );
			} );
	};
};

export default onApprove;
