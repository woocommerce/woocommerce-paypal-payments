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

/**
 * Sets up class toggles for the contact information block based on isAxoActive and isGuest states.
 * @return {Function} Unsubscribe function for cleanup.
 */
export const setupContactInfoClassToggles = () => {
	const targetSelector =
		'.wp-block-woocommerce-checkout-contact-information-block';
	const axoLoadedClass = 'wc-block-axo-is-loaded';
	const authClass = 'wc-block-axo-is-authenticated';

	const updateContactInfoClasses = () => {
		const targetElement = document.querySelector( targetSelector );
		if ( ! targetElement ) {
			console.warn( `Target element not found: ${ targetSelector }` );
			return;
		}

		const isAxoActive = select( STORE_NAME ).getIsAxoActive();
		const isGuest = select( STORE_NAME ).getIsGuest();

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
	};

	// Initial update
	updateContactInfoClasses();

	// Subscribe to state changes
	const unsubscribe = subscribe( () => {
		updateContactInfoClasses();
	} );

	return unsubscribe;
};

/**
 * Initializes all class toggles.
 * @return {Function} Cleanup function.
 */
export const initializeClassToggles = () => {
	const unsubscribeAuth = setupAuthenticationClassToggle();
	const unsubscribeContactInfo = setupContactInfoClassToggles();

	return () => {
		if ( unsubscribeAuth ) {
			unsubscribeAuth();
		}
		if ( unsubscribeContactInfo ) {
			unsubscribeContactInfo();
		}
	};
};
