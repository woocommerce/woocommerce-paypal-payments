<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\Button\Helper\IsolatedCartSimulator;
class SimulateCartEndpoint extends \WooCommerce\PayPalCommerce\Button\Endpoint\AbstractCartEndpoint
{
    const ENDPOINT = 'ppc-simulate-cart';
    private SmartButtonInterface $smart_button;
    private IsolatedCartSimulator $cart_simulator;
    public function __construct(SmartButtonInterface $smart_button, \WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data, CartProductsHelper $cart_products, IsolatedCartSimulator $cart_simulator, LoggerInterface $logger)
    {
        $this->smart_button = $smart_button;
        $this->request_data = $request_data;
        $this->cart_products = $cart_products;
        $this->cart_simulator = $cart_simulator;
        $this->logger = $logger;
        $this->logger_tag = 'simulation';
    }
    /**
     * @throws Exception On error.
     */
    protected function handle_data(): void
    {
        if (!apply_filters('woocommerce_paypal_payments_simulate_cart_enabled', \true)) {
            wp_send_json_error(array('name' => '', 'message' => 'Cart simulation is disabled.', 'code' => 0, 'details' => array()));
        }
        if (!$this->smart_button instanceof SmartButton) {
            wp_send_json_error();
        }
        $products = $this->products_from_request();
        if (!$products) {
            return;
        }
        $result = $this->cart_simulator->simulate($products);
        $total = $result['total'];
        $shipping_fee = $result['shipping_fee'];
        // Process filters.
        $pay_later_enabled = \true;
        $pay_later_messaging_enabled = \true;
        $button_enabled = \true;
        foreach ($products as $product) {
            $context_data = array('product' => $product['product'], 'order_total' => $total);
            $pay_later_enabled = $pay_later_enabled && $this->smart_button->is_pay_later_button_enabled_for_location('product', $context_data);
            // @phpstan-ignore method.notFound
            $pay_later_messaging_enabled = $pay_later_messaging_enabled && $this->smart_button->is_pay_later_messaging_enabled_for_location('product', $context_data);
            // @phpstan-ignore method.notFound
            $button_enabled = $button_enabled && !$this->smart_button->is_button_disabled('product', $context_data);
            // @phpstan-ignore method.notFound
        }
        // Shop settings.
        $base_location = wc_get_base_location();
        $shop_country_code = $base_location['country'];
        $currency_code = get_woocommerce_currency();
        wp_send_json_success(array('total' => $total, 'shipping_fee' => $shipping_fee, 'currency_code' => $currency_code, 'country_code' => $shop_country_code, 'funding' => array('paylater' => array('enabled' => $pay_later_enabled)), 'button' => array('is_disabled' => !$button_enabled), 'messages' => array('is_hidden' => !$pay_later_messaging_enabled)));
    }
    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
    protected function handle_error(bool $send_response = \false): void
    {
        parent::handle_error($send_response);
    }
}
