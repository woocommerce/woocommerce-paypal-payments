/**
 * Internal dependencies
 */
import { shopSettings, customers, ShopConfig } from '.';

const country = 'usa';

export const storeConfigDefault: ShopConfig = {
	classicPages: false, // false = block cart and checkout (default), true = classic cart & checkout pages
	wpDebugging: false, // WP Debugging plugin is deactivated
	subscription: false, // WC Subscription plugin is deactivated
	settings: shopSettings[ country ], // WC general settings
	customer: customers[ country ], // registered customer
};

export const storeConfigClassic: ShopConfig = {
	...storeConfigDefault,
	classicPages: true,
};

export const storeConfigGermany: ShopConfig = {
	...storeConfigDefault,
	settings: shopSettings.germany, // WC general settings
	customer: customers.germany,
};

export const storeConfigUsa: ShopConfig = {
	...storeConfigDefault,
	wpDebugging: true,
	settings: shopSettings.usa,
	customer: customers.usa,
};

export const storeConfigMexico: ShopConfig = {
	...storeConfigDefault,
	settings: shopSettings.mexico,
	customer: customers.mexico,
};

const storeConfigSubscription: ShopConfig = {
	// requireFinalConfirmation: false,
	subscription: true,
};

export const storeConfigSubscriptionGermany: ShopConfig = {
	...storeConfigGermany,
	...storeConfigSubscription,
};

export const storeConfigSubscriptionUsa: ShopConfig = {
	...storeConfigUsa,
	...storeConfigSubscription,
};
