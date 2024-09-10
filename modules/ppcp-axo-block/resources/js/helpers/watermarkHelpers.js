import { createElement, useEffect, createRoot } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { FastlaneWatermark } from '../components/FastlaneWatermark';
import { STORE_NAME } from '../stores/axoStore';

let watermarkReference = {
	container: null,
	root: null,
};

const WatermarkManager = ( { fastlaneSdk } ) => {
	const isGuest = useSelect( ( select ) =>
		select( STORE_NAME ).getIsGuest()
	);
	const isAxoActive = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoActive()
	);

	useEffect( () => {
		const textInputContainer = document.querySelector(
			'.wp-block-woocommerce-checkout-contact-information-block .wc-block-components-text-input'
		);

		if ( textInputContainer ) {
			const emailInput = textInputContainer.querySelector(
				'input[type="email"]'
			);

			if ( emailInput ) {
				if ( ! watermarkReference.container ) {
					watermarkReference.container =
						document.createElement( 'div' );
					watermarkReference.container.setAttribute(
						'class',
						'wc-block-checkout-axo-block-watermark-container'
					);

					// Insert the watermark container after the email input
					emailInput.parentNode.insertBefore(
						watermarkReference.container,
						emailInput.nextSibling
					);

					watermarkReference.root = createRoot(
						watermarkReference.container
					);
				}

				if ( watermarkReference.root && isAxoActive ) {
					watermarkReference.root.render(
						createElement( FastlaneWatermark, {
							fastlaneSdk,
							name: 'fastlane-watermark-email',
							includeAdditionalInfo: isGuest,
						} )
					);
				} else if ( ! isAxoActive && watermarkReference.root ) {
					watermarkReference.root.render( null );
				}
			} else {
				console.warn( 'Email input not found' );
			}
		} else {
			console.warn( 'Text input container not found' );
		}
	}, [ fastlaneSdk, isGuest, isAxoActive ] );

	return null;
};

export const setupWatermark = ( fastlaneSdk ) => {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	createRoot( container ).render(
		createElement( WatermarkManager, { fastlaneSdk } )
	);
};

export const removeWatermark = () => {
	if ( watermarkReference.root ) {
		watermarkReference.root.unmount();
	}
	if (
		watermarkReference.container &&
		watermarkReference.container.parentNode
	) {
		watermarkReference.container.parentNode.removeChild(
			watermarkReference.container
		);
	}
	watermarkReference = { container: null, root: null };
};

export default WatermarkManager;
