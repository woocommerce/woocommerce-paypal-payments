import { populateWooFields } from '../helpers/fieldHelpers';
import { injectShippingChangeButton } from '../components/Shipping';
import { injectCardChangeButton } from '../components/Card';
import { setIsGuest, setIsEmailLookupCompleted } from '../stores/axoStore';

export const createEmailLookupHandler = (
	fastlaneSdk,
	setShippingAddress,
	setCardDetails,
	snapshotFields,
	wooShippingAddress,
	wooBillingAddress,
	setWooShippingAddress,
	setWooBillingAddress,
	onChangeShippingAddressClick,
	onChangeCardButtonClick
) => {
	return async ( email ) => {
		try {
			console.log( 'Email value being looked up:', email );

			if ( ! fastlaneSdk ) {
				throw new Error( 'FastlaneSDK is not initialized' );
			}

			if ( ! fastlaneSdk.identity ) {
				throw new Error(
					'FastlaneSDK identity object is not available'
				);
			}

			const lookup =
				await fastlaneSdk.identity.lookupCustomerByEmail( email );

			console.log( 'Lookup response:', lookup );

			// Gary flow
			if ( lookup && lookup.customerContextId === '' ) {
				setIsEmailLookupCompleted( true );
			}

			if ( ! lookup || ! lookup.customerContextId ) {
				console.warn( 'No customerContextId found in the response' );
				return;
			}

			const authResponse =
				await fastlaneSdk.identity.triggerAuthenticationFlow(
					lookup.customerContextId
				);

			if ( ! authResponse || ! authResponse.authenticationState ) {
				throw new Error( 'Invalid authentication response' );
			}

			const { authenticationState, profileData } = authResponse;

			// OTP success/fail/cancel flow
			if ( authResponse ) {
				setIsEmailLookupCompleted( true );
			}

			if ( authenticationState === 'succeeded' ) {
				snapshotFields( wooShippingAddress, wooBillingAddress );
				setIsGuest( false );

				if ( profileData && profileData.shippingAddress ) {
					setShippingAddress( profileData.shippingAddress );
				}
				if ( profileData && profileData.card ) {
					setCardDetails( profileData.card );
				}

				console.log( 'Profile Data:', profileData );

				populateWooFields(
					profileData,
					setWooShippingAddress,
					setWooBillingAddress
				);

				injectShippingChangeButton( onChangeShippingAddressClick );
				injectCardChangeButton( onChangeCardButtonClick );
			} else {
				console.warn( 'Authentication failed or did not succeed' );
			}
		} catch ( error ) {
			console.error(
				'Error during email lookup or authentication:',
				error
			);
			throw error;
		}
	};
};
