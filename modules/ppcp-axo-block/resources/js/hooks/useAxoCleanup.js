import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
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
		return () => {
			log( 'Cleaning up: Restoring WooCommerce fields' );
			restoreOriginalFields(
				updateWooShippingAddress,
				updateWooBillingAddress
			);
		};
	}, [ updateWooShippingAddress, updateWooBillingAddress ] );

	useEffect( () => {
		return () => {
			log( 'Cleaning up Axo component' );
			setIsAxoActive( false );
			setIsGuest( true );
			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();
			if ( isEmailFunctionalitySetup() ) {
				log( 'Removing email functionality' );
				removeEmailFunctionality();
			}
		};
	}, [] );
};

export default useAxoCleanup;
