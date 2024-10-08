import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

const CHECKOUT_STORE_KEY = 'wc/store/checkout';

/**
 * Custom hook to manage address editing states in the checkout process.
 *
 * When set to true (default), the shipping and billing address forms are displayed.
 * When set to false, the address forms are hidden and the user can only view the address details (card view).
 *
 * @return {Object} An object containing address editing states and setter functions.
 */
export const useAddressEditing = () => {
	// Select address editing states from the checkout store
	const { isEditingShippingAddress, isEditingBillingAddress } = useSelect(
		( select ) => {
			const store = select( CHECKOUT_STORE_KEY );
			return {
				// Default to true if the getter function doesn't exist
				isEditingShippingAddress: store.getEditingShippingAddress
					? store.getEditingShippingAddress()
					: true,
				isEditingBillingAddress: store.getEditingBillingAddress
					? store.getEditingBillingAddress()
					: true,
			};
		},
		[]
	);

	// Get dispatch functions to update address editing states
	const { setEditingShippingAddress, setEditingBillingAddress } =
		useDispatch( CHECKOUT_STORE_KEY );

	// Memoized function to update shipping address editing state
	const setShippingAddressEditing = useCallback(
		( isEditing ) => {
			if ( typeof setEditingShippingAddress === 'function' ) {
				setEditingShippingAddress( isEditing );
			}
		},
		[ setEditingShippingAddress ]
	);

	// Memoized function to update billing address editing state
	const setBillingAddressEditing = useCallback(
		( isEditing ) => {
			if ( typeof setEditingBillingAddress === 'function' ) {
				setEditingBillingAddress( isEditing );
			}
		},
		[ setEditingBillingAddress ]
	);

	// Return an object with address editing states and setter functions
	return {
		isEditingShippingAddress,
		isEditingBillingAddress,
		setShippingAddressEditing,
		setBillingAddressEditing,
	};
};

export default useAddressEditing;
