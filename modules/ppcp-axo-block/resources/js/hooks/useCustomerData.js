import { useCallback, useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

export const useCustomerData = () => {
	const customerData = useSelect( ( select ) =>
		select( 'wc/store/cart' ).getCustomerData()
	);

	const {
		setShippingAddress: setShippingAddressDispatch,
		setBillingAddress: setBillingAddressDispatch,
	} = useDispatch( 'wc/store/cart' );

	const setShippingAddress = useCallback(
		( address ) => {
			setShippingAddressDispatch( address );
		},
		[ setShippingAddressDispatch ]
	);

	const setBillingAddress = useCallback(
		( address ) => {
			setBillingAddressDispatch( address );
		},
		[ setBillingAddressDispatch ]
	);

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
