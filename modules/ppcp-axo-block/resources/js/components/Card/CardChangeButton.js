import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const CardChangeButton = ( { onChangeButtonClick } ) =>
	createElement(
		'a',
		{
			className:
				'wc-block-checkout-axo-block-card__edit wc-block-axo-change-link',
			role: 'button',
			onClick: ( event ) => {
				event.preventDefault();
				onChangeButtonClick();
			},
		},
		__( 'Choose a different card', 'woocommerce-paypal-payments' )
	);

export default CardChangeButton;
