import { __ } from '@wordpress/i18n';

/**
 * Renders a button to change the shipping address.
 *
 * @param {Object}   props
 * @param {Function} props.onChangeShippingAddressClick - Callback function to handle the click event.
 * @return {JSX.Element} The rendered button as an anchor tag.
 */
const ShippingChangeButton = ( { onChangeShippingAddressClick } ) => (
	<a
		className="wc-block-axo-change-link"
		role="button"
		onClick={ ( event ) => {
			// Prevent default anchor behavior
			event.preventDefault();
			// Call the provided click handler
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
