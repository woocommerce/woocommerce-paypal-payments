/**
 * The funding sources the v6 stack knows about.
 *
 * Deliberately free of imports: boot.js is registered without script
 * dependencies, so anything reachable from it must not pull in a wp-* global.
 * The display labels, which need @wordpress/i18n, live in fundingSourceLabel.js
 * and are only reachable from the block bundles.
 *
 * @package
 */

/**
 * The values are the v6 SDK's own vocabulary (findEligibleMethods, the
 * create*OneTimePaymentSession factories) and are also what the WC AJAX
 * endpoints accept as funding_source, so they are fixed by both contracts
 * rather than free to rename.
 */
export const FundingSources = {
	PAYPAL: 'paypal',
	VENMO: 'venmo',
	PAYLATER: 'paylater',
	GOOGLEPAY: 'googlepay',
};
