import { useEffect, useState } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

// Hooks
import useAxoBlockManager from './hooks/useAxoBlockManager';
import { useCustomerData } from './hooks/useCustomerData';

// Components
import { Payment } from './components/Payment';

// Helpers
import {
	injectShippingChangeButton,
	removeShippingChangeButton,
} from './helpers/buttonHelpers';
import { snapshotFields, restoreOriginalFields } from './helpers/fieldHelpers';
import { setupWatermark, cleanupWatermark } from './helpers/watermarkHelpers';

// Event handlers
import { onEmailSubmit } from './events/fastlaneEmailManager';

const ppcpConfig = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

// Call this function when the payment gateway is loaded or switched
const handlePaymentGatewaySwitch = ( onChangeShippingAddressClick ) => {
	removeShippingChangeButton();
	injectShippingChangeButton( onChangeShippingAddressClick );
};

// Axo Component
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

	// Cleanup function to handle component unmounting
	useEffect( () => {
		return () => {
			console.log( 'Axo component unmounted, restoring original fields' );
			restoreOriginalFields(
				updateWooShippingAddress,
				updateWooBillingAddress
			); // Pass the correct arguments
		};
	}, [ updateWooShippingAddress, updateWooBillingAddress ] ); // Add the dependencies

	const {
		setShippingAddress: setWooShippingAddress,
		setBillingAddress: setWooBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		console.log( 'ppcpConfig', ppcpConfig );
		loadPaypalScript( ppcpConfig, () => {
			console.log( 'PayPal script loaded' );
			setPaypalLoaded( true );
		} );
	}, [] );

	useEffect( () => {
		let watermarkHandlers = {};

		if ( paypalLoaded && fastlaneSdk ) {
			console.log( 'Fastlane SDK and PayPal loaded' );

			watermarkHandlers = setupWatermark(
				fastlaneSdk,
				shouldIncludeAdditionalInfo
			);
			const { emailInput } = watermarkHandlers;

			console.log(
				'shouldIncludeAdditionalInfo',
				shouldIncludeAdditionalInfo
			);

			if ( emailInput ) {
				emailInput.addEventListener( 'keyup', async ( event ) => {
					const email = event.target.value;
					if ( email ) {
						await onEmailSubmit(
							email,
							fastlaneSdk,
							setIsGuest,
							setShippingAddress,
							setCard,
							snapshotFields,
							wooShippingAddress,
							wooBillingAddress,
							setWooShippingAddress,
							setWooBillingAddress,
							handlePaymentGatewaySwitch,
							onChangeShippingAddressClick,
							onChangeButtonClick,
							shouldIncludeAdditionalInfo,
                            setShouldIncludeAdditionalInfo
						);
					}
				} );
			}
		}

		return () => {
			cleanupWatermark( watermarkHandlers );
		};
	}, [ paypalLoaded, fastlaneSdk, shouldIncludeAdditionalInfo ] );

	const onChangeShippingAddressClick = async () => {
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
	};

	const onChangeButtonClick = async () => {
		const { selectionChanged, selectedCard } =
			await fastlaneSdk.profile.showCardSelector();
		if ( selectionChanged ) {
			setCard( selectedCard );
		}
	};

	const handlePaymentLoad = ( paymentComponent ) => {
		console.log( 'Payment component loaded', paymentComponent );
	};

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
			isGuestFlow={ isGuest }
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
