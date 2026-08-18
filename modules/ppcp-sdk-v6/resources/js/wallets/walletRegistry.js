/**
 * The wallets the v6 stack can render, and how each one is configured.
 *
 * One entry per wallet: the config subtree PHP fills in, and the SDK component
 * its session factory lives in. Everything that has to answer "is this wallet
 * enabled" reads it from here, rather than reaching for a config key directly.
 *
 * @package
 */

import { FundingSources } from '../utils/fundingSources';

const WALLETS = {
	[ FundingSources.GOOGLEPAY ]: {
		configKey: 'google_pay',
		sdkComponent: 'googlepay-payments',
	},
	[ FundingSources.APPLEPAY ]: {
		configKey: 'apple_pay',
		sdkComponent: 'applepay-payments',
		// The only wallet with a second identity, hence the only entry that
		// overrides the SDK's spelling.
		fundingSource: 'apple_pay',
	},
};

/**
 * The funding sources that are wallets, as opposed to express buttons.
 */
export const WALLET_METHODS = Object.keys( WALLETS );

/**
 * The funding_source the WC AJAX endpoints know a wallet by.
 *
 * The SDK's own spelling, unless the wallet answers to a second one:
 * ApplepayModule's ppcp_create_order_request_body_data filter matches the
 * underscored 'apple_pay', and an express-context order that misses it loses its
 * payment_source.
 *
 * @param {string} method - The funding source, as the SDK spells it.
 * @return {string} The spelling the endpoints expect.
 */
export function walletFundingSource( method ) {
	return WALLETS[ method ]?.fundingSource ?? method;
}

/**
 * A wallet's config subtree, so callers do not name the key themselves.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @param {string} method - The funding source to look up.
 * @return {Object|undefined} The subtree, or undefined when absent.
 */
export function walletConfig( config, method ) {
	const wallet = WALLETS[ method ];

	return wallet ? config[ wallet.configKey ] : undefined;
}

/**
 * Whether a wallet is switched on for this store.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @param {string} method - The funding source to check.
 * @return {boolean} False for anything that is not an enabled wallet.
 */
export function isWalletEnabled( config, method ) {
	return !! walletConfig( config, method )?.enabled;
}

/**
 * The SDK components needed by the enabled wallets.
 *
 * A component that is not requested leaves its session factory undefined, and
 * calling a missing factory takes every button on the page down, so this list
 * and the sessions that get created must agree.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {string[]} The component names to pass to createInstance().
 */
export function walletSdkComponents( config ) {
	return WALLET_METHODS.filter( ( method ) =>
		isWalletEnabled( config, method )
	).map( ( method ) => WALLETS[ method ].sdkComponent );
}
