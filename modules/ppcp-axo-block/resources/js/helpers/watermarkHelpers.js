import { createElement, useEffect, createRoot } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { FastlaneWatermark } from '../components/FastlaneWatermark';
import { STORE_NAME } from '../stores/axoStore';

let watermarkReference = {
	container: null,
	root: null,
};

const WatermarkManager = ( { fastlaneSdk, isLoaded } ) => {
	const isGuest = useSelect( ( select ) =>
		select( STORE_NAME ).getIsGuest()
	);
	const isAxoActive = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoActive()
	);
	const isAxoScriptLoaded = useSelect( ( select ) =>
		select( STORE_NAME ).isAxoScriptLoaded()
	);

	useEffect( () => {
		const createWatermark = () => {
			const textInputContainer = document.querySelector(
				'.wp-block-woocommerce-checkout-contact-information-block .wc-block-components-text-input'
			);

			if ( textInputContainer && ! watermarkReference.container ) {
				const emailInput = textInputContainer.querySelector(
					'input[type="email"]'
				);

				if ( emailInput ) {
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
				if ( ! isAxoActive && ! isAxoScriptLoaded ) {
					watermarkReference.root.render(
						createElement( 'span', {
							className: 'wc-block-components-spinner',
							'aria-hidden': 'true',
						} )
					);
				} else if ( isAxoActive ) {
					watermarkReference.root.render(
						createElement( FastlaneWatermark, {
							fastlaneSdk,
							name: 'fastlane-watermark-email',
							includeAdditionalInfo: isGuest,
						} )
					);
				} else {
					watermarkReference.root.render( null );
				}
			}
		};

		const removeWatermark = () => {
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
		};

		if ( isAxoActive || ( ! isAxoActive && ! isAxoScriptLoaded ) ) {
			createWatermark();
		} else {
			removeWatermark();
		}

		return removeWatermark;
	}, [ fastlaneSdk, isGuest, isAxoActive, isLoaded, isAxoScriptLoaded ] );

	return null;
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
	watermarkReference = { container: null, root: null };
};

export default WatermarkManager;
