<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Assets;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

class SmartButton implements SmartButtonInterface
{
    private $moduleUrl;
    private $sessionHandler;
    private $settings;
    private $payeeRepository;
    private $identityToken;

    public function __construct(
        string $moduleUrl,
        SessionHandler $sessionHandler,
        Settings $settings,
        PayeeRepository $payeeRepository,
        IdentityToken $identityToken
    ) {

        $this->moduleUrl = $moduleUrl;
        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
        $this->payeeRepository = $payeeRepository;
        $this->identityToken = $identityToken;
    }

    public function renderWrapper(): bool
    {

        $hostedFieldsEnabled = $this->dccIsEnabled();
        $renderer = static function () use ($hostedFieldsEnabled) {
            echo '<div id="ppc-button"></div>';
            if (! $hostedFieldsEnabled) {
                return;
            }
            printf(
                '<form id="ppc-hosted-fields"><label for="ppcp-credit-card">%s</label><div id="ppcp-credit-card"></div><label for="ppcp-expiration-date">%s</label><div id="ppcp-expiration-date"></div><label for="ppcp-cvv">%s</label><div id="ppcp-cvv"></div><button>%s</button></form>',
                __('Card number', 'woocommerce-paypal-commerce-gateway'),
                __('Expiration Date', 'woocommerce-paypal-commerce-gateway'),
                __('CVV', 'woocommerce-paypal-commerce-gateway'),
                __('Pay with Card', 'woocommerce-paypal-commerce-gateway')
            );
        };
        if (is_cart() && wc_string_to_bool($this->settings->get('button_cart_enabled'))) {
            add_action(
                'woocommerce_proceed_to_checkout',
                $renderer,
                20
            );
        }
        if (is_product() && wc_string_to_bool($this->settings->get('button_single_product_enabled'))) {
            add_action(
                'woocommerce_single_product_summary',
                $renderer,
                31
            );
        }
        if (wc_string_to_bool($this->settings->get('button_mini_cart_enabled'))) {
            add_action(
                'woocommerce_widget_shopping_cart_after_buttons',
                static function () {
                    echo '<p id="ppc-button-minicart" class="woocommerce-mini-cart__buttons buttons"></p>';
                },
                30
            );
        }
        add_action(
            'woocommerce_review_order_after_submit',
            $renderer,
            10
        );
        return true;
    }

    public function enqueue(): bool
    {
        wp_enqueue_script(
            'paypal-smart-button',
            $this->moduleUrl . '/assets/js/button.js',
            ['jquery'],
            1,
            true
        );

        wp_localize_script(
            'paypal-smart-button',
            'PayPalCommerceGateway',
            $this->localizeScript()
        );
        return true;
    }

    private function localizeScript(): array
    {
        $localize = [
            'script_attributes' => $this->attributes(),
            'redirect' => wc_get_checkout_url(),
            'context' => $this->context(),
            'ajax' => [
                'change_cart' => [
                    'endpoint' => home_url(\WC_AJAX::get_endpoint(ChangeCartEndpoint::ENDPOINT)),
                    'nonce' => wp_create_nonce(ChangeCartEndpoint::nonce()),
                ],
                'create_order' => [
                    'endpoint' => home_url(\WC_AJAX::get_endpoint(CreateOrderEndpoint::ENDPOINT)),
                    'nonce' => wp_create_nonce(CreateOrderEndpoint::nonce()),
                ],
                'approve_order' => [
                    'endpoint' => home_url(\WC_AJAX::get_endpoint(ApproveOrderEndpoint::ENDPOINT)),
                    'nonce' => wp_create_nonce(ApproveOrderEndpoint::nonce()),
                ],
            ],
            'payer' => $this->payerData(),
            'button' => [
                'wrapper' => '#ppc-button',
                'mini_cart_wrapper' => '#ppc-button-minicart',
                'cancel_wrapper' => '#ppcp-cancel',
                'url' => $this->url(),
                'style' => [
                    'layout' => 'vertical',
                    'color' => $this->settings->get('button_color'),
                    'shape' => $this->settings->get('button_shape'),
                    'label' => 'paypal',
                ],
            ],
            'hosted_fields' => [
                'wrapper' => '#ppc-hosted-fields',
                'labels' => [
                    'credit_card_number' => __('Credit Card Number', 'woocommerce-paypal-commerce-gateway'),
                    'cvv' => __('CVV', 'woocommerce-paypal-commerce-gateway'),
                    'mm_yyyy' => __('MM/YYYY', 'woocommerce-paypal-commerce-gateway'),
                ],
            ],
        ];
        return $localize;
    }

