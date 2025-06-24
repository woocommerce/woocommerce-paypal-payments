export {
	shopSettings,
	shippingZones,
	flatRate,
	freeShipping,
	customers,
	taxSettings,
	coupons,
} from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';

export * from './cards';
export * from './paypal-accounts';
export * from './pcp-config';
export * from './pcp-gateways';
export * from './pcp-payments';
export * from './pcp-products';
export * from './pcp-merchants';
export * from './guests';
export * from './orders';
export * from './woocommerce-config';
export * from './types';

export { default as pcpPlugin } from './pcp-plugin.json';
export { default as disableNoncePlugin } from './disable-nonce-plugin.json';
export { default as enableVaultV2Plugin } from './enable-vault-v2-plugin.json';
export { default as subscriptionsPlugin } from './woocommerce-subscriptions-plugin.json';
export { default as wpDebuggingPlugin } from './wp-debugging-plugin.json';
export { default as disableWcSetupWizard } from './disable-wc-setup-wizard-plugin.json';
export { default as disableGutenbergWelcomeGuide } from './disable-gutenberg-welcome-guide-plugin.json';
