import { populateWooFields } from '../helpers/fieldHelpers';
import { injectShippingChangeButton } from '../helpers/shippingChangeButtonManager';
import { injectCardChangeButton } from '../helpers/cardChangeButtonManager';

export const onEmailSubmit = async (
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

			setIsGuest( false );
			setShippingAddress( profileData.shippingAddress );
			setCard( profileData.card );
			setShouldIncludeAdditionalInfo( false );

			console.log( 'Profile Data:', profileData );

			populateWooFields(
				profileData,
				setWooShippingAddress,
				setWooBillingAddress
			);

			injectShippingChangeButton( onChangeShippingAddressClick );
			injectCardChangeButton( onChangeButtonClick );
		} else {
			console.warn( 'Authentication failed or did not succeed' );
		}
	} catch ( error ) {
		console.error( 'Error during email lookup or authentication:', error );
	}
};
