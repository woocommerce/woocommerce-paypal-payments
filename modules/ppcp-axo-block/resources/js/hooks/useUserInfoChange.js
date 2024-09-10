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

export const useCardChange = ( fastlaneSdk, setCard ) => {
	return useCallback( async () => {
		if ( fastlaneSdk ) {
			const { selectionChanged, selectedCard } =
				await fastlaneSdk.profile.showCardSelector();
			if ( selectionChanged ) {
				setCard( selectedCard );
				console.log( 'Selected card changed:', selectedCard );
			}
		}
	}, [ fastlaneSdk, setCard ] );
};
