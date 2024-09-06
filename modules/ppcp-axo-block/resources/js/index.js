import { useCallback, useEffect, useState } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

// Hooks
import useAxoBlockManager from './hooks/useAxoBlockManager';
import { useCustomerData } from './hooks/useCustomerData';

// Components
import { Payment } from './components/Payment';

// Helpers
import { snapshotFields, restoreOriginalFields } from './helpers/fieldHelpers';
import { setupWatermark, removeWatermark } from './helpers/watermarkHelpers';
import { removeCardChangeButton } from './helpers/cardChangeButtonManager';
import { removeShippingChangeButton } from './helpers/shippingChangeButtonManager';

// Event handlers
import { onEmailSubmit } from './events/fastlaneEmailManager';

const ppcpConfig = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

const Axo = () => {
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ isGuest, setIsGuest ] = useState( true );
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );
	const [ shouldIncludeAdditionalInfo, setShouldIncludeAdditionalInfo ] =
		useState( true );
	const fastlaneSdk = useAxoBlockManager( axoConfig, ppcpConfig );

	// Access WooCommerce customer data
	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	// Cleanup logic for Change buttons
	useEffect( () => {
		return () => {
			removeShippingChangeButton();
			removeCardChangeButton();
			removeWatermark();

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

	useEffect( () => {
		if ( paypalLoaded && fastlaneSdk ) {
			console.log( 'Fastlane SDK and PayPal loaded' );
			setupWatermark(
				fastlaneSdk,
				shouldIncludeAdditionalInfo,
				async ( email ) => {
					await onEmailSubmit(
						email,
						fastlaneSdk,
						setIsGuest,
						isGuest,
						setShippingAddress,
						setCard,
						snapshotFields,
						wooShippingAddress,
						wooBillingAddress,
						setWooShippingAddress,
						setWooBillingAddress,
						onChangeShippingAddressClick,
						onChangeButtonClick,
						shouldIncludeAdditionalInfo,
						setShouldIncludeAdditionalInfo
					);
				}
			);
		}

		return () => {
			removeWatermark();
		};
	}, [ paypalLoaded, fastlaneSdk, shouldIncludeAdditionalInfo ] );

	const onChangeShippingAddressClick = useCallback( async () => {
		// Updated
		if ( fastlaneSdk ) {
			const { selectionChanged, selectedAddress } =
				await fastlaneSdk.profile.showShippingAddressSelector();
			if ( selectionChanged ) {
				setShippingAddress( selectedAddress );
				console.log(
					'Selected shipping address changed:',
					selectedAddress
				);

				const { address, name, phoneNumber } = selectedAddress;

				setWooShippingAddress( {
					first_name: name.firstName,
					last_name: name.lastName,
					address_1: address.addressLine1,
					address_2: address.addressLine2 || '',
					city: address.adminArea2,
					state: address.adminArea1,
					postcode: address.postalCode,
					country: address.countryCode,
					phone: phoneNumber.nationalNumber,
				} );
			}
		}
	}, [ fastlaneSdk, setWooShippingAddress ] );

	const onChangeButtonClick = useCallback( async () => {
		const { selectionChanged, selectedCard } =
			await fastlaneSdk.profile.showCardSelector();
		if ( selectionChanged ) {
			setCard( selectedCard );
		}
	}, [ fastlaneSdk ] );

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
			isGuest={ isGuest }
			onPaymentLoad={ handlePaymentLoad }
			onChangeButtonClick={ onChangeButtonClick }
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
