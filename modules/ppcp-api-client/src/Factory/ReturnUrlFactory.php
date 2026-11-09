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
     * @throws RuntimeException When the pay-now order cannot be found.
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
                return $this->product_url_from_request($request_data) ?? $this->product_url_from_cart() ?? wc_get_cart_url();
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
    /**
     * The URL of the first item in the posted purchase units.
     *
     * Absent whenever the sanitizer ditched the items, e.g. when a negative fee
     * leaves them out of step with the item total.
     *
     * @param array<string, mixed> $request_data The request parameters.
     */
    private function product_url_from_request(array $request_data): ?string
    {
        $units = $request_data['purchase_units'] ?? null;
        if (!is_array($units) || !$units) {
            return null;
        }
        $first_unit = reset($units);
        $items = is_array($first_unit) ? $first_unit['items'] ?? null : null;
        if (!is_array($items) || !$items) {
            return null;
        }
        $first_item = reset($items);
        $url = is_array($first_item) ? $first_item['url'] ?? '' : '';
        return is_string($url) && $url ? $url : null;
    }
    /**
     * The permalink of the first product in the cart.
     *
     * On the product page the viewed product has just replaced the cart contents,
     * so this is the page the buyer started from.
     */
    private function product_url_from_cart(): ?string
    {
        if (!function_exists('WC') || !isset(WC()->cart)) {
            return null;
        }
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if ($product instanceof \WC_Product) {
                $url = $product->get_permalink();
                return $url ? $url : null;
            }
        }
        return null;
    }
}
