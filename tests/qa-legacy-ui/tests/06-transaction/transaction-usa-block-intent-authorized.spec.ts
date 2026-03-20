/**
 * Internal dependencies
 */
import {
	transactionsOnCart,
	transactionsOnCheckout
} from './_test-scenarios';
import {
	payLaterCartIntentAuthorized,
	payLaterCheckoutIntentAuthorized
} from './_test-data/pay-later';
import {
	payPalCartIntentAuthorized,
	payPalCheckoutIntentAuthorized
} from './_test-data/paypal';

transactionsOnCart( payPalCartIntentAuthorized );
transactionsOnCart( payLaterCartIntentAuthorized );
transactionsOnCheckout( payPalCheckoutIntentAuthorized );
transactionsOnCheckout( payLaterCheckoutIntentAuthorized );
