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
};

/**
 * The funding sources that are wallets, as opposed to express buttons.
 */
export const WALLET_METHODS = Object.keys( WALLETS );

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
