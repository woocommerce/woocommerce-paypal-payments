import { useCallback } from '@wordpress/element';
import { useAddressEditing } from './useAddressEditing';
import useCustomerData from './useCustomerData';

/**
 * Custom hook to handle the 'Choose a different shipping address' selection.
 *
 * @param {Object}   fastlaneSdk        - The Fastlane SDK instance.
 * @param {Function} setShippingAddress - Function to update the shipping address state.
 * @return {Function} Callback function to trigger shipping address selection and update.
 */
export const useShippingAddressChange = ( fastlaneSdk, setShippingAddress ) => {
	const { setShippingAddressEditing } = useAddressEditing();
	const { setShippingAddress: setWooShippingAddress } = useCustomerData();

	return useCallback( async () => {
		if ( fastlaneSdk ) {
			// Show shipping address selector and get the user's selection
			const { selectionChanged, selectedAddress } =
				await fastlaneSdk.profile.showShippingAddressSelector();

			if ( selectionChanged ) {
				// Update the shipping address in the custom store with the selected address
				setShippingAddress( selectedAddress );

				const { address, name, phoneNumber } = selectedAddress;

				// Transform the selected address into WooCommerce format
				const newShippingAddress = {
					first_name: name.firstName,
					last_name: name.lastName,
					address_1: address.addressLine1,
					address_2: address.addressLine2 || '',
					city: address.adminArea2,
					state: address.adminArea1,
					postcode: address.postalCode,
					country: address.countryCode,
					phone: phoneNumber.nationalNumber,
				};

				// Update the WooCommerce shipping address in the WooCommerce store
				await new Promise( ( resolve ) => {
					setWooShippingAddress( newShippingAddress );
					resolve();
				} );

				// Trigger the Address Card view by setting the shipping address editing state to false
				await new Promise( ( resolve ) => {
					setShippingAddressEditing( false );
					resolve();
				} );
			}
		}
	}, [
		fastlaneSdk,
		setShippingAddress,
		setWooShippingAddress,
		setShippingAddressEditing,
	] );
};

export default useShippingAddressChange;
