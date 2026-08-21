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
import {
	createCardSetupToken,
	exchangeSetupToken,
} from '../sessions/freeTrialSave';
import { cardFieldStyles } from '../cardFields/cardFieldStyles';
import { V6CardFieldContainer } from './V6CardFieldContainer';

/**
 * Creates the order, confirms it through the card session (which runs 3D
 * Secure), approves it, and maps the outcome to a Blocks onPaymentSetup
 * response.
 *
 * @param {Object}  args                   - Arguments.
 * @param {Object}  args.config            - The sdk-v6 config object.
 * @param {string}  args.context           - The page context.
 * @param {Object}  args.session           - The v6 card-fields session.
 * @param {Object}  args.responseTypes     - The Blocks response-type constants.
 * @param {boolean} args.savePaymentMethod - Whether to vault the card.
 * @param {string} args.cardName       - The cardholder name (v6 has no name field).
 * @param {Object} [args.billingAddress] - Billing address for AVS/3D Secure.
 * @return {Promise<Object>} A Blocks onPaymentSetup response object.
 */
async function submitCardPayment( {
	config,
	context,
	session,
	responseTypes,
	savePaymentMethod,
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
		const { orderId } = await createCardOrder(
			config,
			context,
			cardName,
			savePaymentMethod
		);
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
 * Free-trial ($0) card checkout: save the card without a purchase.
 *
 * Creates a card setup token, confirms it through the save session (running 3D
 * Secure when required), and exchanges it for a stored token. The $0 WC order is
 * then placed by the card gateway's zero-total short-circuit on submit.
 *
 * @param {Object} args               - Arguments.
 * @param {Object} args.config        - The sdk-v6 config object.
 * @param {Object} args.session       - The v6 card-fields save session.
 * @param {Object} args.responseTypes - The Blocks response-type constants.
 * @return {Promise<Object>} A Blocks onPaymentSetup response object.
 */
async function submitCardSave( { config, session, responseTypes } ) {
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
		const setupTokenId = await createCardSetupToken( config );
		// The save session confirms the setup token (running 3D Secure when the
		// store's contingency requires it); it takes no billing-address option.
		const result = await session.submit( setupTokenId );

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
					'Card could not be saved.',
					'woocommerce-paypal-payments'
				),
			};
		}

		await exchangeSetupToken(
			config,
			setupTokenId,
			config.card_fields.payment_method
		);

		return { type: responseTypes.SUCCESS };
	} catch ( error ) {
		return {
			type: responseTypes.ERROR,
			message:
				error?.message ||
				__( 'Card could not be saved.', 'woocommerce-paypal-payments' ),
		};
	}
}

/**
 * @param {Object}  props                     - Props from the Blocks payment method registry.
 * @param {Object}  props.config              - The localized sdk-v6 config.
 * @param {Object}  props.eventRegistration   - Blocks checkout event subscriptions.
 * @param {Object}  props.emitResponse        - Blocks response-type constants.
 * @param {string}  props.activePaymentMethod - The active payment method id.
 * @param {boolean} props.shouldSavePayment   - WC Blocks' native "save payment method" choice.
 * @param {Object} [props.billing]           - Blocks billing data (address, totals).
 * @return {?Object} The card fields element, or null before the session is ready.
 */
export function V6CardFieldsComponent( {
	config,
	eventRegistration,
	emitResponse,
	activePaymentMethod,
	shouldSavePayment,
	billing,
} ) {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;

	const context = config.page_context;
	const methodId = config.card_fields.payment_method;
	const hasNameField = Boolean( config.card_fields.name_field );

	const hasSubscriptions = Boolean( config.card_fields.has_subscriptions );

	// A $0 free-trial subscription card is vaulted through the save session (no
	// purchase); the gateway places the $0 order on submit.
	const isFreeTrial = Boolean( config.is_free_trial_cart );

	const [ session, setSession ] = useState( null );
	const [ inputStyle, setInputStyle ] = useState( null );
	const [ cardName, setCardName ] = useState( '' );
	const referenceRef = useRef( null );

	// Whether to vault the card, read at submit time. The choice comes from WC
	// Blocks' native "Save payment information…" checkbox (shouldSavePayment
	// prop, shown via supports.showSaveOption); a subscription cart forces it
	// on since the card must be saved to charge renewals. A ref keeps the
	// latest value without resubscribing onPaymentSetup.
	const savePaymentRef = useRef( false );
	savePaymentRef.current = Boolean( shouldSavePayment ) || hasSubscriptions;

	// On a subscription cart the native "Save payment information…" option is
	// suppressed (see checkout-block.js) and this component shows its own
	// checked-and-disabled checkbox instead, since the card must always be
	// vaulted for renewals and the native option cannot be locked.
	const isVaultingEnabled = Boolean( config.card_fields.is_vaulting_enabled );
	const showLockedSaveOption = hasSubscriptions && isVaultingEnabled;

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
			const cardSession = isFreeTrial
				? sdk.createCardFieldsSavePaymentSession()
				: sdk.createCardFieldsOneTimePaymentSession();
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
	}, [ config, context, isFreeTrial ] );

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
			isFreeTrial
				? submitCardSave( {
						config,
						session: sessionRef.current,
						responseTypes,
				  } )
				: submitCardPayment( {
						config,
						context,
						session: sessionRef.current,
						responseTypes,
						savePaymentMethod: savePaymentRef.current,
						cardName: cardNameRef.current?.trim() || '',
						// null when no billing address is available.
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
		isFreeTrial,
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
			),
		// Subscription cart: a checked, disabled save option in place of the
		// suppressed native one, so the buyer sees the card will be saved for
		// renewals but cannot opt out (matches the classic checkout). A plain
		// native checkbox, not WooCommerce's checkbox component, whose CSS hides
		// the input in favour of an SVG mark this markup does not provide.
		showLockedSaveOption &&
			createElement(
				'label',
				{
					className: 'ppcp-sdk-v6-card-fields__save',
					htmlFor: 'ppcp-sdk-v6-save-payment-method',
					style: {
						display: 'flex',
						alignItems: 'center',
						gap: '8px',
						cursor: 'default',
					},
				},
				createElement( 'input', {
					id: 'ppcp-sdk-v6-save-payment-method',
					type: 'checkbox',
					checked: true,
					disabled: true,
					readOnly: true,
				} ),
				createElement(
					'span',
					null,
					__(
						'Save payment information to my account for future purchases.',
						'woocommerce-paypal-payments'
					)
				)
			)
	);
}
