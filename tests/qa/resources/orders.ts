/**
 * External dependencies
 */
import { orders } from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';
/**
 * Internal dependencies
 */
import { merchants } from '.';

const country = process.env.WC_DEFAULT_COUNTRY || 'usa';
const currency = process.env.WC_DEFAULT_CURRENCY || 'USD';
const merchant = merchants[ country ];

orders.negative12PercentFee = {
	...orders.default,
	fees: [ {
		name: 'negative12PercentFee',
		total: '-12.00',
		type: 'percent',
	} ],
};

orders.negative15FixedFee = {
	...orders.default,
	fees: [ {
		name: 'negative15FixedFee',
		total: '-15.00',
		type: 'fixed',
	} ],
};

for ( const order in orders ) {
	orders[ order ].merchant = merchant;
	orders[ order ].currency = currency;
}

export { orders };
