import { createElement, createRoot } from '@wordpress/element';
import { Watermark, WatermarkManager } from '../Watermark';

const watermarkReference = {
	container: null,
	root: null,
};

export const createWatermarkContainer = () => {
	console.log( 'Creating watermark container' );
	const textInputContainer = document.querySelector(
		'.wp-block-woocommerce-checkout-contact-information-block .wc-block-components-text-input'
	);

	if ( textInputContainer && ! watermarkReference.container ) {
		console.log(
			'Text input container found, creating watermark container'
		);
		const emailInput =
			textInputContainer.querySelector( 'input[id="email"]' );

		if ( emailInput ) {
			console.log( 'Email input found, setting up watermark' );
			watermarkReference.container = document.createElement( 'div' );
			watermarkReference.container.setAttribute(
				'class',
				'wc-block-checkout-axo-block-watermark-container'
			);

			emailInput.parentNode.insertBefore(
				watermarkReference.container,
				emailInput.nextSibling
			);

			watermarkReference.root = createRoot(
				watermarkReference.container
			);
		}
	}
};

export const setupWatermark = ( fastlaneSdk ) => {
	console.log( 'Setting up watermark', { fastlaneSdk } );
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	root.render( createElement( WatermarkManager, { fastlaneSdk } ) );

	return () => {
		console.log( 'Cleaning up watermark setup' );
		root.unmount();
		if ( container && container.parentNode ) {
			container.parentNode.removeChild( container );
		}
	};
};

export const removeWatermark = () => {
	console.log( 'Removing watermark' );
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
	console.log( 'Watermark removed' );
};

export const renderWatermarkContent = ( content ) => {
	if ( watermarkReference.root ) {
		console.log( 'Rendering watermark content' );
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
		console.log( 'Rendering spinner' );
		renderWatermarkContent(
			createElement( 'span', {
				className: 'wc-block-components-spinner',
				'aria-hidden': 'true',
			} )
		);
	} else if ( isAxoActive ) {
		console.log( 'Rendering FastlaneWatermark' );
		renderWatermarkContent(
			createElement( Watermark, {
				fastlaneSdk,
				name: 'fastlane-watermark-email',
				includeAdditionalInfo: isGuest,
			} )
		);
	} else {
		console.log( 'Rendering null content' );
		renderWatermarkContent( null );
	}
};
