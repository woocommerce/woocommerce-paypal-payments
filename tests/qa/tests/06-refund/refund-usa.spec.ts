/**
 * Internal dependencies
 */
import { testRefund } from './_test-scenarios';
import {
	refundPayPalFromCheckout,
	refundPayPalFromPayByLink,
	refundAcdcFromCheckout,
	refundAcdcFromPayByLink,
} from './_test-data';

for ( const testOrder of refundPayPalFromCheckout ) {
	testRefund( testOrder );
}

for ( const testOrder of refundAcdcFromCheckout ) {
	testRefund( testOrder );
}

for ( const testOrder of refundPayPalFromPayByLink ) {
	testRefund( testOrder );
}

for ( const testOrder of refundAcdcFromPayByLink ) {
	testRefund( testOrder );
}
