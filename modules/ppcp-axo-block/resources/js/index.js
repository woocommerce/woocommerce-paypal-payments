import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';

import { registerPaymentMethod } from '@woocommerce/blocks-registry';

import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

// Hooks
import useFastlaneSdk from './hooks/useFastlaneSdk';
import {
	useCustomerData,
	useTokenizeCustomerData,
} from './hooks/useCustomerData';
import { useShippingAddressChange } from './hooks/useShippingAddressChange';
import { useCardChange } from './hooks/useCardChange';

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

const gatewayHandle = 'ppcp-axo-gateway';
const ppcpConfig = wc.wcSettings.getSetting( `${ gatewayHandle }_data` );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

const Axo = ( props ) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );
	const [ paymentComponent, setPaymentComponent ] = useState( null );
	const tokenizedCustomerData = useTokenizeCustomerData();
	const fastlaneSdk = useFastlaneSdk( axoConfig, ppcpConfig );

	console.log( 'Axo component rendering' );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			// Validate payment options and emit response.

			// Note: This response supports the Ryan flow (payment via saved card-token)
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						axo_nonce: card?.id,
					},
				},
			};
		} );

		// Unsubscribes when this component is unmounted.
		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentSetup,
		card,
	] );

	const handlePaymentSetup = useCallback( async () => {
		const isRyanFlow = !! card?.id;
		let cardToken = card?.id;

		if ( ! cardToken && paymentComponent ) {
			cardToken = await paymentComponent
				.getPaymentToken( tokenizedCustomerData )
				.then( ( response ) => response.id );
		}

		if ( ! cardToken ) {
			return {
				type: emitResponse.responseTypes.ERROR,
				message: 'Could not process the payment (tokenization error)',
			};
		}

		return {
			type: emitResponse.responseTypes.SUCCESS,
			meta: {
				paymentMethodData: {
					fastlane_member: isRyanFlow,
					axo_nonce: cardToken,
				},
			},
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		card,
		paymentComponent,
		tokenizedCustomerData,
	] );

	const { setIsAxoActive, setIsGuest, setIsAxoScriptLoaded } =
		useDispatch( STORE_NAME );

	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	/**
	 * `onPaymentSetup()` fires when we enter the "PROCESSING" state in the checkout flow.
	 * It pre-processes the payment details and returns data for server-side processing.
	 */
	useEffect( () => {
		const unsubscribe = onPaymentSetup( handlePaymentSetup );

		return () => {
			unsubscribe();
		};
	}, [ onPaymentSetup, handlePaymentSetup ] );

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

	const handlePaymentLoad = useCallback( ( component ) => {
		setPaymentComponent( component );
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
