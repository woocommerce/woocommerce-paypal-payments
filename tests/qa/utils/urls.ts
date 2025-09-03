/**
 * External dependencies
 */
import { urls } from '@inpsyde/playwright-utils/build';

export default {
	...urls.frontend,
	payPalWebhook: '/wp-json/paypal/v1/incoming',
	admin: {
		...urls.admin,
		pcp: {
			onboarding:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway',
			overview:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=overview',
			paymentMethods:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=payment-methods',
			settings:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=settings',
			styling:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=styling',
			payLaterMessaging:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=pay-later-messaging',
		},
		wooCommerce: {
			subscription: {
				edit: './wp-admin/admin.php?page=wc-orders--shop_subscription&action=edit&id=',
			},
		},
	},
};
