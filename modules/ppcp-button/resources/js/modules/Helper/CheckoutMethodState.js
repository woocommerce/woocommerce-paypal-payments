export const PaymentMethods = {
	PAYPAL: 'ppcp-gateway',
	CARDS: 'ppcp-credit-card-gateway',
	OXXO: 'ppcp-oxxo-gateway',
	CARD_BUTTON: 'ppcp-card-button-gateway',
	GOOGLEPAY: 'ppcp-googlepay',
	APPLEPAY: 'ppcp-applepay',
};

/**
 * List of valid context values that the button can have.
 *
 * The "context" describes the placement or page where a payment button might be displayed.
 *
 * @type {Object}
 */
export const PaymentContext = {
	Cart: 'cart', // Classic cart.
	Checkout: 'checkout', // Classic checkout.
	BlockCart: 'cart-block', // Block cart.
	BlockCheckout: 'checkout-block', // Block checkout.
	Product: 'product', // Single product page.
	MiniCart: 'mini-cart', // Mini cart available on all pages except checkout & cart.
	PayNow: 'pay-now', // Pay for order, via admin generated link.
	Preview: 'preview', // Layout preview on settings page.

	// Contexts that use blocks to render payment methods.
	Blocks: [ 'cart-block', 'checkout-block' ],

	// Contexts that display "classic" payment gateways.
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

/**
 * Whether a saved PayPal token (not "Use a new payment method") is the selected
 * token for the PayPal gateway on classic checkout.
 *
 * Such a payment is completed through the vault component and "Place order", so
 * the express buttons must not stand in for it.
 *
 * @return {boolean} True when a saved ppcp-gateway token is selected.
 */
export const isSavedPayPalTokenSelected = () => {
	const checked = document.querySelector(
		'input[name="wc-ppcp-gateway-payment-token"]:checked'
	);
	return Boolean( checked && checked.value && checked.value !== 'new' );
};
