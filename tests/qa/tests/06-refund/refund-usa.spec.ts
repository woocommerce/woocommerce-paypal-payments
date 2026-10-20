/**
 * Internal dependencies
 */
import { testRefund, testRefundFromPayPal } from './_test-scenarios';
import {
	refundPayPalFromCheckout,
	refundPayPalFromPayByLink,
	refundAcdcFromCheckout,
	refundAcdcFromPayByLink,
	refundPayPalFromPayPalDashboard,
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

for ( const testOrder of refundPayPalFromPayPalDashboard ) {
	testRefundFromPayPal( testOrder );
}
