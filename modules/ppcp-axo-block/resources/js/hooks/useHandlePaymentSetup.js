import { useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';

const useHandlePaymentSetup = (
	emitResponse,
	paymentComponent,
	tokenizedCustomerData
) => {
	const { cardDetails } = useSelect(
		( select ) => ( {
			shippingAddress: select( STORE_NAME ).getShippingAddress(),
			cardDetails: select( STORE_NAME ).getCardDetails(),
		} ),
		[]
	);

	return useCallback( async () => {
		const isRyanFlow = !! cardDetails?.id;
		let cardToken = cardDetails?.id;

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
	}, [
		cardDetails?.id,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		paymentComponent,
		tokenizedCustomerData,
	] );
};

export default useHandlePaymentSetup;
