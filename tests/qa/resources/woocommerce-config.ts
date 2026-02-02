/**
 * Internal dependencies
 */
import { shopSettings, ShopConfig } from '.';

const country = process.env.WC_DEFAULT_COUNTRY || 'usa';

export const storeConfigDefault: ShopConfig = {
	enableClassicPages: false, // false = block cart and checkout (default), true = classic cart & checkout pages
	enableWpDebugging: false, // WP Debugging plugin is deactivated
	enableSubscriptionsPlugin: false, // WC Subscription plugin is deactivated
	settings: shopSettings[ country ], // WC general settings
};

export const storeConfigClassic: ShopConfig = {
	...storeConfigDefault,
	enableClassicPages: true,
};

export const storeConfigGermany: ShopConfig = {
	...storeConfigDefault,
	settings: shopSettings.germany, // WC general settings
};

export const storeConfigUsa: ShopConfig = {
	...storeConfigDefault,
	enableWpDebugging: true,
	settings: shopSettings.usa,
};

export const storeConfigMexico: ShopConfig = {
	...storeConfigDefault,
	settings: shopSettings.mexico,
};

const storeConfigSubscription: ShopConfig = {
	enableSubscriptionsPlugin: true,
};

export const storeConfigSubscriptionGermany: ShopConfig = {
	...storeConfigGermany,
	...storeConfigSubscription,
};

export const storeConfigSubscriptionUsa: ShopConfig = {
	...storeConfigUsa,
	...storeConfigSubscription,
};
