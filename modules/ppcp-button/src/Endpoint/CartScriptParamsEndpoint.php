<?php

/**
 * The endpoint for returning the PayPal SDK Script parameters for the current cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
/**
 * Class CartScriptParamsEndpoint.
 */
class CartScriptParamsEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    public const ENDPOINT = 'ppc-cart-script-params';
    /**
     * The SmartButton.
     *
     * @var SmartButtonInterface
     */
    private $smart_button;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * CartScriptParamsEndpoint constructor.
     *
     * @param SmartButtonInterface $smart_button he SmartButton.
     * @param LoggerInterface      $logger The logger.
     */
    public function __construct(SmartButtonInterface $smart_button, LoggerInterface $logger)
    {
        $this->smart_button = $smart_button;
        $this->logger = $logger;
    }
    /**
     * Returns the nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     *
     * @return bool
     */
    public function handle_request(): bool
    {
        try {
            if (!$this->smart_button instanceof SmartButton) {
                wp_send_json_error();
                return \false;
            }
            if (is_callable('wc_maybe_define_constant')) {
                wc_maybe_define_constant('WOOCOMMERCE_CART', \true);
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $include_shipping = (bool) wc_clean(wp_unslash($_GET['shipping'] ?? ''));
            $script_data = $this->smart_button->script_data();
            if (!$script_data) {
                wp_send_json_error();
                return \false;
            }
            $total = (float) WC()->cart->get_total('numeric');
            // Shop settings.
            $base_location = wc_get_base_location();
            $shop_country_code = $base_location['country'] ?? '';
            $currency_code = get_woocommerce_currency();
            $response = array('url_params' => $script_data['url_params'], 'button' => $script_data['button'], 'messages' => $script_data['messages'], 'amount' => WC()->cart->get_total('raw'), 'total' => $total, 'total_str' => (new Money($total, $currency_code))->value_str(), 'currency_code' => $currency_code, 'country_code' => $shop_country_code);
            if ($include_shipping) {
                $response = $this->append_shipping_data($response, $currency_code);
            }
            wp_send_json_success($response);
            return \true;
        } catch (Throwable $error) {
            $this->logger->error("CartScriptParamsEndpoint execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}");
            wp_send_json_error();
            return \false;
        }
    }
    /**
     * Appends shipping data to response.
     *
     * @param array  $response The response array.
     * @param string $currency_code The currency code.
     * @return array
     */
    private function append_shipping_data(array $response, string $currency_code): array
    {
        $calculated_packages = WC()->shipping->calculate_shipping(WC()->cart->get_shipping_packages());
        if (!count($calculated_packages)) {
            // Shipping disabled, or no shipping methods available.
            $response['chosen_shipping_methods'] = array();
            $response['shipping_packages'] = array();
            return $response;
        }
        $shipping_packages = array();
        foreach ($calculated_packages[0]['rates'] as $rate) {
            $rate_cost = $rate->get_cost();
            /**
             * The shipping rate.
             *
             * @var \WC_Shipping_Rate $rate
             */
            $shipping_packages[] = array('id' => $rate->get_id(), 'label' => $rate->get_label(), 'cost' => (float) $rate_cost, 'cost_str' => (new Money((float) $rate_cost, $currency_code))->value_str(), 'description' => html_entity_decode(wp_strip_all_tags(wc_price((float) $rate->get_cost(), array('currency' => get_woocommerce_currency()))), \ENT_QUOTES, 'UTF-8'));
        }
        $response['chosen_shipping_methods'] = WC()->session->get('chosen_shipping_methods');
        $response['shipping_packages'] = $shipping_packages;
        return $response;
    }
}
