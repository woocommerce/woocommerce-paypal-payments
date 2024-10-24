import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { populateWooFields } from '../helpers/fieldHelpers';
import { injectShippingChangeButton } from '../components/Shipping';
import { setIsGuest, setIsEmailLookupCompleted } from '../stores/axoStore';

/**
 * Creates an email lookup handler function for AXO checkout.
 *
 * @param {Object}   fastlaneSdk                  - The Fastlane SDK instance.
 * @param {Function} setShippingAddress           - Function to set shipping address in the store.
 * @param {Function} setCardDetails               - Function to set card details in the store.
 * @param {Function} snapshotFields               - Function to save current field values.
 * @param {Object}   wooShippingAddress           - Current WooCommerce shipping address.
 * @param {Object}   wooBillingAddress            - Current WooCommerce billing address.
 * @param {Function} setWooShippingAddress        - Function to update WooCommerce shipping address.
 * @param {Function} setWooBillingAddress         - Function to update WooCommerce billing address.
 * @param {Function} onChangeShippingAddressClick - Handler for shipping address change.
 * @return {Function} The email lookup handler function.
 */
export const createEmailLookupHandler = (
	fastlaneSdk,
	setShippingAddress,
	setCardDetails,
	snapshotFields,
	wooShippingAddress,
	wooBillingAddress,
	setWooShippingAddress,
	setWooBillingAddress,
	onChangeShippingAddressClick
) => {
	return async ( email ) => {
		try {
			log( `Email value being looked up: ${ email }` );

			// Validate Fastlane SDK initialization
			if ( ! fastlaneSdk ) {
				throw new Error( 'FastlaneSDK is not initialized' );
			}

			if ( ! fastlaneSdk.identity ) {
				throw new Error(
					'FastlaneSDK identity object is not available'
				);
			}

			// Perform email lookup
			const lookup =
				await fastlaneSdk.identity.lookupCustomerByEmail( email );

			log( `Lookup response: ${ JSON.stringify( lookup ) }` );

			// Handle Gary flow (new user)
			if ( lookup && lookup.customerContextId === '' ) {
				setIsEmailLookupCompleted( true );
			}

			if ( ! lookup || ! lookup.customerContextId ) {
				log( 'No customerContextId found in the response', 'warn' );
				return;
			}

			// Trigger authentication flow
			const authResponse =
				await fastlaneSdk.identity.triggerAuthenticationFlow(
					lookup.customerContextId
				);

			if ( ! authResponse || ! authResponse.authenticationState ) {
				throw new Error( 'Invalid authentication response' );
			}

			const { authenticationState, profileData } = authResponse;

			// Mark email lookup as completed for OTP flow
			if ( authResponse ) {
				setIsEmailLookupCompleted( true );
			}

			// Handle successful authentication
			if ( authenticationState === 'succeeded' ) {
				// Save current field values
				snapshotFields( wooShippingAddress, wooBillingAddress );
				setIsGuest( false );

				// Update store with profile data
				if ( profileData && profileData.shippingAddress ) {
					setShippingAddress( profileData.shippingAddress );
				}
				if ( profileData && profileData.card ) {
					setCardDetails( profileData.card );
				}

				log( `Profile Data: ${ JSON.stringify( profileData ) }` );

				// Populate WooCommerce fields with profile data
				populateWooFields(
					profileData,
					setWooShippingAddress,
					setWooBillingAddress
				);

				// Inject the change button for shipping
				injectShippingChangeButton( onChangeShippingAddressClick );
			} else {
				log( 'Authentication failed or did not succeed', 'warn' );
			}
		} catch ( error ) {
			log(
				`Error during email lookup or authentication:
				${ error }`
			);
			throw error;
		}
	};
};
