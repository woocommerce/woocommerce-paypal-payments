import { createRoot } from '@wordpress/element';
import ShippingChangeButtonManager from './ShippingChangeButtonManager';

/**
 * Injects a shipping change button into the DOM if it doesn't already exist.
 *
 * @param {Function} onChangeShippingAddressClick - Callback function for when the shipping change button is clicked.
 */
export const injectShippingChangeButton = ( onChangeShippingAddressClick ) => {
	// Check if the button already exists
	const existingButton = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);

	if ( ! existingButton ) {
		// Create a new container for the button
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		// Render the ShippingChangeButtonManager in the new container
		createRoot( container ).render(
			<ShippingChangeButtonManager
				onChangeShippingAddressClick={ onChangeShippingAddressClick }
			/>
		);
	}
};

/**
 * Removes the shipping change button from the DOM if it exists.
 */
export const removeShippingChangeButton = () => {
	const span = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);
	if ( span ) {
		createRoot( span ).unmount();
		span.remove();
	}
};
