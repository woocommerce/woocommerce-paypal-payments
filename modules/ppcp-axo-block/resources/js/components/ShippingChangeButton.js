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

// ShippingChangeButton component for injecting the button
const ShippingChangeButton = ( { onChangeShippingAddressClick } ) => {
	useEffect( () => {
		injectShippingChangeButton( onChangeShippingAddressClick );
	}, [ onChangeShippingAddressClick ] );

	return null;
};

export default ShippingChangeButton;
