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
import {
	cardFieldStyles,
	hostedFieldTextStyles,
} from '../cardFields/cardFieldStyles';
import { V6CardFieldContainer } from './V6CardFieldContainer';
import { amountFromBilling } from '../utils/amount';
import { isFreeTrialCart } from '../utils/freeTrial';
import { hasCheckoutValidationErrors } from './checkoutValidation';
import {
	CARD_DECLINE_MESSAGE,
	CARD_SAVE_DECLINE_MESSAGE,
	userFacingMessage,
} from '../utils/cardDeclineMessages';

const CHECKOUT_FIELDS_NOT_VALID_MESSAGE = __(
	'Please complete all required checkout fields before continuing with payment.',
	'woocommerce-paypal-payments'
);

const FIELD_LABELS = {
	number: __( 'Card number', 'woocommerce-paypal-payments' ),
	expiry: __( 'Expiry (MM/YY)', 'woocommerce-paypal-payments' ),
	cvv: __( 'CVV', 'woocommerce-paypal-payments' ),
};

const CARD_NAME_LABEL = __(
	'Cardholder name (optional)',
	'woocommerce-paypal-payments'
);

// Each event payload carries the state of all three fields, so these four
// events keep every label in sync.
const FIELD_STATE_EVENTS = [ 'focus', 'blur', 'empty', 'notempty' ];

const NAME_FIELD_ID = 'ppcp-sdk-v6-card-name';

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
				message: CARD_DECLINE_MESSAGE,
			};
		}

		await approveCardOrder( config, orderId );

		return { type: responseTypes.SUCCESS };
	} catch ( error ) {
		return {
			type: responseTypes.ERROR,
			message: userFacingMessage( error, CARD_DECLINE_MESSAGE ),
		};
	}
}

/**
 * Keeps the latest value in a ref, so the onPaymentSetup callback can read it
 * without resubscribing every time it changes.
 *
 * @param {*} value - The value to track.
 * @return {Object} A ref holding the latest value.
 */
