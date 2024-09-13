import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';
import usePayPalScript from './usePayPalScript';
import { setupWatermark } from '../components/Watermark';
import { setupEmailFunctionality } from '../components/EmailButton';
import { createEmailLookupHandler } from '../events/emailLookupManager';
import { initializeClassToggles } from '../helpers/classnamesManager';
import { snapshotFields } from '../helpers/fieldHelpers';

const useAxoSetup = (
	ppcpConfig,
	fastlaneSdk,
	wooShippingAddress,
	wooBillingAddress,
	setWooShippingAddress,
	setWooBillingAddress,
	onChangeShippingAddressClick,
	onChangeCardButtonClick,
	setShippingAddress,
	setCard
) => {
	const { setIsAxoActive, setIsAxoScriptLoaded } = useDispatch( STORE_NAME );
	const paypalLoaded = usePayPalScript( ppcpConfig );

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
	] );

	return paypalLoaded;
};

export default useAxoSetup;
