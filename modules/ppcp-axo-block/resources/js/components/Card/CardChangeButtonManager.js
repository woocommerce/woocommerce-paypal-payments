import { createElement, createRoot, useEffect } from '@wordpress/element';
import CardChangeButton from './CardChangeButton';

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

export default CardChangeButtonManager;
