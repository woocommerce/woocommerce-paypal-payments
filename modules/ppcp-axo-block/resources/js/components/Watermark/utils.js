import { createElement, createRoot } from '@wordpress/element';
import { Watermark, WatermarkManager } from '../Watermark';

// Object to store references to the watermark container and root
const watermarkReference = {
	container: null,
	root: null,
};

/**
 * Creates a container for the watermark in the checkout contact information block.
 */
export const createWatermarkContainer = () => {
	const textInputContainer = document.querySelector(
		'.wp-block-woocommerce-checkout-contact-information-block .wc-block-components-text-input'
	);

	if ( textInputContainer && ! watermarkReference.container ) {
		const emailInput =
			textInputContainer.querySelector( 'input[id="email"]' );

		if ( emailInput ) {
			// Create watermark container
			watermarkReference.container = document.createElement( 'div' );
			watermarkReference.container.setAttribute(
				'class',
				'wc-block-checkout-axo-block-watermark-container'
			);

			const emailButton = textInputContainer.querySelector(
				'.wc-block-axo-email-submit-button-container'
			);

			// Insert the watermark after the "Continue" button or email input
			const insertAfterElement = emailButton || emailInput;

			insertAfterElement.parentNode.insertBefore(
				watermarkReference.container,
				insertAfterElement.nextSibling
			);

			// Create a root for the watermark
			watermarkReference.root = createRoot(
				watermarkReference.container
			);
		}
	}
};

/**
 * Sets up the watermark manager component.
 *
 * @param {Object} fastlaneSdk - The Fastlane SDK instance.
 * @return {Function} Cleanup function to remove the watermark.
 */
export const setupWatermark = ( fastlaneSdk ) => {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	root.render( createElement( WatermarkManager, { fastlaneSdk } ) );

	// Return cleanup function
	return () => {
		root.unmount();
		if ( container && container.parentNode ) {
			container.parentNode.removeChild( container );
		}
	};
};

/**
 * Removes the watermark from the DOM and resets the reference.
 */
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
			// Fallback removal if parent node is not available
			const detachedContainer = document.querySelector(
				'.wc-block-checkout-axo-block-watermark-container'
			);
			if ( detachedContainer ) {
				detachedContainer.remove();
			}
		}
	}
	// Reset watermark reference
	Object.assign( watermarkReference, { container: null, root: null } );
};

/**
 * Renders content in the watermark container.
 *
 * @param {ReactElement} content - The content to render.
 */
export const renderWatermarkContent = ( content ) => {
	if ( watermarkReference.root ) {
		watermarkReference.root.render( content );
	}
};

/**
 * Updates the watermark content based on the current state.
 *
 * @param {Object}  params                   - State parameters.
 * @param {boolean} params.isAxoActive       - Whether AXO is active.
 * @param {boolean} params.isAxoScriptLoaded - Whether AXO script is loaded.
 * @param {Object}  params.fastlaneSdk       - The Fastlane SDK instance.
 */
export const updateWatermarkContent = ( {
	isAxoActive,
	isAxoScriptLoaded,
	fastlaneSdk,
} ) => {
	if ( ! isAxoActive && ! isAxoScriptLoaded ) {
		// Show loading spinner
		renderWatermarkContent(
			createElement( 'span', {
				className: 'wc-block-components-spinner',
				'aria-hidden': 'true',
			} )
		);
	} else if ( isAxoActive ) {
		// Show Fastlane watermark
		renderWatermarkContent(
			createElement( Watermark, {
				fastlaneSdk,
				name: 'fastlane-watermark-email',
				includeAdditionalInfo: true,
			} )
		);
	} else {
		// Clear watermark content
		renderWatermarkContent( null );
	}
};
