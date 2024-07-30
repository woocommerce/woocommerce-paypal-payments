export const PaymentMethods = {
	PAYPAL: 'ppcp-gateway',
	CARDS: 'ppcp-credit-card-gateway',
	OXXO: 'ppcp-oxxo-gateway',
	CARD_BUTTON: 'ppcp-card-button-gateway',
	GOOGLEPAY: 'ppcp-googlepay',
};

/**
 * List of valid context values that the button can have.
 *
 * The "context" describes the placement or page where a payment button might be displayed.
 *
 * @type {Object}
 */
export const PaymentContext = {
	Product: 'product',
	Cart: 'cart',
	Checkout: 'checkout',
	PayNow: 'pay-now',
	MiniCart: 'mini-cart',
	BlockCart: 'cart-block',
	BlockCheckout: 'checkout-block',
	Preview: 'preview',

	// Block editor contexts.
	Blocks: [ 'cart-block', 'checkout-block' ],

	// Custom gateway contexts.
	Gateways: [ 'checkout', 'pay-now' ],
};

export const ORDER_BUTTON_SELECTOR = '#place_order';

export const getCurrentPaymentMethod = () => {
	const el = document.querySelector( 'input[name="payment_method"]:checked' );
	if ( ! el ) {
		return null;
	}

	return el.value;
};

export const isSavedCardSelected = () => {
	const savedCardList = document.querySelector( '#saved-credit-card' );
	return savedCardList && savedCardList.value !== '';
};
