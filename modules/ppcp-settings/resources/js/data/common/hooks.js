/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

import { createHooksForStore } from '../utils';
import { STORE_NAME } from './constants';

/**
 * Single source of truth for access Redux details.
 *
 * This hook returns a stable API to access actions, selectors and special hooks to generate
 * getter- and setters for transient or persistent properties.
 *
 * @return {{select, dispatch, useTransient, usePersistent}} Store data API.
 */
const useStoreData = () => {
	const select = useSelect( ( selectors ) => selectors( STORE_NAME ), [] );
	const dispatch = useDispatch( STORE_NAME );
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );

	return useMemo(
		() => ( {
			select,
			dispatch,
			useTransient,
			usePersistent,
		} ),
		[ select, dispatch, useTransient, usePersistent ]
	);
};

const useHooks = () => {
	const { useTransient, usePersistent, dispatch, select } = useStoreData();
	const {
		persist,
		sandboxOnboardingUrl,
		productionOnboardingUrl,
		authenticateWithCredentials,
		authenticateWithOAuth,
		startWebhookSimulation,
		checkWebhookSimulationState,
	} = dispatch;

	// Transient accessors.
	const [ activeModal, setActiveModal ] = useTransient( 'activeModal' );
	const [ activeHighlight, setActiveHighlight ] =
		useTransient( 'activeHighlight' );

	// Persistent accessors.
	const [ isSandboxMode, setSandboxMode ] = usePersistent( 'useSandbox' );
	const [ isManualConnectionMode, setManualConnectionMode ] = usePersistent(
		'useManualConnection'
	);

	// Read-only properties.
	const wooSettings = select.wooSettings();
	const features = select.features();
	const webhooks = select.webhooks();

	const savePersistent = async ( setter, value ) => {
		setter( value );
		await persist();
	};

	return {
		activeModal,
		setActiveModal,
		activeHighlight,
		setActiveHighlight,
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
		wooSettings,
		features,
		webhooks,
		startWebhookSimulation,
		checkWebhookSimulationState,
	};
};

export const useStore = () => {
	const { select, dispatch, useTransient } = useStoreData();
	const { persist, refresh } = dispatch;
	const [ isReady ] = useTransient( 'isReady' );

	// Load persistent data from REST if not done yet.
	if ( ! isReady ) {
		select.persistentData();
	}

	return { persist, refresh, isReady };
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

export const useDisconnectMerchant = () => {
	const { disconnectMerchant } = useDispatch( STORE_NAME );
	return { disconnectMerchant };
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
	const { features } = useHooks();
	const merchant = useMerchant();
	const { refreshMerchantData, setMerchant } = useDispatch( STORE_NAME );
	const { isReady } = useStore();

	const verifyLoginStatus = useCallback( async () => {
		const result = await refreshMerchantData();

		if ( ! result.success || ! result.merchant ) {
			throw new Error( result?.message || result?.error?.message );
		}

		const newMerchant = result.merchant;

		// Verify if the server state is "connected" and we have a merchant ID.
		if ( newMerchant?.isConnected && newMerchant?.id ) {
			// Update the verified merchant details in Redux.
			setMerchant( newMerchant );

			return true;
		}

		return false;
	}, [ refreshMerchantData, setMerchant ] );

	return {
		merchant, // Merchant details
		features, // Eligible merchant features
		verifyLoginStatus, // Callback
		isReady,
	};
};

// Read-only access to the sanitized merchant details.
export const useMerchant = () => {
	const merchant = useSelect(
		( select ) => select( STORE_NAME ).merchant(),
		[]
	);

	return useMemo(
		() => ( {
			isConnected: merchant.isConnected ?? false,
			isSandbox: merchant.isSandbox ?? true,
			id: merchant.id ?? '',
			email: merchant.email ?? '',
			clientId: merchant.clientId ?? '',
			clientSecret: merchant.clientSecret ?? '',
			isBusinessSeller: 'business' === merchant.sellerType,
			isCasualSeller: 'personal' === merchant.sellerType,
		} ),
		// the merchant object is stable, so a new memo is only generated when a merchant prop changes.
		[ merchant ]
	);
};

export const useActiveModal = () => {
	const { activeModal, setActiveModal } = useHooks();
	return { activeModal, setActiveModal };
};

export const useActiveHighlight = () => {
	const { activeHighlight, setActiveHighlight } = useHooks();
	return { activeHighlight, setActiveHighlight };
};

/*
 * Busy state management hooks
 */

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

			// Intentionally does not catch errors but propagates them to the calling module.
			try {
				return await asyncFn();
			} finally {
				stopActivity( id );
			}
		},
		[ startActivity, stopActivity ]
	);

	return {
		startActivity,
		stopActivity,
		withActivity, // HOC
		isBusy, // Boolean.
	};
};

export const useActivityObserver = () => {
	const activities = useSelect(
		( select ) => select( STORE_NAME ).getActivityList(),
		[]
	);

	const [ prevActivities, setPrevActivities ] = useState( activities );

	useEffect( () => {
		setPrevActivities( activities );
	}, [ activities ] );

	const onStarted = useCallback(
		( callback ) => {
			const newActivities = Object.keys( activities ).filter(
				( id ) => ! prevActivities[ id ]
			);
			if ( ! newActivities.length ) {
				return;
			}
			newActivities.forEach( ( id ) =>
				callback( id, Object.keys( activities ) )
			);
		},
		[ activities, prevActivities ]
	);

	const onFinished = useCallback(
		( callback ) => {
			const finishedActivities = Object.keys( prevActivities ).filter(
				( id ) => ! activities[ id ]
			);
			if ( ! finishedActivities.length ) {
				return;
			}
			finishedActivities.forEach( ( id ) =>
				callback( id, Object.keys( activities ) )
			);
		},
		[ activities, prevActivities ]
	);

	return {
		activities,
		onStarted,
		onFinished,
	};
};
