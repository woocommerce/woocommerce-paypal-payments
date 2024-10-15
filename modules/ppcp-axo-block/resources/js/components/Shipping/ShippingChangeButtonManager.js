import { useEffect, createRoot } from '@wordpress/element';
import ShippingChangeButton from './ShippingChangeButton';

/**
 * Manages the insertion and removal of the ShippingChangeButton in the DOM.
 *
 * @param {Object}   props
 * @param {Function} props.onChangeShippingAddressClick - Callback function for when the shipping change button is clicked.
 * @return {null} This component doesn't render any visible elements directly.
 */
const ShippingChangeButtonManager = ( { onChangeShippingAddressClick } ) => {
	useEffect( () => {
		const shippingHeading = document.querySelector(
			'#shipping-fields .wc-block-components-checkout-step__heading'
		);

		// Check if the shipping heading exists and doesn't already have a change button
		if (
			shippingHeading &&
			! shippingHeading.querySelector(
				'.wc-block-checkout-axo-block-card__edit'
			)
		) {
			// Create a new span element to contain the ShippingChangeButton
			const spanElement = document.createElement( 'span' );
			spanElement.className = 'wc-block-checkout-axo-block-card__edit';
			shippingHeading.appendChild( spanElement );

			// Create a React root and render the ShippingChangeButton
			const root = createRoot( spanElement );
			root.render(
				<ShippingChangeButton
					onChangeShippingAddressClick={
						onChangeShippingAddressClick
					}
				/>
			);

			// Cleanup function to remove the button when the component unmounts
			return () => {
				root.unmount();
				spanElement.remove();
			};
		}
	}, [ onChangeShippingAddressClick ] );

	// This component doesn't render anything directly
	return null;
};

export default ShippingChangeButtonManager;
