/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { storeConfigDefault, pcpConfigDefault } from '../../resources';
import { testRefund } from './_test-scenarios';
import {
	refundPayPalFromCheckout,
	refundPayPalFromPayByLink,
} from './_test-data/paypal/refund-paypal.data';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.configurePcp( pcpConfigDefault );
} );

testRefund( refundPayPalFromCheckout );
testRefund( refundPayPalFromPayByLink );
