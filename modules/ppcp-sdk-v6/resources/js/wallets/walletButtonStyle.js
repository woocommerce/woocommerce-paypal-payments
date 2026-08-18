/**
 * The button styling both wallets resolve the same way.
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
export function walletButtonStyle( styles ) {
	return {
		color: styles.color || 'black',
		type: styles.type || 'pay',
		language: styles.language || 'en',
		borderRadius: styles.borderRadius,
	};
}
