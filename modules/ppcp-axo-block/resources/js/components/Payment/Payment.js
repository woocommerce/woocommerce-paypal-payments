import { useEffect, useCallback, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { log } from '../../../../../ppcp-axo/resources/js/Helper/Debug';
import { Card } from '../Card';
import { STORE_NAME } from '../../stores/axoStore';

/**
 * Renders the payment component based on the user's state (guest or authenticated).
 *
 * @param {Object}   props
 * @param {Object}   props.fastlaneSdk   - The Fastlane SDK instance.
 * @param {Function} props.onPaymentLoad - Callback function when payment component is loaded.
 * @return {JSX.Element} The rendered payment component.
 */
export const Payment = ( { fastlaneSdk, onPaymentLoad } ) => {
	const [ isCardElementReady, setIsCardElementReady ] = useState( false );

	// Select relevant states from the AXO store
	const { isGuest, isEmailLookupCompleted, cardDetails } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			isEmailLookupCompleted:
				select( STORE_NAME ).getIsEmailLookupCompleted(),
			cardDetails: select( STORE_NAME ).getCardDetails(),
		} ),
		[]
	);

	/**
	 * Loads and renders the Fastlane card fields component when necessary.
	 * This function is called for:
	 * 1. Guest users who have completed email lookup
	 * 2. Authenticated users who are missing card details
	 *
	 * The component allows users to enter new card details for payment.
	 */
	const loadPaymentComponent = useCallback( async () => {
		if (
			( isGuest && isEmailLookupCompleted && isCardElementReady ) ||
			( ! isGuest && ! cardDetails )
		) {
			try {
				const paymentComponent =
					await fastlaneSdk.FastlaneCardComponent( {} );
				// Check if the container exists before rendering
				const cardContainer =
					document.querySelector( '#fastlane-card' );
				if ( cardContainer ) {
					paymentComponent.render( '#fastlane-card' );
					onPaymentLoad( paymentComponent );
				}
			} catch ( error ) {
				log( `Error loading payment component: ${ error }`, 'error' );
			}
		}
	}, [
		isGuest,
		isEmailLookupCompleted,
		isCardElementReady,
		cardDetails,
		fastlaneSdk,
		onPaymentLoad,
	] );

	// Set card element ready when guest email lookup is completed
	useEffect( () => {
		if ( isGuest && isEmailLookupCompleted ) {
			setIsCardElementReady( true );
		}
	}, [ isGuest, isEmailLookupCompleted ] );

	// Load payment component when card element is ready
	useEffect( () => {
		if ( isCardElementReady ) {
			loadPaymentComponent();
		}
	}, [ isCardElementReady, loadPaymentComponent ] );

	/**
	 * Determines which component to render based on the current state.
	 *
	 * Rendering logic:
	 * 1. For guests without completed email lookup: Show message to enter email
	 * 2. For guests with completed email lookup: Render Fastlane card fields
	 * 3. For authenticated users without card details: Render Fastlane card fields
	 * 4. For authenticated users with card details: Render Card component
	 *
	 * @return {JSX.Element} The appropriate component based on the current state
	 */
	const renderPaymentComponent = () => {
		// Case 1: Guest user without completed email lookup
		if ( isGuest && ! isEmailLookupCompleted ) {
			return (
				<div id="ppcp-axo-block-radio-content">
					{ __(
						'Enter your email address above to continue.',
						'woocommerce-paypal-payments'
					) }
				</div>
			);
		}

		// Case 2 & 3: Guest with completed email lookup or authenticated user without card details
		if (
			( isGuest && isEmailLookupCompleted ) ||
			( ! isGuest && ! cardDetails )
		) {
			return <div id="fastlane-card" />;
		}

		// Case 4: Authenticated user with card details
		return <Card fastlaneSdk={ fastlaneSdk } showWatermark={ ! isGuest } />;
	};

	return renderPaymentComponent();
};
