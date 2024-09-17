import { select, subscribe } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';

/**
 * Sets up a class toggle based on the isGuest state for the express payment block.
 * @return {Function} Unsubscribe function for cleanup.
 */
export const setupAuthenticationClassToggle = () => {
	const targetSelector =
		'.wp-block-woocommerce-checkout-express-payment-block';
	const authClass = 'wc-block-axo-is-authenticated';

	const updateAuthenticationClass = () => {
		const targetElement = document.querySelector( targetSelector );
		if ( ! targetElement ) {
			console.warn( `Target element not found: ${ targetSelector }` );
			return;
		}

		const isGuest = select( STORE_NAME ).getIsGuest();

		if ( ! isGuest ) {
			targetElement.classList.add( authClass );
		} else {
			targetElement.classList.remove( authClass );
		}
	};

	// Initial update
	updateAuthenticationClass();

	// Subscribe to state changes
	const unsubscribe = subscribe( () => {
		updateAuthenticationClass();
	} );

	return unsubscribe;
};

export const setupEmailLookupCompletedClassToggle = () => {
	const targetSelector = '.wp-block-woocommerce-checkout-fields-block';
	const emailLookupCompletedClass = 'wc-block-axo-email-lookup-completed';

	const updateEmailLookupCompletedClass = () => {
		const targetElement = document.querySelector( targetSelector );
		if ( ! targetElement ) {
			console.warn( `Target element not found: ${ targetSelector }` );
			return;
		}

		const isEmailLookupCompleted =
			select( STORE_NAME ).getIsEmailLookupCompleted();

		if ( isEmailLookupCompleted ) {
			targetElement.classList.add( emailLookupCompletedClass );
		} else {
			targetElement.classList.remove( emailLookupCompletedClass );
		}
	};

	// Initial update
	updateEmailLookupCompletedClass();

	// Subscribe to state changes
	const unsubscribe = subscribe( () => {
		updateEmailLookupCompletedClass();
	} );

	return unsubscribe;
};

/**
 * Sets up class toggles for the contact information block based on isAxoActive and isGuest states.
 * @return {Function} Unsubscribe function for cleanup.
 */
export const setupCheckoutBlockClassToggles = () => {
	const targetSelector = '.wp-block-woocommerce-checkout-fields-block';
	const axoLoadedClass = 'wc-block-axo-is-loaded';
	const authClass = 'wc-block-axo-is-authenticated';
	const emailLookupCompletedClass = 'wc-block-axo-email-lookup-completed';

	const updateCheckoutBlockClassToggles = () => {
		const targetElement = document.querySelector( targetSelector );
		if ( ! targetElement ) {
			console.warn( `Target element not found: ${ targetSelector }` );
			return;
		}

		const isAxoActive = select( STORE_NAME ).getIsAxoActive();
		const isGuest = select( STORE_NAME ).getIsGuest();
		const isEmailLookupCompleted =
			select( STORE_NAME ).getIsEmailLookupCompleted();

		if ( isAxoActive ) {
			targetElement.classList.add( axoLoadedClass );
		} else {
			targetElement.classList.remove( axoLoadedClass );
		}
		triggerCounterRecalculation();

		if ( ! isGuest ) {
			targetElement.classList.add( authClass );
		} else {
			targetElement.classList.remove( authClass );
		}

		if ( isEmailLookupCompleted ) {
			targetElement.classList.add( emailLookupCompletedClass );
		} else {
			targetElement.classList.remove( emailLookupCompletedClass );
		}
	};

	// Initial update
	updateCheckoutBlockClassToggles();

	// Subscribe to state changes
	const unsubscribe = subscribe( () => {
		updateCheckoutBlockClassToggles();
	} );

	return unsubscribe;
};

/**
 * Initializes all class toggles.
 * @return {Function} Cleanup function.
 */
export const initializeClassToggles = () => {
	const unsubscribeAuth = setupAuthenticationClassToggle();
	const unsubscribeEmailLookupCompleted =
		setupEmailLookupCompletedClassToggle();
	const unsubscribeContactInfo = setupCheckoutBlockClassToggles();

	return () => {
		if ( unsubscribeAuth ) {
			unsubscribeAuth();
		}
		if ( unsubscribeEmailLookupCompleted ) {
			unsubscribeEmailLookupCompleted();
		}
		if ( unsubscribeContactInfo ) {
			unsubscribeContactInfo();
		}
	};
};

/**
 * Fixes the number values on the block checkout page, when step numbering is enabled.
 *
 * We briefly add a CSS class to the body, that modifies the counter's CSS attributes.
 * Changing _any_ attribute of the counter element triggers the recalculation of the counters.
 */
export const triggerCounterRecalculation = () => {
	document.body.classList.add( 'ppcp-recalculate' );

	requestAnimationFrame( () => {
		document.body.classList.remove( 'ppcp-recalculate' );
	} );
};
