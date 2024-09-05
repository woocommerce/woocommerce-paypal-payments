import ReactDOM from 'react-dom/client';
import { FastlaneWatermark } from '../components/FastlaneWatermark';

export const setupWatermark = ( fastlaneSdk, shouldIncludeAdditionalInfo ) => {
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

		const watermarkElement = document.createElement( 'div' );
		watermarkContainer.appendChild( watermarkElement );

		watermarkRoot = ReactDOM.createRoot( watermarkElement );
		watermarkRoot.render(
			<FastlaneWatermark
				fastlaneSdk={ fastlaneSdk }
				name="fastlane-watermark-email"
				includeAdditionalInfo={ shouldIncludeAdditionalInfo }
			/>
		);
	}

	return { watermarkRoot, watermarkContainer, emailInput };
};

export const cleanupWatermark = ( {
	watermarkRoot,
	watermarkContainer,
	emailInput,
	onEmailSubmit,
} ) => {
	if ( watermarkRoot && watermarkContainer ) {
		watermarkRoot.unmount();
		watermarkContainer.parentNode.removeChild( watermarkContainer );
		console.log( 'Fastlane watermark removed' );
	}
	if ( emailInput ) {
		emailInput.removeEventListener( 'keyup', async ( event ) => {
			const email = event.target.value;
			if ( email ) {
				await onEmailSubmit( email );
			}
		} );
	}
};
