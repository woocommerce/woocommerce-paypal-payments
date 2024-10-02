import { createElement, createRoot } from '@wordpress/element';
import CardChangeButtonManager from './CardChangeButtonManager';

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
