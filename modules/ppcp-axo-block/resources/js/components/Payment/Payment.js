import { useEffect, useCallback, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Card } from '../Card';
import { STORE_NAME } from '../../stores/axoStore';

export const Payment = ( { fastlaneSdk, onPaymentLoad } ) => {
	const [ isCardElementReady, setIsCardElementReady ] = useState( false );
	const { isGuest, isEmailLookupCompleted } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			isEmailLookupCompleted:
				select( STORE_NAME ).getIsEmailLookupCompleted(),
		} ),
		[]
	);

	const loadPaymentComponent = useCallback( async () => {
		if ( isGuest && isEmailLookupCompleted && isCardElementReady ) {
			const paymentComponent = await fastlaneSdk.FastlaneCardComponent(
				{}
			);
			paymentComponent.render( `#fastlane-card` );
			onPaymentLoad( paymentComponent );
		}
	}, [
		isGuest,
		isEmailLookupCompleted,
		isCardElementReady,
		fastlaneSdk,
		onPaymentLoad,
	] );

	useEffect( () => {
		if ( isGuest && isEmailLookupCompleted ) {
			setIsCardElementReady( true );
		}
	}, [ isGuest, isEmailLookupCompleted ] );

	useEffect( () => {
		loadPaymentComponent();
	}, [ loadPaymentComponent ] );

	if ( isGuest ) {
		if ( isEmailLookupCompleted ) {
			return <div id="fastlane-card" key="fastlane-card" />;
		}
		return (
			<div id="ppcp-axo-block-radio-content">
				{ __(
					'Enter your email address above to continue.',
					'woocommerce-paypal-payments'
				) }
			</div>
		);
	}
	return <Card fastlaneSdk={ fastlaneSdk } showWatermark={ ! isGuest } />;
};
