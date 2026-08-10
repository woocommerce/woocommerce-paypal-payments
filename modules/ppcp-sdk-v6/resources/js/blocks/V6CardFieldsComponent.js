/**
 * Advanced Card Fields (ACDC), v6 Web SDK — WooCommerce Blocks checkout.
 *
 * The block counterpart to cardFields/renderer.js. It mounts the
 * number/expiry/CVV/name fields into React-rendered containers (rather than
 * the classic WC card-form inputs) and drives the submission through the
 * Blocks `onPaymentSetup` event (rather than intercepting a Place Order
 * click): a new card runs through the v6 card session (which also runs 3D
 * Secure automatically), the result is approved server-side, and the block
 * checkout submission then captures it via the existing
 * CreditCardGateway::process_payment().
 *
 * Scope: fresh card, one-time payment only. Saving a new card, paying with an
 * already-saved card, and the $0 free-trial variant are out of scope here, as
 * in the classic v6 story (PCP-5781).
 *
 * @package
 */

import {
	createElement,
	Fragment,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadSdkV6 } from '../sdkLoader';
import { createCardOrder, approveCardOrder } from '../endpointsAdapter';
import { cardFieldStyles } from '../cardFields/cardFieldStyles';
import { V6CardFieldContainer } from './V6CardFieldContainer';

/**
 * Runs the card submission: create the order, run the card session (which
 * runs 3D Secure), approve it server-side, and report the outcome to the
 * Blocks checkout. On success the block submit captures the session-stored
 * order through the existing CreditCardGateway.
 *
 * @param {Object} args               - Arguments.
 * @param {Object} args.config        - The sdk-v6 config object.
 * @param {string} args.context       - The page context (checkout-block).
 * @param {Object} args.session       - The v6 card-fields session.
 * @param {Object} args.responseTypes - The Blocks response-type constants.
 * @return {Promise<Object>} A Blocks onPaymentSetup response object.
 */
async function submitCardPayment( {
	config,
	context,
	session,
	responseTypes,
} ) {
	if ( ! session ) {
		return {
			type: responseTypes.ERROR,
			message: __(
				'The card form is not ready yet. Please try again.',
				'woocommerce-paypal-payments'
			),
		};
	}

	try {
		const { orderId } = await createCardOrder( config, context );
		const result = await session.submit( orderId );

		// The buyer closed the 3DS challenge or the popup. Blocks has no silent
		// retry, so surface a neutral prompt rather than a hard failure.
		if ( result.state === 'canceled' ) {
			return {
				type: responseTypes.ERROR,
				message: __(
					'Card authentication was not completed. Please try again.',
					'woocommerce-paypal-payments'
				),
			};
		}

		if ( result.state !== 'succeeded' ) {
			return {
				type: responseTypes.ERROR,
				message: __(
					'Card payment failed.',
					'woocommerce-paypal-payments'
				),
			};
		}

		await approveCardOrder( config, orderId );

		return { type: responseTypes.SUCCESS };
	} catch ( error ) {
		return {
			type: responseTypes.ERROR,
			message:
				error?.message ||
				__( 'Card payment failed.', 'woocommerce-paypal-payments' ),
		};
	}
}

/**
 * @param {Object} props                     - Props from the Blocks payment method registry.
 * @param {Object} props.config              - The localized sdk-v6 config.
 * @param {Object} props.eventRegistration   - Blocks checkout event subscriptions.
 * @param {Object} props.emitResponse        - Blocks response-type constants.
 * @param {string} props.activePaymentMethod - The active payment method id.
 * @return {?Object} The card fields element, or null before the session is ready.
 */
export function V6CardFieldsComponent( {
	config,
	eventRegistration,
	emitResponse,
	activePaymentMethod,
} ) {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;

	const context = config.page_context;
	const methodId = config.card_fields.payment_method;
	const hasNameField = Boolean( config.card_fields.name_field );

	const [ session, setSession ] = useState( null );
	const [ inputStyle, setInputStyle ] = useState( null );
	const referenceRef = useRef( null );

	// One card session for the component's lifetime. The SDK cannot dispose a
	// session, so this must not recreate on ordinary re-renders (config is a
	// stable reference read once at module load).
	useEffect( () => {
		let active = true;

		( async () => {
			const sdk = await loadSdkV6( config, context );
			const cardSession = sdk.createCardFieldsOneTimePaymentSession();
			if ( active ) {
				setSession( cardSession );
			}
		} )().catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[ppcp-sdk-v6] card session load failed', error );
		} );

		return () => {
			active = false;
		};
	}, [ config, context ] );

	// The classic renderer reads the field styling off the WC input it replaces;
	// block checkout has none, so a hidden reference input carrying the theme's
	// input styling stands in as the style source.
	useEffect( () => {
		if ( referenceRef.current ) {
			setInputStyle( cardFieldStyles( referenceRef.current ) );
		}
	}, [] );

	// The session is read through a ref so onPaymentSetup always sees the current
	// one without resubscribing when it arrives.
	const sessionRef = useRef( null );
	useEffect( () => {
		sessionRef.current = session;
	}, [ session ] );

	// onPaymentSetup fires for every registered method during checkout
	// processing, so gate on the active one before running the card flow.
	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return undefined;
		}

		return onPaymentSetup( () =>
			submitCardPayment( {
				config,
				context,
				session: sessionRef.current,
				responseTypes,
			} )
		);
	}, [
		onPaymentSetup,
		activePaymentMethod,
		methodId,
		config,
		context,
		responseTypes,
	] );

	const fieldsReady = session && inputStyle;

	return createElement(
		'div',
		{ className: 'ppcp-sdk-v6-card-fields' },
		// Off-screen (not display:none, or getComputedStyle returns nothing) so
		// the field styling can be read from the theme's own input rules.
		createElement( 'input', {
			ref: referenceRef,
			type: 'text',
			className: 'input-text',
			tabIndex: -1,
			'aria-hidden': true,
			style: {
				position: 'absolute',
				left: '-9999px',
				top: 0,
				pointerEvents: 'none',
			},
		} ),
		fieldsReady &&
			createElement(
				Fragment,
				null,
				hasNameField &&
					createElement( V6CardFieldContainer, {
						session,
						type: 'name',
						style: inputStyle,
						placeholder: __(
							'Cardholder name (optional)',
							'woocommerce-paypal-payments'
						),
					} ),
				createElement( V6CardFieldContainer, {
					session,
					type: 'number',
					style: inputStyle,
					placeholder: __(
						'Card number',
						'woocommerce-paypal-payments'
					),
				} ),
				createElement(
					'div',
					{ className: 'ppcp-sdk-v6-card-fields__row' },
					createElement( V6CardFieldContainer, {
						session,
						type: 'expiry',
						style: inputStyle,
						placeholder: __(
							'MM / YY',
							'woocommerce-paypal-payments'
						),
					} ),
					createElement( V6CardFieldContainer, {
						session,
						type: 'cvv',
						style: inputStyle,
						placeholder: __( 'CVV', 'woocommerce-paypal-payments' ),
					} )
				)
			)
	);
}
