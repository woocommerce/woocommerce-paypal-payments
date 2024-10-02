import { __ } from '@wordpress/i18n';

const ShippingChangeButton = ( { onChangeShippingAddressClick } ) => (
	<a
		className="wc-block-axo-change-link"
		role="button"
		onClick={ ( event ) => {
			event.preventDefault();
			onChangeShippingAddressClick();
		} }
	>
		{ __(
			'Choose a different shipping address',
			'woocommerce-paypal-payments'
		) }
	</a>
);

export default ShippingChangeButton;
