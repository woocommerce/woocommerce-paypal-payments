<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Assets;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Helper\MessagesApply;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

//phpcs:disable Inpsyde.CodeQuality.PropertyPerClassLimit.TooManyProperties
class SmartButton implements SmartButtonInterface
{
    private $moduleUrl;
    private $sessionHandler;
    private $settings;
    private $payeeRepository;
    private $identityToken;
    private $payerFactory;
    private $clientId;
    private $requestData;
    private $dccApplies;
    private $subscriptionHelper;
    private $messagesApply;

    public function __construct(
        string $moduleUrl,
        SessionHandler $sessionHandler,
        Settings $settings,
        PayeeRepository $payeeRepository,
        IdentityToken $identityToken,
        PayerFactory $payerFactory,
        string $clientId,
        RequestData $requestData,
        DccApplies $dccApplies,
        SubscriptionHelper $subscriptionHelper,
        MessagesApply $messagesApply
    ) {

        $this->moduleUrl = $moduleUrl;
        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
        $this->payeeRepository = $payeeRepository;
        $this->identityToken = $identityToken;
        $this->payerFactory = $payerFactory;
        $this->clientId = $clientId;
        $this->requestData = $requestData;
        $this->dccApplies = $dccApplies;
        $this->subscriptionHelper = $subscriptionHelper;
        $this->messagesApply = $messagesApply;
    }

    /**
     * @return bool
     */
    public function renderWrapper(): bool
    {

        if (! $this->canSaveVaultToken() && $this->hasSubscription()) {
            return false;
        }

        if ($this->settings->has('enabled') && $this->settings->get('enabled')) {
            $this->renderButtonWrapperRegistrar();
            $this->renderMessageWrapperRegistrar();
        }

        if (
            $this->settings->has('dcc_gateway_enabled')
            && $this->settings->get('dcc_gateway_enabled')
            && ! $this->sessionHandler->order()
        ) {
            add_action(
                'woocommerce_review_order_after_submit',
                [
                    $this,
                    'dccRenderer',
                ],
                11
            );
        }
        return true;
    }

    private function renderMessageWrapperRegistrar(): bool
    {

        $notEnabledOnCart = $this->settings->has('message_cart_enabled') &&
            !$this->settings->get('message_cart_enabled');
        if (
            is_cart()
            && !$notEnabledOnCart
        ) {
            add_action(
                'woocommerce_proceed_to_checkout',
                [
                    $this,
                    'messageRenderer',
                ],
                19
            );
        }

        $notEnabledOnProductPage = $this->settings->has('message_product_enabled') &&
            !$this->settings->get('message_product_enabled');
        if (
            (is_product() || wc_post_content_has_shortcode('product_page'))
            && !$notEnabledOnProductPage
        ) {
            add_action(
                'woocommerce_single_product_summary',
                [
                    $this,
                    'messageRenderer',
                ],
                30
            );
        }

        $notEnabledOnCheckout = $this->settings->has('message_enabled') &&
            !$this->settings->get('message_enabled');
        if (! $notEnabledOnCheckout) {
            add_action(
                'woocommerce_review_order_after_submit',
                [
                    $this,
                    'messageRenderer',
                ],
                11
            );
        }
        return true;
    }

    private function renderButtonWrapperRegistrar(): bool
    {

        $notEnabledOnCart = $this->settings->has('button_cart_enabled') &&
            !$this->settings->get('button_cart_enabled');
        if (
            is_cart()
            && !$notEnabledOnCart
        ) {
            add_action(
                'woocommerce_proceed_to_checkout',
                [
                    $this,
                    'buttonRenderer',
                ],
                20
            );
        }

        $notEnabledOnProductPage = $this->settings->has('button_single_product_enabled') &&
            !$this->settings->get('button_single_product_enabled');
        if (
            (is_product() || wc_post_content_has_shortcode('product_page'))
            && !$notEnabledOnProductPage
        ) {
            add_action(
                'woocommerce_single_product_summary',
                [
                    $this,
                    'buttonRenderer',
                ],
                31
            );
        }

        $notEnabledOnMiniCart = $this->settings->has('button_mini_cart_enabled') &&
            !$this->settings->get('button_mini_cart_enabled');
        if (
            ! $notEnabledOnMiniCart
        ) {
            add_action(
                'woocommerce_widget_shopping_cart_after_buttons',
                static function () {
                    echo '<p
                                id="ppc-button-minicart"
                                class="woocommerce-mini-cart__buttons buttons"
                          ></p>';
                },
                30
            );
        }

        add_action('woocommerce_review_order_after_submit', [$this, 'buttonRenderer'], 10);

        return true;
    }

