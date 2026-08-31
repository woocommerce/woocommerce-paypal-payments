/**
 * Computes the style objects for the v6 card-fields component.
 *
 * cardFieldStyles() describes an element on our own page, so the theme's full
 * box belongs on it. hostedFieldTextStyles() is handed to the SDK as
 * `style.input` and reaches text inside a PayPal-hosted iframe, where box
 * decoration would paint a frame the theme never drew on this element — and
 * would carry state the theme does not own, such as the `border-color:
 * var(--wc-green)` WooCommerce puts on a validated row.
 *
 * The v6 SDK takes camelCase property names (fontSize, not font-size) and
 * rejects vendor-prefixed ones, logging a "css property is not supported"
 * DevError per property it skips.
 *
 * @package
 */

/**
 * Properties describing the field's frame rather than its text; never handed
 * to the SDK.
 */
const BOX_PROPERTIES = [
	'background',
	'border',
	'borderRadius',
	'boxShadow',
	'height',
];

const ALLOWED_PROPERTIES = [
	'appearance',
	'background',
	'border',
	'borderRadius',
	'boxShadow',
	'color',
	'direction',
	'font',
	'fontFamily',
	'fontSize',
	'fontSizeAdjust',
	'fontStretch',
	'fontStyle',
	'fontVariant',
	'fontVariantAlternates',
	'fontVariantCaps',
	'fontVariantEastAsian',
	'fontVariantLigatures',
	'fontVariantNumeric',
	'fontWeight',
	'height',
	'letterSpacing',
	'lineHeight',
	'opacity',
	'outline',
	'padding',
	'paddingBottom',
	'paddingLeft',
	'paddingRight',
	'paddingTop',
	'textShadow',
	'transition',
];

const TEXT_PROPERTIES = ALLOWED_PROPERTIES.filter(
	( property ) => ! BOX_PROPERTIES.includes( property )
);

function toCamelCase( property ) {
	return property.replace( /-([a-z])/g, ( match, letter ) =>
		letter.toUpperCase()
	);
}

/**
 * Reads the field's live computed styles and keeps only the listed properties,
 * camelCased.
 *
 * @param {HTMLElement} field      - The existing WC input being replaced.
 * @param {string[]}    properties - The camelCase property names to keep.
 * @return {Object} The style object.
 */
function pickComputed( field, properties ) {
	const computed = window.getComputedStyle( field );
	const styles = {};

	for ( let i = 0; i < computed.length; i++ ) {
		const property = computed[ i ];
		const camelProperty = toCamelCase( property );
		const value = computed.getPropertyValue( property );

		if ( ! value || ! properties.includes( camelProperty ) ) {
			continue;
		}

		styles[ camelProperty ] = value;
	}

	return styles;
}

/**
 * The full style set, box included, for elements this plugin owns and renders.
 *
 * @param {HTMLElement} field - The existing WC input being replaced.
 * @return {Object} The style object.
 */
export function cardFieldStyles( field ) {
	return pickComputed( field, ALLOWED_PROPERTIES );
}

/**
 * The text-only style set for the SDK's `style.input`, which reaches inside a
 * PayPal-hosted field.
 *
 * @param {HTMLElement} field       - The existing WC input being replaced.
 * @param {Object}      [overrides] - Merchant overrides from card_fields.styles.
 * @return {Object} The style object.
 */
export function hostedFieldTextStyles( field, overrides ) {
	return { ...pickComputed( field, TEXT_PROPERTIES ), ...overrides };
}
