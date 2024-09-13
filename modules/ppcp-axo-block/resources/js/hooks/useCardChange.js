import { useCallback } from '@wordpress/element';
import { useAddressEditing } from './useAddressEditing';

export const useCardChange = ( fastlaneSdk, setCard, setWooBillingAddress ) => {
	const { setBillingAddressEditing } = useAddressEditing();

	return useCallback( async () => {
		if ( fastlaneSdk ) {
			const { selectionChanged, selectedCard } =
				await fastlaneSdk.profile.showCardSelector();
			if ( selectionChanged ) {
				setCard( selectedCard );
				console.log( 'Selected card changed:', selectedCard );
				console.log( 'Setting new billing details:', selectedCard );
				const { name, billingAddress } =
					selectedCard.paymentSource.card;

				// Split the full name into first and last name
				const nameParts = name.split( ' ' );
				const firstName = nameParts[ 0 ];
				const lastName = nameParts.slice( 1 ).join( ' ' );

				const newBillingAddress = {
					first_name: firstName,
					last_name: lastName,
					address_1: billingAddress.addressLine1,
					address_2: billingAddress.addressLine2 || '',
					city: billingAddress.adminArea2,
					state: billingAddress.adminArea1,
					postcode: billingAddress.postalCode,
					country: billingAddress.countryCode,
				};

				await new Promise( ( resolve ) => {
					setWooBillingAddress( newBillingAddress );
					resolve();
				} );

				await new Promise( ( resolve ) => {
					setBillingAddressEditing( false );
					resolve();
				} );
			}
		}
	}, [
		fastlaneSdk,
		setCard,
		setWooBillingAddress,
		setBillingAddressEditing,
	] );
};

export default useCardChange;
