/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useSelect, useDispatch } from '@wordpress/data';

import { PRODUCT_TYPES } from '../constants';
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
		setStep,
		setCompleted,
		setIsCasualSeller,
		setManualClientId,
		setManualClientSecret,
		setAreOptionalPaymentMethodsEnabled,
		setProducts,
	} = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	const flags = useSelect( ( select ) => select( STORE_NAME ).flags(), [] );
	const determineProducts = useSelect(
		( select ) => select( STORE_NAME ).determineProducts(),
		[]
	);

	// Transient accessors.
	const isReady = useTransient( 'isReady' );
	const manualClientId = useTransient( 'manualClientId' );
	const manualClientSecret = useTransient( 'manualClientSecret' );

	// Persistent accessors.
	const step = usePersistent( 'step' );
	const completed = usePersistent( 'completed' );
	const isCasualSeller = usePersistent( 'isCasualSeller' );
	const areOptionalPaymentMethodsEnabled = usePersistent(
		'areOptionalPaymentMethodsEnabled'
	);
	const products = usePersistent( 'products' );

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
		areOptionalPaymentMethodsEnabled,
		setAreOptionalPaymentMethodsEnabled: ( value ) => {
			return savePersistent( setAreOptionalPaymentMethodsEnabled, value );
		},
		products,
		setProducts: ( activeProducts ) => {
			const validProducts = activeProducts.filter( ( item ) =>
				Object.values( PRODUCT_TYPES ).includes( item )
			);
			return savePersistent( setProducts, validProducts );
		},
		determineProducts,
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
	const {
		areOptionalPaymentMethodsEnabled,
		setAreOptionalPaymentMethodsEnabled,
	} = useHooks();

	return {
		areOptionalPaymentMethodsEnabled,
		setAreOptionalPaymentMethodsEnabled,
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

	return {
		products,
		business,
	};
};

export const useDetermineProducts = () => {
	const { determineProducts } = useHooks();

	return determineProducts;
};

export const useFlags = () => {
	const { flags } = useHooks();
	return flags;
};
