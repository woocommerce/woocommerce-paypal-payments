/**
 * The button styling every method resolves the same way.
 *
 * @package
 */

/**
 * Fills in the defaults, as v5 does: the settings leave these empty when the
 * merchant never picked a value, and an empty locale breaks both buttons —
 * Google rejects it, Apple renders nothing. borderRadius is passed through
 * untouched, since PHP always sends one.
 *
 * @param {Object} styles - The wallet's styles for this context.
 * @return {Object} The resolved { color, type, language, borderRadius }.
 */
export function buttonStyle( styles ) {
	return {
		color: styles.color || 'black',
		type: styles.type || 'pay',
		language: styles.language || 'en',
		borderRadius: styles.borderRadius,
	};
}

/**
 * The button height for a context.
 *
 * The flat `button_height` is the page context's. The mini cart carries its
 * own, shorter one, and it renders on pages whose own context is something
 * else entirely, so the context has to be asked for by name.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {string|undefined} The CSS length, or undefined when neither is set.
 */
export function buttonHeight( config, context ) {
	return config.button_styles?.[ context ]?.height || config.button_height;
}
