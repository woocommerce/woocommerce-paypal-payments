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
					return actions.restart().catch( ( err ) => {
						errorHandler.genericError();
					} );
				}

				const orderReceivedUrl = approveData.data?.order_received_url;

				/**
				 * Notice how this step initiates a redirect to a new page using a plain
				 * URL as new location. This process does not send any details about the
				 * approved order or billed customer.
				 * Also, due to the redirect starting _instantly_ there should be no other
				 * logic scheduled after calling `await onApprove()`;
				 */

				window.location.href = orderReceivedUrl
					? orderReceivedUrl
					: context.config.redirect;
			} );
	};
};

export default onApprove;
