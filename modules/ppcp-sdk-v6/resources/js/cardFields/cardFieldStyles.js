/**
 * Computes the `style.input` object for the v6 card-fields component.
 *
 * Unlike v5's paypal.CardFields() (see CardFieldsHelper.js), the v6 SDK
 * takes camelCase JS style properties (fontSize, not font-family) and
 * supports a different, narrower property set (e.g. no vendor-prefixed
 * properties, but background/border/borderRadius/boxShadow/height are
 * allowed) — reusing the v5 helper as-is causes the SDK to log a
 * "css property is not supported" DevError per property and skip it.
 *
 * @package
 */

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

/**
 * Converts a kebab-case CSS property name to camelCase.
 *
 * @param {string} property - The kebab-case property name.
 * @return {string} The camelCase property name.
 */
function toCamelCase( property ) {
	return property.replace( /-([a-z])/g, ( match, letter ) =>
		letter.toUpperCase()
	);
}

/**
 * Reads the field's live computed styles and returns only the properties
 * the v6 card-fields component actually supports, camelCased.
 *
 * @param {HTMLElement} field - The existing WC input being replaced.
 * @return {Object} The style object for the component's `style.input`.
 */
export function cardFieldStyles( field ) {
	const computed = window.getComputedStyle( field );
	const styles = {};

	for ( let i = 0; i < computed.length; i++ ) {
		const property = computed[ i ];
		const camelProperty = toCamelCase( property );

		if (
			! ALLOWED_PROPERTIES.includes( camelProperty ) ||
			! computed[ property ]
		) {
			continue;
		}

		styles[ camelProperty ] = '' + computed[ property ];
	}

	return styles;
}
