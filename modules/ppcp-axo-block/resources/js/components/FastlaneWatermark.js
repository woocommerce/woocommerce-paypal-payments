import { useEffect } from '@wordpress/element';

export const FastlaneWatermark = ( {
	fastlaneSdk,
	name = 'fastlane-watermark-container',
	includeAdditionalInfo = true,
} ) => {
	// This web component can be instantiated inside of a useEffect.
	useEffect( () => {
		( async () => {
			const watermark = await fastlaneSdk.FastlaneWatermarkComponent( {
				includeAdditionalInfo,
			} );
			// The ID can be a react element
			watermark.render( `#${ name }` );
		} )();
	}, [] );

	// Give the react element the ID that you will render the watermark component into.
	return <div id={ name } />;
};
