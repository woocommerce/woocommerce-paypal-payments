<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent;

use WC_Order;
use WC_Payment_Token;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\VaultComponent\Endpoint\CreateVaultOrderEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
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
        // Standalone renderer for pages the SDK v6 stack owns, where the smart
        // button (which carries the v5 vault renderer) is replaced by a no-op.
        // Enqueued only when that renderer will not run, so the two never both
        // render into #ppcp-vault-component.
        add_action('wp_enqueue_scripts', static function () use ($c, $eligibility_check) {
            if (!is_checkout() || !$eligibility_check()) {
                return;
            }
            $smart_button = $c->get('button.smart-button');
            assert($smart_button instanceof SmartButtonInterface);
            // The v5 stack is live here; it renders the vault component itself.
            if ($smart_button->should_load_ppcp_script()) {
                return;
            }
            $data = $c->get('vault-component.data');
            assert($data instanceof \WooCommerce\PayPalCommerce\VaultComponent\VaultComponentData);
            $vault_component = $data->add_localized_data(array())['vault_component'] ?? null;
            if (!$vault_component) {
                return;
            }
            $factory = $c->get('assets.asset_getter_factory');
            assert($factory instanceof AssetGetterFactory);
            $asset_getter = $factory->for_module('ppcp-vault-component');
            assert($asset_getter instanceof AssetGetter);
            $script_url = $asset_getter->get_asset_url('checkout.js');
            if (!$script_url) {
                return;
            }
            $version = $c->get('ppcp.asset-version');
            $asset_php = $asset_getter->get_asset_php_path('checkout.js');
            $asset = file_exists($asset_php) ? require $asset_php : array('dependencies' => array(), 'version' => $version);
            $free_trial = $c->get('wc-subscriptions.free-trial-subscription-helper');
            assert($free_trial instanceof FreeTrialSubscriptionHelper);
            wp_register_script('ppcp-vault-component', $script_url, array_merge($asset['dependencies'], array('jquery')), $asset['version'], \true);
            wp_localize_script('ppcp-vault-component', 'ppcp_vault_component', array(
                'vault_component' => $vault_component,
                // The vault SDK (saved-payment-methods) is a v5 component that
                // needs a client-id; v6 uses a client token, so source it here.
                'url_params' => array('client-id' => $c->get('button.client_id')),
                'script_attributes' => (object) array(),
                'is_free_trial_cart' => $free_trial->is_free_trial_cart(),
                'gateway_id' => PayPalGateway::ID,
            ));
            wp_enqueue_script('ppcp-vault-component');
            // The button module's stylesheet (which lays the vault widget out
            // inside the saved-token row so its iframe does not overlap the
            // "Use a new payment method" option) is not enqueued under v6, so
            // ship the same rules here.
            wp_register_style('ppcp-vault-component', \false, array(), $asset['version']);
            wp_enqueue_style('ppcp-vault-component');
            wp_add_inline_style('ppcp-vault-component', '.woocommerce-SavedPaymentMethods-token:has(#ppcp-vault-component){display:flex;align-items:center;gap:8px;}' . '.woocommerce-SavedPaymentMethods-token:has(#ppcp-vault-component) #ppcp-vault-component{flex:1;overflow:hidden;}');
        });
        return \true;
    }
}
