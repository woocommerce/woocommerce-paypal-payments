import { createElement, useEffect, createRoot } from '@wordpress/element';

const ShippingChangeButton = ( { onChangeShippingAddressClick } ) =>
	createElement(
		'a',
		{
			className:
				'wc-block-checkout-axo-block-card__edit wc-block-axo-change-link',
			role: 'button',
			onClick: ( event ) => {
				event.preventDefault();
				onChangeShippingAddressClick();
			},
		},
		'Choose a different shipping address'
	);

const ShippingChangeButtonManager = ( { onChangeShippingAddressClick } ) => {
	useEffect( () => {
		const shippingTitle = document.querySelector(
			'#shipping-fields h2.wc-block-components-title'
		);

		if ( shippingTitle ) {
			if (
				! shippingTitle.nextElementSibling?.classList?.contains(
					'wc-block-checkout-axo-block-card__edit'
				)
			) {
				const buttonContainer = document.createElement( 'span' );
				shippingTitle.parentNode.insertBefore(
					buttonContainer,
					shippingTitle.nextSibling
				);

				const root = createRoot( buttonContainer );
				root.render(
					createElement( ShippingChangeButton, {
						onChangeShippingAddressClick,
					} )
				);
			}
		}

		return () => {
			const button = document.querySelector(
				'#shipping-fields .wc-block-checkout-axo-block-card__edit'
			);
			if ( button && button.parentNode ) {
				button.parentNode.remove();
			}
		};
	}, [ onChangeShippingAddressClick ] );

	return null;
};

export const injectShippingChangeButton = ( onChangeShippingAddressClick ) => {
	// Check if the button already exists
	const existingButton = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);

	if ( ! existingButton ) {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		createRoot( container ).render(
			createElement( ShippingChangeButtonManager, {
				onChangeShippingAddressClick,
			} )
		);
	} else {
		console.log(
			'Shipping change button already exists. Skipping injection.'
		);
	}
};

export const removeShippingChangeButton = () => {
	const button = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);
	if ( button && button.parentNode ) {
		button.parentNode.remove();
	}
};

export default ShippingChangeButtonManager;
