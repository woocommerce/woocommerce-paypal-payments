import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { STORE_NAME } from '../stores/axoStore';
import { removeShippingChangeButton } from '../components/Shipping';
import { removeWatermark } from '../components/Watermark';
import {
	removeEmailFunctionality,
	isEmailFunctionalitySetup,
} from '../components/EmailButton';
import { restoreOriginalFields } from '../helpers/fieldHelpers';
import useCustomerData from './useCustomerData';

/**
 * Custom hook to handle cleanup of AXO functionality.
 * This hook ensures that all AXO-related changes are reverted when the component unmounts (a different payment method gets selected).
 */
const useAxoCleanup = () => {
	// Get dispatch functions from the AXO store
	const { setIsAxoActive, setIsGuest, setIsEmailLookupCompleted } =
		useDispatch( STORE_NAME );

	// Get functions to update WooCommerce shipping and billing addresses
	const {
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	// Effect to restore original WooCommerce fields on unmount
	useEffect( () => {
		return () => {
			log( 'Cleaning up: Restoring WooCommerce fields' );
			restoreOriginalFields(
				updateWooShippingAddress,
				updateWooBillingAddress
			);
		};
	}, [ updateWooShippingAddress, updateWooBillingAddress ] );

	// Effect to clean up AXO-specific functionality on unmount
	useEffect( () => {
		return () => {
			log( 'Cleaning up Axo component' );

			// Reset AXO state
			setIsAxoActive( false );
			setIsGuest( true );
			setIsEmailLookupCompleted( false );

			// Remove AXO UI elements
			removeShippingChangeButton();
			removeWatermark();

			// Remove email functionality if it was set up
			if ( isEmailFunctionalitySetup() ) {
				log( 'Removing email functionality' );
				removeEmailFunctionality();
			}
		};
	}, [] );
};

export default useAxoCleanup;
