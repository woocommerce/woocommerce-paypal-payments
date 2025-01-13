/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import { STORE_NAME } from './constants';

const useTransient = ( key ) =>
	useSelect(
		( select ) => select( STORE_NAME ).transientData()?.[ key ],
		[ key ]
	);

const usePersistent = ( key ) =>
	useSelect(
		( select ) => select( STORE_NAME ).persistentData()?.[ key ],
		[ key ]
	);

const useHooks = () => {
	const {
		persist,
		setSandboxMode,
		setManualConnectionMode,
		sandboxOnboardingUrl,
		productionOnboardingUrl,
		authenticateWithCredentials,
		authenticateWithOAuth,
		setActiveModal,
		startWebhookSimulation,
		checkWebhookSimulationState,
	} = useDispatch( STORE_NAME );

	// Transient accessors.
	const isReady = useTransient( 'isReady' );
	const activeModal = useTransient( 'activeModal' );

	// Persistent accessors.
	const isSandboxMode = usePersistent( 'useSandbox' );
	const isManualConnectionMode = usePersistent( 'useManualConnection' );
	const merchant = useSelect(
		( select ) => select( STORE_NAME ).merchant(),
		[]
	);

	// Read-only properties.
	const wooSettings = useSelect(
		( select ) => select( STORE_NAME ).wooSettings(),
		[]
	);
	const features = useSelect(
		( select ) => select( STORE_NAME ).features(),
		[]
	);
	const webhooks = useSelect(
		( select ) => select( STORE_NAME ).webhooks(),
		[]
	);

	const savePersistent = async ( setter, value ) => {
		setter( value );
		await persist();
	};

	return {
		isReady,
		activeModal,
		setActiveModal,
		isSandboxMode,
		setSandboxMode: ( state ) => {
			return savePersistent( setSandboxMode, state );
		},
		isManualConnectionMode,
		setManualConnectionMode: ( state ) => {
			return savePersistent( setManualConnectionMode, state );
		},
		sandboxOnboardingUrl,
		productionOnboardingUrl,
		authenticateWithCredentials,
		authenticateWithOAuth,
		merchant,
		wooSettings,
		features,
		webhooks,
		startWebhookSimulation,
		checkWebhookSimulationState,
	};
};

export const useSandbox = () => {
	const { isSandboxMode, setSandboxMode, sandboxOnboardingUrl } = useHooks();

	return { isSandboxMode, setSandboxMode, sandboxOnboardingUrl };
};

export const useProduction = () => {
	const { productionOnboardingUrl } = useHooks();

	return { productionOnboardingUrl };
};

export const useAuthentication = () => {
	const {
		isManualConnectionMode,
		setManualConnectionMode,
		authenticateWithCredentials,
		authenticateWithOAuth,
	} = useHooks();

	return {
		isManualConnectionMode,
		setManualConnectionMode,
		authenticateWithCredentials,
		authenticateWithOAuth,
	};
};

export const useWooSettings = () => {
	const { wooSettings } = useHooks();

	return wooSettings;
};

export const useWebhooks = () => {
	const {
		webhooks,
		setWebhooks,
		registerWebhooks,
		startWebhookSimulation,
		checkWebhookSimulationState,
	} = useHooks();
	return {
		webhooks,
		setWebhooks,
		registerWebhooks,
		startWebhookSimulation,
		checkWebhookSimulationState,
	};
};
export const useMerchantInfo = () => {
	const { isReady, merchant, features } = useHooks();
	const { refreshMerchantData } = useDispatch( STORE_NAME );

	const verifyLoginStatus = useCallback( async () => {
		const result = await refreshMerchantData();

		if ( ! result.success ) {
			throw new Error( result?.message || result?.error?.message );
		}

		// Verify if the server state is "connected" and we have a merchant ID.
		return merchant?.isConnected && merchant?.id;
	}, [ refreshMerchantData, merchant ] );

	return {
		isReady,
		merchant, // Merchant details
		features, // Eligible merchant features
		verifyLoginStatus, // Callback
	};
};

export const useActiveModal = () => {
	const { activeModal, setActiveModal } = useHooks();
	return { activeModal, setActiveModal };
};

// -- Not using the `useHooks()` data provider --

export const useBusyState = () => {
	const { startActivity, stopActivity } = useDispatch( STORE_NAME );

	// Resolved value (object), contains a list of all running actions.
	const activities = useSelect(
		( select ) => select( STORE_NAME ).getActivityList(),
		[]
	);

	// Derive isBusy state from activities
	const isBusy = Object.keys( activities ).length > 0;

	// HOC that starts and stops an activity while the callback is executed.
	const withActivity = useCallback(
		async ( id, description, asyncFn ) => {
			startActivity( id, description );
			try {
				return await asyncFn();
			} finally {
				stopActivity( id );
			}
		},
		[ startActivity, stopActivity ]
	);

	return {
		withActivity, // HOC
		isBusy, // Boolean.
		activities, // Object.
	};
};
