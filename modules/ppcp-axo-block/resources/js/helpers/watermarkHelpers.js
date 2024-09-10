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
		const emailInput = document.getElementById( 'email' );

		if ( emailInput ) {
			if ( ! watermarkReference.container ) {
				watermarkReference.container = document.createElement( 'div' );
				watermarkReference.container.setAttribute(
					'class',
					'ppcp-axo-block-watermark-container'
				);

				const emailLabel =
					emailInput.parentNode.querySelector( 'label[for="email"]' );
				if ( emailLabel ) {
					emailLabel.parentNode.insertBefore(
						watermarkReference.container,
						emailLabel.nextSibling
					);
				} else {
					emailInput.parentNode.insertBefore(
						watermarkReference.container,
						emailInput.nextSibling
					);
				}

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
			} else {
				console.warn( 'Watermark root not found' );
			}
		} else {
			console.warn( 'Email input not found' );
		}
	}, [ fastlaneSdk, isGuest ] );

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
