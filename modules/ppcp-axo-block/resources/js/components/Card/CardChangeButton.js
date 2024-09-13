import { createElement } from '@wordpress/element';

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
		'Choose a different card'
	);

export default CardChangeButton;
