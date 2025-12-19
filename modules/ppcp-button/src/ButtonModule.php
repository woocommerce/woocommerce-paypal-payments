<?php

/**
 * The button module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ReturnUrlFactory;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveSubscriptionEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SaveCheckoutFormEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ValidateCheckoutEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\Button\VaultV2\StartPayPalVaultingEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class ButtonModule
 */
class ButtonModule implements ServiceModule, ExtendingModule, ExecutableModule
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
        add_action('wp', static function () use ($c) {
            if (is_admin()) {
                return;
            }
            $smart_button = $c->get('button.smart-button');
            /**
             * The Smart Button.
             *
             * @var SmartButtonInterface $smart_button
             */
            $smart_button->render_wrapper();
        });
        add_action('wp_enqueue_scripts', static function () use ($c) {
            $smart_button = $c->get('button.smart-button');
            assert($smart_button instanceof SmartButtonInterface);
            if ($smart_button->should_load_ppcp_script()) {
                $smart_button->enqueue();
            }
        });
        add_filter('woocommerce_create_order', static function ($value) use ($c) {
            $early_order_handler = $c->get('button.helper.early-order-handler');
            if (!is_null($value)) {
                $value = (int) $value;
            }
            /**
             * The Early Order Handler
             *
             * @var EarlyOrderHandler $early_order_handler
             */
            return $early_order_handler->determine_wc_order_id($value);
        });
        $this->register_ajax_endpoints($c);
        $this->register_appswitch_crossbrowser_handler($c);
        return \true;
    }
    /**
     * Registers the Ajax Endpoints.
     *
     * @param ContainerInterface $container The Container.
     */
    private function register_ajax_endpoints(ContainerInterface $container): void
    {
        add_action('wc_ajax_' . DataClientIdEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.data-client-id');
            /**
             * The Data Client ID Endpoint.
             *
             * @var DataClientIdEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . SimulateCartEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.simulate-cart');
            /**
             * The Simulate Cart Endpoint.
             *
             * @var SimulateCartEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . ChangeCartEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.change-cart');
            /**
             * The Change Cart Endpoint.
             *
             * @var ChangeCartEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . ApproveOrderEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.approve-order');
            /**
             * The Approve Order Endpoint.
             *
             * @var ApproveOrderEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . ApproveSubscriptionEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.approve-subscription');
            assert($endpoint instanceof ApproveSubscriptionEndpoint);
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . CreateOrderEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.create-order');
            /**
             * The Create Order Endpoint.
             *
             * @var CreateOrderEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . SaveCheckoutFormEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.save-checkout-form');
            assert($endpoint instanceof SaveCheckoutFormEndpoint);
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . ValidateCheckoutEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.validate-checkout');
            assert($endpoint instanceof ValidateCheckoutEndpoint);
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . CartScriptParamsEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.cart-script-params');
            assert($endpoint instanceof CartScriptParamsEndpoint);
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . GetOrderEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.endpoint.get-order');
            assert($endpoint instanceof GetOrderEndpoint);
            $endpoint->handle_request();
        });
        /**
         * Vault v2 ajax handler, would be removed when vault v3 becomes the default for all merchants.
         */
        add_action('wc_ajax_' . StartPayPalVaultingEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('button.vault-v2.endpoint.vault-paypal');
            assert($endpoint instanceof StartPayPalVaultingEndpoint);
            $endpoint->handle_request();
        });
    }
    private static function is_cross_browser_order(WC_Order $wc_order): bool
    {
        return wc_string_to_bool($wc_order->get_meta(PayPalGateway::CROSS_BROWSER_APPSWITCH_META_KEY));
    }
    private function register_appswitch_crossbrowser_handler(ContainerInterface $container): void
    {
        if (!$container->get('wcgateway.appswitch-enabled')) {
            return;
        }
        // After returning from cross-browser AppSwitch (started in non-default browser, then redirected to the default one)
        // we need to retrieve the saved cart and PayPal order, create a WC order and redirect to Pay for order.
        add_action('wp', static function () use ($container) {
            // phpcs:ignore WordPress.Security.NonceVerification
            if (!isset($_GET[ReturnUrlFactory::PCP_QUERY_ARG])) {
                return;
            }
            if (is_checkout_pay_page()) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification
            if (!isset($_GET[CreateOrderEndpoint::RETURN_URL_CART_QUERY_ARG])) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification
            $cart_key = wc_clean(wp_unslash($_GET[CreateOrderEndpoint::RETURN_URL_CART_QUERY_ARG]));
            if (!is_string($cart_key)) {
                return;
            }
            $card_data_storage = $container->get('button.session.storage.card-data.transient');
            assert($card_data_storage instanceof CartDataTransientStorage);
            $cart_data = $card_data_storage->get($cart_key);
            if (!$cart_data) {
                return;
            }
            // Delete the data to avoid accidentally triggering it again, duplicating orders etc.
            $card_data_storage->remove($cart_data);
            if (!WC()->cart) {
                return;
            }
            // The current cart is the same, so we don't need to do anything (probably not cross-browser).
            if (WC()->cart->get_cart_hash() === $cart_data->cart_hash()) {
                return;
            }
            $paypal_order_id = $cart_data->paypal_order_id();
            if (empty($paypal_order_id)) {
                return;
            }
            $order_endpoint = $container->get('api.endpoint.order');
            assert($order_endpoint instanceof OrderEndpoint);
            $paypal_order = $order_endpoint->order($paypal_order_id);
            $wc_order_creator = $container->get('button.helper.wc-order-creator');
            assert($wc_order_creator instanceof WooCommerceOrderCreator);
            $wc_order = $wc_order_creator->create_from_paypal_order($paypal_order, $cart_data);
            $wc_order->update_meta_data(PayPalGateway::CROSS_BROWSER_APPSWITCH_META_KEY, wc_bool_to_string(\true));
            $wc_order->save();
            // Redirect via JS because we need to keep the # parameters which are not accessible on the server side.
            // phpcs:ignore WordPress.Security.EscapeOutput
            echo "<script>location.href = '" . $wc_order->get_checkout_payment_url() . "' + location.hash;</script>";
        });
        /**
         * Restore PayPal as the chosen payment method when returning from an App Switch flow.
         *
         * Context:
         * --------
         * When Fastlane (AXO) is active, AxoModule forces the chosen payment method to
         * AxoGateway on every checkout page load. This causes a problem when the customer
         * initiated checkout with PayPal and was redirected to the PayPal app
         * (App Switch): after resuming, the checkout would incorrectly show AxoGateway,
         * leading to validation errors and failed payments.
         *
         * Solution:
         * ---------
         * This handler runs after the AxoModule one (priority 20). It checks for the
         * PayPal return URL query arguments that indicate an App Switch resume, and if
         * present, forces the chosen method back to PayPalGateway. This ensures the
         * resumed PayPal flow continues as expected.
         */
        add_action('template_redirect', function () use ($container) {
            // phpcs:ignore WordPress.Security.NonceVerification
            if (!isset($_GET[ReturnUrlFactory::PCP_QUERY_ARG])) {
                return;
            }
            if (is_checkout_pay_page()) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification
            if (!isset($_GET[CreateOrderEndpoint::RETURN_URL_CART_QUERY_ARG])) {
                return;
            }
            WC()->session->set('chosen_payment_method', PayPalGateway::ID);
        }, 20);
        /**
         * By default, WC asks to log in when opening a non-guest Pay for order page as a guest,
         * so we disable this for cross-browser AppSwitch.
         *
         * @param array<string, bool> $allcaps Array of key/value pairs where keys represent a capability name
         *                           and boolean values represent whether the user has that capability.
         * @param string[] $caps Required primitive capabilities for the requested capability.
         * @param array $args {
         *      Arguments that accompany the requested capability check.
         *
         * @type string    $0 Requested capability.
         * @type int       $1 Concerned user ID.
         * @type mixed  ...$2 Optional second and further parameters, typically object ID.
         *  }
         *
         * @returns array<string, bool>
         *
         * @psalm-suppress MissingClosureParamType
         */
        add_filter('user_has_cap', static function ($allcaps, $cap, $args) {
            if (!in_array('pay_for_order', $cap, \true)) {
                return $allcaps;
            }
            $wc_order_id = $args[2] ?? null;
            if (!is_int($wc_order_id)) {
                return $allcaps;
            }
            $wc_order = wc_get_order($wc_order_id);
            if (!$wc_order instanceof WC_Order) {
                return $allcaps;
            }
            if (!self::is_cross_browser_order($wc_order)) {
                return $allcaps;
            }
            return array_merge($allcaps, array('pay_for_order' => \true));
        }, 10, 3);
        /**
         * By default, WC asks to log in when opening a non-guest order received page as a guest,
         * so we disable this for cross-browser AppSwitch.
         *
         * @param bool $result
         *
         * @psalm-suppress MissingClosureParamType
         */
        add_filter('woocommerce_order_received_verify_known_shoppers', static function ($result) {
            if (!is_order_received_page()) {
                return $result;
            }
            $wc_order = null;
            // phpcs:disable WordPress.Security.NonceVerification
            if (isset($_GET['key']) && is_string($_GET['key'])) {
                $wc_order_key = sanitize_text_field(wp_unslash($_GET['key']));
                // phpcs:enable WordPress.Security.NonceVerification
                $wc_order_id = wc_get_order_id_by_order_key($wc_order_key);
                $wc_order = wc_get_order($wc_order_id);
            }
            if (!$wc_order instanceof WC_Order) {
                return $result;
            }
            if (!self::is_cross_browser_order($wc_order)) {
                return $result;
            }
            return \false;
        });
        /**
         * Disabling the email prompt on Pay for order page for guests.
         * Should not affect anything in most cases because it is skipped for just created orders (< 10 min).
         *
         * @param bool $email_verification_required
         * @param WC_Order $order
         *
         * @returns bool
         *
         * @psalm-suppress MissingClosureParamType
         */
        add_filter('woocommerce_order_email_verification_required', static function ($email_verification_required, $order) {
            if (!$order instanceof WC_Order) {
                return $email_verification_required;
            }
            if (!self::is_cross_browser_order($order)) {
                return $email_verification_required;
            }
            return \false;
        }, 10, 2);
    }
}
