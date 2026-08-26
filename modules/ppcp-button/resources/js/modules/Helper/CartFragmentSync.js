import { debounce } from '@ppcp-blocks/Helper/debounce';

/**
 * Reads the current cart signature (item count + total) from the block Store
 * API data store, or null when the store is not available.
 *
 * @param {Object} cart The `wc/store/cart` selector.
 * @return {string|null} A stable signature string, or null.
 */
const readCartSignature = ( cart ) => {
	if ( ! cart ) {
		return null;
	}

	const cartData = cart.getCartData?.() ?? null;
	const itemsCount = cartData?.itemsCount ?? cart.getItemsCount?.() ?? null;
	if ( itemsCount === null ) {
		return null;
	}

	// Include the total so coupon/total-only changes also refresh the widget.
	const total = cartData?.totals?.total_price ?? '';

	return `${ itemsCount }:${ total }`;
};

/**
 * Keeps the classic header mini-cart (`.cart-contents`) count in sync with the
 * block Cart/Checkout.
 *
 * The block cart mutates the `wc/store/cart` data store instead of firing the
 * jQuery events (`wc_fragment_refresh`, `added_to_cart`, ...) that WooCommerce's
 * `wc-cart-fragments` script listens to. On classic-theme headers this leaves
 * the mini-cart count stale after items are added/removed in the block cart.
 * Here we watch the store and trigger a fragment refresh when the cart changes.
 *
 * Idempotent: several entry points call this (the button bundle and the block
 * integration bundle) so it works regardless of which one runs on a given page;
 * only the first call subscribes. It is a no-op on pages without the Store API.
 *
 * @return {void}
 */
export const initCartFragmentSync = () => {
	if ( typeof wp === 'undefined' || ! wp.data?.subscribe ) {
		return;
	}

	// Only subscribe once, even when called from multiple bundles.
	if ( window.ppcpCartFragmentSyncActive ) {
		return;
	}
	window.ppcpCartFragmentSyncActive = true;

	// Coalesce bursts of store updates into a single fragment refresh.
	const refreshFragments = debounce( () => {
		if ( typeof jQuery !== 'undefined' ) {
			jQuery( document.body ).trigger( 'wc_fragment_refresh' );
		}
	}, 300 );

	// The `wc/store/cart` store may not be registered yet at load time (block
	// scripts load asynchronously), so subscribe unconditionally and seed the
	// signature lazily on the first notification where the store is ready.
	let seeded = false;
	let lastSignature = null;

	wp.data.subscribe( () => {
		const signature = readCartSignature(
			wp.data.select?.( 'wc/store/cart' )
		);
		if ( signature === null ) {
			return;
		}

		// Seed on the first available read so page load does not refresh.
		if ( ! seeded ) {
			seeded = true;
			lastSignature = signature;
			return;
		}

		if ( signature === lastSignature ) {
			return;
		}
		lastSignature = signature;

		refreshFragments();
	} );
};
