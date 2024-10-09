import { useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { useAddressEditing } from './useAddressEditing';
import useCustomerData from './useCustomerData';
import { STORE_NAME } from '../stores/axoStore';

/**
 * Custom hook to handle the 'Choose a different card' selection.
 *
 * @param {Object} fastlaneSdk - The Fastlane SDK instance.
 * @return {Function} Callback function to trigger card selection and update related data.
 */
export const useCardChange = ( fastlaneSdk ) => {
	const { setBillingAddressEditing } = useAddressEditing();
	const { setBillingAddress: setWooBillingAddress } = useCustomerData();
	const { setCardDetails } = useDispatch( STORE_NAME );

	return useCallback( async () => {
		if ( fastlaneSdk ) {
			// Show card selector and get the user's selection
			const { selectionChanged, selectedCard } =
				await fastlaneSdk.profile.showCardSelector();

			if ( selectionChanged && selectedCard?.paymentSource?.card ) {
				// Extract cardholder and billing information from the selected card
				const { name, billingAddress } =
					selectedCard.paymentSource.card;

				// Parse cardholder's name, using billing details as a fallback if missing
				let firstName = '';
				let lastName = '';

				if ( name ) {
					const nameParts = name.split( ' ' );
					firstName = nameParts[ 0 ];
					lastName = nameParts.slice( 1 ).join( ' ' );
				}

				// Transform the billing address into WooCommerce format
				const newBillingAddress = {
					first_name: firstName,
					last_name: lastName,
					address_1: billingAddress?.addressLine1 || '',
					address_2: billingAddress?.addressLine2 || '',
					city: billingAddress?.adminArea2 || '',
					state: billingAddress?.adminArea1 || '',
					postcode: billingAddress?.postalCode || '',
					country: billingAddress?.countryCode || '',
				};

				// Batch update states
				await Promise.all( [
					// Update the selected card details in the custom store
					new Promise( ( resolve ) => {
						setCardDetails( selectedCard );
						resolve();
					} ),
					// Update the WooCommerce billing address in the WooCommerce store
					new Promise( ( resolve ) => {
						setWooBillingAddress( newBillingAddress );
						resolve();
					} ),
					// Trigger the Address Card view by setting the billing address editing state to false
					new Promise( ( resolve ) => {
						setBillingAddressEditing( false );
						resolve();
					} ),
				] );
			} else {
				log( 'Selected card or billing address is missing.', 'error' );
			}
		}
	}, [
		fastlaneSdk,
		setCardDetails,
		setWooBillingAddress,
		setBillingAddressEditing,
	] );
};

export default useCardChange;
