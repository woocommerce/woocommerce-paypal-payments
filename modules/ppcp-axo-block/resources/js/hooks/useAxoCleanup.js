import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { log } from '@ppcp-axo/Helper/Debug';
import { STORE_NAME } from '@ppcp-axo-block/stores/axoStore';
import { removeShippingChangeButton } from '@ppcp-axo-block/components/Shipping';
import { removeWatermark } from '@ppcp-axo-block/components/Watermark';
import {
	removeEmailFunctionality,
	isEmailFunctionalitySetup,
} from '@ppcp-axo-block/components/EmailButton';
import { restoreOriginalFields } from '@ppcp-axo-block/helpers/fieldHelpers';
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
