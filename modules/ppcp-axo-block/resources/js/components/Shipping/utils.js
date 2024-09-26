import { createRoot } from '@wordpress/element';
import ShippingChangeButtonManager from './ShippingChangeButtonManager';

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
