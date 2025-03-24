/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useSelect, useDispatch } from '@wordpress/data';

import { createHooksForStore } from '../utils';
import { PRODUCT_TYPES } from './configuration';
import { STORE_NAME } from './constants';
import ACTION_TYPES from './action-types';

const useHooks = () => {
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );
	const { persist, dispatch } = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	const flags = useSelect( ( select ) => select( STORE_NAME ).flags(), [] );

	// Transient accessors.
	const [ isReady ] = useTransient( 'isReady' );
	const [ manualClientId, setManualClientId ] =
		useTransient( 'manualClientId' );
	const [ manualClientSecret, setManualClientSecret ] =
		useTransient( 'manualClientSecret' );

	// Persistent accessors.
	const [ step, setStep ] = usePersistent( 'step' );
	const [ completed, setCompleted ] = usePersistent( 'completed' );
	const [ isCasualSeller, setIsCasualSeller ] =
		usePersistent( 'isCasualSeller' );
	const [ optionalMethods, setOptionalMethods ] = usePersistent(
		'areOptionalPaymentMethodsEnabled'
	);
	const [ products, setProducts ] = usePersistent( 'products' );
	// Add the setter for gatewaysSynced
	const [ gatewaysSynced, setGatewaysSynced ] =
		usePersistent( 'gatewaysSynced' );

	const [ gatewaysRefreshed, setGatewaysRefreshed ] =
		usePersistent( 'gatewaysRefreshed' );

	const savePersistent = async ( setter, value ) => {
		setter( value );
		await persist();
	};

	return {
		flags,
		isReady,
		step,
		setStep: ( value ) => {
			return savePersistent( setStep, value );
		},
		completed,
		setCompleted: ( state ) => {
			return savePersistent( setCompleted, state );
		},
		isCasualSeller,
		setIsCasualSeller: ( value ) => {
			return savePersistent( setIsCasualSeller, value );
		},
		manualClientId,
		setManualClientId: ( value ) => {
			return savePersistent( setManualClientId, value );
		},
		manualClientSecret,
		setManualClientSecret: ( value ) => {
			return savePersistent( setManualClientSecret, value );
		},
		optionalMethods,
		setOptionalMethods: ( value ) => {
			return savePersistent( setOptionalMethods, value );
		},
		products,
		setProducts: ( activeProducts ) => {
			const validProducts = activeProducts.filter( ( item ) =>
				Object.values( PRODUCT_TYPES ).includes( item )
			);
			return savePersistent( setProducts, validProducts );
		},
		gatewaysSynced,
		setGatewaysSynced: ( value ) => {
			return savePersistent( setGatewaysSynced, value );
		},
		syncGateways: async () => {
			await savePersistent( setGatewaysSynced, true );
			dispatch( {
				type: ACTION_TYPES.SYNC_GATEWAYS,
			} );
		},
		gatewaysRefreshed,
		setGatewaysRefreshed: ( value ) => {
			return savePersistent( setGatewaysRefreshed, value );
		},
		refreshGateways: async () => {
			await savePersistent( setGatewaysRefreshed, true );
			dispatch( {
				type: ACTION_TYPES.REFRESH_GATEWAYS,
			} );
		},
	};
};

export const useManualConnectionForm = () => {
	const {
		manualClientId,
		setManualClientId,
		manualClientSecret,
		setManualClientSecret,
	} = useHooks();

	return {
		manualClientId,
		setManualClientId,
		manualClientSecret,
		setManualClientSecret,
	};
};

export const useBusiness = () => {
	const { isCasualSeller, setIsCasualSeller } = useHooks();

	return { isCasualSeller, setIsCasualSeller };
};

export const useProducts = () => {
	const { products, setProducts } = useHooks();

	return { products, setProducts };
};

export const useOptionalPaymentMethods = () => {
	const { optionalMethods, setOptionalMethods } = useHooks();

	return {
		optionalMethods,
		setOptionalMethods,
	};
};

export const useSteps = () => {
	const { flags, isReady, step, setStep, completed, setCompleted } =
		useHooks();

	return { flags, isReady, step, setStep, completed, setCompleted };
};

export const useNavigationState = () => {
	const products = useProducts();
	const business = useBusiness();
	const methods = useOptionalPaymentMethods();

	return {
		products,
		business,
		methods,
	};
};

export const useDetermineProducts = () => {
	return useSelect( ( select ) => {
		return select( STORE_NAME ).determineProductsAndCaps();
	}, [] );
};

export const useFlags = () => {
	const { flags } = useHooks();
	return flags;
};

export const useGatewaySync = () => {
	const { gatewaysSynced, syncGateways } = useHooks();
	return { gatewaysSynced, syncGateways };
};

export const useGatewayRefresh = () => {
	const { gatewaysRefreshed, refreshGateways } = useHooks();
	return { gatewaysRefreshed, refreshGateways };
};
