/**
 * Internal dependencies
 */
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';
import {
	debitOrCreditCardClassicCheckoutIntentAuthorized
} from './_test-data/debit-or-credit-card';

transactionsOnClassicCheckout(
	debitOrCreditCardClassicCheckoutIntentAuthorized
);
