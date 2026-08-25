/**
 * Renders Pay Later messages with the PayPal SDK v6 messages component.
 *
 * Fills every `.ppcp-messages` placeholder with a `<paypal-message>` element.
 *
 * Free of `@wordpress/*` imports: this is reachable from `boot.js`, and classic
 * pages should not gain a WP dependency for it.
 *
 * @package
 */

import { loadSdkV6 } from '../sdkLoader';

const MESSAGE_TAG_NAME = 'paypal-message';

// Two guards, for two different problems. Idempotence reads the actual child,
// because block pages replace the placeholder node during their client render
// and a marker attribute would go with the old one. The WeakSet covers the
// window where callers race across the awaited SDK load.
let inFlight = new WeakSet();
let watching = false;
let rescanTimer = null;

const RESCAN_DEBOUNCE_MS = 100;

// Mapping of v5 placements from `data-pp-placement` of the Pay Later block.
// `payment` is what the checkout block emits.
const PLACEMENT_PAGE_TYPES = {
	product: 'product-details',
	'product-list': 'product-listing',
	cart: 'cart',
	payment: 'checkout',
	checkout: 'checkout',
	home: 'home',
};

// v5 styles from `data-pp-style-*`. Mirrors MessageStyleMapper.
const LOGO_TYPES = {
	primary: 'WORDMARK',
	alternative: 'MONOGRAM',
	inline: 'WORDMARK',
	none: 'TEXT',
};
const LOGO_POSITIONS = { left: 'LEFT', right: 'RIGHT', top: 'TOP' };
const TEXT_COLORS = {
	black: 'BLACK',
	white: 'WHITE',
	monochrome: 'MONOCHROME',
	grayscale: 'MONOCHROME',
};

const FONT_SIZE_MIN = 10;
const FONT_SIZE_MAX = 16;

let messagesInstance = null;
let rendered = [];
let latestAmount = null;

/**
 * Resets the module state. Test-only.
 */
export function resetMessages() {
	messagesInstance = null;
	rendered = [];
	latestAmount = null;
	inFlight = new WeakSet();
	watching = false;
	clearTimeout( rescanTimer );
	rescanTimer = null;
}

/**
 * Starts watching for placeholders and fills the ones already present.
 *
 * Observer first, so a placeholder inserted while the initial pass awaits the
 * SDK still triggers a rescan.
 *
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} sdkPageType - The page context for the shared SDK instance.
 * @return {Promise<number>} How many messages the initial pass rendered.
 */
export async function initMessages( config, sdkPageType ) {
    if ( ! config?.messages?.enabled || config.messages.is_hidden ) {
        return 0;
    }

    watchForWrappers( config, sdkPageType );

    return renderMessages( config, sdkPageType );
}

/**
 * Clamps the v5 text size onto the range the v6 component accepts.
 *
 * @param {string} size - The configured text size.
 * @return {string} A CSS length, or '' when the size is unusable.
 */
function fontSize( size ) {
	const parsed = parseInt( size, 10 );
	if ( Number.isNaN( parsed ) ) {
		return '';
	}

	return `${ Math.max(
		FONT_SIZE_MIN,
		Math.min( FONT_SIZE_MAX, parsed )
	) }px`;
}

/**
 * Resolves the style for one placeholder.
 *
 * A wrapper's own `data-pp-style-*` wins over the page config: the Pay Later
 * blocks are styled per block, not from the location settings.
 *
 * @param {Element} wrapper     - The placeholder element.
 * @param {Object}  configStyle - The style from the localized config.
 * @return {Object} The v6 style values.
 */
function styleFor( wrapper, configStyle ) {
	const logoType = wrapper.getAttribute( 'data-pp-style-logo-type' );
	if ( ! logoType ) {
		return configStyle;
	}

	const position = wrapper.getAttribute( 'data-pp-style-logo-position' );

	return {
		logoType: LOGO_TYPES[ logoType ] || 'WORDMARK',
		logoPosition:
			'inline' === logoType
				? 'INLINE'
				: LOGO_POSITIONS[ position ] || 'LEFT',
		textColor:
			TEXT_COLORS[ wrapper.getAttribute( 'data-pp-style-text-color' ) ] ||
			'BLACK',
		fontSize: fontSize( wrapper.getAttribute( 'data-pp-style-text-size' ) ),
	};
}

/**
 * Resolves the v6 page type for one placeholder.
 *
 * @param {Element} wrapper - The placeholder element.
 * @param {Object}  config  - The wc_ppcp_sdk_v6 config object.
 * @return {string} The v6 page type.
 */
