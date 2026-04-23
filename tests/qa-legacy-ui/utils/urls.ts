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
		connection:
			'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection',
		standardPayments:
			'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway',
		payLater:
			'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-pay-later',
		advancedCardProcessing:
			'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-credit-card-gateway',
		standardCardButton:
			'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-card-button-gateway',
		oxxo: './wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-oxxo-gateway',
		payUponInvoice:
			'./wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway',
	},
};
