import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';

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
	console.log( 'Axo component rendering' );
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );
	const fastlaneSdk = useAxoBlockManager( axoConfig, ppcpConfig );

	const { setIsAxoActive, setIsGuest, setIsAxoScriptLoaded } =
		useDispatch( STORE_NAME );

	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		console.log( 'Initializing class toggles' );
		initializeClassToggles();
	}, [] );

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
		if ( ! paypalLoaded ) {
			console.log( 'Loading PayPal script' );
			loadPaypalScript( ppcpConfig, () => {
				console.log( 'PayPal script loaded' );
				setPaypalLoaded( true );
			} );
		}
	}, [ paypalLoaded, ppcpConfig ] );

	const {
		setShippingAddress: setWooShippingAddress,
		setBillingAddress: setWooBillingAddress,
	} = useCustomerData();

	const onChangeShippingAddressClick = useShippingAddressChange(
		fastlaneSdk,
		setShippingAddress,
		updateWooShippingAddress
	);
	const onChangeCardButtonClick = useCardChange(
		fastlaneSdk,
		setCard,
		updateWooBillingAddress
	);

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
	] );

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

	const handlePaymentLoad = useCallback( ( paymentComponent ) => {
		console.log( 'Payment component loaded', paymentComponent );
	}, [] );

	const handleChange = ( selectedCard ) => {
		console.log( 'Card selection changed', selectedCard );
		setCard( selectedCard );
	};

	console.log( 'Rendering Axo component', {
		fastlaneSdk,
		card,
		shippingAddress,
	} );

	return fastlaneSdk ? (
		<Payment
			fastlaneSdk={ fastlaneSdk }
			card={ card }
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
	canMakePayment: () => {
		console.log( 'Checking if payment can be made' );
		return true;
	},
	supports: {
		showSavedCards: true,
		features: ppcpConfig.supports,
	},
} );

console.log( 'Axo module loaded' );

export default Axo;
