import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';
import { removeShippingChangeButton } from '../components/Shipping';
import { removeCardChangeButton } from '../components/Card';
import { removeWatermark } from '../components/Watermark';
import {
	removeEmailFunctionality,
	isEmailFunctionalitySetup,
} from '../components/EmailButton';
import { restoreOriginalFields } from '../helpers/fieldHelpers';
import useCustomerData from './useCustomerData';

const useAxoCleanup = () => {
	const { setIsAxoActive, setIsGuest } = useDispatch( STORE_NAME );
	const {
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		console.log( 'Setting up cleanup for WooCommerce fields' );
		return () => {
			console.log( 'Cleaning up: Restoring WooCommerce fields' );
			restoreOriginalFields(
				updateWooShippingAddress,
				updateWooBillingAddress
			);
		};
	}, [ updateWooShippingAddress, updateWooBillingAddress ] );

	useEffect( () => {
		console.log( 'Setting up cleanup for Axo component' );
		return () => {
			console.log( 'Cleaning up Axo component' );
			setIsAxoActive( false );
			setIsGuest( true );
			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();
			if ( isEmailFunctionalitySetup() ) {
				console.log( 'Removing email functionality' );
				removeEmailFunctionality();
			}
		};
	}, [] );
};

export default useAxoCleanup;
