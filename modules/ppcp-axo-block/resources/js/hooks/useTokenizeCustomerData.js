import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

export const useTokenizeCustomerData = () => {
	const customerData = useSelect( ( select ) =>
		select( 'wc/store/cart' ).getCustomerData()
	);

	const isValidAddress = ( address ) => {
		// At least one name must be present.
		if ( ! address.first_name && ! address.last_name ) {
			return false;
		}

		// Street, city, postcode, country are mandatory; state is optional.
		return (
			address.address_1 &&
			address.city &&
			address.postcode &&
			address.country
		);
	};

	// Memoize the customer data to avoid unnecessary re-renders (and potential infinite loops).
	return useMemo( () => {
		const { billingAddress, shippingAddress } = customerData;

		// Prefer billing address, but fallback to shipping address if billing address is not valid.
		const mainAddress = isValidAddress( billingAddress )
			? billingAddress
			: shippingAddress;

		return {
			cardholderName: {
				fullName: `${ mainAddress.first_name } ${ mainAddress.last_name }`,
			},
			billingAddress: {
				addressLine1: mainAddress.address_1,
				addressLine2: mainAddress.address_2,
				adminArea1: mainAddress.state,
				adminArea2: mainAddress.city,
				postalCode: mainAddress.postcode,
				countryCode: mainAddress.country,
			},
		};
	}, [ customerData ] );
};

export default useTokenizeCustomerData;
