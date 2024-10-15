import { useMemo } from '@wordpress/element';
import useCustomerData from './useCustomerData';

/**
 * Custom hook to prepare customer data for tokenization.
 *
 * @return {Object} Formatted customer data for tokenization.
 */
export const useTokenizeCustomerData = () => {
	const { billingAddress, shippingAddress } = useCustomerData();

	/**
	 * Validates if an address contains the minimum required data.
	 *
	 * @param {Object} address - The address object to validate.
	 * @return {boolean} True if the address is valid, false otherwise.
	 */
	const isValidAddress = ( address ) => {
		// At least one name must be present
		if ( ! address.first_name && ! address.last_name ) {
			return false;
		}

		// Street, city, postcode, country are mandatory; state is optional
		return (
			address.address_1 &&
			address.city &&
			address.postcode &&
			address.country
		);
	};

	// Memoize the customer data to avoid unnecessary re-renders (and potential infinite loops)
	return useMemo( () => {
		// Determine the main address, preferring billing address if valid
		const mainAddress = isValidAddress( billingAddress )
			? billingAddress
			: shippingAddress;

		// Format the customer data for tokenization
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
	}, [ billingAddress, shippingAddress ] );
};

export default useTokenizeCustomerData;
