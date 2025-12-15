import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME } from '@ppcp-axo-block/stores/axoStore';
import usePayPalScript from './usePayPalScript';
import { setupWatermark } from '@ppcp-axo-block/components/Watermark';
import { setupEmailFunctionality } from '@ppcp-axo-block/components/EmailButton';
import { createEmailLookupHandler } from '@ppcp-axo-block/events/emailLookupManager';
import usePhoneSyncHandler from './usePhoneSyncHandler';
import { initializeClassToggles } from '@ppcp-axo-block/helpers/classnamesManager';
import { snapshotFields } from '@ppcp-axo-block/helpers/fieldHelpers';
import useCustomerData from './useCustomerData';
import useShippingAddressChange from './useShippingAddressChange';
import useCardChange from './useCardChange';
import useSessionRestoration from './useSessionRestoration';

/**
 * Custom hook to set up AXO functionality.
 *
 * @param {string}  namespace        - Namespace for the PayPal script.
 * @param {Object}  ppcpConfig       - PayPal Checkout configuration.
 * @param {boolean} isConfigLoaded   - Whether the PayPal config has loaded.
 * @param {Object}  fastlaneSdk      - Fastlane SDK instance.
 * @param {Object}  paymentComponent - Payment component instance.
 * @return {boolean} Whether PayPal script has loaded.
 */
const useAxoSetup = (
	namespace,
	ppcpConfig,
	isConfigLoaded,
	fastlaneSdk,
	paymentComponent
) => {
	// Get dispatch functions from the AXO store
	const {
		setIsAxoActive,
		setIsAxoScriptLoaded,
		setShippingAddress,
		setCardDetails,
		setCardChangeHandler,
	} = useDispatch( STORE_NAME );

	// Check if PayPal script has loaded
	const paypalLoaded = usePayPalScript(
		namespace,
		ppcpConfig,
		isConfigLoaded
	);

	// Set up card and shipping address change handlers
	const onChangeCardButtonClick = useCardChange( fastlaneSdk );
	const onChangeShippingAddressClick = useShippingAddressChange(
		fastlaneSdk,
		setShippingAddress
	);

	// Get customer data and setter functions
	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: setWooShippingAddress,
		setBillingAddress: setWooBillingAddress,
	} = useCustomerData();

	// Set up phone sync handler
	usePhoneSyncHandler( paymentComponent );

	// Set up session restoration
	useSessionRestoration( fastlaneSdk );

	// Initialize class toggles on mount
	useEffect( () => {
		initializeClassToggles();
	}, [] );

	// Set up AXO functionality when PayPal and Fastlane are loaded
	useEffect( () => {
		setupWatermark( fastlaneSdk );
		if ( paypalLoaded && fastlaneSdk ) {
			setIsAxoScriptLoaded( true );
			setIsAxoActive( true );
			setCardChangeHandler( onChangeCardButtonClick );

			// Create and set up email lookup handler
			const emailLookupHandler = createEmailLookupHandler(
				fastlaneSdk,
				setShippingAddress,
				setCardDetails,
				snapshotFields,
				wooShippingAddress,
				wooBillingAddress,
				setWooShippingAddress,
				setWooBillingAddress,
				onChangeShippingAddressClick
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
		setCardChangeHandler,
	] );

	return paypalLoaded;
};

export default useAxoSetup;
