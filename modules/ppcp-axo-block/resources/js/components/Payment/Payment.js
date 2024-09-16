import { useEffect, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Card } from '../Card';
import { STORE_NAME } from '../../stores/axoStore';

export const Payment = ( { fastlaneSdk, card, onPaymentLoad } ) => {
	const isGuest = useSelect( ( select ) =>
		select( STORE_NAME ).getIsGuest()
	);

	const isEmailLookupCompleted = useSelect( ( select ) =>
		select( STORE_NAME ).getIsEmailLookupCompleted()
	);

	const loadPaymentComponent = useCallback( async () => {
		if ( isGuest && isEmailLookupCompleted ) {
			const paymentComponent = await fastlaneSdk.FastlaneCardComponent(
				{}
			);
			paymentComponent.render( `#fastlane-card` );
			onPaymentLoad( paymentComponent );
		}
	}, [ isGuest, isEmailLookupCompleted, fastlaneSdk, onPaymentLoad ] );

	useEffect( () => {
		loadPaymentComponent();
	}, [ loadPaymentComponent ] );

	if ( isGuest ) {
		if ( isEmailLookupCompleted ) {
			return <div id="fastlane-card" key="fastlane-card" />;
		}
		return (
			<div id="ppcp-axo-block-radio-content">
				Enter your email address above to continue.
			</div>
		);
	}
	return (
		<Card
			card={ card }
			fastlaneSdk={ fastlaneSdk }
			showWatermark={ ! isGuest }
		/>
	);
};