    public function enqueue(): bool
    {
        $buttonsEnabled = $this->settings->has('enabled') && $this->settings->get('enabled');
        if (! is_checkout() && !$buttonsEnabled) {
            return false;
        }
        if (! $this->canSaveVaultToken() && $this->hasSubscription()) {
            return false;
        }
        wp_enqueue_style(
            'ppcp-hosted-fields',
            $this->moduleUrl . '/assets/css/hosted-fields.css',
            [],
            1
        );
        wp_enqueue_script(
            'ppcp-smart-button',
            $this->moduleUrl . '/assets/js/button.js',
            ['jquery'],
            1,
            true
        );

        wp_localize_script(
            'ppcp-smart-button',
            'PayPalCommerceGateway',
            $this->localizeScript()
        );
        return true;
    }

    public function buttonRenderer()
    {
        $product = wc_get_product();
        if (
            ! is_checkout() && is_a($product, \WC_Product::class)
            && (
                $product->is_type(['external', 'grouped'])
                || ! $product->is_in_stock()
            )
        ) {
            return;
        }

        echo '<div id="ppc-button"></div>';
    }

    public function messageRenderer()
    {

        echo '<div id="ppcp-messages"></div>';
    }

    //phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    private function messageValues(): array
    {

        if (
            $this->settings->has('disable_funding')
            && in_array('credit', (array) $this->settings->get('disable_funding'), true)
        ) {
            return [];
        }
        $placement = 'product';
        $product = wc_get_product();
        $amount = (is_a($product, \WC_Product::class)) ? wc_get_price_including_tax($product) : 0;
        $layout = $this->settings->has('message_product_layout') ?
            $this->settings->get('message_product_layout') : 'text';
        $logoType = $this->settings->has('message_product_logo') ?
            $this->settings->get('message_product_logo') : 'primary';
        $logoPosition = $this->settings->has('message_product_position') ?
            $this->settings->get('message_product_position') : 'left';
        $textColor = $this->settings->has('message_product_color') ?
            $this->settings->get('message_product_color') : 'black';
        $styleColor = $this->settings->has('message_product_flex_color') ?
            $this->settings->get('message_product_flex_color') : 'blue';
        $ratio = $this->settings->has('message_product_flex_ratio') ?
            $this->settings->get('message_product_flex_ratio') : '1x1';
        $shouldShow = $this->settings->has('message_product_enabled')
            && $this->settings->get('message_product_enabled');
        if (is_checkout()) {
            $placement = 'payment';
            $amount = WC()->cart->get_total('raw');
            $layout = $this->settings->has('message_layout') ?
                $this->settings->get('message_layout') : 'text';
            $logoType = $this->settings->has('message_logo') ?
                $this->settings->get('message_logo') : 'primary';
            $logoPosition = $this->settings->has('message_position') ?
                $this->settings->get('message_position') : 'left';
            $textColor = $this->settings->has('message_color') ?
                $this->settings->get('message_color') : 'black';
            $styleColor = $this->settings->has('message_flex_color') ?
                $this->settings->get('message_flex_color') : 'blue';
            $ratio = $this->settings->has('message_flex_ratio') ?
                $this->settings->get('message_flex_ratio') : '1x1';
            $shouldShow = $this->settings->has('message_enabled')
                && $this->settings->get('message_enabled');
        }
        if (is_cart()) {
            $placement = 'cart';
            $amount = WC()->cart->get_total('raw');
            $layout = $this->settings->has('message_cart_layout') ?
                $this->settings->get('message_cart_layout') : 'text';
            $logoType = $this->settings->has('message_cart_logo') ?
                $this->settings->get('message_cart_logo') : 'primary';
            $logoPosition = $this->settings->has('message_cart_position') ?
                $this->settings->get('message_cart_position') : 'left';
            $textColor = $this->settings->has('message_cart_color') ?
                $this->settings->get('message_cart_color') : 'black';
            $styleColor = $this->settings->has('message_cart_flex_color') ?
                $this->settings->get('message_cart_flex_color') : 'blue';
            $ratio = $this->settings->has('message_cart_flex_ratio') ?
                $this->settings->get('message_cart_flex_ratio') : '1x1';
            $shouldShow = $this->settings->has('message_cart_enabled')
                && $this->settings->get('message_cart_enabled');
        }

        if (! $shouldShow) {
            return [];
        }

        $values = [
            'wrapper' => '#ppcp-messages',
            'amount' => $amount,
            'placement' => $placement,
            'style' => [
                'layout' => $layout,
                'logo' => [
                    'type' => $logoType,
                    'position' => $logoPosition,
                ],
                'text' => [
                    'color' => $textColor,
                ],
                'color' => $styleColor,
                'ratio' => $ratio,
            ],
        ];

        return $values;
    }
    //phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong

