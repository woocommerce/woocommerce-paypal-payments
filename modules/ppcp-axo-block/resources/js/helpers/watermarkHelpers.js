import { createElement, useEffect, createRoot } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { FastlaneWatermark } from '../components/FastlaneWatermark';
import { STORE_NAME } from '../stores/axoStore';

let watermarkReference = {
	container: null,
	root: null,
};

console.log( 'WatermarkManager module loaded' );

const WatermarkManager = ( { fastlaneSdk } ) => {
	console.log( 'WatermarkManager rendering', { fastlaneSdk } );

	const isGuest = useSelect( ( select ) =>
		select( STORE_NAME ).getIsGuest()
	);
	const isAxoActive = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoActive()
	);
	const isAxoScriptLoaded = useSelect( ( select ) =>
		select( STORE_NAME ).isAxoScriptLoaded()
	);

	console.log( 'WatermarkManager state', {
		isGuest,
		isAxoActive,
		isAxoScriptLoaded,
	} );

	useEffect( () => {
		console.log( 'WatermarkManager useEffect triggered' );

		const createWatermark = () => {
			console.log( 'Creating watermark' );
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
					watermarkReference.container =
						document.createElement( 'div' );
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

			if ( watermarkReference.root ) {
				console.log( 'Rendering watermark content' );
				if ( ! isAxoActive && ! isAxoScriptLoaded ) {
					console.log( 'Rendering spinner' );
					watermarkReference.root.render(
						createElement( 'span', {
							className: 'wc-block-components-spinner',
							'aria-hidden': 'true',
						} )
					);
				} else if ( isAxoActive ) {
					console.log( 'Rendering FastlaneWatermark' );
					watermarkReference.root.render(
						createElement( FastlaneWatermark, {
							fastlaneSdk,
							name: 'fastlane-watermark-email',
							includeAdditionalInfo: isGuest,
						} )
					);
				} else {
					console.log( 'Rendering null content' );
					watermarkReference.root.render( null );
				}
			}
		};

		const removeWatermark = () => {
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
			watermarkReference = { container: null, root: null };
			console.log( 'Watermark removed' );
		};

		if ( isAxoActive || ( ! isAxoActive && ! isAxoScriptLoaded ) ) {
			console.log( 'Conditions met, creating watermark' );
			createWatermark();
		} else {
			console.log( 'Conditions not met, removing watermark' );
			removeWatermark();
		}

		return removeWatermark;
	}, [ fastlaneSdk, isGuest, isAxoActive, isAxoScriptLoaded ] );

	return null;
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
	console.log( 'Removing watermark (external call)' );
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
	watermarkReference = { container: null, root: null };
	console.log( 'Watermark removed (external call)' );
};

export default WatermarkManager;
