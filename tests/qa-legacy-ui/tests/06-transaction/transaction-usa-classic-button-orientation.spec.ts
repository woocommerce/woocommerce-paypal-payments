/**
 * Internal dependencies
 */
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
} from './_test-scenarios';
import {
	payLaterClassicCartHorizontalButton,
	payLaterClassicCheckoutHorizontalButton,
	payLaterClassicProductVerticalButton
} from './_test-data/pay-later';
import {
	payPalClassicCartHorizontalButton,
	payPalClassicCheckoutHorizontalButton,
	payPalClassicProductVerticalButton
} from './_test-data/paypal';

transactionsOnClassicCart( payPalClassicCartHorizontalButton );
transactionsOnClassicCart( payLaterClassicCartHorizontalButton );

transactionsOnClassicProduct( payPalClassicProductVerticalButton );
transactionsOnClassicProduct( payLaterClassicProductVerticalButton );

transactionsOnClassicCheckout( payPalClassicCheckoutHorizontalButton );
transactionsOnClassicCheckout( payLaterClassicCheckoutHorizontalButton );
