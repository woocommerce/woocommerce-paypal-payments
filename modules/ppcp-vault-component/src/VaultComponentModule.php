<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent;

use WC_Order;
use WC_Payment_Token;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\VaultComponent\Endpoint\CreateVaultOrderEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
class VaultComponentModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    public function run(ContainerInterface $c): bool
    {
        // The eligibility check performs a (cached) PayPal API call, so it must be
        // evaluated lazily at request time inside each callback, never during boot.
        $eligibility_check = $c->get('vault-component.eligibility.check');
        add_filter('woocommerce_paypal_payments_sdk_components_hook', static function (array $components) use ($eligibility_check): array {
            if (!$eligibility_check()) {
                return $components;
            }
            $components[] = 'saved-payment-methods';
            return $components;
        });
        add_action('wc_ajax_' . CreateVaultOrderEndpoint::ENDPOINT, static function () use ($c, $eligibility_check) {
            if (!$eligibility_check()) {
                return;
            }
            $endpoint = $c->get('vault-component.endpoint.create-order');
            assert($endpoint instanceof CreateVaultOrderEndpoint);
            $endpoint->handle_request();
        });
        $vault_injected = \false;
        add_filter('woocommerce_payment_gateway_get_saved_payment_method_option_html', static function (string $html, WC_Payment_Token $token, $gateway) use (&$vault_injected, $eligibility_check): string {
            if ($vault_injected || PayPalGateway::ID !== $gateway->id || !$token instanceof PaymentTokenPayPal) {
                return $html;
            }
            if (!$eligibility_check()) {
                return $html;
            }
            $vault_injected = \true;
            $html = preg_replace('/<label\b/', '<label style="display:none"', $html, 1) ?? $html;
            return str_replace('</li>', '<div id="ppcp-vault-component"></div></li>', $html);
        }, 10, 3);
        add_action('woocommerce_paypal_payments_after_order_processor', static function (WC_Order $wc_order, Order $order) use ($c, $eligibility_check) {
            if (!$eligibility_check()) {
                return;
            }
            $data = $c->get('vault-component.data');
            assert($data instanceof \WooCommerce\PayPalCommerce\VaultComponent\VaultComponentData);
            $data->update_token_fi_details($order);
        }, 10, 2);
        add_action('after_setup_theme', static function () use ($c, $eligibility_check) {
            add_filter('woocommerce_paypal_payments_localized_script_data', static function (array $localized_script_data) use ($c, $eligibility_check): array {
                if (!$eligibility_check()) {
                    return $localized_script_data;
                }
                $data = $c->get('vault-component.data');
                assert($data instanceof \WooCommerce\PayPalCommerce\VaultComponent\VaultComponentData);
                return $data->add_localized_data($localized_script_data);
            });
        });
        return \true;
    }
}
