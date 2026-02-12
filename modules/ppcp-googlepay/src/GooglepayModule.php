<?php

/**
 * The Googlepay module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmProductStatus;
use WooCommerce\PayPalCommerce\Googlepay\Helper\AvailabilityNotice;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Settings\SettingsModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
/**
 * Class GooglepayModule
 */
class GooglepayModule implements ServiceModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     */
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        // Clears product status when appropriate.
        add_action('woocommerce_paypal_payments_clear_apm_product_status', function (?Settings $settings = null) use ($c): void {
            $apm_status = $c->get('googlepay.helpers.apm-product-status');
            assert($apm_status instanceof ApmProductStatus);
            $apm_status->clear($settings);
        });
        add_action('init', static function () use ($c) {
            // Check if the module is applicable, correct country, currency, ... etc.
            if (!$c->get('googlepay.eligible')) {
                return;
            }
            // Load the button handler.
            $button = $c->get('googlepay.button');
            assert($button instanceof ButtonInterface);
            $button->initialize();
            // Show notice if there are product availability issues.
            $availability_notice = $c->get('googlepay.availability_notice');
            assert($availability_notice instanceof AvailabilityNotice);
            $availability_notice->execute();
            // Check if this merchant can activate / use the buttons.
            // We allow non referral merchants as they can potentially still use GooglePay, we just have no way of checking the capability.
            if (!$c->get('googlepay.available') && $c->get('googlepay.is_referral')) {
                return;
            }
            // Initializes button rendering.
            add_action('wp', static function () use ($c, $button) {
                if (is_admin()) {
                    return;
                }
                $button->render();
            });
            // Enqueue frontend scripts.
            add_action('wp_enqueue_scripts', static function () use ($c, $button) {
                $smart_button = $c->get('button.smart-button');
                assert($smart_button instanceof SmartButtonInterface);
                if ($smart_button->should_load_ppcp_script()) {
                    $button->enqueue();
                    return;
                }
                /*
                 * Checkout page, but no PPCP scripts were loaded. Most likely in continuation mode.
                 * Need to enqueue some Google Pay scripts to populate the billing form with details
                 * provided by Google Pay.
                 */
                if (is_checkout()) {
                    $button->enqueue();
                }
                if (has_block('woocommerce/checkout') || has_block('woocommerce/cart')) {
                    /**
                     * Should add this to the ButtonInterface.
                     *
                     * @psalm-suppress UndefinedInterfaceMethod
                     */
                    $button->enqueue_styles();
                }
            });
            // Enqueue backend scripts.
            add_action('admin_enqueue_scripts', static function () use ($c, $button) {
                if (!is_admin() || !$c->get('wcgateway.is-ppcp-settings-payment-methods-page')) {
                    return;
                }
                /**
                 * Should add this to the ButtonInterface.
                 *
                 * @psalm-suppress UndefinedInterfaceMethod
                 */
                $button->enqueue_admin();
            });
            // Registers buttons on blocks pages.
            add_action('woocommerce_blocks_payment_method_type_registration', function (PaymentMethodRegistry $payment_method_registry) use ($c, $button): void {
                if (SettingsModule::should_use_the_old_ui() && !$button->is_enabled()) {
                    return;
                }
                $payment_method_registry->register($c->get('googlepay.blocks-payment-method'));
            });
            // Adds GooglePay component to the backend button preview settings.
            add_action('woocommerce_paypal_payments_admin_gateway_settings', function (array $settings) use ($c): array {
                if (is_array($settings['components'])) {
                    $settings['components'][] = 'googlepay';
                }
                return $settings;
            });
            // Initialize AJAX endpoints.
            add_action('wc_ajax_' . UpdatePaymentDataEndpoint::ENDPOINT, static function () use ($c) {
                $endpoint = $c->get('googlepay.endpoint.update-payment-data');
                assert($endpoint instanceof UpdatePaymentDataEndpoint);
                $endpoint->handle_request();
            });
        }, 1);
        add_filter(
            'woocommerce_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($methods) use ($c): array {
                if (!is_array($methods)) {
                    return $methods;
                }
                $settings = $c->get('wcgateway.settings');
                assert($settings instanceof Settings);
                if ($settings->has('googlepay_button_enabled') && $settings->get('googlepay_button_enabled')) {
                    $googlepay_gateway = $c->get('googlepay.wc-gateway');
                    assert($googlepay_gateway instanceof WC_Payment_Gateway);
                    $methods[] = $googlepay_gateway;
                }
                return $methods;
            }
        );
        add_action('woocommerce_review_order_after_submit', function () {
            echo '<div id="ppc-button-' . esc_attr(\WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID) . '"></div>';
        });
        add_action('woocommerce_pay_order_after_submit', function () {
            echo '<div id="ppc-button-' . esc_attr(\WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID) . '"></div>';
        });
        add_filter('woocommerce_paypal_payments_selected_button_locations', function (array $locations, string $setting_name): array {
            $gateway = WC()->payment_gateways()->payment_gateways()[\WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID] ?? '';
            if ($gateway && $gateway->enabled === 'yes' && $setting_name === 'smart_button_locations') {
                $locations[] = 'checkout';
            }
            return $locations;
        }, 10, 2);
        add_filter('woocommerce_paypal_payments_rest_common_merchant_features', function (array $features) use ($c): array {
            $product_status = $c->get('googlepay.helpers.apm-product-status');
            assert($product_status instanceof ApmProductStatus);
            $google_pay_enabled = $product_status->is_active();
            $features[FeaturesDefinition::FEATURE_GOOGLE_PAY] = array('enabled' => $google_pay_enabled);
            return $features;
        });
        add_filter('ppcp_create_order_request_body_data', static function (array $data, string $payment_method, array $request) use ($c): array {
            $funding_source = $request['funding_source'] ?? '';
            if ($payment_method !== \WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID && $funding_source !== 'googlepay') {
                return $data;
            }
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $experience_context_builder = $c->get('wcgateway.builder.experience-context');
            assert($experience_context_builder instanceof ExperienceContextBuilder);
            $payment_source_data = array('experience_context' => $experience_context_builder->with_endpoint_return_urls()->build()->to_array());
            $three_d_secure_contingency = $settings->has('3d_secure_contingency') ? apply_filters('woocommerce_paypal_payments_three_d_secure_contingency', $settings->get('3d_secure_contingency')) : '';
            if ($three_d_secure_contingency === 'SCA_ALWAYS' || $three_d_secure_contingency === 'SCA_WHEN_REQUIRED') {
                $payment_source_data['attributes'] = array('verification' => array('method' => $three_d_secure_contingency));
            }
            $data['payment_source'] = array('google_pay' => $payment_source_data);
            return $data;
        }, 10, 3);
        return \true;
    }
}
