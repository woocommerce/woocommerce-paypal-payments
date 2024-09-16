import { useEffect, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Card } from '../Card';
import { STORE_NAME } from '../../stores/axoStore';

export const Payment = ( { fastlaneSdk, card, onPaymentLoad } ) => {
	const isGuest = useSelect( ( select ) =>
		select( STORE_NAME ).getIsGuest()
	);

	// Memoized Fastlane card rendering
	const loadPaymentComponent = useCallback( async () => {
		if ( isGuest ) {
			const paymentComponent = await fastlaneSdk.FastlaneCardComponent(
				{}
			);
			paymentComponent.render( `#fastlane-card` );
			onPaymentLoad( paymentComponent );
		}
	}, [ isGuest, fastlaneSdk, onPaymentLoad ] );

	useEffect( () => {
		loadPaymentComponent();
	}, [ loadPaymentComponent ] );

	return isGuest ? (
		<div id="fastlane-card" key="fastlane-card" />
	) : (
		<Card
			card={ card }
			fastlaneSdk={ fastlaneSdk }
			showWatermark={ ! isGuest }
		/>
	);
};
