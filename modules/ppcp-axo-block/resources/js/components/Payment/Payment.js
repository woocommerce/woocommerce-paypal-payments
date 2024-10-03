import { useEffect, useCallback, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
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
	const { isGuest, isEmailLookupCompleted } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			isEmailLookupCompleted:
				select( STORE_NAME ).getIsEmailLookupCompleted(),
		} ),
		[]
	);

	const loadPaymentComponent = useCallback( async () => {
		if ( isGuest && isEmailLookupCompleted && isCardElementReady ) {
			const paymentComponent = await fastlaneSdk.FastlaneCardComponent(
				{}
			);
			paymentComponent.render( `#fastlane-card` );
			onPaymentLoad( paymentComponent );
		}
	}, [
		isGuest,
		isEmailLookupCompleted,
		isCardElementReady,
		fastlaneSdk,
		onPaymentLoad,
	] );

	// Set card element ready when guest email lookup is completed
	useEffect( () => {
		if ( isGuest && isEmailLookupCompleted ) {
			setIsCardElementReady( true );
		}
	}, [ isGuest, isEmailLookupCompleted ] );

	// Load payment component when dependencies change
	useEffect( () => {
		loadPaymentComponent();
	}, [ loadPaymentComponent ] );

	// Conditional rendering based on user state:
	// 1. If authenticated: Render the Card component
	// 2. If guest with completed email lookup: Render the card fields
	// 3. If guest without completed email lookup: Render a message to enter email
	if ( isGuest ) {
		if ( isEmailLookupCompleted ) {
			return <div id="fastlane-card" key="fastlane-card" />;
		}
		return (
			<div id="ppcp-axo-block-radio-content">
				{ __(
					'Enter your email address above to continue.',
					'woocommerce-paypal-payments'
				) }
			</div>
		);
	}
	return <Card fastlaneSdk={ fastlaneSdk } showWatermark={ ! isGuest } />;
};
