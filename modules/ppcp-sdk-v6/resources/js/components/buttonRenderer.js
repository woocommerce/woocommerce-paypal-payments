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
 * @typedef {(fundingSource: string) => OrderCreator} OrderCreatorFactory
 * Builds an OrderCreator for a funding source.
 */

const BUTTON_TAGS = {
	paypal: 'paypal-button',
	venmo: 'venmo-button',
	paylater: 'paylater-button',
};

/**
 * Creates and appends a button Web Component.
 *
 * @param {HTMLElement}  wrapper       - The container element.
 * @param {string}       tagName       - The web component tag.
 * @param {Object}       styles        - Style config from ButtonStyleMapper.
 * @param {Object}       session       - The payment session.
 * @param {OrderCreator} createOrderFn - Returns the created order id.
 * @return {HTMLElement} The created button element.
 */
function createButton( wrapper, tagName, styles, session, createOrderFn ) {
	const button = document.createElement( tagName );
	button.setAttribute( 'type', 'pay' );

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

	wrapper.appendChild( button );
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

	for ( const [ method, tagName ] of Object.entries( BUTTON_TAGS ) ) {
		if ( ! sessions[ method ] ) {
			continue;
		}

		const button = createButton(
			wrapper,
			tagName,
			styles,
			sessions[ method ],
			createOrderForFunding( method )
		);

		if ( method === 'paylater' && payLaterDetails ) {
			if ( payLaterDetails.productCode ) {
				button.productCode = payLaterDetails.productCode;
			}
			if ( payLaterDetails.countryCode ) {
				button.countryCode = payLaterDetails.countryCode;
			}
		}

		rendered.push( button );
	}

	return rendered;
}
