import { useState, useEffect, createRoot } from '@wordpress/element';
import AddressCard from '../components/AddressCard';
import { useCustomerData } from '../hooks/useCustomerData';

const AddressManager = ( { onChangeShippingAddressClick } ) => {
	const [ isEditing, setIsEditing ] = useState( false );
	const { shippingAddress } = useCustomerData();

	useEffect( () => {
		const injectAddressCard = () => {
			const shippingForm = document.querySelector(
				'#shipping.wc-block-components-address-form'
			);
			if (
				shippingForm &&
				! document.querySelector(
					'.wc-block-components-axo-address-card'
				)
			) {
				const cardWrapper = document.createElement( 'div' );
				cardWrapper.className = 'wc-block-components-axo-address-card';
				shippingForm.parentNode.insertBefore(
					cardWrapper,
					shippingForm
				);

				const root = createRoot( cardWrapper );
				root.render(
					<AddressCard
						address={ shippingAddress }
						onEdit={ () => {
							setIsEditing( ! isEditing );
							onChangeShippingAddressClick();
						} }
						isExpanded={ isEditing }
					/>
				);
			}
		};

		if ( shippingAddress ) {
			injectAddressCard();
		}

		return () => {
			const cardWrapper = document.querySelector(
				'.wc-block-components-axo-address-card'
			);
			if ( cardWrapper ) {
				cardWrapper.remove();
			}
		};
	}, [ shippingAddress, isEditing, onChangeShippingAddressClick ] );

	return null;
};

export default AddressManager;
