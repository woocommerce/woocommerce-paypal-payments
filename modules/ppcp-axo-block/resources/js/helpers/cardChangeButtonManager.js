import { createElement, useEffect, createRoot } from '@wordpress/element';

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

const CardChangeButtonManager = ( { onChangeButtonClick } ) => {
	useEffect( () => {
		const radioLabelElement = document.getElementById(
			'ppcp-axo-block-radio-label'
		);

		if ( radioLabelElement ) {
			if (
				! radioLabelElement.querySelector(
					'.wc-block-checkout-axo-block-card__edit'
				)
			) {
				const buttonContainer = document.createElement( 'div' );
				radioLabelElement.appendChild( buttonContainer );

				const root = createRoot( buttonContainer );
				root.render(
					createElement( CardChangeButton, { onChangeButtonClick } )
				);
			}
		}

		return () => {
			const button = document.querySelector(
				'.wc-block-checkout-axo-block-card__edit'
			);
			if ( button && button.parentNode ) {
				button.parentNode.remove();
			}
		};
	}, [ onChangeButtonClick ] );

	return null;
};

export const injectCardChangeButton = ( onChangeButtonClick ) => {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	createRoot( container ).render(
		createElement( CardChangeButtonManager, { onChangeButtonClick } )
	);
};

export const removeCardChangeButton = () => {
	const button = document.querySelector(
		'.wc-block-checkout-axo-block-card__edit'
	);
	if ( button && button.parentNode ) {
		button.parentNode.remove();
	}
};

export default CardChangeButtonManager;
