import { useEffect, useCallback } from '@wordpress/element';

const usePaymentSetupEffect = ( onPaymentSetup, handlePaymentSetup ) => {
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

	const handlePaymentLoad = useCallback( ( component ) => {
		// We'll return this function instead of calling setPaymentComponent directly
		return component;
	}, [] );

	return { handlePaymentLoad };
};

export default usePaymentSetupEffect;
