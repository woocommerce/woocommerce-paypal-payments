import { useEffect, useRef } from '@wordpress/element';
import { log } from '../../../../../ppcp-axo/resources/js/Helper/Debug';

/**
 * Watermark component for displaying AXO watermark.
 *
 * @param {Object}  props
 * @param {Object}  props.fastlaneSdk                           - The Fastlane SDK instance.
 * @param {string}  [props.name='fastlane-watermark-container'] - ID for the watermark container.
 * @param {boolean} [props.includeAdditionalInfo=true]          - Whether to include additional info in the watermark.
 * @return {JSX.Element} The watermark container element.
 */
const Watermark = ( {
	fastlaneSdk,
	name = 'fastlane-watermark-container',
	includeAdditionalInfo = true,
} ) => {
	const containerRef = useRef( null );
	const watermarkRef = useRef( null );

	useEffect( () => {
		/**
		 * Renders the Fastlane watermark.
		 */
		const renderWatermark = async () => {
			if ( ! containerRef.current ) {
				return;
			}

			// Clear the container before rendering
			containerRef.current.innerHTML = '';

			try {
				// Create and render the Fastlane watermark
				const watermark = await fastlaneSdk.FastlaneWatermarkComponent(
					{
						includeAdditionalInfo,
					}
				);

				watermarkRef.current = watermark;
				watermark.render( `#${ name }` );
			} catch ( error ) {
				log( `Error rendering watermark: ${ error }`, 'error' );
			}
		};

		renderWatermark();

		// Cleanup function to clear the container on unmount
		return () => {
			if ( containerRef.current ) {
				containerRef.current.innerHTML = '';
			}
		};
	}, [ fastlaneSdk, name, includeAdditionalInfo ] );

	// Render the container for the watermark
	return <div id={ name } ref={ containerRef } />;
};

export default Watermark;