function useLatestRef( value ) {
	const ref = useRef( value );
	ref.current = value;
	return ref;
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
				message: CARD_SAVE_DECLINE_MESSAGE,
			};
		}

		await exchangeSetupToken( config, setupTokenId );

		return { type: responseTypes.SUCCESS };
	} catch ( error ) {
		return {
			type: responseTypes.ERROR,
			message: userFacingMessage( error, CARD_SAVE_DECLINE_MESSAGE ),
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
	const { onPaymentSetup, onCheckoutValidation, onCheckoutFail } =
		eventRegistration;
	const { responseTypes } = emitResponse;

	const context = config.page_context;
	const methodId = config.card_fields.payment_method;
	const hasNameField = Boolean( config.card_fields.name_field );

	const hasSubscriptions = Boolean( config.has_subscriptions );

	// A $0 free-trial subscription card is vaulted through the save session; the
	// gateway places the $0 order on submit.
	const isFreeTrial = isFreeTrialCart( config, amountFromBilling( billing ) );

	const [ session, setSession ] = useState( null );
	const [ inputStyle, setInputStyle ] = useState( null );
	const [ textStyle, setTextStyle ] = useState( null );
	const [ cardName, setCardName ] = useState( '' );
	const [ nameFocused, setNameFocused ] = useState( false );
	const [ floatingLabel, setFloatingLabel ] = useState( false );
	const [ fieldStates, setFieldStates ] = useState( {} );
	const referenceRef = useRef( null );

	// The choice comes from WC Blocks' native "Save payment information…"
	// checkbox (shown via supports.showSaveOption); a subscription cart forces
	// it on, since the card must be saved to charge renewals.
	const savePaymentRef = useLatestRef(
		Boolean( shouldSavePayment ) || hasSubscriptions
	);
	const cardNameRef = useLatestRef( cardName );

	// The native save option is suppressed on a subscription cart (see
	// checkout-block.js), which shows this component's own locked checkbox.
	const isVaultingEnabled = Boolean( config.card_fields.is_vaulting_enabled );
	const showLockedSaveOption = hasSubscriptions && isVaultingEnabled;

	// The v6 SDK uses the billing address for AVS / 3D Secure.
	const billingAddress = billing?.billingAddress || billing?.billingData;
	const postalCode = billingAddress?.postcode?.trim();
	const billingRef = useLatestRef(
		postalCode
			? {
					postalCode,
					...( billingAddress?.country
						? { countryCode: billingAddress.country }
						: {} ),
			  }
			: null
	);

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

	// v6 returns unstyled field elements, so their styling is derived from a real
	// block text input on the page, or a hidden reference input when there is
	// none. Two objects: inputStyle is for elements this component renders, while
	// textStyle goes to the SDK and lands inside a PayPal iframe, where box
	// decoration has no business.
	const cardFieldOverrides = config.card_fields.styles;
	useEffect( () => {
		const blockInput = document.querySelector(
			'.wc-block-components-text-input input'
		);
		const source = blockInput || referenceRef.current;

		// A Blocks text input on the page means its floating-label CSS is
		// loaded. Without one, the fields fall back to plain placeholders.
		setFloatingLabel( Boolean( blockInput ) );

		if ( source ) {
			setInputStyle( cardFieldStyles( source ) );
			setTextStyle( hostedFieldTextStyles( source, cardFieldOverrides ) );
		}
	}, [ cardFieldOverrides ] );

	// Mirrors the SDK's field state onto the wrappers so WooCommerce's CSS can
	// float each label.
	useEffect( () => {
		if ( ! session || ! floatingLabel ) {
			return undefined;
		}

		let listening = true;
		const handler = ( payload ) => {
			if ( ! listening || ! payload?.data ) {
				return;
			}

			const { number, expiry, cvv } = payload.data;
			setFieldStates( { number, expiry, cvv } );
		};

		// The session exposes no per-listener removal, only destroy(), so this
		// flag is what stops a late event from reaching an unmounted component.
		FIELD_STATE_EVENTS.forEach( ( event ) => {
			Promise.resolve( session.on( event, handler ) ).catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.warn(
					'[PPCP SDK v6] card field event not available',
					event,
					error
				);
			} );
		} );

		return () => {
			listening = false;
		};
	}, [ session, floatingLabel ] );

	// WooCommerce floats a label once its field is focused or filled.
	const isFieldActive = ( type ) => {
		const state = fieldStates[ type ];
		return Boolean( state && ( state.isFocused || ! state.isEmpty ) );
	};

	const sessionRef = useLatestRef( session );

	// onPaymentSetup fires for every registered method during checkout
	// processing, so gate on the active one before running the card flow.
	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return undefined;
		}

		return onPaymentSetup( () => {
			// The onCheckoutValidation check ran earlier and its verdict can go
			// stale, so re-check right before calling PayPal.
			if ( hasCheckoutValidationErrors() ) {
				return {
					type: responseTypes.ERROR,
					message: CHECKOUT_FIELDS_NOT_VALID_MESSAGE,
				};
			}

			return isFreeTrial
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
				  } );
		} );
	}, [
		onPaymentSetup,
		activePaymentMethod,
		methodId,
		config,
		context,
		responseTypes,
		isFreeTrial,
	] );

	// WooCommerce reaches payment processing with required fields still empty (its
	// own validation observer returns a non-response for plain field errors), so
	// without this check the card flow creates a PayPal order and runs 3D Secure
	// against an invalid form.
	//
	// Gated on the active method because this is a global event.
    // For other methods like express PayPal or Venmo checkout an invalid form
    // can be ok, since PayPal returns the address.
	useEffect( () => {
		if (
			activePaymentMethod !== methodId ||
			typeof onCheckoutValidation !== 'function'
		) {
			return undefined;
		}

		return onCheckoutValidation( () => {
			if ( hasCheckoutValidationErrors() ) {
				// No message since WooCommerce shows the field errors itself.
				return { type: responseTypes.ERROR };
			}

			return true;
		} );
	}, [ onCheckoutValidation, activePaymentMethod, methodId, responseTypes ] );

	// The Store API clears the gateway's notices and Blocks ignores the
	// errorMessage it returns, so a decline raised during process_payment
	// reaches the shopper as WooCommerce's generic string unless it is put
	// back here.
	useEffect( () => {
		if (
			activePaymentMethod !== methodId ||
			typeof onCheckoutFail !== 'function'
		) {
			return undefined;
		}

		return onCheckoutFail( ( { processingResponse } ) => {
			const message = processingResponse?.paymentDetails?.errorMessage;
			if ( ! message ) {
				return true;
			}

			return { type: responseTypes.ERROR, message };
		} );
	}, [ onCheckoutFail, activePaymentMethod, methodId, responseTypes ] );

	const nameFieldClassName = [
		'ppcp-sdk-v6-card-field',
		'ppcp-sdk-v6-card-field--name',
		floatingLabel && 'wc-block-components-text-input',
		floatingLabel && ( nameFocused || cardName !== '' ) && 'is-active',
	]
		.filter( Boolean )
		.join( ' ' );

	const fieldsReady = session && inputStyle && textStyle;

	return createElement(
		'div',
		{
			className: 'ppcp-sdk-v6-card-fields',
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
				// The SDK has no name component, so this is a plain input whose
				// value is forwarded to create-order.
				hasNameField &&
					createElement(
						'div',
						{
							className: nameFieldClassName,
						},
						createElement( 'input', {
							id: NAME_FIELD_ID,
							type: 'text',
							className: 'input-text',
							value: cardName,
							onChange: ( event ) =>
								setCardName( event.target.value ),
							onFocus: () => setNameFocused( true ),
							onBlur: () => setNameFocused( false ),
							placeholder: floatingLabel
								? undefined
								: CARD_NAME_LABEL,
							style: { width: '100%', ...inputStyle },
						} ),
						// A real input, so this label uses for/id where the
						// hosted fields' labels are aria-hidden.
						floatingLabel &&
							createElement(
								'label',
								{ htmlFor: NAME_FIELD_ID },
								CARD_NAME_LABEL
							)
					),
				createElement( V6CardFieldContainer, {
					session,
					type: 'number',
					style: textStyle,
					height: inputStyle.height,
					label: FIELD_LABELS.number,
					floatingLabel,
					isActive: isFieldActive( 'number' ),
				} ),
				createElement(
					'div',
					{ className: 'ppcp-sdk-v6-card-fields__row' },
					createElement( V6CardFieldContainer, {
						session,
						type: 'expiry',
						style: textStyle,
						height: inputStyle.height,
						label: FIELD_LABELS.expiry,
						floatingLabel,
						isActive: isFieldActive( 'expiry' ),
					} ),
					createElement( V6CardFieldContainer, {
						session,
						type: 'cvv',
						style: textStyle,
						height: inputStyle.height,
						label: FIELD_LABELS.cvv,
						floatingLabel,
						isActive: isFieldActive( 'cvv' ),
					} )
				)
			),
		// Subscription cart: a checked, disabled save option in place of the
		// suppressed native one, so the buyer cannot opt out. A plain input, not
		// WooCommerce's checkbox component, whose CSS hides the input in favour
		// of an SVG mark this markup does not provide.
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
