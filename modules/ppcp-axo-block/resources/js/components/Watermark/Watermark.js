import { useEffect, useRef } from '@wordpress/element';

const Watermark = ( {
	fastlaneSdk,
	name = 'fastlane-watermark-container',
	includeAdditionalInfo = true,
} ) => {
	const containerRef = useRef( null );
	const watermarkRef = useRef( null );

	useEffect( () => {
		const renderWatermark = async () => {
			if ( ! containerRef.current ) {
				return;
			}

			// Clear the container
			containerRef.current.innerHTML = '';

			try {
				const watermark = await fastlaneSdk.FastlaneWatermarkComponent(
					{
						includeAdditionalInfo,
					}
				);

				watermarkRef.current = watermark;
				watermark.render( `#${ name }` );
			} catch ( error ) {
				console.error( 'Error rendering watermark:', error );
			}
		};

		renderWatermark();

		return () => {
			if ( containerRef.current ) {
				containerRef.current.innerHTML = '';
			}
		};
	}, [ fastlaneSdk, name, includeAdditionalInfo ] );

	return <div id={ name } ref={ containerRef } />;
};

export default Watermark;
