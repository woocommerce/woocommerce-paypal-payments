import { createElement, useEffect, createRoot } from '@wordpress/element';

const ShippingChangeButton = ( { onChangeShippingAddressClick } ) =>
	createElement(
		'button',
		{
			className: 'wc-block-checkout-axo-block-card__edit',
			'aria-label': 'Change shipping details',
			type: 'button',
			onClick: ( event ) => {
				event.preventDefault();
				onChangeShippingAddressClick();
			},
		},
		'Change'
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
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	createRoot( container ).render(
		createElement( ShippingChangeButtonManager, {
			onChangeShippingAddressClick,
		} )
	);
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
