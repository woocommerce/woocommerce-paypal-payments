/**
 * External dependencies
 */
import { urls } from '@inpsyde/playwright-utils/build';

export default {
	...urls.frontend,
	admin: {
		...urls.admin,
	},
	pcp: {
		onboarding:
			'wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway',
		overview:
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=overview',
		paymentMehods:
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=payment-methods',
		settings:
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=settings',
		styling:
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=styling',
		payLaterMessaging:
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=pay-later-messaging',
	},
};
