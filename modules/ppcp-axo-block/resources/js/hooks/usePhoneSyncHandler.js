import { useEffect, useRef, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { debounce } from '../../../../ppcp-blocks/resources/js/Helper/debounce';
import { STORE_NAME } from '../stores/axoStore';
import useCustomerData from './useCustomerData';

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
 * Updates the prefilled phone number in the Fastlane CardField component.
 *
 * @param {Object} paymentComponent - The CardField component from Fastlane
 * @param {string} phoneNumber      - The new phone number to prefill.
 */
const updatePrefills = ( paymentComponent, phoneNumber ) => {
	log( `Update the phone prefill value: ${ phoneNumber }` );
	paymentComponent.updatePrefills( { phoneNumber } );
};

/**
 * Custom hook to synchronize the WooCommerce phone number with a React component state.
 *
 * @param {Object} paymentComponent - The CardField component from Fastlane.
 */
const usePhoneSyncHandler = ( paymentComponent ) => {
	const { setPhoneNumber } = useDispatch( STORE_NAME );

	const { phoneNumber } = useSelect( ( select ) => ( {
		phoneNumber: select( STORE_NAME ).getPhoneNumber(),
	} ) );

	const { shippingAddress, billingAddress } = useCustomerData();

	// Create a debounced function that updates the prefilled phone-number.
	const debouncedUpdatePhone = useRef(
		debounce( updatePrefills, PHONE_DEBOUNCE_DELAY )
	).current;

	// Fetch and update the phone number from the billing or shipping address.
	const fetchAndUpdatePhoneNumber = useCallback( () => {
		const billingPhone = billingAddress?.phone || '';
		const shippingPhone = shippingAddress?.phone || '';
		const sanitizedPhoneNumber = sanitizePhoneNumber(
			billingPhone || shippingPhone
		);

		if ( sanitizedPhoneNumber && sanitizedPhoneNumber !== phoneNumber ) {
			setPhoneNumber( sanitizedPhoneNumber );
		}
	}, [ billingAddress, shippingAddress, phoneNumber, setPhoneNumber ] );

	// Fetch and update the phone number from the billing or shipping address.
	useEffect( () => {
		fetchAndUpdatePhoneNumber();
	}, [ fetchAndUpdatePhoneNumber ] );

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

export default usePhoneSyncHandler;
