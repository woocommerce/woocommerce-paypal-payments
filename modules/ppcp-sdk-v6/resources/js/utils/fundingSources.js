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
 * create*OneTimePaymentSession factories), which for every method but Apple Pay
 * is also what the WC AJAX endpoints accept as funding_source, so they are fixed
 * by both contracts rather than free to rename. Apple Pay is the exception: the
 * endpoints know it as apple_pay, so its bridge names that value itself and these
 * constants stay purely the SDK's vocabulary.
 */
export const FundingSources = {
	PAYPAL: 'paypal',
	VENMO: 'venmo',
	PAYLATER: 'paylater',
	GOOGLEPAY: 'googlepay',
	APPLEPAY: 'applepay',
};
