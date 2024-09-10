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

// Stores
import { STORE_NAME } from './stores/axoStore';

// Event handlers
import { onEmailSubmit } from './events/emailLookupManager';
import {
	setupEmailEvent,
	removeEmailEvent,
	isEmailEventSetup,
} from './helpers/emailHelpers';

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

	const isAxoActive = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAxoActive()
	);
	const { setIsAxoActive, setIsGuest } = useDispatch( STORE_NAME );

	const handleEmailInputRef = useRef( null );

	// Access WooCommerce customer data
	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		console.log( 'isAxoActive updated:', isAxoActive );
	}, [ isAxoActive ] );

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
		console.log( 'ppcpConfig', ppcpConfig );
		if ( ! paypalLoaded ) {
			loadPaypalScript( ppcpConfig, () => {
				console.log( 'PayPal script loaded' );
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

	const handleEmailInput = useCallback(
		async ( email ) => {
			if ( fastlaneSdk ) {
				await onEmailSubmit(
					email,
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
			} else {
				console.warn( 'FastLane SDK is not available' );
			}
		},
		[
			fastlaneSdk,
			setShippingAddress,
			setCard,
			wooShippingAddress,
			wooBillingAddress,
			setWooShippingAddress,
			setWooBillingAddress,
			onChangeShippingAddressClick,
			onChangeCardButtonClick,
		]
	);

	useEffect( () => {
		handleEmailInputRef.current = handleEmailInput;
	}, [ handleEmailInput ] );

	useEffect( () => {
		if ( paypalLoaded && fastlaneSdk ) {
			console.log( 'Enabling Axo' );
			setIsAxoActive( true );
			setupWatermark( fastlaneSdk );
			setupEmailEvent( handleEmailInputRef.current );
		}
	}, [ paypalLoaded, fastlaneSdk, setIsAxoActive ] );

	useEffect( () => {
		return () => {
			console.log( 'Disabling Axo' );
			console.log( 'Axo component unmounting' );
			setIsAxoActive( false );
			setIsGuest( true );

			console.log( 'isAxoActive', isAxoActive );

			console.log( 'isEmailEventSetup', isEmailEventSetup() );

			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();

			if ( isEmailEventSetup() ) {
				console.log(
					'Axo became inactive, removing email event listener'
				);
				removeEmailEvent( handleEmailInputRef.current );
			}
		};
	}, [
		setIsAxoActive,
		setIsGuest,
		updateWooShippingAddress,
		updateWooBillingAddress,
	] );

	useEffect( () => {
		return () => {
			console.log( 'Disabling Axo' );
			setIsAxoActive( false );
			setIsGuest( true );

			console.log( 'isAxoActive', isAxoActive );

			console.log( 'isEmailEventSetup', isEmailEventSetup() );

			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();

			if ( isEmailEventSetup() ) {
				console.log(
					'Axo became inactive, removing email event listener'
				);
				removeEmailEvent( handleEmailInputRef.current );
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
		console.log( 'Selected card changed', selectedCard );
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
