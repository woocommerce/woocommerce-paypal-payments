import {
	getCurrentPaymentMethod,
	PaymentMethods,
} from '../Helper/CheckoutMethodState';
import Spinner from '../Helper/Spinner';

import resumeFlowHelper from '../Helper/ResumeFlowHelper';

const onApprove = ( context, errorHandler ) => {
	return ( data, actions ) => {
		const spinner = Spinner.fullPage();
		spinner.block();
		errorHandler.clear();
		// Pay Now submits via form (not AJAX), so we can't detect payment errors.
		// Preemptively remove hash params to prevent reload issues.
		if ( resumeFlowHelper.isResumeFlow() ) {
			resumeFlowHelper.cleanHashParams();
		}

		return fetch( context.config.ajax.approve_order.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				nonce: context.config.ajax.approve_order.nonce,
				order_id: data.orderID,
				funding_source: window.ppcpFundingSource,
			} ),
		} )
			.then( ( res ) => {
				return res.json();
			} )
			.then( ( data ) => {
				if ( ! data.success ) {
					if ( data.data.code === 100 ) {
						errorHandler.message( data.data.message );
					} else {
						errorHandler.genericError();
					}
					if (
						typeof actions !== 'undefined' &&
						typeof actions.restart !== 'undefined'
					) {
						return actions.restart();
					}
					throw new Error( data.data.message );
				}

				// in some cases a different method may get selected,
				// such as when returning from AppSwitch in a different browser and PayPal is not default
				if ( ! getCurrentPaymentMethod().startsWith( 'ppcp-' ) ) {
					jQuery(
						`input[name="payment_method"][value="${ PaymentMethods.PAYPAL }"]`
					).prop( 'checked', true );
				}

				document.querySelector( '#place_order' ).click();
			} )
			.finally( () => {
				spinner.unblock();
			} );
	};
};

export default onApprove;
