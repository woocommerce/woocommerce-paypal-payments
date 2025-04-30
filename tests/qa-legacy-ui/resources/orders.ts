/**
 * External dependencies
 */
import { orders } from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';
/**
 * Internal dependencies
 */
import { merchants } from '.';

const country = 'germany';
const merchant = merchants[ country ];

for ( const order in orders ) {
	orders[ order ].merchant = merchant;
}

export { orders };
