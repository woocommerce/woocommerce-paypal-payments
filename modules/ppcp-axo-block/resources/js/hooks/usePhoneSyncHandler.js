import { useEffect, useRef } from 'react';
import { useSelect } from '@wordpress/data';
import { debounce } from '../../../../ppcp-blocks/resources/js/Helper/debounce';

const WOO_STORE_NAME = 'wc/store/cart';
const PHONE_DEBOUNCE_DELAY = 250;

/**
 * Sanitizes a phone number by removing country code and non-numeric characters.
 * Only returns the sanitized number if it's exactly 10 digits long (US phone number).
 *
 * @param {string} phoneNumber - The phone number to sanitize.
 * @return {string} The sanitized phone number; an empty string if it's invalid.
 */
const sanitizePhoneNumber = ( phoneNumber = '' ) => {
	const localNumber = phoneNumber.replace( /^\+?[01]+/, '' );
	const cleanNumber = localNumber.replace( /[^0-9]/g, '' );

	return cleanNumber.length === 10 ? cleanNumber : '';
};

/**
 * Retrieves and sanitizes the phone number from WooCommerce customer data.
 *
 * @param {Function} select - The select function from @wordpress/data.
 * @return {string} The sanitized phone number.
 */
const getSanitizedPhoneNumber = ( select ) => {
	const data = select( WOO_STORE_NAME ).getCustomerData() || {};
	const billingPhone = sanitizePhoneNumber( data.billingAddress?.phone );
	const shippingPhone = sanitizePhoneNumber( data.shippingAddress?.phone );
	return billingPhone || shippingPhone || '';
};

/**
 * Updates the prefilled phone number in the Fastlane CardField component.
 *
 * @param {Object} paymentComponent - The CardField component from Fastlane
 * @param {string} phoneNumber      - The new phone number to prefill.
 */
const updatePrefills = ( paymentComponent, phoneNumber ) => {
	console.log( 'Update the phone prefill value', phoneNumber );
	paymentComponent.updatePrefills( { phoneNumber } );
};

/**
 * Custom hook to synchronize the WooCommerce phone number with a React component state.
 *
 * @param {Object} paymentComponent - The CardField component from Fastlane.
 */
export const usePhoneSyncHandler = ( paymentComponent ) => {
	// Fetch and sanitize phone number from WooCommerce.
	const phoneNumber = useSelect( ( select ) =>
		getSanitizedPhoneNumber( select )
	);

	// Create a debounced function that updates the prefilled phone-number.
	const debouncedUpdatePhone = useRef(
		debounce( updatePrefills, PHONE_DEBOUNCE_DELAY )
	).current;

	// Invoke debounced function when paymentComponent or phoneNumber changes.
	useEffect( () => {
		if ( paymentComponent && phoneNumber ) {
			debouncedUpdatePhone( paymentComponent, phoneNumber );
		}
	}, [ debouncedUpdatePhone, paymentComponent, phoneNumber ] );

	// Cleanup on unmount, canceling any pending debounced calls.
	useEffect( () => {
		return () => {
			if ( debouncedUpdatePhone?.cancel ) {
				debouncedUpdatePhone.cancel();
			}
		};
	}, [ debouncedUpdatePhone ] );
};