    public function dccRenderer()
    {

        $id = 'ppcp-hosted-fields';
        $canRenderDcc = $this->dccApplies->forCountryCurrency()
                && $this->settings->has('client_id')
                && $this->settings->get('client_id');
        if (! $canRenderDcc) {
            return;
        }

        $saveCard = $this->canSaveVaultToken() ? sprintf(
            '<div>

                <label for="ppcp-vault-%1$s">%2$s</label>
                <input
                    type="checkbox"
                    id="ppcp-vault-%1$s"
                    class="ppcp-credit-card-vault"
                    name="vault"
                >
            </div>',
            esc_attr($id),
            esc_html__('Save your card', 'woocommerce-paypal-commerce-gateway')
        ) : '';

        printf(
            '<form id="%1$s">
                        <div class="ppcp-dcc-credit-card-wrapper" style="display: none">
                            <label for="ppcp-credit-card-%1$s">%2$s</label>
                            <span id="ppcp-credit-card-%1$s" class="ppcp-credit-card"></span>
                            <label for="ppcp-expiration-date-%1$s">%3$s</label>
                            <span
                             id="ppcp-expiration-date-%1$s"
                             class="ppcp-expiration-date"
                            ></span>
                            <label for="ppcp-cvv-%1$s">%4$s</label>
                            <span id="ppcp-cvv-%1$s" class="ppcp-cvv"></span>
                            %5$s
                            <button class="button alt">%6$s</button>
                        </div>
                    </form><div id="payments-sdk__contingency-lightbox"></div>',
            esc_attr($id),
            esc_html__('Credit Card number', 'woocommerce-paypal-commerce-gateway'),
            esc_html__('Expiration', 'woocommerce-paypal-commerce-gateway'),
            esc_html__('CVV', 'woocommerce-paypal-commerce-gateway'),
            //phpcs:ignore
            $saveCard,
            esc_html__('Place order', 'woocommerce')
        );
    }
    // phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong

    public function canSaveVaultToken(): bool
    {

        if (! $this->settings->has('client_id') || ! $this->settings->get('client_id')) {
            return false;
        }
        if (! $this->settings->has('vault_enabled') || ! $this->settings->get('vault_enabled')) {
            return false;
        }
        return is_user_logged_in();
    }

    private function hasSubscription(): bool
    {
        if (! $this->subscriptionHelper->acceptOnlyAutomaticPaymentGateways()) {
            return false;
        }
        if (is_product()) {
            return $this->subscriptionHelper->currentProductIsSubscription();
        }
        return $this->subscriptionHelper->cartContainsSubscription();
    }

    //phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    private function localizeScript(): array
    {
        $this->requestData->enqueueNonceFix();
        $localize = [
            'script_attributes' => $this->attributes(),
            'data_client_id' => [
                'set_attribute' => (is_checkout() && $this->dccIsEnabled())
                    || $this->canSaveVaultToken(),
                'endpoint' => home_url(\WC_AJAX::get_endpoint(DataClientIdEndpoint::ENDPOINT)),
                'nonce' => wp_create_nonce(DataClientIdEndpoint::nonce()),
                'user' => get_current_user_id(),
            ],
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
            'enforce_vault' => $this->hasSubscription(),
            'bn_codes' => $this->bnCodes(),
            'payer' => $this->payerData(),
            'button' => [
                'wrapper' => '#ppc-button',
                'mini_cart_wrapper' => '#ppc-button-minicart',
                'cancel_wrapper' => '#ppcp-cancel',
                'url' => $this->url(),
                'mini_cart_style' => [
                    'layout' => $this->styleForContext('layout', 'mini-cart'),
                    'color' => $this->styleForContext('color', 'mini-cart'),
                    'shape' => $this->styleForContext('shape', 'mini-cart'),
                    'label' => $this->styleForContext('label', 'mini-cart'),
                    'tagline' => $this->styleForContext('tagline', 'mini-cart'),
                ],
                'style' => [
                    'layout' => $this->styleForContext('layout', $this->context()),
                    'color' => $this->styleForContext('color', $this->context()),
                    'shape' => $this->styleForContext('shape', $this->context()),
                    'label' => $this->styleForContext('label', $this->context()),
                    'tagline' => $this->styleForContext('tagline', $this->context()),
                ],
            ],
            'hosted_fields' => [
                'wrapper' => '#ppcp-hosted-fields',
                'mini_cart_wrapper' => '#ppcp-hosted-fields-mini-cart',
                'labels' => [
                    'credit_card_number' => '',
                    'cvv' => '',
                    'mm_yyyy' => __('MM/YYYY', 'woocommerce-paypal-commerce-gateway'),
                    'fields_not_valid' => __(
                        'Unfortunatly, your credit card details are not valid.',
                        'woocommerce-paypal-commerce-gateway'
                    ),
                ],
            ],
            'messages' => $this->messageValues(),
            'labels' => [
                'error' => [
                    'generic' => __(
                        'Something went wrong. Please try again or choose another payment source',
                        'woocommerce-paypal-commerce-gateway'
                    ),
                ],
            ],
        ];

        if ($this->styleForContext('layout', 'mini-cart') !== 'horizontal') {
            unset($localize['button']['mini_cart_style']['tagline']);
        }
        if ($this->styleForContext('layout', $this->context()) !== 'horizontal') {
            unset($localize['button']['style']['tagline']);
        }

        $this->requestData->dequeueNonceFix();
        return $localize;
    }
    //phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong

