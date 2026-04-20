/**
 * Internal dependencies
 */
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';
import {
	standardCardButtonClassicCheckoutIntentAuthorized
} from './_test-data/standard-card-button';

transactionsOnClassicCheckout(
	standardCardButtonClassicCheckoutIntentAuthorized
);
