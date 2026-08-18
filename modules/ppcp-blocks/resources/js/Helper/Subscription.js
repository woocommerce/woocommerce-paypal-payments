/**
 * @param {Object} scriptData
 * @return {boolean} Whether the store runs in PayPal Subscriptions mode.
 */
export const isPayPalSubscription = ( scriptData ) => {
	return (
		scriptData.data_client_id.has_subscriptions &&
		scriptData.data_client_id.paypal_subscriptions_enabled
	);
};

/**
 * @param {Object} scriptData
 * @return {boolean} Whether the cart contains at least one subscription product.
 */
export const cartHasSubscriptionProducts = ( scriptData ) => {
	return !! scriptData?.locations_with_subscription_product?.cart;
};

/**
 * Whether the PayPal payment method may be offered for the current cart totals.
 *
 * A zero-total cart usually needs no payment method, but a subscription cart does:
 * the method is vaulted to pay the renewals. `is_free_trial_cart` is computed
 * server-side while the page renders, so it misses a cart that only reaches a zero
 * total afterwards (a coupon applied on the checkout itself); the cart shape is
 * therefore checked as well.
 *
 * @param {Object} scriptData
 * @param {Object} cartData   Live cart data passed to `canMakePayment`.
 * @return {boolean} Whether the PayPal payment method may be displayed.
 */
export const paypalPaymentMethodAllowed = ( scriptData, cartData ) => {
	if (
		scriptData?.is_free_trial_cart ||
		cartHasSubscriptionProducts( scriptData )
	) {
		return true;
	}

	return parseInt( cartData?.cartTotals?.total_price ) > 0;
};

/**
 * Whether the PayPal button is allowed for the current (subscription) cart.
 *
 * Single, mode-aware rule shared by the block cart, classic cart and mini-cart
 * so the button is shown (or hidden) consistently. Prefers the authoritative
 * server flag `subscription_button_allowed`; the explicit checks act as a
 * fallback and also cover the free-trial guest case on the block cart.
 *
 * @param {Object} scriptData
 * @return {boolean} Whether the PayPal button may be displayed for this cart.
 */
export const paypalSubscriptionButtonAllowed = ( scriptData ) => {
	if ( ! cartHasSubscriptionProducts( scriptData ) ) {
		return true;
	}

	// Don't show buttons on the block cart page if the user is not logged in
	// and the cart contains a free trial product.
	if (
		! scriptData.user?.is_logged &&
		scriptData.context === 'cart-block' &&
		scriptData.is_free_trial_cart
	) {
		return false;
	}

	if ( typeof scriptData.subscription_button_allowed !== 'undefined' ) {
		return !! scriptData.subscription_button_allowed;
	}

	// Vaulting mode but vaulting disabled.
	if (
		! isPayPalSubscription( scriptData ) &&
		! scriptData.can_save_vault_token
	) {
		return false;
	}

	// PayPal Subscriptions mode but product not associated with a PayPal plan
	// (or the cart contains more than one item).
	if (
		isPayPalSubscription( scriptData ) &&
		! scriptData.subscription_product_allowed
	) {
		return false;
	}

	return true;
};
