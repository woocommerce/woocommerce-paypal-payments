/**
 * Renders PayPal v6 Web Component buttons and binds click handlers.
 *
 * @package
 */

/**
 * Creates and appends a button Web Component.
 *
 * @param {HTMLElement} wrapper - The container element.
 * @param {string} tagName - The web component tag (paypal-button, venmo-button, etc).
 * @param {Object} styles - Style config from ButtonStyleMapper.
 * @param {Object} session - The payment session.
 * @param {Function} createOrderFn - Function returning a promise with { orderId }.
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
			// eslint-disable-next-line no-console
			console.error( `${ tagName } payment start error:`, error );
		}
	} );

	wrapper.appendChild( button );
	return button;
}

/**
 * Renders all eligible buttons into the wrapper.
 *
 * @param {Object} options - Render options.
 * @param {string} options.wrapperSelector - CSS selector for the button container.
 * @param {Object} options.eligibility - Result from checkEligibility().
 * @param {Object} options.sessions - { paypal, venmo, payLater } session objects.
 * @param {Object} options.styles - Button styles for the current context.
 * @param {Function} options.createOrderForFunding - Function(fundingSource) returning a createOrderFn.
 * @param {Object} [options.payLaterDetails] - Pay Later product details.
 * @return {HTMLElement[]} Array of rendered button elements.
 */
export function renderButtons( {
	wrapperSelector,
	eligibility,
	sessions,
	styles,
	createOrderForFunding,
	payLaterDetails,
} ) {
	const wrapper = document.querySelector( wrapperSelector );
	if ( ! wrapper ) {
		return [];
	}

	wrapper.innerHTML = '';

	const rendered = [];

	if ( eligibility.paypal && sessions.paypal ) {
		rendered.push(
			createButton(
				wrapper,
				'paypal-button',
				styles,
				sessions.paypal,
				createOrderForFunding( 'paypal' )
			)
		);
	}

	if ( eligibility.venmo && sessions.venmo ) {
		rendered.push(
			createButton(
				wrapper,
				'venmo-button',
				styles,
				sessions.venmo,
				createOrderForFunding( 'venmo' )
			)
		);
	}

	if ( eligibility.payLater && sessions.payLater ) {
		const payLaterBtn = createButton(
			wrapper,
			'paylater-button',
			styles,
			sessions.payLater,
			createOrderForFunding( 'paylater' )
		);

		if ( payLaterDetails ) {
			if ( payLaterDetails.productCode ) {
				payLaterBtn.productCode = payLaterDetails.productCode;
			}
			if ( payLaterDetails.countryCode ) {
				payLaterBtn.countryCode = payLaterDetails.countryCode;
			}
		}

		rendered.push( payLaterBtn );
	}

	return rendered;
}
