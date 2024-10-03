import { createElement, createRoot, useEffect } from '@wordpress/element';
import CardChangeButton from './CardChangeButton';

/**
 * Manages the insertion and removal of the CardChangeButton in the DOM.
 *
 * @param {Object}   props
 * @param {Function} props.onChangeButtonClick - Callback function for when the card change button is clicked.
 * @return {null} This component doesn't render any visible elements directly.
 */
const CardChangeButtonManager = ( { onChangeButtonClick } ) => {
	useEffect( () => {
		const radioLabelElement = document.getElementById(
			'ppcp-axo-block-radio-label'
		);

		if ( radioLabelElement ) {
			// Check if the change button doesn't already exist
			if (
				! radioLabelElement.querySelector(
					'.wc-block-checkout-axo-block-card__edit'
				)
			) {
				// Create a new container for the button
				const buttonContainer = document.createElement( 'div' );
				radioLabelElement.appendChild( buttonContainer );

				// Create a React root and render the CardChangeButton
				const root = createRoot( buttonContainer );
				root.render(
					createElement( CardChangeButton, { onChangeButtonClick } )
				);
			}
		}

		// Cleanup function to remove the button when the component unmounts
		return () => {
			const button = document.querySelector(
				'.wc-block-checkout-axo-block-card__edit'
			);
			if ( button && button.parentNode ) {
				button.parentNode.remove();
			}
		};
	}, [ onChangeButtonClick ] );

	// This component doesn't render anything directly
	return null;
};

export default CardChangeButtonManager;
