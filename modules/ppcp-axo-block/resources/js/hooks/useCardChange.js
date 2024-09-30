import { useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { useAddressEditing } from './useAddressEditing';
import useCustomerData from './useCustomerData';
import { STORE_NAME } from '../stores/axoStore';

export const useCardChange = ( fastlaneSdk ) => {
	const { setBillingAddressEditing } = useAddressEditing();
	const { setBillingAddress: setWooBillingAddress } = useCustomerData();
	const { setCardDetails, setShippingAddress } = useDispatch( STORE_NAME );

	return useCallback( async () => {
		if ( fastlaneSdk ) {
			const { selectionChanged, selectedCard } =
				await fastlaneSdk.profile.showCardSelector();

			if ( selectionChanged && selectedCard?.paymentSource?.card ) {
				// Use the fallback logic for cardholder's name.
				const { name, billingAddress } =
					selectedCard.paymentSource.card;

				// If name is missing, use billing details as a fallback for the name.
				let firstName = '';
				let lastName = '';

				if ( name ) {
					const nameParts = name.split( ' ' );
					firstName = nameParts[ 0 ];
					lastName = nameParts.slice( 1 ).join( ' ' );
				}

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

				// Batch state updates.
				await Promise.all( [
					new Promise( ( resolve ) => {
						setCardDetails( selectedCard );
						resolve();
					} ),
					new Promise( ( resolve ) => {
						setWooBillingAddress( newBillingAddress );
						resolve();
					} ),
					new Promise( ( resolve ) => {
						setShippingAddress( newBillingAddress );
						resolve();
					} ),
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
		setShippingAddress,
		setBillingAddressEditing,
	] );
};

export default useCardChange;
