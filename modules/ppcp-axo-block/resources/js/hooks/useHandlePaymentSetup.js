import { useCallback } from '@wordpress/element';

const useHandlePaymentSetup = (
	emitResponse,
	card,
	paymentComponent,
	tokenizedCustomerData
) => {
	return useCallback( async () => {
		const isRyanFlow = !! card?.id;
		let cardToken = card?.id;

		if ( ! cardToken && paymentComponent ) {
			cardToken = await paymentComponent
				.getPaymentToken( tokenizedCustomerData )
				.then( ( response ) => response.id );
		}

		if ( ! cardToken ) {
			let reason = 'tokenization error';

			if ( ! paymentComponent ) {
				reason = 'initialization error';
			}

			return {
				type: emitResponse.responseTypes.ERROR,
				message: `Could not process the payment (${ reason })`,
			};
		}

		return {
			type: emitResponse.responseTypes.SUCCESS,
			meta: {
				paymentMethodData: {
					fastlane_member: isRyanFlow,
					axo_nonce: cardToken,
				},
			},
		};
	}, [ card, paymentComponent, tokenizedCustomerData ] );
};

export default useHandlePaymentSetup;
