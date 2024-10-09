import { useEffect, useCallback } from '@wordpress/element';

/**
 * Custom hook to handle payment setup effects in the checkout flow.
 *
 * @param {Function} onPaymentSetup      - Function to subscribe to payment setup events.
 * @param {Function} handlePaymentSetup  - Callback to process payment setup.
 * @param {Function} setPaymentComponent - Function to update the payment component state.
 * @return {Object} Object containing the handlePaymentLoad function.
 */
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

	/**
	 * Callback function to handle payment component loading.
	 *
	 * @param {Object} component - The loaded payment component.
	 */
	const handlePaymentLoad = useCallback(
		( component ) => {
			setPaymentComponent( component );
		},
		[ setPaymentComponent ]
	);

	return { handlePaymentLoad };
};

export default usePaymentSetupEffect;
