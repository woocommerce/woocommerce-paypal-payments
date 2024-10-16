import { createElement } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../../stores/axoStore';

/**
 * Renders a button to change the selected card in the checkout process.
 *
 * @return {JSX.Element|null} The rendered button as an anchor tag, or null if conditions aren't met.
 */
const CardChangeButton = () => {
	const { isGuest, cardDetails, cardChangeHandler } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			cardDetails: select( STORE_NAME ).getCardDetails(),
			cardChangeHandler: select( STORE_NAME ).getCardChangeHandler(),
		} ),
		[]
	);

	if ( isGuest || ! cardDetails || ! cardChangeHandler ) {
		return null;
	}

	return createElement(
		'a',
		{
			className:
				'wc-block-checkout-axo-block-card__edit wc-block-axo-change-link',
			role: 'button',
			onClick: ( event ) => {
				// Prevent default anchor behavior
				event.preventDefault();
				// Call the provided click handler
				cardChangeHandler();
			},
		},
		__( 'Choose a different card', 'woocommerce-paypal-payments' )
	);
};

export default CardChangeButton;
