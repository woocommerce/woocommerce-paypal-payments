import { useCallback, useEffect, useState, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

import { registerPaymentMethod } from '@woocommerce/blocks-registry';

import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

// Hooks
import useAxoBlockManager from './hooks/useAxoBlockManager';
import { useCustomerData } from './hooks/useCustomerData';
import {
	useShippingAddressChange,
	useCardChange,
} from './hooks/useUserInfoChange';

// Components
import { Payment } from './components/Payment';

// Helpers
import { snapshotFields, restoreOriginalFields } from './helpers/fieldHelpers';
import { removeWatermark, setupWatermark } from './helpers/watermarkHelpers';
import { removeCardChangeButton } from './helpers/cardChangeButtonManager';
import { removeShippingChangeButton } from './helpers/shippingChangeButtonManager';
import { initializeClassToggles } from './helpers/classnamesManager';

// Stores
import { STORE_NAME } from './stores/axoStore';

// Event handlers
import { createEmailLookupHandler } from './events/emailLookupManager';
import {
	setupEmailFunctionality,
	removeEmailFunctionality,
	isEmailFunctionalitySetup,
} from './helpers/emailSubmissionManager';

const ppcpConfig = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

const Axo = () => {
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );
	const fastlaneSdk = useAxoBlockManager( axoConfig, ppcpConfig );

	const { setIsAxoActive, setIsGuest, setIsAxoScriptLoaded } =
		useDispatch( STORE_NAME );

	// Access WooCommerce customer data
	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		initializeClassToggles();
	}, [] );

	useEffect( () => {
		return () => {
			// Restore WooCommerce fields
			restoreOriginalFields(
				updateWooShippingAddress,
				updateWooBillingAddress
			);
		};
	}, [ updateWooShippingAddress, updateWooBillingAddress ] );

	const {
		setShippingAddress: setWooShippingAddress,
		setBillingAddress: setWooBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		if ( ! paypalLoaded ) {
			loadPaypalScript( ppcpConfig, () => {
				setPaypalLoaded( true );
			} );
		}
	}, [ paypalLoaded, ppcpConfig ] );

	const onChangeShippingAddressClick = useShippingAddressChange(
		fastlaneSdk,
		setShippingAddress,
		updateWooShippingAddress
	);

	const onChangeCardButtonClick = useCardChange( fastlaneSdk, setCard );

	useEffect( () => {
		setupWatermark( fastlaneSdk );
		if ( paypalLoaded && fastlaneSdk ) {
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
	] );

	useEffect( () => {
		return () => {
			setIsAxoActive( false );
			setIsGuest( true );

			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();
		};
	}, [
		setIsAxoActive,
		setIsGuest,
		updateWooShippingAddress,
		updateWooBillingAddress,
	] );

	useEffect( () => {
		return () => {
			setIsAxoActive( false );
			setIsGuest( true );

			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();

			if ( isEmailFunctionalitySetup() ) {
				removeEmailFunctionality();
			}
		};
	}, [] );

	const handlePaymentLoad = useCallback(
		( paymentComponent ) => {
			console.log( 'Payment component loaded', paymentComponent );
		},
		[] // Empty dependency array to avoid re-creating the function on every render
	);

	const handleChange = ( selectedCard ) => {
		setCard( selectedCard );
	};

	return fastlaneSdk ? (
		<Payment
			fastlaneSdk={ fastlaneSdk }
			card={ card }
			shippingAddress={ shippingAddress }
			onChange={ handleChange }
			onPaymentLoad={ handlePaymentLoad }
			onChangeButtonClick={ onChangeCardButtonClick }
		/>
	) : (
		<div>Loading Fastlane...</div>
	);
};

// Register the payment method
registerPaymentMethod( {
	name: ppcpConfig.id,
	label: (
		<div
			id="ppcp-axo-block-radio-label"
			dangerouslySetInnerHTML={ { __html: ppcpConfig.title } }
		/>
	),
	content: <Axo />,
	edit: <h1>This is Axo Blocks in the editor</h1>,
	ariaLabel: ppcpConfig.title,
	canMakePayment: () => true,
	supports: {
		showSavedCards: true,
		features: ppcpConfig.supports,
	},
} );

export default Axo;
