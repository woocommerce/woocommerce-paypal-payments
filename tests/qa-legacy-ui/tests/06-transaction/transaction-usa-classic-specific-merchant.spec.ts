/**
 * Internal dependencies
 */
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';
import {
	payPalClassicCheckoutSpecificMerchant,
} from './_test-data/paypal';

transactionsOnClassicCheckout( payPalClassicCheckoutSpecificMerchant );
