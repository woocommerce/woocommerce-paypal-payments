/**
 * External dependencies
 */
import { urls } from '@inpsyde/playwright-utils/build';

export default {
	...urls.frontend,
	admin: {
		...urls.admin,
		pcp: {
			onboarding:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway',
			overview:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection&panel=overview',
			paymentMethods:
				'./wp-admin/page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection&panel=payment-methods',
			settings:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection&panel=settings',
			styling:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection&panel=styling',
			payLaterMessaging:
				'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection&panel=pay-later-messaging',
		},
	},
};