function pageTypeFor( wrapper, config ) {
	const placement = wrapper.getAttribute( 'data-pp-placement' );

	return PLACEMENT_PAGE_TYPES[ placement ] || config.messages.page_type;
}

/**
 * Finds placeholders that still need a message.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Element[]} The placeholders to fill.
 */
function wrappersNeedingMessage( config ) {
	return Array.from(
		document.querySelectorAll( config.messages.wrapper )
	).filter(
		( wrapper ) =>
			! inFlight.has( wrapper ) &&
			! wrapper.querySelector( MESSAGE_TAG_NAME )
	);
}

/**
 * Re-runs discovery on DOM changes.
 *
 * Observes document.body, not the placeholders: block pages replace those,
 * which would leave an observer holding a detached node.
 *
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} sdkPageType - The page context for the shared SDK instance.
 */
function watchForWrappers( config, sdkPageType ) {
	if ( watching || ! document.body ) {
		return;
	}
	watching = true;

	new MutationObserver( () => {
		clearTimeout( rescanTimer );
		rescanTimer = setTimeout( () => {
			renderMessages( config, sdkPageType ).catch( () => {} );
		}, RESCAN_DEBOUNCE_MS );
	} ).observe( document.body, { childList: true, subtree: true } );
}

/**
 * Builds a message Web Component, not yet in the DOM.
 *
 * Every attribute must be set before insertion: the component reads them on
 * connect.
 *
 * @param {Element} wrapper - The placeholder to build for.
 * @param {Object}  config  - The wc_ppcp_sdk_v6 config object.
 * @param {string}  amount  - The amount to price.
 * @return {Element} The configured element.
 */
function createMessage( wrapper, config, amount ) {
	const style = styleFor( wrapper, config.messages.style );
	const element = document.createElement( MESSAGE_TAG_NAME );

	// Required: without it the component lays out but never fetches, leaving an
	// empty one-line box.
	element.setAttribute( 'auto-bootstrap', '' );

	if ( amount ) {
		element.setAttribute( 'amount', amount );
	}
	element.setAttribute( 'currency-code', config.currency );
	element.setAttribute( 'page-type', pageTypeFor( wrapper, config ) );
	element.setAttribute( 'logo-type', style.logoType );
	element.setAttribute( 'logo-position', style.logoPosition );
	element.setAttribute( 'text-color', style.textColor );

	if ( style.fontSize ) {
		element.style.setProperty(
			'--paypal-message-font-size',
			style.fontSize
		);
	}

	return element;
}

/**
 * Fills every unclaimed placeholder with a message, once.
 *
 * The SDK is only loaded when there is something to render, so a page with no
 * placeholder never reaches the client-token endpoint — the same discipline
 * boot.js applies to buttons.
 *
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} sdkPageType - The page context for the shared SDK instance.
 * @return {Promise<number>} How many messages were rendered.
 */
export async function renderMessages( config, sdkPageType ) {
	if ( ! config?.messages?.enabled || config.messages.is_hidden ) {
		return 0;
	}

	const wrappers = wrappersNeedingMessage( config );
	if ( ! wrappers.length ) {
		return 0;
	}

	// Before the first await: several callers race on a normal page load, and
	// each would otherwise see the same unfilled wrapper.
	wrappers.forEach( ( wrapper ) => inFlight.add( wrapper ) );

	try {
		const sdk = await loadSdkV6( config, sdkPageType );

		if ( ! messagesInstance ) {
			messagesInstance = sdk.createPayPalMessages( {
				currencyCode: config.currency,
			} );
		}

		const amount = currentAmount( config );

		wrappers.forEach( ( wrapper ) => {
			const element = createMessage( wrapper, config, amount );
			wrapper.appendChild( element );
			rendered.push( element );
		} );
	} finally {
		// Released either way, so a later pass can pick these up.
		wrappers.forEach( ( wrapper ) => inFlight.delete( wrapper ) );
	}

	return wrappers.length;
}

/**
 * The amount messages should currently price.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {string} The amount.
 */
function currentAmount( config ) {
	return latestAmount ?? config.messages.amount;
}

/**
 * Re-prices every rendered message.
 *
 * Assigning `amount` re-renders in place. Detached elements are pruned, since
 * WooCommerce replaces the cart and checkout DOM wholesale.
 *
 * @param {string} amount - The new amount.
 */
export function updateMessagesAmount( amount ) {
	if ( ! amount ) {
		return;
	}

	latestAmount = amount;
	rendered = rendered.filter( ( element ) => element.isConnected );
	rendered.forEach( ( element ) => {
		element.amount = amount;
	} );
}
