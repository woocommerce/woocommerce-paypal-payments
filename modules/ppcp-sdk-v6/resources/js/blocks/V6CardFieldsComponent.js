/**
 * Advanced Card Fields for the WooCommerce Blocks checkout.
 *
 * Mounts the number/expiry/CVV/name fields and drives submission through the
 * Blocks `onPaymentSetup` event: the card session (which also runs 3D Secure)
 * confirms the card, the order is approved server-side, and the checkout
 * submit captures it through the card gateway.
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
 * Creates the order, confirms it through the card session (which runs 3D
 * Secure), approves it, and maps the outcome to a Blocks onPaymentSetup
 * response.
 *
 * @param {Object} args                - Arguments.
 * @param {Object} args.config         - The sdk-v6 config object.
 * @param {string} args.context        - The page context.
 * @param {Object} args.session        - The v6 card-fields session.
 * @param {Object} args.responseTypes  - The Blocks response-type constants.
 * @param {string} args.cardName       - The cardholder name (v6 has no name field).
 * @param {Object} [args.billingAddress] - Billing address for AVS/3D Secure.
 * @return {Promise<Object>} A Blocks onPaymentSetup response object.
 */
async function submitCardPayment( {
	config,
	context,
	session,
	responseTypes,
	cardName,
	billingAddress,
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
		const { orderId } = await createCardOrder( config, context, cardName );
		const result = billingAddress
			? await session.submit( orderId, { billingAddress } )
			: await session.submit( orderId );

		// The buyer dismissed the 3DS challenge; prompt a retry rather than
		// showing a payment error.
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
 * @param {Object} [props.billing]           - Blocks billing data (address, totals).
 * @return {?Object} The card fields element, or null before the session is ready.
 */
export function V6CardFieldsComponent( {
	config,
	eventRegistration,
	emitResponse,
	activePaymentMethod,
	billing,
} ) {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;

	const context = config.page_context;
	const methodId = config.card_fields.payment_method;
	const hasNameField = Boolean( config.card_fields.name_field );

	const [ session, setSession ] = useState( null );
	const [ inputStyle, setInputStyle ] = useState( null );
	const [ cardName, setCardName ] = useState( '' );
	const referenceRef = useRef( null );

	// Read through a ref so onPaymentSetup sees the latest name without
	// resubscribing every keystroke.
	const cardNameRef = useRef( '' );
	useEffect( () => {
		cardNameRef.current = cardName;
	}, [ cardName ] );

	// The v6 SDK uses the billing address for AVS / 3D Secure; read the live
	// Blocks billing address through a ref so the submit sees it without
	// resubscribing onPaymentSetup on every address change.
	const billingRef = useRef( null );
	useEffect( () => {
		const address = billing?.billingAddress || billing?.billingData;
		const postalCode = address?.postcode?.trim();
		billingRef.current = postalCode
			? {
					postalCode,
					...( address?.country
						? { countryCode: address.country }
						: {} ),
			  }
			: null;
	}, [ billing ] );

	// One card session for the component's lifetime: the SDK cannot dispose a
	// session, so it must not be recreated on ordinary re-renders.
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

	// v6 returns unstyled field elements, so derive their styling from a real
	// block text input on the page (accurate theme height/padding/border),
	// falling back to a hidden reference input when none is found.
	useEffect( () => {
		const source =
			document.querySelector(
				'.wc-block-components-text-input input'
			) || referenceRef.current;
		if ( source ) {
			setInputStyle( cardFieldStyles( source ) );
		}
	}, [] );

	// Read through a ref so onPaymentSetup sees the current session without
	// resubscribing when it arrives.
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
				cardName: cardNameRef.current?.trim() || '',
				// null when no billing address is available (see billingRef effect).
				billingAddress: billingRef.current,
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
		{
			className: 'ppcp-sdk-v6-card-fields',
			style: { display: 'flex', flexDirection: 'column', gap: '16px' },
		},
		// Off-screen rather than display:none, which would make getComputedStyle
		// return nothing.
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
				// The v6 card-fields component set is number|expiry|cvv only, so
				// the cardholder name is a plain input; its value is forwarded to
				// create-order rather than confirmed through the card session.
				hasNameField &&
					createElement(
						'div',
						{
							className:
								'ppcp-sdk-v6-card-field ppcp-sdk-v6-card-field--name',
						},
						createElement( 'input', {
							type: 'text',
							className: 'input-text',
							value: cardName,
							onChange: ( event ) =>
								setCardName( event.target.value ),
							placeholder: __(
								'Cardholder name (optional)',
								'woocommerce-paypal-payments'
							),
							style: { width: '100%', ...inputStyle },
						} )
					),
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
					{
						className: 'ppcp-sdk-v6-card-fields__row',
						style: { display: 'flex', gap: '16px' },
					},
					createElement( V6CardFieldContainer, {
						session,
						type: 'expiry',
						style: inputStyle,
						placeholder: __(
							'MM / YY',
							'woocommerce-paypal-payments'
						),
						containerStyle: { flex: 1 },
					} ),
					createElement( V6CardFieldContainer, {
						session,
						type: 'cvv',
						style: inputStyle,
						placeholder: __( 'CVV', 'woocommerce-paypal-payments' ),
						containerStyle: { flex: 1 },
					} )
				)
			)
	);
}
