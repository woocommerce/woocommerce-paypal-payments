import { useCallback } from '@wordpress/element';

export const useShippingAddressChange = (
	fastlaneSdk,
	setShippingAddress,
	setWooShippingAddress
) => {
	return useCallback( async () => {
		if ( fastlaneSdk ) {
			const { selectionChanged, selectedAddress } =
				await fastlaneSdk.profile.showShippingAddressSelector();
			if ( selectionChanged ) {
				setShippingAddress( selectedAddress );
				console.log(
					'Selected shipping address changed:',
					selectedAddress
				);

				const { address, name, phoneNumber } = selectedAddress;

				setWooShippingAddress( {
					first_name: name.firstName,
					last_name: name.lastName,
					address_1: address.addressLine1,
					address_2: address.addressLine2 || '',
					city: address.adminArea2,
					state: address.adminArea1,
					postcode: address.postalCode,
					country: address.countryCode,
					phone: phoneNumber.nationalNumber,
				} );
			}
		}
	}, [ fastlaneSdk, setShippingAddress, setWooShippingAddress ] );
};

export const useCardChange = ( fastlaneSdk, setCard, setWooBillingAddress ) => {
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

				setWooBillingAddress( {
					first_name: firstName,
					last_name: lastName,
					address_1: billingAddress.addressLine1,
					address_2: billingAddress.addressLine2 || '',
					city: billingAddress.adminArea2,
					state: billingAddress.adminArea1,
					postcode: billingAddress.postalCode,
					country: billingAddress.countryCode,
				} );

				console.log( 'Billing address updated:', billingAddress );
			}
		}
	}, [ fastlaneSdk, setCard ] );
};
