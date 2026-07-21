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

orders.negative12Fee = {
	...orders.default,
	fees: [ {
		name: 'negative12Fee',
		total: '-12.00',
		type: 'fixed',
	} ],
};

for ( const order in orders ) {
	orders[ order ].merchant = merchant;
	orders[ order ].currency = currency;
}

export { orders };
