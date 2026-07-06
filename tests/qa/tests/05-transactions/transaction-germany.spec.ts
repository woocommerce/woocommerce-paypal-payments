/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	customers,
} from '../../resources';
import {
	transactionsOnCheckout,
	transactionsOnPayByLink,
} from './_test-scenarios';
import {
	puiCheckout,
	puiPayByLink,
} from './_test-data/pui';

/**
 * BCDC is classic-checkout only — block checkout is not supported.
 */

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: false,
		customer: customers.germany,
	} );
} );

for ( const testOrder of puiCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of puiPayByLink ) {
	transactionsOnPayByLink( testOrder );
}
