import { useEffect, createRoot } from '@wordpress/element';
import ShippingChangeButton from './ShippingChangeButton';

const ShippingChangeButtonManager = ( { onChangeShippingAddressClick } ) => {
	useEffect( () => {
		const shippingHeading = document.querySelector(
			'#shipping-fields .wc-block-components-checkout-step__heading'
		);

		if (
			shippingHeading &&
			! shippingHeading.querySelector(
				'.wc-block-checkout-axo-block-card__edit'
			)
		) {
			const spanElement = document.createElement( 'span' );
			spanElement.className = 'wc-block-checkout-axo-block-card__edit';
			shippingHeading.appendChild( spanElement );

			const root = createRoot( spanElement );
			root.render(
				<ShippingChangeButton
					onChangeShippingAddressClick={
						onChangeShippingAddressClick
					}
				/>
			);

			return () => {
				root.unmount();
				spanElement.remove();
			};
		}
	}, [ onChangeShippingAddressClick ] );

	return null;
};

export default ShippingChangeButtonManager;
