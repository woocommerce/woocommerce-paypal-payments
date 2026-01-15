import { useEffect, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { setIsEmailLookupCompleted, STORE_NAME } from '@ppcp-axo-block/stores/axoStore';
import { log } from '@ppcp-axo/Helper/Debug';

/**
 * Hook to restore Fastlane session after payment failures using triggerAuthenticationFlow
 * Only runs when ppcp_fastlane_error=1 URL parameter is present
 * @param {Object} fastlaneSdk - The Fastlane SDK instance
 */
const useSessionRestoration = ( fastlaneSdk ) => {
	const { setShippingAddress, setCardDetails, setIsGuest } =
		useDispatch( STORE_NAME );
	const hasProcessed = useRef( false );

	useEffect( () => {
		if ( ! fastlaneSdk || hasProcessed.current ) {
			return;
		}

		const urlParams = new URLSearchParams( window.location.search );
		const hasErrorParam = urlParams.get( 'ppcp_fastlane_error' ) === '1';

		if ( ! hasErrorParam ) {
			return;
		}

		// Remove the error parameter from URL
		urlParams.delete( 'ppcp_fastlane_error' );
		const newUrl = new URL( window.location );
		newUrl.search = urlParams.toString();
		window.history.replaceState( {}, '', newUrl );

		hasProcessed.current = true;

		const restoreSession = async () => {
			try {
				const emailInput = document.getElementById( 'email' );

				if ( emailInput?.value ) {
					const lookupResult =
						await fastlaneSdk.identity.lookupCustomerByEmail(
							emailInput.value
						);

					wp.data.dispatch( STORE_NAME ).setIsEmailSubmitted( true );

					if ( lookupResult?.customerContextId ) {
						const customerContextId =
							lookupResult.customerContextId;

						const authenticatedCustomerResult =
							await fastlaneSdk.identity.triggerAuthenticationFlow(
								customerContextId
							);

						if (
							authenticatedCustomerResult?.authenticationState ===
							'succeeded'
						) {
							const { profileData } = authenticatedCustomerResult;
							setIsGuest( false );

							if ( profileData?.shippingAddress ) {
								setShippingAddress(
									profileData.shippingAddress
								);
							}

							if ( profileData?.card ) {
								setCardDetails( profileData.card );
							}

							setIsEmailLookupCompleted( true );
						}
					}
				}
			} catch ( error ) {
				log( 'Failed to restore Fastlane session', 'warn' );
			}
		};

		restoreSession();
	}, [ fastlaneSdk, setShippingAddress, setCardDetails, setIsGuest ] );
};

export default useSessionRestoration;
