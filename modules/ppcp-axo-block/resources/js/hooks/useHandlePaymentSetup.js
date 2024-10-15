import { useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';

/**
 * Custom hook to handle payment setup in the checkout process.
 *
 * @param {Object} emitResponse          - Object containing response types.
 * @param {Object} paymentComponent      - The payment component instance.
 * @param {Object} tokenizedCustomerData - Tokenized customer data for payment.
 * @return {Function} Callback function to handle payment setup.
 */
const useHandlePaymentSetup = (
	emitResponse,
	paymentComponent,
	tokenizedCustomerData
) => {
	// Select card details from the store
	const { cardDetails } = useSelect(
		( select ) => ( {
			cardDetails: select( STORE_NAME ).getCardDetails(),
		} ),
		[]
	);

	return useCallback( async () => {
		// Determine if it's a Ryan flow (saved card) based on the presence of card ID
		const isRyanFlow = !! cardDetails?.id;
		let cardToken = cardDetails?.id;

		// If no card token and payment component exists, get a new token
		if ( ! cardToken && paymentComponent ) {
			cardToken = await paymentComponent
				.getPaymentToken( tokenizedCustomerData )
				.then( ( response ) => response.id );
		}

		// Handle error cases when card token is not available
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
