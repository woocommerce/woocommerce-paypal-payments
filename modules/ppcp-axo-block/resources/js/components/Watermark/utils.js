import { createElement, createRoot } from '@wordpress/element';
import { Watermark, WatermarkManager } from '../Watermark';

const watermarkReference = {
	container: null,
	root: null,
};

export const createWatermarkContainer = () => {
	const textInputContainer = document.querySelector(
		'.wp-block-woocommerce-checkout-contact-information-block .wc-block-components-text-input'
	);

	if ( textInputContainer && ! watermarkReference.container ) {
		const emailInput =
			textInputContainer.querySelector( 'input[id="email"]' );

		if ( emailInput ) {
			watermarkReference.container = document.createElement( 'div' );
			watermarkReference.container.setAttribute(
				'class',
				'wc-block-checkout-axo-block-watermark-container'
			);

			const emailButton = textInputContainer.querySelector(
				'.wc-block-axo-email-submit-button-container'
			);

			// If possible, insert the watermark after the "Continue" button.
			const insertAfterElement = emailButton || emailInput;

			insertAfterElement.parentNode.insertBefore(
				watermarkReference.container,
				insertAfterElement.nextSibling
			);

			watermarkReference.root = createRoot(
				watermarkReference.container
			);
		}
	}
};

export const setupWatermark = ( fastlaneSdk ) => {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	root.render( createElement( WatermarkManager, { fastlaneSdk } ) );

	return () => {
		root.unmount();
		if ( container && container.parentNode ) {
			container.parentNode.removeChild( container );
		}
	};
};

export const removeWatermark = () => {
	if ( watermarkReference.root ) {
		watermarkReference.root.unmount();
	}
	if ( watermarkReference.container ) {
		if ( watermarkReference.container.parentNode ) {
			watermarkReference.container.parentNode.removeChild(
				watermarkReference.container
			);
		} else {
			const detachedContainer = document.querySelector(
				'.wc-block-checkout-axo-block-watermark-container'
			);
			if ( detachedContainer ) {
				detachedContainer.remove();
			}
		}
	}
	Object.assign( watermarkReference, { container: null, root: null } );
};

export const renderWatermarkContent = ( content ) => {
	if ( watermarkReference.root ) {
		watermarkReference.root.render( content );
	}
};

export const updateWatermarkContent = ( {
	isAxoActive,
	isAxoScriptLoaded,
	fastlaneSdk,
	isGuest,
} ) => {
	if ( ! isAxoActive && ! isAxoScriptLoaded ) {
		renderWatermarkContent(
			createElement( 'span', {
				className: 'wc-block-components-spinner',
				'aria-hidden': 'true',
			} )
		);
	} else if ( isAxoActive ) {
		renderWatermarkContent(
			createElement( Watermark, {
				fastlaneSdk,
				name: 'fastlane-watermark-email',
				includeAdditionalInfo: isGuest,
			} )
		);
	} else {
		renderWatermarkContent( null );
	}
};
