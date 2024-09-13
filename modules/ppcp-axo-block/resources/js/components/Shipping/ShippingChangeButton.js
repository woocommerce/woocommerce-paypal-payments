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

export default ShippingChangeButton;
