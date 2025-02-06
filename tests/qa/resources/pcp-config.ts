/**
 * Internal dependencies
 */
import { merchants, Pcp } from '.';

const country = 'germany';

export const pcpConfigDefault: Pcp.Admin.Config = {
	merchant: merchants[ country ],
};

export const pcpConfigGermany = {
	...pcpConfigDefault,
	merchant: merchants.germany,
	enablePayUponInvoice: true, // true for Germany
};

export const pcpConfigMexico = {
	...pcpConfigDefault,
	merchant: merchants.mexico,
};

export const pcpConfigUsa = {
	...pcpConfigDefault,
	merchant: merchants.usa,
};
