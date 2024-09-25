import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

const CHECKOUT_STORE_KEY = 'wc/store/checkout';

export const useAddressEditing = () => {
	const { isEditingShippingAddress, isEditingBillingAddress } = useSelect(
		( select ) => {
			const store = select( CHECKOUT_STORE_KEY );
			return {
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

	const { setEditingShippingAddress, setEditingBillingAddress } =
		useDispatch( CHECKOUT_STORE_KEY );

	const setShippingAddressEditing = useCallback(
		( isEditing ) => {
			if ( typeof setEditingShippingAddress === 'function' ) {
				setEditingShippingAddress( isEditing );
			}
		},
		[ setEditingShippingAddress ]
	);

	const setBillingAddressEditing = useCallback(
		( isEditing ) => {
			if ( typeof setEditingBillingAddress === 'function' ) {
				setEditingBillingAddress( isEditing );
			}
		},
		[ setEditingBillingAddress ]
	);

	return {
		isEditingShippingAddress,
		isEditingBillingAddress,
		setShippingAddressEditing,
		setBillingAddressEditing,
	};
};

export default useAddressEditing;
