import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../../stores/axoStore';
import {
	createWatermarkContainer,
	removeWatermark,
	updateWatermarkContent,
} from './utils';

/**
 * Manages the lifecycle and content of the AXO watermark.
 *
 * @param {Object} props
 * @param {Object} props.fastlaneSdk - The Fastlane SDK instance.
 * @return {null} This component doesn't render any visible elements.
 */
const WatermarkManager = ( { fastlaneSdk } ) => {
	// Select relevant states from the AXO store
	const isAxoActive = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoActive()
	);
	const isAxoScriptLoaded = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoScriptLoaded()
	);

	useEffect( () => {
		if ( isAxoActive || ( ! isAxoActive && ! isAxoScriptLoaded ) ) {
			// Create watermark container and update content when AXO is active or loading
			createWatermarkContainer();
			updateWatermarkContent( {
				isAxoActive,
				isAxoScriptLoaded,
				fastlaneSdk,
			} );
		} else {
			// Remove watermark when AXO is inactive and not loading
			removeWatermark();
		}

		// Cleanup function to remove watermark on unmount
		return removeWatermark;
	}, [ fastlaneSdk, isAxoActive, isAxoScriptLoaded ] );

	// This component doesn't render anything directly
	return null;
};

export default WatermarkManager;
