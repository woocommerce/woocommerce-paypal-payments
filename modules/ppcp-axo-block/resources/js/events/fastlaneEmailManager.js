import ReactDOM from 'react-dom/client';
import { FastlaneWatermark } from '../components/FastlaneWatermark';

export const onEmailSubmit = async (
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
) => {
	try {
		console.log( 'Email value being looked up:', email );
		const lookup =
			await fastlaneSdk.identity.lookupCustomerByEmail( email );

		console.log( 'Lookup response:', lookup );

		if ( ! lookup.customerContextId ) {
			console.warn( 'No customerContextId found in the response' );
			return;
		}

		const { authenticationState, profileData } =
			await fastlaneSdk.identity.triggerAuthenticationFlow(
				lookup.customerContextId
			);

		console.log( 'authenticationState', authenticationState );

		if ( authenticationState === 'succeeded' ) {
			// Capture the existing WooCommerce data before updating it
			snapshotFields( wooShippingAddress, wooBillingAddress );

			// Update WooCommerce fields with Fastlane data
			setIsGuest( false );
			setShippingAddress( profileData.shippingAddress );
			setCard( profileData.card );
			setShouldIncludeAdditionalInfo( false );

			console.log( 'Profile Data:', profileData );

			const { address, name, phoneNumber } = profileData.shippingAddress;

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

			const billingData =
				profileData.card.paymentSource.card.billingAddress;
			setWooBillingAddress( {
				first_name: profileData.name.firstName,
				last_name: profileData.name.lastName,
				address_1: billingData.addressLine1,
				address_2: billingData.addressLine2 || '',
				city: billingData.adminArea2,
				state: billingData.adminArea1,
				postcode: billingData.postalCode,
				country: billingData.countryCode,
			} );

			const radioLabelElement = document.getElementById(
				'ppcp-axo-block-radio-label'
			);
			if ( radioLabelElement ) {
				const watermarkRoot = ReactDOM.createRoot( radioLabelElement );
				watermarkRoot.render(
					<>
						<FastlaneWatermark
							fastlaneSdk={ fastlaneSdk }
							name="fastlane-watermark-radio"
							includeAdditionalInfo={
								false
							}
						/>
						<button
							className="wc-block-checkout-axo-block-card__edit"
							aria-label="Change billing details"
							type="button"
							onClick={ onChangeButtonClick }
						>
							Change
						</button>
					</>
				);
			}

			handlePaymentGatewaySwitch( onChangeShippingAddressClick );
		} else {
			console.warn( 'Authentication failed or did not succeed' );
		}
	} catch ( error ) {
		console.error( 'Error during email lookup or authentication:', error );
	}
};
