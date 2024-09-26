import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../../stores/axoStore';
import {
	createWatermarkContainer,
	removeWatermark,
	updateWatermarkContent,
} from './utils';

const WatermarkManager = ( { fastlaneSdk } ) => {
	const isGuest = useSelect( ( select ) =>
		select( STORE_NAME ).getIsGuest()
	);
	const isAxoActive = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoActive()
	);
	const isAxoScriptLoaded = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoScriptLoaded()
	);

	useEffect( () => {
		if ( isAxoActive || ( ! isAxoActive && ! isAxoScriptLoaded ) ) {
			createWatermarkContainer();
			updateWatermarkContent( {
				isAxoActive,
				isAxoScriptLoaded,
				fastlaneSdk,
				isGuest,
			} );
		} else {
			removeWatermark();
		}

		return removeWatermark;
	}, [ fastlaneSdk, isGuest, isAxoActive, isAxoScriptLoaded ] );

	return null;
};

export default WatermarkManager;
