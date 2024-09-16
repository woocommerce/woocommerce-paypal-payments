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
 * Custom hook to synchronize the WooCommerce phone number with a React component state.
 *
 * @param {Function} setWooPhone - The state setter function for the phone number.
 */
export const usePhoneSyncHandler = ( setWooPhone ) => {
	// Fetch and sanitize phone number from WooCommerce.
	const phoneNumber = useSelect( ( select ) =>
		getSanitizedPhoneNumber( select )
	);

	// Initialize debounced setter for Fastlane.
	const debouncedSetWooPhoneRef = useRef();

	if ( ! debouncedSetWooPhoneRef.current ) {
		debouncedSetWooPhoneRef.current = debounce( ( number ) => {
			setWooPhone( number );
		}, PHONE_DEBOUNCE_DELAY );
	}

	// Invoke debounced setter when phone number changes.
	useEffect( () => {
		if ( phoneNumber ) {
			console.log( 'New phone number:', phoneNumber );
			debouncedSetWooPhoneRef.current( phoneNumber );
		}
	}, [ phoneNumber ] );

	// Cleanup on unmount, canceling any pending debounced calls.
	useEffect( () => {
		return () => {
			if ( debouncedSetWooPhoneRef.current?.cancel ) {
				debouncedSetWooPhoneRef.current.cancel();
			}
		};
	}, [] );
};
