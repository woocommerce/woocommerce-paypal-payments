import { useDispatch, useSelect } from '@wordpress/data';

export const useCustomerData = () => {
	const customerData = useSelect( ( select ) =>
		select( 'wc/store/cart' ).getCustomerData()
	);
	const { setShippingAddress, setBillingAddress } =
		useDispatch( 'wc/store/cart' );

	return {
		shippingAddress: customerData.shippingAddress,
		billingAddress: customerData.billingAddress,
		setShippingAddress,
		setBillingAddress,
	};
};
