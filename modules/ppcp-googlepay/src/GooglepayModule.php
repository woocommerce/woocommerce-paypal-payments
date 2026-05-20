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
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\Googlepay\Helper\GoogleProductStatus;
use WooCommerce\PayPalCommerce\Googlepay\Helper\AvailabilityNotice;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Googlepay\Helper\PropertiesDictionary;
/**
 * Class GooglepayModule
 */
class GooglepayModule implements ServiceModule, ExecutableModule
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
    public function run(ContainerInterface $c): bool
    {
        // Clears product status when appropriate.
        add_action('woocommerce_paypal_payments_clear_apm_product_status', static function () use ($c): void {
            $apm_status = $c->get('googlepay.helpers.apm-product-status');
            assert($apm_status instanceof GoogleProductStatus);
            $apm_status->clear();
        });
        add_action('init', static function () use ($c) {
            // Check if the module is applicable, correct country, currency, ... etc.
            if (!$c->get('googlepay.eligible')) {
                return;
            }
            // Load the button handler.
            $button = $c->get('googlepay.button');
            assert($button instanceof ButtonInterface);
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
            add_action('wp', static function () use ($button) {
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
                     * @phpstan-ignore method.notFound
                     */
                    $button->enqueue_styles();
                }
            });
            // Enqueue backend scripts.
            add_action('admin_enqueue_scripts', static function () use ($c, $button) {
                if (!is_admin() || !$c->get('wcgateway.is-plugin-settings-page')) {
                    return;
                }
                /**
                 * Should add this to the ButtonInterface.
                 *
                 * @psalm-suppress UndefinedInterfaceMethod
                 * @phpstan-ignore method.notFound
                 */
                $button->enqueue_admin();
            });
            // Registers buttons on blocks pages.
            add_action('woocommerce_blocks_payment_method_type_registration', function (PaymentMethodRegistry $payment_method_registry) use ($c): void {
                $payment_method_registry->register($c->get('googlepay.blocks-payment-method'));
            });
            // Adds GooglePay component to the backend button preview settings.
            add_action('woocommerce_paypal_payments_admin_gateway_settings', function (array $settings): array {
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
            static function ($methods) use ($c) {
                if (!is_array($methods)) {
                    return $methods;
                }
                $settings = $c->get('settings.settings-provider');
                assert($settings instanceof SettingsProvider);
                if (!$settings->googlepay_enabled()) {
                    return $methods;
                }
                $context = $c->get('button.helper.context');
                assert($context instanceof Context);
                $page_methods = $settings->button_styling($context->context())->methods;
                if (!in_array('ppcp-googlepay', $page_methods, \true)) {
                    return $methods;
                }
                $googlepay_gateway = $c->get('googlepay.wc-gateway');
                assert($googlepay_gateway instanceof WC_Payment_Gateway);
                $methods[] = $googlepay_gateway;
                return $methods;
            }
        );
        /**
         * Filters the available payment gateways to remove the Google Pay gateway
         * when the button is disabled for the current location (e.g., classic checkout) in the styling settings.
         * This is necessary because WooCommerce automatically includes the gateway when it is enabled,
         * even if the button is hidden via settings.
         */
        add_filter('woocommerce_available_payment_gateways', static function ($methods) use ($c) {
            if (!is_array($methods)) {
                return $methods;
            }
            $context = $c->get('button.helper.context');
            assert($context instanceof Context);
            $current_context = $context->context();
            if ($current_context !== 'checkout') {
                return $methods;
            }
            $settings = $c->get('settings.settings-provider');
            assert($settings instanceof SettingsProvider);
            $page_methods = $settings->button_styling($current_context)->methods;
            if (!in_array(\WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID, $page_methods, \true)) {
                unset($methods[\WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID]);
            }
            return $methods;
        });
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
            assert($product_status instanceof GoogleProductStatus);
            $google_pay_enabled = $product_status->is_active();
            $features[FeaturesDefinition::FEATURE_GOOGLE_PAY] = array('enabled' => $google_pay_enabled);
            return $features;
        });
        add_filter('ppcp_create_order_request_body_data', static function (array $data, string $payment_method, array $request) use ($c): array {
            $funding_source = $request['funding_source'] ?? '';
            if ($payment_method !== \WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway::ID && $funding_source !== 'googlepay') {
                return $data;
            }
            $settings = $c->get('settings.settings-provider');
            assert($settings instanceof SettingsProvider);
            $experience_context_builder = $c->get('wcgateway.builder.experience-context');
            assert($experience_context_builder instanceof ExperienceContextBuilder);
            $payment_source_data = array('experience_context' => $experience_context_builder->with_endpoint_return_urls()->build()->to_array());
            $three_d_secure_contingency = $settings->three_d_secure_enum() ? apply_filters('woocommerce_paypal_payments_three_d_secure_contingency', $settings->three_d_secure_enum()) : '';
            if ($three_d_secure_contingency === 'SCA_ALWAYS' || $three_d_secure_contingency === 'SCA_WHEN_REQUIRED') {
                $payment_source_data['attributes'] = array('verification' => array('method' => $three_d_secure_contingency));
            }
            $data['payment_source'] = array('google_pay' => $payment_source_data);
            return $data;
        }, 10, 3);
        add_filter('woocommerce_paypal_payments_googlepay_button_styles', static function (LocationStylingDTO $styles): LocationStylingDTO {
            $styles->color = PropertiesDictionary::map_color($styles->color);
            $styles->label = PropertiesDictionary::map_type($styles->label);
            return $styles;
        }, 9999);
        add_filter('woocommerce_paypal_payments_googlepay_button_language', static function (string $language): string {
            return PropertiesDictionary::map_language($language);
        }, 9999);
        return \true;
    }
}
