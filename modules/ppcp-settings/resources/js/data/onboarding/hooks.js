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

const useHooks = () => {
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );
	const dispatchActions = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	const flags = useSelect( ( select ) => select( STORE_NAME ).flags(), [] );

	// Transient accessors.
	const [ isReady ] = useTransient( 'isReady' );
	const [ manualClientId, setManualClientId ] =
		useTransient( 'manualClientId' );
	const [ manualClientSecret, setManualClientSecret ] =
		useTransient( 'manualClientSecret' );
	const [ connectionButtonClicked, setConnectionButtonClicked ] =
		useTransient( 'connectionButtonClicked' );

	// Persistent accessors.
	const [ step, setStep ] = usePersistent( 'step' );
	const [ completed, setCompleted ] = usePersistent( 'completed' );
	const [ isCasualSeller, setIsCasualSeller ] =
		usePersistent( 'isCasualSeller' );
	const [ optionalMethods, setOptionalMethods ] = usePersistent(
		'areOptionalPaymentMethodsEnabled'
	);
	const [ products, setProducts ] = usePersistent( 'products' );
	const [ gatewaysSynced, setGatewaysSynced ] =
		usePersistent( 'gatewaysSynced' );
	const [ gatewaysRefreshed, setGatewaysRefreshed ] =
		usePersistent( 'gatewaysRefreshed' );

	const savePersistent = async ( setter, value, source ) => {
		setter( value, source );
		await dispatchActions.persist();
	};

	const saveTransient = ( setter, value, source ) => {
		setter( value, source );
	};

	return {
		flags,
		isReady,
		step,
		setStep: ( value, source ) => {
			return savePersistent( setStep, value, source );
		},
		completed,
		setCompleted: ( state, source ) => {
			return savePersistent( setCompleted, state, source );
		},
		isCasualSeller,
		setIsCasualSeller: ( value, source ) => {
			return savePersistent( setIsCasualSeller, value, source );
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
		setOptionalMethods: ( value, source ) => {
			return savePersistent( setOptionalMethods, value, source );
		},
		products,
		setProducts: ( activeProducts, source ) => {
			const validProducts = activeProducts.filter( ( item ) =>
				Object.values( PRODUCT_TYPES ).includes( item )
			);
			return savePersistent( setProducts, validProducts, source );
		},
		gatewaysSynced,
		setGatewaysSynced: ( value ) => {
			return savePersistent( setGatewaysSynced, value, undefined );
		},
		syncGateways: async () => {
			return await dispatchActions.syncGateways( undefined );
		},
		gatewaysRefreshed,
		setGatewaysRefreshed: ( value ) => {
			return savePersistent( setGatewaysRefreshed, value, undefined );
		},
		refreshGateways: async () => {
			return await dispatchActions.refreshGateways( undefined );
		},
		connectionButtonClicked,
		setConnectionButtonClicked: ( value ) => {
			return saveTransient( setConnectionButtonClicked, value, 'user' );
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

export const useDetermineProducts = ( ownBrandOnly, storeCountry ) => {
	return useSelect(
		( select ) => {
			return select( STORE_NAME ).determineProductsAndCaps(
				ownBrandOnly,
				storeCountry
			);
		},
		[ ownBrandOnly, storeCountry ]
	);
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

export const useConnectionButton = () => {
	const { connectionButtonClicked, setConnectionButtonClicked } = useHooks();

	return {
		connectionButtonClicked,
		setConnectionButtonClicked,
	};
};

export const OnboardingHooks = {
	useManualConnectionForm,
	useBusiness,
	useProducts,
	useOptionalPaymentMethods,
	useSteps,
	useNavigationState,
	useDetermineProducts,
	useFlags,
	useGatewaySync,
	useGatewayRefresh,
	useConnectionButton,
};
