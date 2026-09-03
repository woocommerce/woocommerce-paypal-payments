/**
 * The Basic Card (BCDC) button element, placed in its own gateway row by
 * cardButton/renderCardButton.js.
 *
 * Deliberately not in components/buttonRenderer.js, which draws into the shared
 * express wrapper: keeping the element here makes "the card button never joins
 * the express stack" true by construction rather than by a condition someone
 * can relax.
 *
 * @package
 */

/**
 * The event the SDK's card button dispatches on click. Documented contract; a
 * plain 'click' listener only works by way of the element's shadow internals.
 */
export const CARD_BUTTON_CLICK_EVENT = 'bcdc-click';

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
export function createCardButtonElements( styles, buyerCountry ) {
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
