import { useEffect, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Card } from '../Card';
import { STORE_NAME } from '../../stores/axoStore';

export const Payment = ( { fastlaneSdk, onPaymentLoad } ) => {
	const { isGuest, isEmailLookupCompleted } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			isEmailLookupCompleted:
				select( STORE_NAME ).getIsEmailLookupCompleted(),
		} ),
		[]
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
	return <Card fastlaneSdk={ fastlaneSdk } showWatermark={ ! isGuest } />;
};
