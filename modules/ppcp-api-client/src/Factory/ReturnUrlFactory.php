<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Factory for determining the appropriate return URL based on context.
 */
class ReturnUrlFactory
{
    public const PCP_QUERY_ARG = 'pcp-return';
    /**
     * @param string                $context The context, like in ContextTrait.
     * @param array<string, mixed>  $request_data The request parameters, if exist.
     *  'order_id`, 'purchase_units' etc.
     * @param array<string, string> $custom_query_args Additional query args to add into the URL.
     *
     * @throws RuntimeException When required data is missing for the context.
     */
    public function from_context(string $context, array $request_data = array(), array $custom_query_args = array()): string
    {
        return $this->wc_url_from_context($context, $request_data);
    }
    protected function wc_url_from_context(string $context, array $request_data = array()): string
    {
        switch ($context) {
            case 'cart':
            case 'cart-block':
            case 'mini-cart':
                return wc_get_cart_url();
            case 'product':
                if (!empty($request_data['purchase_units']) && is_array($request_data['purchase_units'])) {
                    $first_unit = reset($request_data['purchase_units']);
                    if (!empty($first_unit['items']) && is_array($first_unit['items'])) {
                        $first_item = reset($first_unit['items']);
                        if (!empty($first_item['url'])) {
                            return $first_item['url'];
                        }
                    }
                }
                throw new RuntimeException('Product URL is required but not provided in the request data.');
            case 'pay-now':
                if (!empty($request_data['order_id'])) {
                    $order = wc_get_order($request_data['order_id']);
                    if ($order instanceof \WC_Order) {
                        return $order->get_checkout_payment_url();
                    }
                }
                throw new RuntimeException('The order ID is invalid.');
            default:
                return wc_get_checkout_url();
        }
    }
}
