import { useEffect, useCallback } from '@wordpress/element';

const usePaymentSetupEffect = (
	onPaymentSetup,
	handlePaymentSetup,
	setPaymentComponent
) => {
	/**
	 * `onPaymentSetup()` fires when we enter the "PROCESSING" state in the checkout flow.
	 * It pre-processes the payment details and returns data for server-side processing.
	 */
	useEffect( () => {
		const unsubscribe = onPaymentSetup( handlePaymentSetup );

		return () => {
			unsubscribe();
		};
	}, [ onPaymentSetup, handlePaymentSetup ] );

	const handlePaymentLoad = useCallback(
		( component ) => {
			setPaymentComponent( component );
		},
		[ setPaymentComponent ]
	);

	return { handlePaymentLoad };
};

export default usePaymentSetupEffect;
