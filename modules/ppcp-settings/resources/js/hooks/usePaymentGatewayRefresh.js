import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { STORE_NAME as PAYMENT_STORE_NAME } from '../data/payment';
import { OnboardingHooks } from '../data';
import { STORE_NAME as ONBOARDING_STORE_NAME } from '../data/onboarding';

/**
 * Custom hook for refreshing payment gateway data.
 *
 * @return {Object} Refresh API including the refresh function and state
 */
export const usePaymentGatewayRefresh = () => {
	const paymentDispatch = useDispatch( PAYMENT_STORE_NAME );
	const onboardingDispatch = useDispatch( ONBOARDING_STORE_NAME );
	const { gatewaysRefreshed } = OnboardingHooks.useGatewayRefresh();
	const { gatewaysSynced } = OnboardingHooks.useGatewaySync();
	const { refreshGateways } = onboardingDispatch;
	const { hydrate, refresh, reset } = paymentDispatch;

	const [ refreshCompleted, setRefreshCompleted ] = useState( false );
	const [ isRefreshing, setIsRefreshing ] = useState( false );
	const [ refreshError, setRefreshError ] = useState( null );
	// Only refresh if gateways are synced.
	const isReadyToRefresh = gatewaysSynced;

	/**
	 * Refreshes payment gateway data
	 *
	 * @return {Promise<boolean|Object>} True when completed successfully, or object with error details
	 */
	const refreshPaymentGateways = useCallback( async () => {
		if ( isRefreshing ) {
			return {
				success: false,
				skipped: true,
				reason: 'already-refreshing',
			};
		}

		if ( ! isReadyToRefresh ) {
			return {
				success: false,
				skipped: true,
				reason: 'not-ready',
			};
		}

		setIsRefreshing( true );
		setRefreshError( null );

		try {
			// Reset payment store if available.
			if ( typeof reset === 'function' ) {
				await reset();
			}

			// Fetch payment data.
			const response = await apiFetch( {
				path: `/wc/v3/wc_paypal/payment`,
				method: 'GET',
			} );

			// Update store with data.
			hydrate( response );

			// Refresh payment store if available.
			if ( typeof refresh === 'function' ) {
				await refresh();
			}

			// Update Redux state to mark gateways as refreshed.
			const result = await refreshGateways();

			setRefreshCompleted( true );
			return { success: true };
		} catch ( error ) {
			setRefreshError( error );
			return { success: false, error };
		} finally {
			setIsRefreshing( false );
		}
	}, [
		isRefreshing,
		isReadyToRefresh,
		reset,
		hydrate,
		refresh,
		refreshGateways,
	] );

	// Auto-trigger refresh when conditions are met.
	useEffect( () => {
		if (
			isReadyToRefresh &&
			! gatewaysRefreshed &&
			! isRefreshing &&
			! refreshCompleted
		) {
			refreshPaymentGateways().catch( () => {
				// Silent catch to prevent unhandled promise rejections.
			} );
		}
	}, [
		isReadyToRefresh,
		gatewaysRefreshed,
		isRefreshing,
		refreshCompleted,
		refreshPaymentGateways,
	] );

	return {
		refreshPaymentGateways,
		refreshCompleted,
		isRefreshing,
		refreshError,
		gatewaysRefreshed: gatewaysRefreshed || refreshCompleted,
	};
};

export default usePaymentGatewayRefresh;
