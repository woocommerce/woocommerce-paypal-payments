export const injectShippingChangeButton = ( onChangeShippingAddressClick ) => {
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
		shippingTitle.parentNode.insertBefore(
			buttonElement,
			shippingTitle.nextSibling
		);
	}
};

export const removeShippingChangeButton = () => {
	const existingButton = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);
	if ( existingButton ) {
		existingButton.remove();
	}
};
