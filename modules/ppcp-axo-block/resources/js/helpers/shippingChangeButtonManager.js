import { useEffect, createRoot } from '@wordpress/element';

const ShippingChangeButton = ( { onChangeShippingAddressClick } ) => (
	<a
		className="wc-block-axo-change-link"
		role="button"
		onClick={ ( event ) => {
			event.preventDefault();
			onChangeShippingAddressClick();
		} }
	>
		Choose a different shipping address
	</a>
);

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

export const injectShippingChangeButton = ( onChangeShippingAddressClick ) => {
	const existingButton = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);

	if ( ! existingButton ) {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		createRoot( container ).render(
			<ShippingChangeButtonManager
				onChangeShippingAddressClick={ onChangeShippingAddressClick }
			/>
		);
	} else {
		console.log(
			'Shipping change button already exists. Skipping injection.'
		);
	}
};

export const removeShippingChangeButton = () => {
	const span = document.querySelector(
		'#shipping-fields .wc-block-checkout-axo-block-card__edit'
	);
	if ( span ) {
		createRoot( span ).unmount();
		span.remove();
	}
};

export default ShippingChangeButtonManager;
