/**
 * Renders PayPal v6 Web Component buttons and binds click handlers.
 *
 * @package
 */

import { handleError } from '../utils/errorHandler';

/**
 * @typedef {() => Promise<{orderId: string}>} OrderCreator
 * A function resolving to the created PayPal order id.
 */

/**
 * @typedef {(fundingSource: string) => OrderCreatorFactory} OrderCreatorFactory
 * Builds an OrderCreator for a funding source.
 */

// Element names per the SDK core's custom-element registry (the shipped
// core names the Pay Later element paypal-pay-later-button; it carries
// its own localized label and takes no type attribute).
const BUTTON_ELEMENTS = {
	paypal: { tagName: 'paypal-button', type: 'pay' },
	venmo: { tagName: 'venmo-button', type: 'pay' },
	paylater: { tagName: 'paypal-pay-later-button', type: '' },
};

/**
 * Creates a fully configured button Web Component, not yet in the DOM.
 *
 * Components read their configuration when they connect, so every
 * attribute and property must be in place before insertion.
 *
 * @param {string}       tagName       - The web component tag.
 * @param {string}       type          - The button type attribute.
 * @param {Object}       styles        - Style config from ButtonStyleMapper.
 * @param {Object}       session       - The payment session.
 * @param {OrderCreator} createOrderFn - Returns the created order id.
 * @return {HTMLElement} The configured button element.
 */
function createButton( tagName, type, styles, session, createOrderFn ) {
	const button = document.createElement( tagName );
	if ( type ) {
		button.setAttribute( 'type', type );
	}

	if ( styles.colorClass ) {
		button.className = styles.colorClass;
	}

	if ( styles.borderRadius ) {
		button.style.setProperty(
			'--paypal-button-border-radius',
			styles.borderRadius
		);
	}

	if ( styles.height ) {
		button.style.height = styles.height;
	}

	button.addEventListener( 'click', async () => {
		try {
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
 * Renders a button for every created session into the wrapper.
 *
 * @param {Object}              options                       - Render options.
 * @param {HTMLElement}         options.wrapper               - The button container element.
 * @param {Object}              options.sessions              - Sessions keyed by method (paypal, venmo, paylater).
 * @param {Object}              options.styles                - Button styles for the current context.
 * @param {OrderCreatorFactory} options.createOrderForFunding - Builds a createOrder function per funding source.
 * @param {Object}              [options.payLaterDetails]     - Pay Later product details.
 * @return {HTMLElement[]} Array of rendered button elements.
 */
export function renderButtons( {
	wrapper,
	sessions,
	styles,
	createOrderForFunding,
	payLaterDetails,
} ) {
	wrapper.innerHTML = '';

	const rendered = [];

	for ( const [ method, element ] of Object.entries( BUTTON_ELEMENTS ) ) {
		if ( ! sessions[ method ] ) {
			continue;
		}

		// Pay Later requires the product details from eligibility.
		if ( method === 'paylater' && ! payLaterDetails?.productCode ) {
			continue;
		}

		const button = createButton(
			element.tagName,
			element.type,
			styles,
			sessions[ method ],
			createOrderForFunding( method )
		);

		if ( method === 'paylater' ) {
			button.productCode = payLaterDetails.productCode;
			if ( payLaterDetails.countryCode ) {
				button.countryCode = payLaterDetails.countryCode;
			}
		}

		// Insert only after full configuration.
		wrapper.appendChild( button );
		rendered.push( button );
	}

	return rendered;
}
