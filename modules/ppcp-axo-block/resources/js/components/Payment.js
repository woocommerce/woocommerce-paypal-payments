import { useEffect, useCallback } from '@wordpress/element';
import { CreditCard } from './CreditCard';

export const Payment = ( {
	fastlaneSdk,
	card,
	shippingAddress,
	isGuest,
	onPaymentLoad,
} ) => {
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
		<CreditCard
			key="custom-card"
			card={ card }
			shippingAddress={ shippingAddress }
			fastlaneSdk={ fastlaneSdk }
		/>
	);
};
