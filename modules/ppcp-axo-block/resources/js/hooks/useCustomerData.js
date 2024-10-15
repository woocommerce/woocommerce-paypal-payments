import { useCallback, useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Custom hook to manage customer data in the WooCommerce store.
 *
 * @return {Object} An object containing customer addresses and setter functions.
 */
export const useCustomerData = () => {
	// Fetch customer data from the WooCommerce store
	const customerData = useSelect( ( select ) =>
		select( 'wc/store/cart' ).getCustomerData()
	);

	// Get dispatch functions to update shipping and billing addresses
	const {
		setShippingAddress: setShippingAddressDispatch,
		setBillingAddress: setBillingAddressDispatch,
	} = useDispatch( 'wc/store/cart' );

	// Memoized function to update shipping address
	const setShippingAddress = useCallback(
		( address ) => {
			setShippingAddressDispatch( address );
		},
		[ setShippingAddressDispatch ]
	);

	// Memoized function to update billing address
	const setBillingAddress = useCallback(
		( address ) => {
			setBillingAddressDispatch( address );
		},
		[ setBillingAddressDispatch ]
	);

	// Return memoized object with customer data and setter functions
	return useMemo(
		() => ( {
			shippingAddress: customerData.shippingAddress,
			billingAddress: customerData.billingAddress,
			setShippingAddress,
			setBillingAddress,
		} ),
		[
			customerData.shippingAddress,
			customerData.billingAddress,
			setShippingAddress,
			setBillingAddress,
		]
	);
};

export default useCustomerData;
