import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Renders a button to change the selected card in the checkout process.
 *
 * @param {Object}   props
 * @param {Function} props.onChangeButtonClick - Callback function to handle the click event.
 * @return {JSX.Element} The rendered button as an anchor tag.
 */
const CardChangeButton = ( { onChangeButtonClick } ) =>
	createElement(
		'a',
		{
			className:
				'wc-block-checkout-axo-block-card__edit wc-block-axo-change-link',
			role: 'button',
			onClick: ( event ) => {
				// Prevent default anchor behavior
				event.preventDefault();
				// Call the provided click handler
				onChangeButtonClick();
			},
		},
		__( 'Choose a different card', 'woocommerce-paypal-payments' )
	);

export default CardChangeButton;
