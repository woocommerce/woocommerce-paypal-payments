import { useEffect } from '@wordpress/element';

const usePaymentSetup = ( onPaymentSetup, emitResponse, card ) => {
	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						axo_nonce: card?.id,
					},
				},
			};
		} );

		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentSetup,
		card,
	] );
};

export default usePaymentSetup;
