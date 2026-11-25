/**
 * Renders PayPal v6 Web Component buttons and binds click handlers.
 *
 * @package
 */

import { handleError } from '../utils/errorHandler';
import { FundingSources } from '../utils/fundingSources';

/**
 * @typedef {() => Promise<{orderId: string}>} OrderCreator
 * A function resolving to the created PayPal order id.
 */

/**
 * @typedef {(fundingSource: string) => OrderCreatorFactory} OrderCreatorFactory
 * Builds an OrderCreator for a funding source.
 */

// Element names per the SDK core's custom-element registry. The Pay Later
// element carries its own localized label and takes no type attribute.
// Each element reads its own border-radius custom property: the Venmo button
// ignores the PayPal one, so the radius has to be set per funding source.
const BUTTON_ELEMENTS = {
	paypal: {
		tagName: 'paypal-button',
		takesType: true,
		radiusProperty: '--paypal-button-border-radius',
	},
	venmo: {
		tagName: 'venmo-button',
		takesType: true,
		radiusProperty: '--venmo-button-border-radius',
	},
	paylater: {
		tagName: 'paypal-pay-later-button',
		takesType: false,
		radiusProperty: '--paypal-button-border-radius',
	},
};

/**
 * Applies label, color, shape and height to a new button element (shared by the button and preview).
 *
 * @param {Document} doc     - The document to create the element in.
 * @param {Object}   element - The BUTTON_ELEMENTS entry.
 * @param {Object}   styles  - Style config from ButtonStyleMapper.
 * @return {HTMLElement} The styled button element.
 */
function styleButtonElement( doc, element, styles ) {
	const button = doc.createElement( element.tagName );
	if ( element.takesType && styles.type ) {
		button.setAttribute( 'type', styles.type );
	}

	if ( styles.colorClass ) {
		button.className = styles.colorClass;
	}

	if ( styles.borderRadius ) {
		button.style.setProperty( element.radiusProperty, styles.borderRadius );
	}

	if ( styles.height ) {
		button.style.height = styles.height;
	}

	return button;
}

/**
 * Creates a fully configured button Web Component, not yet in the DOM.
 *
 * Components read their configuration when they connect, so every
 * attribute and property must be in place before insertion.
 *
 * @param {Object}       element       - The BUTTON_ELEMENTS entry.
 * @param {Object}       styles        - Style config from ButtonStyleMapper.
 * @param {Object}       session       - The payment session.
 * @param {OrderCreator} createOrderFn - Returns the created order id.
 * @param {() => void}   [onClick]     - Called on click before the session starts.
 * @return {HTMLElement} The configured button element.
 */
function createButton( element, styles, session, createOrderFn, onClick ) {
	const button = styleButtonElement( document, element, styles );

	button.addEventListener( 'click', async () => {
		try {
			// Blocks express uses this to set the active payment method;
			// classic passes nothing.
			if ( onClick ) {
				onClick();
			}
			await session.start(
				{ presentationMode: 'auto' },
				createOrderFn()
			);
		} catch ( error ) {
			handleError( error );
		}
	} );

	return button;
}

/**
 * Creates a fully configured button element for one funding method, not
 * yet in the DOM. Returns null when the method is unknown or Pay Later
 * lacks the product details it needs to render.
 *
 * @param {Object}       options                   - Button options.
 * @param {string}       options.method            - The funding method (paypal, venmo, paylater).
 * @param {Object}       options.styles            - Button styles for the current context.
 * @param {Object}       options.session           - The payment session.
 * @param {OrderCreator} options.createOrderFn     - Returns the created order id.
 * @param {Object}       [options.payLaterDetails] - Pay Later product details.
 * @param {() => void}   [options.onClick]         - Called on click before the session starts.
 * @return {?HTMLElement} The configured button element, or null.
 */
export function createMethodButton( {
	method,
	styles,
	session,
	createOrderFn,
	payLaterDetails,
	onClick,
} ) {
	const element = BUTTON_ELEMENTS[ method ];
	if ( ! element ) {
		return null;
	}

	if (
		method === FundingSources.PAYLATER &&
		! payLaterDetails?.productCode
	) {
		return null;
	}

	const button = createButton(
		element,
		styles,
		session,
		createOrderFn,
		onClick
	);

	if ( method === FundingSources.PAYLATER ) {
		button.productCode = payLaterDetails.productCode;
		if ( payLaterDetails.countryCode ) {
			button.countryCode = payLaterDetails.countryCode;
		}
	}

	return button;
}

/**
 * Creates a styled, behavior-free button element for a block-editor preview, or null when the
 * method has no button element. No session or click handler is bound.
 *
 * @param {Document} doc            - The document to create the element in.
 * @param {Object}   options        - Button options.
 * @param {string}   options.method - The funding method (paypal, venmo, paylater).
 * @param {Object}   options.styles - Button styles ({ colorClass, borderRadius, type, height }).
 * @return {?HTMLElement} The styled button element, or null.
 */
export function createPreviewButton( doc, { method, styles } ) {
	const element = BUTTON_ELEMENTS[ method ];
	if ( ! element ) {
		return null;
	}

	return styleButtonElement( doc, element, styles );
}

/**
 * Renders a button for every created session into the wrapper.
 *
 * @param {Object}              options                       - Render options.
 * @param {HTMLElement}         options.wrapper               - The button container element.
 * @param {Object}              options.sessions              - Sessions keyed by method (paypal, venmo, paylater).
 * @param {Object}              options.styles                - Button styles for the current context.
 * @param {OrderCreatorFactory} options.createOrderForFunding - Builds a createOrder function per funding source.
 * @param {Object}              [options.payLaterDetails]     - Pay Later product details.
 * @param {boolean}             [options.payLaterEnabled]     - Whether the merchant offers Pay Later here.
 * @return {HTMLElement[]} Array of rendered button elements.
 */
export function renderButtons( {
	wrapper,
	sessions,
	styles,
	createOrderForFunding,
	payLaterDetails,
	payLaterEnabled = false,
} ) {
	wrapper.innerHTML = '';

	const rendered = [];

	for ( const method of Object.keys( BUTTON_ELEMENTS ) ) {
		if ( ! sessions[ method ] ) {
			continue;
		}

		// Eligibility only says the buyer could pay this way; the merchant
		// setting decides whether it is offered here, and defaults to off.
		if ( method === FundingSources.PAYLATER && ! payLaterEnabled ) {
			continue;
		}

		const button = createMethodButton( {
			method,
			styles,
			session: sessions[ method ],
			createOrderFn: createOrderForFunding( method ),
			payLaterDetails,
		} );
		if ( ! button ) {
			continue;
		}

		wrapper.appendChild( button );
		rendered.push( button );
	}

	return rendered;
}
