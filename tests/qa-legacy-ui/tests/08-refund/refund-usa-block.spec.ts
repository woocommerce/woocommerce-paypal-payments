/**
 * Internal dependencies
 */
import { testRefund } from './_test-scenarios';
import {
	refundPayPalFromCheckout,
	refundPayPalFromPayByLink,
} from './_test-data/paypal/refund-paypal.data';

testRefund( refundPayPalFromCheckout );
testRefund( refundPayPalFromPayByLink );
