import { createElement, useEffect, createRoot } from '@wordpress/element';
import { FastlaneWatermark } from '../components/FastlaneWatermark';

const WatermarkManager = ( {
	fastlaneSdk,
	shouldIncludeAdditionalInfo,
	onEmailSubmit,
} ) => {
	useEffect( () => {
		const emailInput = document.getElementById( 'email' );
		let watermarkRoot = null;
		let watermarkContainer = null;

		if ( emailInput ) {
			const emailLabel =
				emailInput.parentNode.querySelector( 'label[for="email"]' );
			watermarkContainer = document.createElement( 'div' );
			watermarkContainer.setAttribute(
				'class',
				'ppcp-axo-block-watermark-container'
			);

			if ( emailLabel ) {
				emailLabel.parentNode.insertBefore(
					watermarkContainer,
					emailLabel.nextSibling
				);
			} else {
				emailInput.parentNode.appendChild( watermarkContainer );
			}

			watermarkRoot = createRoot( watermarkContainer );
			watermarkRoot.render(
				createElement( FastlaneWatermark, {
					fastlaneSdk,
					name: 'fastlane-watermark-email',
					includeAdditionalInfo: shouldIncludeAdditionalInfo,
				} )
			);

			const handleEmailInput = async ( event ) => {
				const email = event.target.value;
				if ( email ) {
					await onEmailSubmit( email );
				}
			};

			emailInput.addEventListener( 'keyup', handleEmailInput );

			// Cleanup function
			return () => {
				if ( watermarkRoot ) {
					watermarkRoot.unmount();
				}
				if ( watermarkContainer && watermarkContainer.parentNode ) {
					watermarkContainer.parentNode.removeChild(
						watermarkContainer
					);
				}
				if ( emailInput ) {
					emailInput.removeEventListener( 'keyup', handleEmailInput );
				}
				console.log( 'Fastlane watermark removed' );
			};
		}
	}, [ fastlaneSdk, shouldIncludeAdditionalInfo, onEmailSubmit ] );

	return null;
};

export const setupWatermark = (
	fastlaneSdk,
	shouldIncludeAdditionalInfo,
	onEmailSubmit
) => {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	createRoot( container ).render(
		createElement( WatermarkManager, {
			fastlaneSdk,
			shouldIncludeAdditionalInfo,
			onEmailSubmit,
		} )
	);
};

export const removeWatermark = () => {
	const watermarkContainer = document.querySelector(
		'.ppcp-axo-block-watermark-container'
	);
	if ( watermarkContainer && watermarkContainer.parentNode ) {
		watermarkContainer.parentNode.removeChild( watermarkContainer );
	}
};

export default WatermarkManager;
