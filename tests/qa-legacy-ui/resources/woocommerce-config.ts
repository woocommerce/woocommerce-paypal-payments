/**
 * Internal dependencies
 */
import { shopSettings, customers } from '.';

const country = 'germany';

export const storeConfigDefault = {
	classicPages: false, // false = block cart and checkout (default), true = classic cart & checkout pages
	wpDebugging: false, // WP Debugging plugin is deactivated
	subscription: false, // WC Subscription plugin is deactivated
	settings: shopSettings[ country ], // WC general settings
	customer: customers[ country ], // registered customer
};

export const storeConfigClassic = {
	...storeConfigDefault,
	classicPages: true,
};

export const storeConfigGermany = {
	...storeConfigDefault,
	customer: customers.germany,
};

export const storeConfigUsa = {
	...storeConfigDefault,
	wpDebugging: true,
	settings: shopSettings.usa,
	customer: customers.usa,
};

export const storeConfigMexico = {
	...storeConfigDefault,
	settings: shopSettings.mexico,
	customer: customers.mexico,
};

const storeConfigSubscription = {
	// requireFinalConfirmation: false,
	subscription: true,
};

export const storeConfigSubscriptionGermany = {
	...storeConfigGermany,
	...storeConfigSubscription,
};

export const storeConfigSubscriptionUsa = {
	...storeConfigUsa,
	...storeConfigSubscription,
};
