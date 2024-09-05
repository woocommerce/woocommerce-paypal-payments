import { useEffect } from '@wordpress/element';

// Inject the change button next to the Shipping title
const injectShippingChangeButton = ( onChangeShippingAddressClick ) => {
	const shippingTitle = document.querySelector(
		'#shipping-fields h2.wc-block-components-title'
	);

	if (
		shippingTitle &&
		! shippingTitle.nextElementSibling?.classList?.contains(
			'wc-block-checkout-axo-block-card__edit'
		)
	) {
		const buttonElement = document.createElement( 'button' );
		buttonElement.classList.add( 'wc-block-checkout-axo-block-card__edit' );
		buttonElement.setAttribute( 'aria-label', 'Change shipping details' );
		buttonElement.textContent = 'Change';
		buttonElement.onclick = ( event ) => {
			event.preventDefault();
			onChangeShippingAddressClick();
		};

		// Ensure the button is inserted correctly after the shipping title
		shippingTitle.parentNode.insertBefore(
			buttonElement,
			shippingTitle.nextSibling
		);
	}
};

// Cleanup function to remove the "Change" button when the payment gateway switches
const removeShippingChangeButton = () => {
	const existingButton = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);
	if ( existingButton ) {
		existingButton.remove();
	}
};

// ShippingChangeButton component that will handle injection and cleanup
const ShippingChangeButton = ( { onChangeShippingAddressClick } ) => {
	useEffect( () => {
		// Inject the button when the component mounts
		injectShippingChangeButton( onChangeShippingAddressClick );

		// Cleanup the button when the component unmounts or the payment gateway switches
		return () => {
			removeShippingChangeButton();
		};
	}, [ onChangeShippingAddressClick ] );

	return null;
};

export default ShippingChangeButton;
