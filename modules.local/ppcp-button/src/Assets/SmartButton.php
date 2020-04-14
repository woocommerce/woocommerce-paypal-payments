<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Assets;

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

    public function __construct(
        string $moduleUrl,
        SessionHandler $sessionHandler,
        Settings $settings,
        PayeeRepository $payeeRepository
    ) {

        $this->moduleUrl = $moduleUrl;
        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
        $this->payeeRepository = $payeeRepository;
    }

    public function renderWrapper(): bool
    {
        $renderer = function () {
            echo '<div id="ppc-button"></div>';
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
                function () {
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
            $this->moduleUrl . '/assets/js/button.js'
        );

        wp_localize_script(
            'paypal-smart-button',
            'PayPalCommerceGateway',
            $this->localizeScript()
        );
        return true;
    }

    private function localizeScript() : array
    {
        $localize = [
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
        ];
        return $localize;
    }

    private function url() : string
    {
        $params = [
            //ToDo: Add the correct client id, toggle when settings is set to sandbox
            'client-id' => 'AcVzowpNCpTxFzLG7onQI4JD0sVcA0BkZv-D42qRZPv_gZ8cNfX9zGL_8bXmSu7cbJ5B2DH7sot8vDpw',
            'currency' => get_woocommerce_currency(),
            'locale' => get_user_locale(),
            //'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false',
            //ToDo: Update date on releases.
            'integration-date' => date('Y-m-d'),
            'components' => 'marks,buttons',
            //ToDo: Probably only needed, when DCC
            'vault' => 'true',
            'commit' => is_checkout() ? 'true' : 'false',
            'intent' => $this->settings->get('intent')
        ];
        $payee = $this->payeeRepository->payee();
        if ($payee->merchantId()) {
            $params['merchant-id'] = $payee->merchantId();
        }
        $smartButtonUrl = add_query_arg($params, 'https://www.paypal.com/sdk/js');
        return $smartButtonUrl;
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
}
