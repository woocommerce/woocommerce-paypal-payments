import { useEffect, useState } from '@wordpress/element';
import { useCustomerData } from './useCustomerData';
import { ShippingChangeButton } from './shippingChangeButtonManager';
import { loadPaypalScript } from '../utils/ScriptLoading';
import Payment from './Payment';
import useAxoBlockManager from './useAxoBlockManager';

const ppcpConfig = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

// AxoBlock Component
const AxoBlock = () => {
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ isGuest, setIsGuest ] = useState( true );
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );

	const fastlaneSdk = useAxoBlockManager( axoConfig, ppcpConfig );

	// WooCommerce customer data hooks
	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	// Snapshot and restore original checkout fields from localStorage
	const snapshotFields = () => {
		const originalData = {
			shippingAddress: wooShippingAddress,
			billingAddress: wooBillingAddress,
		};
		localStorage.setItem(
			'originalCheckoutFields',
			JSON.stringify( originalData )
		);
		console.log( 'originalFields saved to localStorage', originalData );
	};

	const restoreOriginalFields = () => {
		const savedData = JSON.parse(
			localStorage.getItem( 'originalCheckoutFields' )
		);
		if ( savedData ) {
			if ( savedData.shippingAddress ) {
				updateWooShippingAddress( savedData.shippingAddress );
			}
			if ( savedData.billingAddress ) {
				updateWooBillingAddress( savedData.billingAddress );
			}
			console.log(
				'originalFields restored from localStorage',
				savedData
			);
		}
	};

	// Cleanup function to handle component unmounting
	useEffect( () => {
		// Perform cleanup when the component unmounts
		return () => {
			console.log( 'Axo component unmounted, restoring original fields' );
			restoreOriginalFields(); // Restore original fields when Axo is unmounted
		};
	}, [] );

	useEffect( () => {
		console.log( 'ppcpConfig', ppcpConfig );
		loadPaypalScript( ppcpConfig, () => {
			console.log( 'PayPal script loaded' );
			setPaypalLoaded( true );
		} );
	}, [] );

	const onEmailSubmit = async ( email ) => {
		try {
			console.log( 'Email value being looked up:', email );
			const lookup =
				await fastlaneSdk.identity.lookupCustomerByEmail( email );

			if ( ! lookup.customerContextId ) {
				console.warn( 'No customerContextId found in the response' );
				return;
			}

			const { authenticationState, profileData } =
				await fastlaneSdk.identity.triggerAuthenticationFlow(
					lookup.customerContextId
				);

			if ( authenticationState === 'succeeded' ) {
				// Snapshot original fields before updating with Fastlane data
				snapshotFields();

				// Update WooCommerce fields with Fastlane data
				setIsGuest( false );
				setShippingAddress( profileData.shippingAddress );
				setCard( profileData.card );

				const { address, name, phoneNumber } =
					profileData.shippingAddress;
				updateWooShippingAddress( {
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

				const billingData =
					profileData.card.paymentSource.card.billingAddress;
				updateWooBillingAddress( {
					first_name: profileData.name.firstName,
					last_name: profileData.name.lastName,
					address_1: billingData.addressLine1,
					address_2: billingData.addressLine2 || '',
					city: billingData.adminArea2,
					state: billingData.adminArea1,
					postcode: billingData.postalCode,
					country: billingData.countryCode,
				} );
			} else {
				console.warn( 'Authentication failed or did not succeed' );
			}
		} catch ( error ) {
			console.error(
				'Error during email lookup or authentication:',
				error
			);
		}
	};

	const onChangeShippingAddressClick = async () => {
		if ( fastlaneSdk ) {
			const { selectionChanged, selectedAddress } =
				await fastlaneSdk.profile.showShippingAddressSelector();
			if ( selectionChanged ) {
				setShippingAddress( selectedAddress );
				const { address, name, phoneNumber } = selectedAddress;

				updateWooShippingAddress( {
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

	return fastlaneSdk ? (
		<div>
			<Payment
				fastlaneSdk={ fastlaneSdk }
				card={ card }
				shippingAddress={ shippingAddress }
				isGuestFlow={ isGuest }
				onPaymentLoad={ handlePaymentLoad }
				onChangeButtonClick={ onChangeButtonClick }
			/>
			<ShippingChangeButton
				onChangeShippingAddressClick={ onChangeShippingAddressClick }
			/>
		</div>
	) : (
		<div>Loading Fastlane...</div>
	);
};

export default AxoBlock;
