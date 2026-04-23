/**
 * External dependencies
 */
import { orders } from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';
/**
 * Internal dependencies
 */
import { merchants } from '.';

const country = 'usa';
const merchant = merchants[ country ];
const currency = 'USD';

for ( const key in orders ) {
	orders[ key ].merchant = merchant;
	orders[ key ].currency = currency;
}

export { orders };
