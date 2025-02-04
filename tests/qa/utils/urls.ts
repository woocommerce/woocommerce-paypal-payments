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
	},
};
