import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';
import usePayPalScript from './usePayPalScript';
import { setupWatermark } from '../components/Watermark';
import { setupEmailFunctionality } from '../components/EmailButton';
import { createEmailLookupHandler } from '../events/emailLookupManager';
import usePhoneSyncHandler from './usePhoneSyncHandler';
import { initializeClassToggles } from '../helpers/classnamesManager';
import { snapshotFields } from '../helpers/fieldHelpers';
import useCustomerData from './useCustomerData';
import useShippingAddressChange from './useShippingAddressChange';
import useCardChange from './useCardChange';

const useAxoSetup = ( ppcpConfig, fastlaneSdk, paymentComponent ) => {
	const {
		setIsAxoActive,
		setIsAxoScriptLoaded,
		setShippingAddress,
		setCardDetails,
	} = useDispatch( STORE_NAME );
	const paypalLoaded = usePayPalScript( ppcpConfig );
	const onChangeCardButtonClick = useCardChange( fastlaneSdk );
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

	usePhoneSyncHandler( paymentComponent );

	useEffect( () => {
		initializeClassToggles();
	}, [] );

	useEffect( () => {
		setupWatermark( fastlaneSdk );
		if ( paypalLoaded && fastlaneSdk ) {
			setIsAxoScriptLoaded( true );
			setIsAxoActive( true );
			const emailLookupHandler = createEmailLookupHandler(
				fastlaneSdk,
				setShippingAddress,
				setCardDetails,
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
		setCardDetails,
		paymentComponent,
	] );

	return paypalLoaded;
};

export default useAxoSetup;
