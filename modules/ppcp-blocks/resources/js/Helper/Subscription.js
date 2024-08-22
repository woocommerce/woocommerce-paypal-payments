/**
 * @param {Object} scriptData
 * @return {boolean}
 */
export const isPayPalSubscription = ( scriptData ) => {
	return (
		scriptData.data_client_id.has_subscriptions &&
		scriptData.data_client_id.paypal_subscriptions_enabled
	);
};

/**
 * @param {Object} scriptData
 * @return {boolean}
 */
export const cartHasSubscriptionProducts = ( scriptData ) => {
	return !! scriptData?.locations_with_subscription_product?.cart;
};
