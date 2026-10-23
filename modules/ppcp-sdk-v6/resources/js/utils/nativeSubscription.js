/**
 * Native PayPal Subscriptions (subscriptions_api mode): the buyer subscribes to
 * a PayPal plan instead of paying an order.
 *
 * `config.native_subscription` maps each context that pays such a subscription
 * to whether its subscription button may render there. A context it names is
 * never paid as a one-time order, so a context whose button may not render
 * offers no PayPal button at all, as on v5.
 *
 * @package
 */

/**
 * Whether the context pays a native PayPal Subscription.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {boolean} True when the context subscribes rather than pays an order.
 */
export function isNativeSubscription( config, context ) {
	return Object.prototype.hasOwnProperty.call(
		config?.native_subscription || {},
		context
	);
}

/**
 * Whether the subscription button may render in the context: it has a plan to
 * subscribe to and, for a cart, nothing else in it.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {boolean} True when the button may render.
 */
export function subscriptionButtonAllowed( config, context ) {
	return config?.native_subscription?.[ context ] === true;
}

/**
 * Whether any context on the page renders the subscription button, which needs
 * the paypal-subscriptions SDK component.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {boolean} True when the component is needed.
 */
export function needsSubscriptionComponent( config ) {
	return Object.values( config?.native_subscription || {} ).some(
		( allowed ) => allowed === true
	);
}
