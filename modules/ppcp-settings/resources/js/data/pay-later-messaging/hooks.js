/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useDispatch } from '@wordpress/data';

import { createHooksForStore } from '../utils';
import { STORE_NAME } from './constants';

const useHooks = () => {
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );
	const { persist } = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	// Nothing here yet.

	// Transient accessors.
	const [ isReady ] = useTransient( 'isReady' );

	// Persistent accessors.
	const [ cart, setCart ] = usePersistent( 'cart' );
	const [ checkout, setCheckout ] = usePersistent( 'checkout' );
	const [ product, setProduct ] = usePersistent( 'product' );
	const [ shop, setShop ] = usePersistent( 'shop' );
	const [ home, setHome ] = usePersistent( 'home' );
	const [ custom_placement, setCustom_placement ] =
		usePersistent( 'custom_placement' );

	return {
		persist,
		isReady,
		cart,
		setCart,
		checkout,
		setCheckout,
		product,
		setProduct,
		shop,
		setShop,
		home,
		setHome,
		custom_placement,
		setCustom_placement,
	};
};

export const useStore = () => {
	const { persist, isReady } = useHooks();
	return { persist, isReady };
};

export const usePayLaterMessaging = () => {
	const {
		cart,
		setCart,
		checkout,
		setCheckout,
		product,
		setProduct,
		shop,
		setShop,
		home,
		setHome,
		custom_placement,
		setCustom_placement,
	} = useHooks();

	return {
		config: {
			cart,
			checkout,
			product,
			shop,
			home,
			custom_placement,
		},
		setCart,
		setCheckout,
		setProduct,
		setShop,
		setHome,
		setCustom_placement,
	};
};
