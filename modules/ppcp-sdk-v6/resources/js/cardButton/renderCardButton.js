/**
 * Renders the Basic Card button (BCDC) as its own payment-method row.
 *
 * Kept out of components/buttonRenderer.js, which draws into the shared express
 * wrapper: separate files make "the card button never joins the express stack"
 * true by construction rather than by a condition someone can relax.
 *
 * @package
 */

import { createOrder } from '../endpointsAdapter';
import { handleError } from '../utils/errorHandler';
import { FundingSources } from '../utils/fundingSources';
import { revealMethodGateway } from '../methods/gatewayPlacement';

/**
 * The event the SDK's card button dispatches on click. Documented contract; a
 * plain 'click' listener only works by way of the element's shadow internals.
 */
const CLICK_EVENT = 'bcdc-click';

/**
 * Builds the button element and its required container, detached.
 *
 * The button checks on connect that its parent is a
 * <paypal-basic-card-container>, so the tree must be assembled before any of it
 * enters the document.
 *
 * @param {Object}  styles         - Style config from the card_button subtree.
 * @param {?string} [buyerCountry] - ISO 3166-1 alpha-2 buyer country.
 * @return {{container: HTMLElement, button: HTMLElement}} The detached pair.
 */
function createCardButtonElements( styles, buyerCountry ) {
	const container = document.createElement( 'paypal-basic-card-container' );
	const button = document.createElement( 'paypal-basic-card-button' );

	if ( buyerCountry ) {
		button.buyerCountry = buyerCountry;
	}

	if ( styles?.borderRadius ) {
		button.style.setProperty(
			'--paypal-button-border-radius',
			styles.borderRadius
		);
	}

	if ( styles?.height ) {
		button.style.height = styles.height;
	}

	// The element ships a fixed 225px width, where v5's card button spanned the
	// checkout column. An outer declaration wins over its own :host rule.
	if ( styles?.width ) {
		container.style.width = styles.width;
		button.style.width = styles.width;
	}

	container.appendChild( button );

	return { container, button };
}

/**
 * Renders the card button into its gateway row, if it belongs on this page.
 *
 * Idempotent, because it runs again on every checkout update: WooCommerce
 * replaces the whole order-review DOM, which takes our container with it.
 *
 * @param {Object}   config         - The wc_ppcp_sdk_v6 config object.
 * @param {Function} ensureSessions - Resolves the sessions for a context.
 * @return {Promise<void>} Resolves once the pass is done.
 */
export async function initCardButton( config, ensureSessions ) {
	if ( ! config.card_button?.enabled ) {
		return;
	}

	const findEmptyWrapper = () => {
		const el = document.querySelector( config.card_button.wrapper );
		return el && el.childElementCount === 0 ? el : null;
	};

	if ( ! findEmptyWrapper() ) {
		return;
	}

	const context = config.page_context;
	const { map } = await ensureSessions( context );

	// Asked again after the await, never only before it: the initial render and
	// the updated_checkout that follows it overlap, and a check that straddles
	// the await lets both through, appending the button twice.
	const target = findEmptyWrapper();
	if ( ! target ) {
		return;
	}

	// No session means the buyer is not eligible for cards; the row stays hidden
	// and "Place order" stays visible, so the checkout still offers a way to pay.
	const session = map[ FundingSources.CARD ];
	if ( ! session ) {
		return;
	}

	const { container, button } = createCardButtonElements(
		config.card_button.styles,
		config.buyer_country
	);
	target.appendChild( container );

	button.addEventListener( CLICK_EVENT, async () => {
		try {
			await session.start(
				// Naming the target beats the SDK's "last clicked button"
				// fallback, ambient state on a page that also carries the
				// express buttons. 'auto' is the only mode this session takes.
				{ presentationMode: 'auto', targetElement: button },
				createOrder(
					config,
					context,
					FundingSources.CARD,
					undefined,
					config.card_button.payment_method
				)
			);
		} catch ( error ) {
			handleError( error );
		}
	} );

	// After insertion, so the row counts as rendered when the placement logic
	// decides what to do with "Place order".
	revealMethodGateway(
		{
			id: config.card_button.payment_method,
			wrapper: config.card_button.wrapper,
		},
		config
	);
}