    private function payerData(): ?array
    {

        $customer = WC()->customer;
        if (! is_user_logged_in() || ! is_a($customer, \WC_Customer::class)) {
            return null;
        }
        return [
            'email_address' => $customer->get_billing_email(),
            'name' => [
                'surname' => $customer->get_billing_last_name(),
                'given_name' => $customer->get_billing_last_name(),
            ],
            'address' => [
                'country_code' => $customer->get_billing_country(),
                'address_line_1' => $customer->get_billing_address_1(),
                'address_line_2' => $customer->get_billing_address_2(),
                'admin_area_1' => $customer->get_billing_city(),
                'admin_area_2' => $customer->get_billing_state(),
                'postal_code' => $customer->get_billing_postcode(),
            ],
            'phone' => [
                'phone_type' => 'HOME',
                'phone_number' => [
                    'national_number' => $customer->get_billing_phone(),
                ],
            ],
        ];
    }

    private function url(): string
    {
        $params = [
            //ToDo: Add the correct client id, toggle when settings is set to sandbox
            'client-id' => 'AQB97CzMsd58-It1vxbcDAGvMuXNCXRD9le_XUaMlHB_U7XsU9IiItBwGQOtZv9sEeD6xs2vlIrL4NiD',
            'currency' => get_woocommerce_currency(),
            'locale' => get_user_locale(),
            //'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false',
            //ToDo: Update date on releases.
            'integration-date' => date('Y-m-d'),
            'components' => implode(',', $this->components()),
            //ToDo: Probably only needed, when DCC
            'vault' => $this->dccIsEnabled() ? 'false' : 'false',
            'commit' => is_checkout() ? 'true' : 'false',
            'intent' => $this->settings->get('intent'),
        ];
        if (defined('WP_DEBUG') && \WP_DEBUG && WC()->customer) {
            $params['buyer-country'] = WC()->customer->get_billing_country();
        }
        $payee = $this->payeeRepository->payee();
        if ($payee->merchantId()) {
            $params['merchant-id'] = $payee->merchantId();
        }
        $disableFunding = $this->settings->get('disable_funding');
        if (is_array($disableFunding) && count($disableFunding)) {
            $params['disable-funding'] = implode(',', $disableFunding);
        }
        $smartButtonUrl = add_query_arg($params, 'https://www.paypal.com/sdk/js');
        return $smartButtonUrl;
    }

    private function attributes() : array {
        $attributes = [
            //'data-partner-attribution-id' => '',
        ];
        try {
            $clientToken = $this->identityToken->generate();
            $attributes['data-client-token'] = $clientToken->token();
            return $attributes;
        } catch (RuntimeException $exception) {
            return $attributes;
        }
    }

    private function components() : array
    {
        $components = ['buttons'];
        if ($this->dccIsEnabled()) {
            $components[] = 'hosted-fields';
        }
        return $components;
    }

    private function context(): string
    {
        $context = 'mini-cart';
        if (is_product()) {
            $context = 'product';
        }
        if (is_cart()) {
            $context = 'cart';
        }
        if (is_checkout() && !$this->sessionHandler->order()) {
            $context = 'checkout';
        }
        return $context;
    }

    private function dccIsEnabled() : bool
    {
        return wc_string_to_bool($this->settings->get('enable_dcc'));
    }
}
