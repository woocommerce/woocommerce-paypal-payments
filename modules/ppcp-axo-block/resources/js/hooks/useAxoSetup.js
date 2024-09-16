import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';
import usePayPalScript from './usePayPalScript';
import { setupWatermark } from '../components/Watermark';
import { setupEmailFunctionality } from '../components/EmailButton';
import { createEmailLookupHandler } from '../events/emailLookupManager';
import { usePhoneSyncHandler } from './usePhoneSyncHandler';
import { initializeClassToggles } from '../helpers/classnamesManager';
import { snapshotFields } from '../helpers/fieldHelpers';
import useCustomerData from './useCustomerData';
import useShippingAddressChange from './useShippingAddressChange';

const useAxoSetup = (
	ppcpConfig,
	fastlaneSdk,
	paymentComponent,
	onChangeCardButtonClick,
	setShippingAddress,
	setCard,
	setWooPhone
) => {
	const { setIsAxoActive, setIsAxoScriptLoaded } = useDispatch( STORE_NAME );
	const paypalLoaded = usePayPalScript( ppcpConfig );

	const onChangeShippingAddressClick = useShippingAddressChange(
		fastlaneSdk,
		setShippingAddress
	);

	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: setWooShippingAddress,
		setBillingAddress: setWooBillingAddress,
	} = useCustomerData();

	usePhoneSyncHandler( paymentComponent, setWooPhone );

	useEffect( () => {
		console.log( 'Initializing class toggles' );
		initializeClassToggles();
	}, [] );

	useEffect( () => {
		console.log( 'Setting up Axo functionality' );
		setupWatermark( fastlaneSdk );
		if ( paypalLoaded && fastlaneSdk ) {
			console.log(
				'PayPal loaded and FastlaneSDK available, setting up email functionality'
			);
			setIsAxoScriptLoaded( true );
			setIsAxoActive( true );
			const emailLookupHandler = createEmailLookupHandler(
				fastlaneSdk,
				setShippingAddress,
				setCard,
				snapshotFields,
				wooShippingAddress,
				wooBillingAddress,
				setWooShippingAddress,
				setWooBillingAddress,
				onChangeShippingAddressClick,
				onChangeCardButtonClick
			);
			setupEmailFunctionality( emailLookupHandler );
		}
	}, [
		paypalLoaded,
		fastlaneSdk,
		setIsAxoActive,
		setIsAxoScriptLoaded,
		wooShippingAddress,
		wooBillingAddress,
		setWooShippingAddress,
		setWooBillingAddress,
		onChangeShippingAddressClick,
		onChangeCardButtonClick,
		setShippingAddress,
		setCard,
		paymentComponent,
	] );

	return paypalLoaded;
};

export default useAxoSetup;
