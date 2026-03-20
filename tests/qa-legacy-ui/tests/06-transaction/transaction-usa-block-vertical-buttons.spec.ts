/**
 * Internal dependencies
 */
import {
	transactionsOnProduct,
} from './_test-scenarios';
import {
	payPalProductVerticalButton,
} from './_test-data/paypal';
import {
	payLaterProductVerticalButton,
} from './_test-data/pay-later';

transactionsOnProduct( payPalProductVerticalButton );
transactionsOnProduct( payLaterProductVerticalButton );
