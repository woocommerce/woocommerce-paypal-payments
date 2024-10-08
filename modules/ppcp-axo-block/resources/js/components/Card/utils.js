import { createElement, createRoot } from '@wordpress/element';
import CardChangeButtonManager from './CardChangeButtonManager';

/**
 * Injects a card change button into the DOM.
 *
 * @param {Function} onChangeButtonClick - Callback function for when the card change button is clicked.
 */
export const injectCardChangeButton = ( onChangeButtonClick ) => {
	// Create a container for the button
	const container = document.createElement( 'div' );
	document.body.appendChild( container );

	// Render the CardChangeButtonManager in the new container
	createRoot( container ).render(
		createElement( CardChangeButtonManager, { onChangeButtonClick } )
	);
};

/**
 * Removes the card change button from the DOM if it exists.
 */
export const removeCardChangeButton = () => {
	const button = document.querySelector(
		'.wc-block-checkout-axo-block-card__edit'
	);

	// Remove the button's parent node if it exists
	if ( button && button.parentNode ) {
		button.parentNode.remove();
	}
};