    private function payerData(): ?array
    {

        $customer = WC()->customer;
        if (! is_user_logged_in() || ! is_a($customer, \WC_Customer::class)) {
            return null;
        }
        return $this->payerFactory->fromCustomer($customer)->toArray();
    }

    private function url(): string
    {
        $params = [
            'client-id' => $this->clientId,
            'currency' => get_woocommerce_currency(),
            'locale' => get_user_locale(),
            //'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false',
            //ToDo: Update date on releases.
            'integration-date' => date('Y-m-d'),
            'components' => implode(',', $this->components()),
            'vault' => (is_checkout() && $this->dccIsEnabled()) || $this->canSaveVaultToken() ?
                'true' : 'false',
            'commit' => is_checkout() ? 'true' : 'false',
            'intent' => ($this->settings->has('intent')) ?
                $this->settings->get('intent') : 'capture',
        ];
        if (defined('WP_DEBUG') && \WP_DEBUG && WC()->customer) {
            $params['buyer-country'] = WC()->customer->get_billing_country();
        }
        $payee = $this->payeeRepository->payee();
        if ($payee->merchantId()) {
            $params['merchant-id'] = $payee->merchantId();
        }
        $disableFunding = $this->settings->has('disable_funding') ?
            $this->settings->get('disable_funding') : [];
        $disableFunding[] = 'venmo';
        if (! is_checkout()) {
            $disableFunding[] = 'card';
        }
        $params['disable-funding'] = implode(',', array_unique($disableFunding));
        $smartButtonUrl = add_query_arg($params, 'https://www.paypal.com/sdk/js');
        return $smartButtonUrl;
    }

    private function attributes(): array
    {
        return [
            'data-partner-attribution-id' => $this->bnCodeForContext($this->context()),
        ];
    }

    /**
     * @param string $context
     * @return string
     */
    private function bnCodeForContext(string $context): string
    {

        $codes = $this->bnCodes();
        return (isset($codes[$context])) ? $codes[$context] : '';
    }

    /**
     * BN Codes
     *
     * @return array
     */
    private function bnCodes(): array
    {

        return [
            'checkout' => 'Woo_PPCP',
            'cart' => 'Woo_PPCP',
            'mini-cart' => 'Woo_PPCP',
            'product' => 'Woo_PPCP',
        ];
    }

    private function components(): array
    {
        $components = ['buttons'];
        if ($this->messagesApply->forCountry()) {
            $components[] = 'messages';
        }
        if ($this->dccIsEnabled()) {
            $components[] = 'hosted-fields';
        }
        return $components;
    }

    private function context(): string
    {
        $context = 'mini-cart';
        if (is_product() || wc_post_content_has_shortcode('product_page')) {
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

    private function dccIsEnabled(): bool
    {
        if (! $this->dccApplies->forCountryCurrency()) {
            return false;
        }
        $keys = [
            'dcc_gateway_enabled' => 'is_checkout',
        ];
        foreach ($keys as $key => $callback) {
            if ($this->settings->has($key) && $this->settings->get($key) && $callback()) {
                return true;
            }
        }
        return false;
    }

    private function styleForContext(string $style, string $context): string
    {
        $defaults = [
            'layout' => 'vertical',
            'size' => 'responsive',
            'color' => 'gold',
            'shape' => 'pill',
            'label' => 'paypal',
            'tagline' => true,
        ];

        $value = isset($defaults[$style]) ?
            $defaults[$style] : '';
        $value = $this->settings->has('button_' . $style) ?
            $this->settings->get('button_' . $style) : $value;
        $value = $this->settings->has('button_' . $context . '_' . $style) ?
            $this->settings->get('button_' . $context . '_' . $style) : $value;

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        return $value;
    }
}
