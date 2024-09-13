import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../../stores/axoStore';
import {
	createWatermarkContainer,
	removeWatermark,
	updateWatermarkContent,
} from './utils';

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
		select( STORE_NAME ).getIsAxoScriptLoaded()
	);

	console.log( 'WatermarkManager state', {
		isGuest,
		isAxoActive,
		isAxoScriptLoaded,
	} );

	useEffect( () => {
		console.log( 'WatermarkManager useEffect triggered' );

		if ( isAxoActive || ( ! isAxoActive && ! isAxoScriptLoaded ) ) {
			console.log( 'Conditions met, creating watermark' );
			createWatermarkContainer();
			updateWatermarkContent( {
				isAxoActive,
				isAxoScriptLoaded,
				fastlaneSdk,
				isGuest,
			} );
		} else {
			console.log( 'Conditions not met, removing watermark' );
			removeWatermark();
		}

		return removeWatermark;
	}, [ fastlaneSdk, isGuest, isAxoActive, isAxoScriptLoaded ] );

	return null;
};

export default WatermarkManager;
