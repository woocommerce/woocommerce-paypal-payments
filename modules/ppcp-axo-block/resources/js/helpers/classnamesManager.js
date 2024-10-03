import { select, subscribe } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { STORE_NAME } from '../stores/axoStore';

/**
 * Sets up a class toggle based on the isGuest state for the express payment block.
 * This hides the express payment methods if the user is authenticated (Ryan flow).
 *
 * @return {Function} Unsubscribe function for cleanup.
 */
export const setupAuthenticationClassToggle = () => {
	const targetSelector =
		'.wp-block-woocommerce-checkout-express-payment-block';
	const authClass = 'wc-block-axo-is-authenticated';

	const updateAuthenticationClass = () => {
		const targetElement = document.querySelector( targetSelector );
		if ( ! targetElement ) {
			log(
				`Authentication class target element not found: ${ targetSelector }`,
				'warn'
			);
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

/**
 * Sets up a class toggle based on the isEmailLookupCompleted state for the checkout fields block.
 * This hides the Shipping Address fields, Billing Address fields, Shipping Options section,
 * Order Notes section, Checkout Terms section, and Place Order button until email lookup is completed.
 *
 * @return {Function} Unsubscribe function for cleanup.
 */
export const setupEmailLookupCompletedClassToggle = () => {
	const targetSelector = '.wp-block-woocommerce-checkout-fields-block';
	const emailLookupCompletedClass = 'wc-block-axo-email-lookup-completed';

	const updateEmailLookupCompletedClass = () => {
		const targetElement = document.querySelector( targetSelector );
		if ( ! targetElement ) {
			log(
				`Email lookup completed class target element not found: ${ targetSelector }`,
				'warn'
			);
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
 * Sets up class toggles for the contact information block based on isAxoActive, isGuest, and isEmailLookupCompleted states.
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
			log(
				`Checkout block class target element not found: ${ targetSelector }`,
				'warn'
			);
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
 * @return {Function} Cleanup function to unsubscribe all listeners.
 */
export const initializeClassToggles = () => {
	const unsubscribeAuth = setupAuthenticationClassToggle();
	const unsubscribeEmailLookupCompleted =
		setupEmailLookupCompletedClassToggle();
	const unsubscribeContactInfo = setupCheckoutBlockClassToggles();

	// Return a cleanup function that unsubscribes all listeners
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
