<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent;

use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
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
        $eligibility_check = $c->get('vault-component.eligibility.check');
        if (!$eligibility_check()) {
            return \true;
        }
        add_filter('woocommerce_paypal_payments_sdk_components_hook', static function (array $components): array {
            $components[] = 'vault';
            return $components;
        });
        add_action('wc_ajax_' . CreateVaultOrderEndpoint::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('vault-component.endpoint.create-order');
            assert($endpoint instanceof CreateVaultOrderEndpoint);
            $endpoint->handle_request();
        });
        add_action('ppcp_end_button_wrapper_ppcp_gateway', static function () {
            echo '<div id="ppcp-vault-component" style="display:none"></div>';
        });
        add_action('after_setup_theme', function () use ($c) {
            add_filter('woocommerce_paypal_payments_localized_script_data', function (array $localized_script_data) use ($c): array {
                return $this->maybe_add_vault_component_data($localized_script_data, $c);
            });
        });
        return \true;
    }
    private function maybe_add_vault_component_data(array $localized_script_data, ContainerInterface $c): array
    {
        if (!is_user_logged_in()) {
            return $localized_script_data;
        }
        $customer_id = get_current_user_id();
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, PayPalGateway::ID);
        $paypal_tokens = array_filter($wc_tokens, static function ($token) {
            return $token instanceof PaymentTokenPayPal;
        });
        if (empty($paypal_tokens)) {
            return $localized_script_data;
        }
        $primary_token = reset($paypal_tokens);
        $localized_script_data['vault_component'] = array('is_eligible' => \true, 'token_id' => $primary_token->get_token(), 'ajax' => array('create_order' => array('endpoint' => \WC_AJAX::get_endpoint(CreateVaultOrderEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(CreateVaultOrderEndpoint::nonce()))));
        return $localized_script_data;
    }
}
